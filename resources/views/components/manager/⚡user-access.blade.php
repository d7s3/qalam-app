<?php

use App\Models\Circle;
use App\Models\Role;
use App\Models\Stage;
use App\Models\Screen;
use App\Models\User;
use App\Models\UserRole;
use App\Models\UserScreenOverride;
use App\Support\Access;
use App\Support\RoleHierarchy;
use App\Support\RolePages;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $search = '';

    public ?int $userId = null;

    /** The role whose screens are being reviewed for the chosen person. */
    public ?string $roleKey = null;

    /** The reach written on that holding: '' inherits the role's own. */
    public string $scopeType = '';

    /** @var array<int, int> */
    public array $scopeIds = [];

    /** Which roles each role carries, as the administrator arranges them. */
    public array $inherits = [];

    public bool $showHierarchy = false;

    #[Computed]
    public function roles(): Collection
    {
        return Role::orderByDesc('is_system')->orderBy('id')->get();
    }

    #[Computed]
    public function people(): Collection
    {
        $query = User::query()->with('roles')->orderBy('name')->limit(40);

        if ($this->search !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$this->search.'%')
                ->orWhere('email', 'like', '%'.$this->search.'%'));
        }

        return $query->get();
    }

    #[Computed]
    public function person(): ?User
    {
        return $this->userId ? User::with('roles')->find($this->userId) : null;
    }

    /** The roles this person actually holds, as keys. */
    #[Computed]
    public function heldRoles(): array
    {
        return $this->person?->roles->pluck('role')->all() ?? [];
    }

    /**
     * Every screen of the role under review, with how this person stands on it:
     * what the role gives, whether an exception was written, and the outcome.
     */
    #[Computed]
    public function rows(): Collection
    {
        $person = $this->person;
        $role = $this->roleKey;

        if (! $person || ! $role) {
            return collect();
        }

        $granted = RolePages::enabledScreenIdsFor($role);
        $overrides = UserScreenOverride::where('user_id', $person->id)
            ->pluck('is_allowed', 'screen_id')
            ->map(fn ($v) => (bool) $v);

        return Screen::whereHas('ownerRole', fn ($q) => $q->where('key', $role))
            ->orderBy('group_label')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Screen $screen) => [
                'screen' => $screen,
                'byRole' => in_array($screen->id, $granted, true),
                'override' => $overrides->get($screen->id),
                'effective' => Access::canSee($person, $role, $screen->route_name),
            ]);
    }

    public function selectPerson(int $id): void
    {
        $this->userId = $id;
        $this->roleKey = $this->person?->roles->first()?->role;
        $this->loadScope();
        $this->refresh();
    }

    public function updatedRoleKey(): void
    {
        $this->loadScope();
    }

    private function loadScope(): void
    {
        $holding = $this->holding();

        $this->scopeType = $holding?->scope_type ?? '';
        $this->scopeIds = array_map('intval', $holding?->scope_ids ?? []);
    }

    private function holding(): ?UserRole
    {
        return $this->roleKey
            ? $this->person?->roles->firstWhere('role', $this->roleKey)
            : null;
    }

    #[Computed]
    public function programmes(): Collection
    {
        return Stage::orderBy('name')->get();
    }

    #[Computed]
    public function cohorts(): Collection
    {
        return Circle::with('stage')->orderBy('name')->get();
    }

    /**
     * Write the reach onto this holding of the role.
     *
     * An empty type erases it, and the role decides again — which is how every
     * holding starts and how most of them stay.
     */
    public function saveScope(): void
    {
        $person = $this->guardedPerson();
        $holding = $this->holding();

        abort_unless($holding instanceof UserRole, 404);

        $this->validate([
            'scopeType' => ['nullable', 'in:,all,stages,circles'],
            'scopeIds' => ['array'],
            'scopeIds.*' => ['integer'],
        ], [], ['scopeType' => 'النطاق']);

        $needsIds = in_array($this->scopeType, ['stages', 'circles'], true);

        if ($needsIds && $this->scopeIds === []) {
            Flux::toast(__('اختر ما يبلغه على الأقل واحداً.'), variant: 'warning');

            return;
        }

        $holding->update([
            'scope_type' => $this->scopeType ?: null,
            'scope_ids' => $needsIds ? array_values(array_map('intval', $this->scopeIds)) : null,
        ]);

        $person->load('roles');
        $this->refresh();

        Flux::toast(__('حُفظ نطاق هذا الدور له.'), variant: 'success');
    }

    /**
     * Move one page through its three states for this person: inheriting the
     * role, forced open, forced shut — and back to inheriting.
     */
    public function cycle(int $screenId): void
    {
        $person = $this->guardedPerson();
        $screen = Screen::findOrFail($screenId);

        if ($screen->is_protected) {
            Flux::toast(__('هذه صفحة لا يمكن إخفاؤها — إليها يصل المستخدم بعد الدخول.'), variant: 'warning');

            return;
        }

        $existing = UserScreenOverride::where('user_id', $person->id)->where('screen_id', $screenId)->first();

        if (! $existing) {
            // Inheriting: step to the opposite of what the role currently says,
            // since an exception that matches the role would say nothing.
            $byRole = in_array($screenId, RolePages::enabledScreenIdsFor($this->roleKey ?? ''), true);

            UserScreenOverride::create([
                'user_id' => $person->id,
                'screen_id' => $screenId,
                'is_allowed' => ! $byRole,
                'set_by' => Auth::guard('manager')->id(),
            ]);
        } elseif ($existing->is_allowed) {
            $existing->update(['is_allowed' => false, 'set_by' => Auth::guard('manager')->id()]);
        } else {
            $existing->delete();
        }

        $this->refresh();
    }

    public function clearOverrides(): void
    {
        $person = $this->guardedPerson();

        UserScreenOverride::where('user_id', $person->id)->delete();
        $this->refresh();

        Flux::toast(__('عاد المستخدم إلى صلاحيات دوره.'), variant: 'success');
    }

    /**
     * Raise or lower a super administrator.
     *
     * The last one cannot be lowered, and nobody may lower himself: either would
     * leave the permissions screen with no one able to open it, and only a
     * programmer could undo that.
     */
    public function toggleSuperAdmin(): void
    {
        $person = $this->guardedPerson();
        $me = Auth::guard('manager')->user();

        if ($person->is_super_admin) {
            if ($person->id === $me?->id) {
                Flux::toast(__('لا يمكنك نزع الصلاحية العليا عن نفسك.'), variant: 'danger');

                return;
            }

            if (User::where('is_super_admin', true)->count() <= 1) {
                Flux::toast(__('لا يمكن نزع آخر صلاحية عليا في النظام.'), variant: 'danger');

                return;
            }
        }

        $person->update(['is_super_admin' => ! $person->is_super_admin]);
        $this->refresh();

        Flux::toast(
            $person->is_super_admin ? __('رُفع إلى الصلاحية العليا.') : __('نُزعت الصلاحية العليا.'),
            variant: 'success',
        );
    }

    /**
     * Arrange which offices carry which.
     *
     * Seniority includes: what a cohort teacher may open, his supervisor opens
     * too, and the centre manager above them both — without the same grant
     * being made three times over and drifting apart afterwards.
     */
    public function saveHierarchy(): void
    {
        abort_unless(Auth::guard('manager')->user()?->is_super_admin, 403);

        $arrangeable = array_keys(RoleHierarchy::arrangeable());
        $map = [];

        foreach ($this->inherits as $role => $carried) {
            if (! in_array($role, $arrangeable, true)) {
                continue;
            }

            $clean = array_values(array_filter(
                array_unique((array) $carried),
                // A role never carries itself, and only real roles are carried.
                fn ($item) => $item !== $role && in_array($item, $arrangeable, true),
            ));

            if ($clean !== []) {
                $map[$role] = $clean;
            }
        }

        // A cycle would make "who carries whom" unanswerable, so it is refused
        // rather than left for the walk to trip over.
        foreach (array_keys($map) as $role) {
            RoleHierarchy::set($map);

            if (in_array($role, RoleHierarchy::inheritedBy($role), true)) {
                RoleHierarchy::set($this->inherits === [] ? [] : RoleHierarchy::map());

                Flux::toast(__('لا يصح أن يحمل دورٌ نفسه، ولو بواسطة.'), variant: 'danger');

                return;
            }
        }

        RoleHierarchy::set($map);
        $this->inherits = RoleHierarchy::map();
        $this->refresh();

        Flux::toast(__('حُفظ ترتيب الأدوار.'), variant: 'success');
    }

    /** Only a super administrator may hand out access. */
    private function guardedPerson(): User
    {
        abort_unless(Auth::guard('manager')->user()?->is_super_admin, 403);

        $person = $this->person;

        abort_unless($person instanceof User, 404);

        return $person;
    }

    private function refresh(): void
    {
        Access::forget();

        unset($this->person, $this->rows, $this->people, $this->heldRoles);
    }

    public function with(): array
    {
        return ['isSuperAdmin' => (bool) Auth::guard('manager')->user()?->is_super_admin];
    }
};
?>

