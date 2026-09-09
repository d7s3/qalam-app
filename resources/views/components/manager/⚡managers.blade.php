<?php

use App\Models\Circle;
use App\Models\Manager;
use App\Models\Stage;
use App\Models\UserRole;
use App\Support\Access;
use App\Support\ManagerTier;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The managers, and the making of them.
 *
 * Supervisors and teachers could always be created from here; managers could
 * not. The only way to become one was to register and be approved, so an
 * administrator asking for a manager over one programme had nothing to click —
 * the tier existed, and nobody could make anybody into it.
 *
 * The three are one office at three reaches, so they are made on one screen:
 * choose the reach, and the name follows. A man made over a programme carries
 * every manager's screen inside it and none of the centre's own.
 *
 * His own address is asked for and no password is: he sets his own from
 * «نسيت كلمة المرور», so nobody invents a password for another person and
 * nobody has to pass one along.
 */
new class extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $tier = ManagerTier::PROGRAMME;

    /** @var array<int, int> */
    public array $reaches = [];

    public string $search = '';

    /** Only the centre's own manager makes managers. */
    public function mount(): void
    {
        abort_unless(Access::canSee(auth('manager')->user(), 'manager', 'manager.managers'), 403);
    }

    /** @return Collection<int, Manager> */
    #[Computed]
    public function managers(): Collection
    {
        return Manager::query()
            ->when($this->search !== '', fn ($q) => $q->where(
                fn ($w) => $w->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%"),
            ))
            ->with('roles')
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, Stage> */
    #[Computed]
    public function programmes(): Collection
    {
        return Stage::orderBy('name')->get();
    }

    /** @return Collection<int, Circle> */
    #[Computed]
    public function cohorts(): Collection
    {
        return Circle::with('stage')->orderBy('name')->get();
    }

    /** What choosing this reach makes him. */
    #[Computed]
    public function tierLabel(): string
    {
        return ManagerTier::LABELS[$this->tier] ?? '';
    }

    public function updatedTier(): void
    {
        // A programme is not a cohort: what was ticked for one means nothing
        // for the other, and carrying it over would silently make him a manager
        // of something he was never given.
        $this->reaches = [];
    }

    /**
     * Make a manager at the chosen reach.
     *
     * The reach is written onto his holding of the role rather than onto him,
     * because it is this role's reach and not the man's — he may hold another
     * elsewhere, and `Scope` keeps the two apart.
     */
    public function create(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'tier' => ['required', 'in:'.implode(',', array_keys(ManagerTier::LABELS))],
            'reaches' => ['array'],
            'reaches.*' => ['integer'],
        ], [], [
            'name' => __('الاسم'),
            'email' => __('البريد'),
            'tier' => __('المستوى'),
        ]);

        if ($this->tier !== ManagerTier::CENTRE && $this->reaches === []) {
            Flux::toast(__('اختر ما يبلغه، ولو واحداً.'), variant: 'warning');

            return;
        }

        $manager = Manager::create([
            'name' => $this->name,
            'phone' => $this->phone ?: null,
            'email' => $this->email,
            // Unguessable and never shown: he sets his own from the sign-in
            // page's «نسيت كلمة المرور», so no password passes through anybody.
            'password' => Hash::make(Str::random(40)),
            'is_approved' => true,
            'approved_by' => auth('manager')->id(),
        ]);

        $this->writeReach($manager);

        $this->reset(['name', 'email', 'phone', 'reaches']);
        unset($this->managers);

        Flux::modal('manager-modal')->close();
        Flux::toast(
            __('أُنشئ :label. يضبط كلمته من «نسيت كلمة المرور».', ['label' => ManagerTier::LABELS[$this->tier]]),
            variant: 'success',
        );
    }

    /** Move a manager already made from one reach to another. */
    public function retier(int $id, string $tier): void
    {
        $manager = Manager::with('roles')->findOrFail($id);

        abort_unless(in_array($tier, array_keys(ManagerTier::LABELS), true), 422);

        // The centre's own manager is never narrowed by this screen: his mark
        // overrules every reach, so the name would say one thing and the
        // application do another.
        if ($manager->is_super_admin) {
            Flux::toast(__('صاحب الصلاحية العليا لا يُقيَّد.'), variant: 'warning');

            return;
        }

        $this->tier = $tier;
        $this->reaches = $tier === ManagerTier::CENTRE
            ? []
            : array_map('intval', $manager->roles->firstWhere('role', 'manager')?->scope_ids ?? []);

        if ($tier === ManagerTier::CENTRE) {
            $this->writeReach($manager);
            unset($this->managers);
            ManagerTier::forget();

            Flux::toast(__('صار :label.', ['label' => ManagerTier::LABELS[$tier]]), variant: 'success');

            return;
        }

        // Below the centre he needs to be told what he reaches, so the screen
        // opens rather than guessing.
        Flux::modal('reach-modal')->show();
        $this->retiering = $id;
    }

    public ?int $retiering = null;

    public function saveReach(): void
    {
        $manager = Manager::with('roles')->findOrFail($this->retiering);

        if ($this->reaches === []) {
            Flux::toast(__('اختر ما يبلغه، ولو واحداً.'), variant: 'warning');

            return;
        }

        $this->writeReach($manager);
        $this->retiering = null;
        unset($this->managers);
        ManagerTier::forget();

        Flux::modal('reach-modal')->close();
        Flux::toast(__('حُفظ ما يبلغه.'), variant: 'success');
    }

    /** Write the chosen reach onto this man's holding of the manager's role. */
    private function writeReach(Manager $manager): void
    {
        $holding = $manager->roles()->firstOrCreate(
            ['role' => 'manager'],
            ['is_approved' => true, 'approved_by' => auth('manager')->id()],
        );

        $holding->update(match ($this->tier) {
            ManagerTier::CENTRE => ['scope_type' => null, 'scope_ids' => null],
            ManagerTier::PROGRAMME => ['scope_type' => UserRole::SCOPE_STAGES, 'scope_ids' => array_values(array_map('intval', $this->reaches))],
            ManagerTier::COHORT => ['scope_type' => UserRole::SCOPE_CIRCLES, 'scope_ids' => array_values(array_map('intval', $this->reaches))],
        });

        $manager->load('roles');
        ManagerTier::forget();
        Access::forget();
    }

}; ?>

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <x-section-heading icon="user-group">
            {{ __('المديرون') }}

            <x-slot:detail>
                {{ __('مكتبٌ واحد على ثلاثة مَدَيات: المركز كلّه، أو برنامجٌ بعينه، أو دفعةٌ بعينها.') }}
            </x-slot:detail>
        </x-section-heading>

        <flux:modal.trigger name="manager-modal">
            <flux:button variant="primary" icon="plus">{{ __('مدير جديد') }}</flux:button>
        </flux:modal.trigger>
    </div>

    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
        :placeholder="__('ابحث بالاسم أو البريد')" class="max-w-sm" />

    <div class="space-y-3">
        @forelse ($this->managers as $manager)
            {{-- Named apart from the component's own `$tier`: an inline block
                 in a loop writes into the same scope, so calling it `$tier`
                 here silently overwrote the chosen reach, and every panel below
                 read the last manager's instead. --}}
            @php
                $his = \App\Support\ManagerTier::of($manager);
            @endphp
            <flux:card class="flex flex-wrap items-center justify-between gap-4" wire:key="m-{{ $manager->id }}">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-zinc-900 dark:text-white">{{ $manager->name }}</span>

                        @if ($manager->is_super_admin)
                            <flux:badge size="sm" color="red">{{ __('صلاحية عليا') }}</flux:badge>
                        @else
                            <flux:badge size="sm">{{ \App\Support\ManagerTier::LABELS[$his] }}</flux:badge>
                        @endif
                    </div>

                    <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400" dir="ltr">{{ $manager->email }}</div>
                </div>

                @unless ($manager->is_super_admin)
                    <div class="flex flex-wrap items-center gap-1.5">
                        @foreach (\App\Support\ManagerTier::LABELS as $key => $label)
                            <flux:button size="sm" :variant="$key === $his ? 'primary' : 'ghost'"
                                wire:click="retier({{ $manager->id }}, '{{ $key }}')"
                                wire:confirm="{{ __('سيصير :name :label. متابعة؟', ['name' => $manager->name, 'label' => $label]) }}">
                                {{ $label }}
                            </flux:button>
                        @endforeach
                    </div>
                @endunless
            </flux:card>
        @empty
            <x-empty-note icon="user-group" :title="__('لا مديرين بعد')">
                {{ __('أنشئ أوّلهم من «مدير جديد».') }}
            </x-empty-note>
        @endforelse
    </div>

    {{-- إنشاء مدير --}}
    <flux:modal name="manager-modal" class="w-full max-w-lg">
        <form wire:submit="create" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('مدير جديد') }}</flux:heading>
                <flux:subheading>{{ __('اختر مداه، ويأخذ اسمه منه.') }}</flux:subheading>
            </div>

            <flux:field>
                <flux:label>{{ __('الاسم') }}</flux:label>
                <flux:input wire:model="name" :placeholder="__('الاسم الكامل')" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('البريد الإلكتروني') }}</flux:label>
                <flux:input wire:model="email" type="email" dir="ltr" placeholder="name@example.com" />
                <flux:description>{{ __('يدخل به، ويضبط كلمته بنفسه من «نسيت كلمة المرور».') }}</flux:description>
                <flux:error name="email" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('الجوال') }} <span class="text-zinc-400">({{ __('اختياري') }})</span></flux:label>
                <flux:input wire:model="phone" dir="ltr" placeholder="05XXXXXXXX" />
                <flux:error name="phone" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('مداه') }}</flux:label>
                <flux:select wire:model.live="tier">
                    @foreach (\App\Support\ManagerTier::LABELS as $key => $label)
                        <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="tier" />
            </flux:field>

            @if ($tier === \App\Support\ManagerTier::CENTRE)
                <div class="rounded-xl border border-gold/40 bg-gold/5 px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                    {{ __('يرى الأكاديمية كلّها، ويحمل صفحات المركز: النسخ الاحتياطي، والأدوار وصلاحياتها، ونطاقات الناس، وإنشاء البرامج والمديرين.') }}
                </div>
            @else
                <flux:field>
                    <flux:label>
                        {{ $tier === \App\Support\ManagerTier::PROGRAMME ? __('برامجه') : __('دفعاته') }}
                    </flux:label>

                    <div class="max-h-48 space-y-1.5 overflow-y-auto rounded-xl border border-zinc-200 dark:border-zinc-700 p-3">
                        @if ($tier === \App\Support\ManagerTier::PROGRAMME)
                            @forelse ($this->programmes as $programme)
                                <flux:checkbox wire:model="reaches" value="{{ $programme->id }}" :label="$programme->name" />
                            @empty
                                <p class="text-sm text-zinc-400">{{ __('لا برامج بعد.') }}</p>
                            @endforelse
                        @else
                            @forelse ($this->cohorts as $cohort)
                                <flux:checkbox wire:model="reaches" value="{{ $cohort->id }}"
                                    :label="$cohort->name.' — '.($cohort->stage?->name ?? __('بلا برنامج'))" />
                            @empty
                                <p class="text-sm text-zinc-400">{{ __('لا دفعات بعد.') }}</p>
                            @endforelse
                        @endif
                    </div>
                </flux:field>

                <div class="rounded-xl border border-gold/40 bg-gold/5 px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                    {{ __('يرى ما اخترتَه له كاملاً — بمشرفيه ومعلّميه وطلّابه وكلّ ما جُدول فيه — ولا يرى ما سواه. وتبقى للمركز صفحاته.') }}
                </div>
            @endif

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('إلغاء') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">
                    {{ __('أنشئ :label', ['label' => $this->tierLabel]) }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- نقل مديرٍ قائم إلى مدى آخر --}}
    <flux:modal name="reach-modal" class="w-full max-w-lg">
        <form wire:submit="saveReach" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('ما الذي يبلغه؟') }}</flux:heading>
                <flux:subheading>{{ __('سيصير :label.', ['label' => $this->tierLabel]) }}</flux:subheading>
            </div>

            <div class="max-h-64 space-y-1.5 overflow-y-auto rounded-xl border border-zinc-200 dark:border-zinc-700 p-3">
                @if ($tier === \App\Support\ManagerTier::PROGRAMME)
                    @foreach ($this->programmes as $programme)
                        <flux:checkbox wire:model="reaches" value="{{ $programme->id }}" :label="$programme->name" />
                    @endforeach
                @else
                    @foreach ($this->cohorts as $cohort)
                        <flux:checkbox wire:model="reaches" value="{{ $cohort->id }}"
                            :label="$cohort->name.' — '.($cohort->stage?->name ?? __('بلا برنامج'))" />
                    @endforeach
                @endif
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('إلغاء') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('احفظ') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
