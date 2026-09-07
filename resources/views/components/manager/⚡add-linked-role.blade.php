<?php

use App\Models\Attendance;
use App\Models\StudentPlan;
use App\Models\StudentSelfProgramEntry;
use App\Models\User;
use App\Models\UserRole;
use App\Services\MessagingService;
use App\Support\Access;
use App\Support\RecitationOnlyTeacher;
use App\Support\Scope;
use Illuminate\Support\Facades\DB;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public string $sourceGuard;

    public int $sourceId;

    public string $sourceName;

    /**
     * Only the four "directory" roles are offered here — matching the tabs
     * on the unified users page this component is embedded in.
     */
    protected const ADDABLE_GUARDS = ['student', 'teacher', 'supervisor', 'guardian'];

    public function mount(string $sourceGuard, int $sourceId, string $sourceName): void
    {
        $this->sourceGuard = $sourceGuard;
        $this->sourceId = $sourceId;
        $this->sourceName = $sourceName;
    }

    /**
     * Grants the same person (same `users` row) an additional role. Unlike
     * the old cross-account linking, this doesn't create a new account —
     * it's the same login, just with another `user_roles` row attached.
     */
    public function grant(string $guard): void
    {
        if (! in_array($guard, self::ADDABLE_GUARDS, true) || $guard === $this->sourceGuard) {
            return;
        }

        UserRole::firstOrCreate(
            ['user_id' => $this->sourceId, 'role' => $guard],
            ['is_approved' => true, 'approved_by' => Auth::guard('manager')->id()]
        );

        $roleLabel = MessagingService::ROLE_LABELS[$guard];

        Flux::toast(
            __('تم إضافة دور :role لـ :name بنجاح', ['role' => $roleLabel, 'name' => $this->sourceName]),
            variant: 'success'
        );
    }

    /**
     * Move a person from one office to another.
     *
     * Not the same as granting a second role: a supervisor who becomes a
     * teacher stops being a supervisor, and what he held as one has to be let
     * go of. His programmes are released, his cohorts are released — leaving
     * them attached would keep him inside a reach his office no longer gives
     * him, and `Scope` would go on answering for the office he left.
     *
     * What he did as that office stays exactly where it is. The attendance he
     * recorded, the tasks he was given, the placements he approved — those are
     * a record of what happened, and moving him does not unhappen it.
     */
    public function move(string $to): void
    {
        if (! in_array($to, self::ADDABLE_GUARDS, true) || $to === $this->sourceGuard) {
            return;
        }

        $user = User::find($this->sourceId);

        if (! $user) {
            return;
        }

        if ($refusal = $this->reasonNotToMove($user)) {
            Flux::toast($refusal, variant: 'danger');

            return;
        }

        DB::transaction(function () use ($user, $to) {
            UserRole::firstOrCreate(
                ['user_id' => $user->id, 'role' => $to],
                ['is_approved' => true, 'approved_by' => Auth::guard('manager')->id()],
            );

            UserRole::where('user_id', $user->id)->where('role', $this->sourceGuard)->delete();

            $this->releaseWhatTheOfficeHeld($user);
        });

        // The three that remember who may see what, and how far.
        Access::forget();
        Scope::forget();
        RecitationOnlyTeacher::forget($user->id);

        $this->dispatch('user-list-updated');

        Flux::toast(
            __('نُقل :name إلى :role', [
                'name' => $this->sourceName,
                'role' => MessagingService::ROLE_LABELS[$to] ?? $to,
            ]),
            variant: 'success',
        );
    }

    /**
     * Why this person may not be moved out of the office he is in, if he may not.
     *
     * A student who has been taught carries a record that only means anything
     * for a student: his plans, his self programme, his attendance. Moving him
     * would leave all of it pointing at somebody who is no longer one. And a
     * guardian with children attached is the link those children are found by.
     */
    private function reasonNotToMove(User $user): ?string
    {
        if ($this->sourceGuard === 'student') {
            $taught = StudentPlan::where('student_id', $user->id)->exists()
                || StudentSelfProgramEntry::where('student_id', $user->id)->exists()
                || Attendance::where('student_id', $user->id)->exists();

            if ($taught) {
                return __('لهذا الطالب سجل دراسي — خطط أو إنجاز أو حضور. أضِف له الدور الجديد ولا تنقله، كي لا يبقى سجلّه معلّقاً بلا صاحب.');
            }
        }

        if ($this->sourceGuard === 'guardian' && User::where('guardian_id', $user->id)->exists()) {
            return __('هذا وليّ أمر مرتبط به أبناء. افصل الأبناء عنه أولاً، أو أضِف له الدور الجديد بدل نقله.');
        }

        return null;
    }

    /** Let go of what the office he is leaving gave him, and nothing else. */
    private function releaseWhatTheOfficeHeld(User $user): void
    {
        match ($this->sourceGuard) {
            'teacher' => $user->circles()->detach(),
            'supervisor' => $user->stages()->detach(),
            'student' => $user->forceFill(['circle_id' => null])->save(),
            default => null,
        };
    }

    public function revoke(string $guard): void
    {
        if ($guard === $this->sourceGuard) {
            return;
        }

        UserRole::where('user_id', $this->sourceId)->where('role', $guard)->delete();

        Flux::toast(__('تم سحب الدور بنجاح'), variant: 'success');
    }

    public function with(): array
    {
        $roleLabels = MessagingService::ROLE_LABELS;

        $otherRoles = UserRole::where('user_id', $this->sourceId)
            ->where('role', '!=', $this->sourceGuard)
            ->get();

        $heldGuards = $otherRoles->pluck('role')->all();

        $availableGuards = collect(self::ADDABLE_GUARDS)
            ->reject(fn ($guard) => $guard === $this->sourceGuard)
            ->reject(fn ($guard) => in_array($guard, $heldGuards, true))
            ->values();

        return compact('roleLabels', 'otherRoles', 'availableGuards');
    }
};
?>