<div class="space-y-6" dir="rtl">
    @unless ($isSuperAdmin)
        <flux:card class="text-center py-12">
            <flux:icon icon="lock-closed" class="size-10 mx-auto text-amber-400" />
            <flux:heading size="lg" class="mt-3">{{ __('هذه الشاشة للصلاحية العليا') }}</flux:heading>
            <flux:subheading class="mt-1">
                {{ __('توزيع الصلاحيات على الأشخاص لا يملكه إلا حساب ذو صلاحية عليا.') }}
            </flux:subheading>
        </flux:card>
    @else

        {{-- ترتيب الأدوار: من يحمل من --}}
        <flux:card>
            <button type="button" wire:click="$toggle('showHierarchy')"
                class="w-full flex items-center justify-between gap-3 text-start cursor-pointer">
                <div>
                    <flux:heading size="lg">{{ __('ترتيب الأدوار') }}</flux:heading>
                    <flux:subheading class="mt-0.5">
                        {{ __('الأقدم يحمل ما دونه: ما يفتحه معلّم الدفعة يفتحه مشرفها، وما يفتحه المشرف يفتحه مدير المركز — بلا منح الشيء ثلاث مرات.') }}
                    </flux:subheading>
                </div>
                <flux:icon :icon="$showHierarchy ? 'chevron-up' : 'chevron-down'" class="size-5 text-zinc-400 shrink-0" />
            </button>

            @if ($showHierarchy)
                <div class="mt-5 pt-5 border-t border-zinc-100 dark:border-zinc-800 space-y-4">
                    @foreach ($this->roles as $role)
                        <div wire:key="hier-{{ $role->id }}" class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4">
                            <div class="text-sm font-bold text-zinc-900 dark:text-white mb-2">
                                {{ $role->label }} <span class="text-xs font-medium text-zinc-400">{{ __('يحمل:') }}</span>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($this->roles as $carried)
                                    @if ($carried->key !== $role->key)
                                        <label wire:key="h-{{ $role->id }}-{{ $carried->id }}"
                                            class="flex items-center gap-2 rounded-lg border border-zinc-200 dark:border-zinc-700 px-2.5 py-1.5 cursor-pointer text-sm">
                                            <flux:checkbox wire:model="inherits.{{ $role->key }}" value="{{ $carried->key }}" />
                                            {{ $carried->label }}
                                        </label>
                                    @endif
                                @endforeach
                            </div>
                            @if (($inheritedBy = \App\Support\RoleHierarchy::inheritedBy($role->key)) !== [])
                                <p class="mt-2.5 text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ __('فيبلغ فعلياً:') }}
                                    {{ collect($inheritedBy)->map(fn ($k) => $this->roles->firstWhere('key', $k)?->label ?? $k)->join(' · ') }}
                                </p>
                            @endif
                        </div>
                    @endforeach

                    <flux:button variant="primary" wire:click="saveHierarchy" icon="check">
                        {{ __('احفظ الترتيب') }}
                    </flux:button>
                </div>
            @endif
        </flux:card>

        <div class="grid grid-cols-1 lg:grid-cols-[20rem_1fr] gap-6 items-start">

            {{-- المستخدمون --}}
            <flux:card class="lg:sticky lg:top-4">
                <flux:field>
                    <flux:label>{{ __('ابحث عن مستخدم') }}</flux:label>
                    <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass"
                        placeholder="{{ __('الاسم أو البريد') }}" />
                </flux:field>

                <div class="mt-4 max-h-[26rem] overflow-y-auto divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->people as $candidate)
                        <button type="button" wire:key="u-{{ $candidate->id }}"
                            wire:click="selectPerson({{ $candidate->id }})"
                            class="w-full text-start py-2.5 px-2 rounded-lg transition-colors cursor-pointer
                                {{ $candidate->id === $userId ? 'bg-zinc-100 dark:bg-zinc-800' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm font-medium text-zinc-900 dark:text-white truncate">{{ $candidate->name }}</span>
                                @if ($candidate->is_super_admin)
                                    <flux:badge color="amber" size="sm">{{ __('عليا') }}</flux:badge>
                                @endif
                            </div>
                            <div class="text-[11px] text-zinc-500 dark:text-zinc-400 truncate">
                                {{ $candidate->roles->pluck('role')->map(fn ($r) => $this->roles->firstWhere('key', $r)?->label ?? $r)->join(' · ') ?: __('بلا دور') }}
                            </div>
                        </button>
                    @empty
                        <p class="py-6 text-center text-sm text-zinc-400">{{ __('لا نتائج.') }}</p>
                    @endforelse
                </div>
            </flux:card>

            {{-- صفحات المستخدم --}}
            <div class="space-y-5">
                @if (! $this->person)
                    <flux:card class="text-center py-16">
                        <flux:icon icon="user" class="size-10 mx-auto text-zinc-300 dark:text-zinc-600" />
                        <flux:subheading class="mt-3">{{ __('اختر مستخدماً لضبط ما يظهر له.') }}</flux:subheading>
                    </flux:card>
                @else
                    <flux:card>
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <flux:heading size="lg">{{ $this->person->name }}</flux:heading>
                                <flux:subheading class="mt-0.5">{{ $this->person->email }}</flux:subheading>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:button size="sm" variant="subtle" wire:click="clearOverrides" icon="arrow-path">
                                    {{ __('أعده لصلاحيات دوره') }}
                                </flux:button>
                                <flux:button size="sm" :variant="$this->person->is_super_admin ? 'danger' : 'filled'"
                                    wire:click="toggleSuperAdmin" icon="shield-check">
                                    {{ $this->person->is_super_admin ? __('انزع الصلاحية العليا') : __('ارفعه للصلاحية العليا') }}
                                </flux:button>
                            </div>
                        </div>

                        @if ($this->person->is_super_admin)
                            <div class="mt-4 rounded-xl border border-amber-200 dark:border-amber-900/50 bg-amber-50 dark:bg-amber-900/20 p-3 text-xs text-amber-700 dark:text-amber-300">
                                {{ __('صاحب صلاحية عليا: يرى كل شيء، ولا يُقيَّد بدور ولا باستثناء.') }}
                            </div>
                        @endif

                        <div class="mt-5 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                            <div class="text-xs font-bold text-zinc-500 dark:text-zinc-400 mb-2">{{ __('الأدوار التي يحملها') }}</div>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($this->roles as $role)
                                    @if (in_array($role->key, $this->heldRoles, true))
                                        <flux:button size="sm" wire:key="r-{{ $role->id }}"
                                            :variant="$role->key === $roleKey ? 'primary' : 'filled'"
                                            wire:click="$set('roleKey', '{{ $role->key }}')">
                                            {{ $role->label }}
                                        </flux:button>
                                    @endif
                                @endforeach
                                @if ($this->heldRoles === [])
                                    <span class="text-sm text-zinc-400">{{ __('لا يحمل أي دور بعد.') }}</span>
                                @endif
                            </div>
                        </div>
                    </flux:card>


                    {{-- نطاق هذا الدور له --}}
                    @if ($roleKey)
                        <flux:card>
                            <flux:heading size="lg">{{ __('كم يبلغ في هذا الدور') }}</flux:heading>
                            <flux:subheading class="mt-0.5">
                                {{ __('اتركه على «حسب الدور» ليعمل كما يعمل أمثاله. وحدّده لتصنع طبقة جديدة — مدير برنامج هو دور مدير المركز محدوداً ببرامج بعينها.') }}
                            </flux:subheading>

                            <div class="mt-4 space-y-4">
                                <flux:field>
                                    <flux:label>{{ __('النطاق') }}</flux:label>
                                    <flux:select wire:model.live="scopeType">
                                        <flux:select.option value="">{{ __('حسب الدور') }}</flux:select.option>
                                        <flux:select.option value="all">{{ __('المركز كله') }}</flux:select.option>
                                        <flux:select.option value="stages">{{ __('برامج بعينها') }}</flux:select.option>
                                        <flux:select.option value="circles">{{ __('دفعات بعينها') }}</flux:select.option>
                                    </flux:select>
                                    <flux:error name="scopeType" />
                                </flux:field>

                                @if ($scopeType === 'stages')
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        @foreach ($this->programmes as $programme)
                                            <label wire:key="pg-{{ $programme->id }}"
                                                class="flex items-center gap-2.5 rounded-xl border border-zinc-200 dark:border-zinc-700 px-3 py-2 cursor-pointer">
                                                <flux:checkbox wire:model="scopeIds" value="{{ $programme->id }}" />
                                                <span class="text-sm">{{ $programme->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                @elseif ($scopeType === 'circles')
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-64 overflow-y-auto">
                                        @foreach ($this->cohorts as $cohort)
                                            <label wire:key="ch-{{ $cohort->id }}"
                                                class="flex items-center gap-2.5 rounded-xl border border-zinc-200 dark:border-zinc-700 px-3 py-2 cursor-pointer">
                                                <flux:checkbox wire:model="scopeIds" value="{{ $cohort->id }}" />
                                                <span class="text-sm">
                                                    {{ $cohort->name }}
                                                    <span class="text-xs text-zinc-400">— {{ $cohort->stage?->name }}</span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                @endif

                                <flux:button variant="primary" wire:click="saveScope" icon="check">
                                    {{ __('احفظ النطاق') }}
                                </flux:button>
                            </div>
                        </flux:card>
                    @endif

                    @if ($this->rows->isNotEmpty())
                        <flux:card class="p-0 overflow-hidden">
                            <div class="overflow-x-auto">
                                <flux:table>
                                    <flux:table.columns>
                                        <flux:table.column>{{ __('الصفحة') }}</flux:table.column>
                                        <flux:table.column>{{ __('القسم') }}</flux:table.column>
                                        <flux:table.column>{{ __('دوره يعطيها') }}</flux:table.column>
                                        <flux:table.column>{{ __('الحالة له') }}</flux:table.column>
                                    </flux:table.columns>
                                    <flux:table.rows>
                                        @foreach ($this->rows as $row)
                                            <flux:table.row wire:key="s-{{ $row['screen']->id }}">
                                                <flux:table.cell class="font-medium">{{ $row['screen']->label }}</flux:table.cell>
                                                <flux:table.cell class="text-zinc-500 whitespace-nowrap">{{ $row['screen']->group_label }}</flux:table.cell>
                                                <flux:table.cell>
                                                    <span class="text-xs {{ $row['byRole'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-400' }}">
                                                        {{ $row['byRole'] ? __('نعم') : __('لا') }}
                                                    </span>
                                                </flux:table.cell>
                                                <flux:table.cell>
                                                    @if ($row['screen']->is_protected)
                                                        <flux:badge color="zinc" size="sm">{{ __('دائمة') }}</flux:badge>
                                                    @else
                                                        <button type="button" wire:click="cycle({{ $row['screen']->id }})"
                                                            class="cursor-pointer">
                                                            @if ($row['override'] === true)
                                                                <flux:badge color="lime" size="sm">{{ __('مفتوحة له استثناءً') }}</flux:badge>
                                                            @elseif ($row['override'] === false)
                                                                <flux:badge color="rose" size="sm">{{ __('مخفية عنه استثناءً') }}</flux:badge>
                                                            @else
                                                                <flux:badge :color="$row['effective'] ? 'zinc' : 'zinc'" size="sm">
                                                                    {{ $row['effective'] ? __('يرث دوره — ظاهرة') : __('يرث دوره — مخفية') }}
                                                                </flux:badge>
                                                            @endif
                                                        </button>
                                                    @endif
                                                </flux:table.cell>
                                            </flux:table.row>
                                        @endforeach
                                    </flux:table.rows>
                                </flux:table>
                            </div>
                            <p class="px-4 py-3 text-xs text-zinc-500 dark:text-zinc-400 border-t border-zinc-100 dark:border-zinc-800">
                                {{ __('اضغط الحالة لتنتقل بين: يرث دوره ← استثناء يفتح ← استثناء يخفي ← يرث دوره.') }}
                            </p>
                        </flux:card>
                    @endif
                @endif
            </div>
        </div>
    @endunless
</div>
