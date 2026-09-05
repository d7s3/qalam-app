<?php

use App\Models\Circle;
use App\Models\Stage;
use App\Models\Student;
use App\Models\User;
use App\Models\StudentPlacementRequest;
use App\Services\StudentPlacementService;
use App\Support\Scope;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * The supervisor's queue: who is asking for whom, and who is waiting to be let
 * into a programme at all.
 *
 * Both are his to answer, and both used to happen without him. A teacher wrote
 * `circle_id` on a student directly, from a pool that was every unplaced student
 * in the academy; and a student admitted to no programme was in that same pool,
 * so being admitted was never a decision anybody made.
 */
new class extends Component
{
    public array $notes = [];

    public ?int $admitStage = null;

    public string $search = '';

    /**
     * The role this page was opened under, kept for the rest of its life.
     *
     * Resolving it afresh on every action would read the route, and a Livewire
     * update's route is `livewire.update` — so a manager who also teaches would
     * be answered for as a teacher the moment he clicked anything. Tampering
     * with it from the browser gains nothing: the guard it names still has to
     * have him signed in, and `decider()` refuses when it does not.
     */
    public string $asRole = 'supervisor';

    public function mount(): void
    {
        $this->asRole = Scope::resolveRole();
        $this->admitStage = $this->scope()->stageIds()?->first() ?? Stage::value('id');
    }

    /**
     * The reach of whoever is reading, not of the role the page was written for.
     *
     * The manager carries the supervisor and opens this through `manager.held`,
     * where he is signed in on his own guard and not on the supervisor's — so
     * naming that guard here answered for nobody and the page fell over in his
     * hands.
     */
    private function scope(): Scope
    {
        return Scope::forRole($this->asRole);
    }

    /** Whoever is answering, which the service records against the decision. */
    private function decider(): User
    {
        return $this->scope()->user() ?? abort(403);
    }

    /** The requests standing for cohorts this supervisor answers for. */
    private function pendingQuery()
    {
        $circleIds = $this->scope()->circleIds();

        return StudentPlacementRequest::pending()
            ->with(['student', 'circle.stage', 'requestedBy'])
            ->when($circleIds !== null, fn ($q) => $q->whereIn('circle_id', $circleIds ?? []))
            ->latest();
    }

    public function approve(int $requestId): void
    {
        $request = $this->pendingQuery()->whereKey($requestId)->firstOrFail();

        StudentPlacementService::approve($request, $this->decider());

        Flux::toast(__('تم التسكين'), variant: 'success');
    }

    public function reject(int $requestId): void
    {
        $request = $this->pendingQuery()->whereKey($requestId)->firstOrFail();

        StudentPlacementService::reject(
            $request,
            $this->decider(),
            $this->notes[$requestId] ?? null,
        );

        unset($this->notes[$requestId]);

        Flux::toast(__('لم يُقبل الطلب'), variant: 'success');
    }

    /** Letting a student into a programme, which is what makes him placeable. */
    public function admit(int $studentId): void
    {
        $stage = Stage::findOrFail($this->admitStage);
        $reachable = $this->scope()->stageIds();

        abort_if($reachable !== null && ! $reachable->contains($stage->id), 403);

        StudentPlacementService::admitToProgramme(
            Student::whereNull('stage_id')->findOrFail($studentId),
            $stage,
            $this->decider(),
        );

        Flux::toast(__('قُبل الطالب في البرنامج'), variant: 'success');
    }

    public function with(): array
    {
        $stageIds = $this->scope()->stageIds();

        return [
            'requests' => $this->pendingQuery()->get(),
            'awaiting' => Student::whereNull('stage_id')
                ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
                ->orderBy('name')
                ->take(25)
                ->get(),
            'stages' => $stageIds === null
                ? Stage::orderBy('name')->get()
                : Stage::whereIn('id', $stageIds)->orderBy('name')->get(),
        ];
    }
};
?>

