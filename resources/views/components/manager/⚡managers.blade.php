<?php

use App\Models\Circle;
use App\Models\Manager;
use App\Models\Stage;
use App\Models\UserRole;
use App\Notifications\AccountInvitation;
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
 * His own address is asked for and no password is. The account writes to him
 * itself with a link that lets him choose one, so nobody invents a password for
 * another person, nobody passes one along, and nobody has to remember to tell
 * him where the door is.
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

    public ?int $editing = null;

    /** Whose invitation link to show, when the letter could not be posted. */
    public ?int $showLinkFor = null;

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

        $sent = $this->invite($manager);

        $this->reset(['name', 'email', 'phone', 'reaches']);
        unset($this->managers);

        Flux::modal('manager-modal')->close();
        Flux::toast(
            $sent
                ? __('أُنشئ :label، وأُرسلت له دعوةٌ لضبط كلمته.', ['label' => ManagerTier::LABELS[$this->tier]])
                : __('أُنشئ :label، لكنّ الدعوة لم تُرسل — انسخ رابطها من «أعد الدعوة».', ['label' => ManagerTier::LABELS[$this->tier]]),
            variant: $sent ? 'success' : 'warning',
        );
    }

    /**
     * Write to him, and say plainly when it could not be done.
     *
     * A mail server that is misconfigured or unreachable must not take the
     * account down with it: it was made, the reach is written, and the letter is
     * the one thing that failed — so the failure is reported and the account
     * kept, rather than the whole thing rolled back over an SMTP timeout.
     */
    private function invite(Manager $manager): bool
    {
        try {
            $manager->notify(new AccountInvitation(
                auth('manager')->user()?->name ?? config('brand.name'),
            ));

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    /** Send it again — the link lasts three days, and people miss things. */
    public function resendInvitation(int $id): void
    {
        $manager = Manager::findOrFail($id);

        // Asked once and remembered: calling it twice — once for the words and
        // once for the colour — would post the man two letters.
        $sent = $this->invite($manager);

        Flux::toast(
            $sent
                ? __('أُرسلت الدعوة إلى :email.', ['email' => $manager->email])
                : __('تعذّر الإرسال. راجع إعداد البريد، أو انسخ رابط الدعوة.'),
            variant: $sent ? 'success' : 'warning',
        );

        $this->showLinkFor = $sent ? null : $manager->id;
    }

    /** The link itself, for an academy whose mail is not set up yet. */
    public function invitationLink(int $id): string
    {
        return AccountInvitation::linkFor(Manager::findOrFail($id));
    }

    /**
     * Open a manager's own details for changing.
     *
     * A super administrator is edited by a super administrator only. Any centre
     * manager could otherwise change his address and then take the account
     * through «نسيت كلمة المرور» — which would hand himself the one mark that
     * overrules every check in the application.
     */
    public function edit(int $id): void
    {
        $manager = Manager::findOrFail($id);

        abort_if($manager->is_super_admin && ! auth('manager')->user()?->is_super_admin, 403);

        $this->editing = $manager->id;
        $this->name = $manager->name;
        $this->email = $manager->email;
        $this->phone = (string) ($manager->phone ?? '');

        Flux::modal('edit-modal')->show();
    }

    /** Save what was changed. The reach is not touched here; it has its own. */
    public function update(): void
    {
        $manager = Manager::findOrFail($this->editing);

        abort_if($manager->is_super_admin && ! auth('manager')->user()?->is_super_admin, 403);

        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$manager->id],
            'phone' => ['nullable', 'string', 'max:20'],
        ], [], [
            'name' => __('الاسم'),
            'email' => __('البريد'),
        ]);

        $manager->update([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone ?: null,
        ]);

        $this->reset(['name', 'email', 'phone', 'editing']);
        unset($this->managers);

        Flux::modal('edit-modal')->close();
        Flux::toast(__('حُفظت البيانات.'), variant: 'success');
    }

    /**
     * Shut an account without unmaking the man.
     *
     * This is the one to reach for. He cannot sign in, and every trace of what
     * he did — whom he approved, what he wrote, what he was assigned — stays
     * exactly where it is, under his own name. Turning him back on is one
     * click, which is not true of the other.
     */
    public function setActive(int $id, bool $active): void
    {
        $manager = $this->guarded($id);

        $manager->update([
            'is_approved' => $active,
            'approved_by' => $active ? auth('manager')->id() : $manager->approved_by,
        ]);

        unset($this->managers);

        Flux::toast($active ? __('أُعيد تفعيله.') : __('عُطِّل حسابه.'), variant: 'success');
    }

    /**
     * Remove the account itself.
     *
     * What he did under his own name survives him: the columns that record who
     * approved, who granted and who set a thing are emptied rather than taken
     * away, so the approval stands and only the name beside it goes. What is his
     * own — his attendance, his tasks, his holdings of roles — goes with him.
     *
     * Irreversible, which is why «عطِّل» sits beside it.
     */
    public function destroy(int $id): void
    {
        $manager = $this->guarded($id);

        $manager->delete();

        unset($this->managers);

        Flux::toast(__('حُذف الحساب.'), variant: 'success');
    }

    /**
     * The manager a destructive action may be aimed at.
     *
     * Neither the administrator nor oneself: the first because his mark
     * overrules every check in the application, the second because a man who
     * shuts his own account is locked out by his own click.
     *
     * The second is also what keeps the academy from being locked out entirely.
     * This screen is the centre's own, so whoever is deleting reaches the whole
     * centre himself — and since he cannot delete himself, one such man is
     * always left standing. A separate "not the last one" guard was written
     * here and then removed: it could never fire.
     */
    private function guarded(int $id): Manager
    {
        $manager = Manager::with('roles')->findOrFail($id);

        abort_if($manager->is_super_admin, 403);
        abort_if($manager->id === auth('manager')->id(), 403);

        return $manager;
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

                        @unless ($manager->is_approved)
                            <flux:badge size="sm" color="zinc">{{ __('معطَّل') }}</flux:badge>
                        @endunless

                        @if ($manager->is_super_admin)
                            <flux:badge size="sm" color="red">{{ __('صلاحية عليا') }}</flux:badge>
                        @else
                            <flux:badge size="sm">{{ \App\Support\ManagerTier::LABELS[$his] }}</flux:badge>
                        @endif
                    </div>

                    <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400" dir="ltr">{{ $manager->email }}</div>

                    @if ($showLinkFor === $manager->id)
                        {{-- Mail could not go out, so the link is handed over by
                             hand rather than lost with the letter. --}}
                        <div class="mt-2 rounded-xl border border-amber-300 bg-amber-50 dark:bg-amber-900/20 px-3 py-2">
                            <p class="text-[11px] font-bold text-amber-700 dark:text-amber-300">
                                {{ __('لم يخرج البريد — أرسل له هذا الرابط بنفسك (صالح ٣ أيام):') }}
                            </p>
                            <p class="mt-1 text-[11px] text-amber-800 dark:text-amber-200 break-all" dir="ltr">
                                {{ $this->invitationLink($manager->id) }}
                            </p>
                        </div>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-1.5">
                    <flux:button size="sm" variant="filled" icon="pencil-square" wire:click="edit({{ $manager->id }})">
                        {{ __('عدّل') }}
                    </flux:button>

                    <flux:button size="sm" variant="ghost" icon="envelope"
                        wire:click="resendInvitation({{ $manager->id }})"
                        wire:confirm="{{ __('سترسل دعوةً جديدة إلى :email، ويبطل الرابط القديم بعد ثلاثة أيام. متابعة؟', ['email' => $manager->email]) }}">
                        {{ __('أعد الدعوة') }}
                    </flux:button>

                    @unless ($manager->is_super_admin || $manager->id === auth('manager')->id())
                        <flux:button size="sm" variant="ghost"
                            :icon="$manager->is_approved ? 'pause-circle' : 'play-circle'"
                            wire:click="setActive({{ $manager->id }}, {{ $manager->is_approved ? 'false' : 'true' }})"
                            wire:confirm="{{ $manager->is_approved
                                ? __('سيُمنع :name من الدخول، ويبقى كلّ ما سجّله. متابعة؟', ['name' => $manager->name])
                                : __('سيعود :name إلى الدخول. متابعة؟', ['name' => $manager->name]) }}">
                            {{ $manager->is_approved ? __('عطِّل') : __('فعِّل') }}
                        </flux:button>

                        <flux:button size="sm" variant="ghost" icon="trash"
                            class="text-red-secondary hover:!text-white hover:!bg-red-secondary"
                            wire:click="destroy({{ $manager->id }})"
                            wire:confirm="{{ __('حذفٌ لا رجعة فيه لحساب :name. يبقى ما اعتمده ووقّعه بلا اسمٍ بجانبه، ويذهب ما هو له وحده. والأسلم «عطِّل». متابعة؟', ['name' => $manager->name]) }}">
                            {{ __('احذف') }}
                        </flux:button>
                    @endunless

                    @unless ($manager->is_super_admin)
                        @foreach (\App\Support\ManagerTier::LABELS as $key => $label)
                        <flux:button size="sm" :variant="$key === $his ? 'primary' : 'ghost'"
                            wire:click="retier({{ $manager->id }}, '{{ $key }}')"
                            wire:confirm="{{ __('سيصير :name :label. متابعة؟', ['name' => $manager->name, 'label' => $label]) }}">
                            {{ $label }}
                        </flux:button>
                        @endforeach
                    @endunless
                </div>
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

    {{-- تعديل بيانات مدير --}}
    <flux:modal name="edit-modal" class="w-full max-w-lg">
        <form wire:submit="update" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('تعديل البيانات') }}</flux:heading>
                <flux:subheading>{{ __('مداه يُغيَّر من أزرار الطبقات، لا من هنا.') }}</flux:subheading>
            </div>

            <flux:field>
                <flux:label>{{ __('الاسم') }}</flux:label>
                <flux:input wire:model="name" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('البريد الإلكتروني') }}</flux:label>
                <flux:input wire:model="email" type="email" dir="ltr" />
                <flux:error name="email" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('الجوال') }} <span class="text-zinc-400">({{ __('اختياري') }})</span></flux:label>
                <flux:input wire:model="phone" dir="ltr" placeholder="05XXXXXXXX" />
                <flux:error name="phone" />
            </flux:field>

            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                {{ __('كلمة المرور لا تُغيَّر من هنا — يضبطها صاحبها من «نسيت كلمة المرور».') }}
            </p>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('إلغاء') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('احفظ') }}</flux:button>
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