<div class="border-t border-zinc-100 dark:border-zinc-800 pt-4 mt-2 space-y-3">
    <flux:heading size="sm">{{ __('الأدوار الأخرى لهذا الشخص') }}</flux:heading>

    @if($otherRoles->isNotEmpty())
        <div class="flex flex-wrap gap-1.5">
            @foreach($otherRoles as $role)
                <flux:badge size="sm" variant="neutral">
                    {{ $roleLabels[$role->role] ?? $role->role }}
                    <button type="button" wire:click="revoke('{{ $role->role }}')" wire:confirm="{{ __('متأكد إنك عايز تسحب دور :role من الشخص ده؟', ['role' => $roleLabels[$role->role] ?? $role->role]) }}" class="ms-1 opacity-60 hover:opacity-100">×</button>
                </flux:badge>
            @endforeach
        </div>
    @endif

    @if($availableGuards->isNotEmpty())
        <div class="space-y-1.5">
            <span class="text-xs text-zinc-400">{{ __('إضافة دور — يبقى على دوره الحالي ويحمل الجديد معه') }}</span>
            <div class="flex flex-wrap gap-1.5">
                @foreach($availableGuards as $guard)
                    <flux:button size="sm" variant="ghost" icon="plus" wire:click="grant('{{ $guard }}')" wire:confirm="{{ __('سيحمل :name دور :role مع دوره الحالي. متابعة؟', ['name' => $sourceName, 'role' => $roleLabels[$guard] ?? $guard]) }}">
                        {{ __(':role', ['role' => $roleLabels[$guard] ?? $guard]) }}
                    </flux:button>
                @endforeach
            </div>
        </div>

        <div class="space-y-1.5 pt-3 border-t border-zinc-100 dark:border-zinc-800">
            <span class="text-xs text-zinc-400">
                {{ __('نقل الدور — يترك دوره الحالي ويصير :role', ['role' => __('الدور الجديد')]) }}
            </span>
            <div class="flex flex-wrap gap-1.5">
                @foreach($availableGuards as $guard)
                    <flux:button size="sm" variant="ghost" icon="arrows-right-left" class="text-maroon dark:text-red-secondary"
                        wire:click="move('{{ $guard }}')"
                        wire:confirm="{{ __('سيترك :name دور :from ويصير :to. وما ارتبط به من دفعات أو برامج يُفكّ عنه، ويبقى ما سجّله كما هو. متابعة؟', ['name' => $sourceName, 'from' => $roleLabels[$sourceGuard] ?? $sourceGuard, 'to' => $roleLabels[$guard] ?? $guard]) }}">
                        {{ __('نقله إلى :role', ['role' => $roleLabels[$guard] ?? $guard]) }}
                    </flux:button>
                @endforeach
            </div>
        </div>
    @endif
</div>