<div class="space-y-8" dir="rtl">
    <div>
        <flux:heading size="xl" class="font-bold text-zinc-900 dark:text-white">
            {{ __('طلبات التسكين') }}
        </flux:heading>
        <flux:subheading class="text-zinc-500 dark:text-zinc-400 mt-1">
            {{ __('المعلّم يطلب الطالب، وأنت تقرّر. ولا يدخل طالب برنامجاً حتى تقبله فيه.') }}
        </flux:subheading>
    </div>

    <div class="space-y-3">
        <flux:heading size="lg">{{ __('طلبات المعلّمين') }}</flux:heading>

        @forelse($requests as $request)
            <flux:card wire:key="request-{{ $request->id }}" class="space-y-3">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <div class="text-sm font-bold text-zinc-800 dark:text-zinc-100">
                            {{ $request->student?->name }}
                        </div>
                        <div class="text-xs text-zinc-500 mt-1">
                            {{ __('إلى') }} <span class="font-bold">{{ $request->circle?->name }}</span>
                            @if($request->circle?->stage)
                                — {{ $request->circle->stage->name }}
                            @endif
                        </div>
                        <div class="text-xs text-zinc-400 mt-1">
                            {{ __('طلبه') }} {{ $request->requestedBy?->name ?? __('غير معروف') }}
                            · <x-hijri-date :date="$request->created_at" />
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:button size="sm" variant="primary" class="!bg-emerald-600 hover:!bg-emerald-700"
                            wire:click="approve({{ $request->id }})">
                            {{ __('موافقة') }}
                        </flux:button>
                        <flux:button size="sm" variant="ghost" class="text-rose-600"
                            wire:click="reject({{ $request->id }})">
                            {{ __('رفض') }}
                        </flux:button>
                    </div>
                </div>

                <flux:input size="sm" wire:model="notes.{{ $request->id }}"
                    placeholder="{{ __('سبب الرفض (اختياري) — يصل المعلّم') }}" />
            </flux:card>
        @empty
            <flux:card>
                <flux:text class="text-zinc-400">{{ __('لا توجد طلبات معلّقة.') }}</flux:text>
            </flux:card>
        @endforelse
    </div>

    <div class="space-y-3">
        <flux:heading size="lg">{{ __('بانتظار القبول في برنامج') }}</flux:heading>
        <flux:subheading class="text-zinc-500 dark:text-zinc-400">
            {{ __('طلاب سجّلوا ولم يُقبلوا في برنامج بعد، فلا يظهرون لأي معلّم.') }}
        </flux:subheading>

        <flux:card class="space-y-4">
            <div class="flex items-end gap-3 flex-wrap">
                <flux:field class="flex-1 min-w-48">
                    <flux:label>{{ __('البرنامج الذي يُقبلون فيه') }}</flux:label>
                    <flux:select wire:model="admitStage">
                        @foreach($stages as $stage)
                            <flux:select.option value="{{ $stage->id }}">{{ $stage->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
                <flux:field class="flex-1 min-w-48">
                    <flux:label>{{ __('بحث') }}</flux:label>
                    <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('اسم الطالب') }}" />
                </flux:field>
            </div>

            <div class="divide-y divide-zinc-50 dark:divide-zinc-800/60">
                @forelse($awaiting as $student)
                    <div class="flex items-center justify-between py-3" wire:key="awaiting-{{ $student->id }}">
                        <div>
                            <div class="text-sm font-bold text-zinc-800 dark:text-zinc-100">{{ $student->name }}</div>
                            <div class="text-xs text-zinc-400">{{ $student->phone ?: $student->email }}</div>
                        </div>
                        <flux:button size="sm" variant="primary" class="!bg-maroon hover:!bg-burgundy"
                            wire:click="admit({{ $student->id }})">
                            {{ __('قبول') }}
                        </flux:button>
                    </div>
                @empty
                    <flux:text class="text-zinc-400 py-3 block">{{ __('لا أحد بانتظار القبول.') }}</flux:text>
                @endforelse
            </div>
        </flux:card>
    </div>
</div>
