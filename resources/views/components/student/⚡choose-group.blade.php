<?php

use App\Models\Circle;
use App\Models\Student;
use App\Models\StudentPlacementRequest;
use App\Services\StudentPlacementService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Where a student without a group chooses his sheikh.
 *
 * Nobody could see a student who signed himself up — no programme, no cohort,
 * and every screen bounded by one or the other — so he waited for the manager
 * to remember him. He now names his sheikh's group himself, and the sheikh
 * accepts him with one press. Shown only while he has no cohort.
 */
new class extends Component
{
    public ?int $picked = null;

    public bool $changing = false;

    private function student(): ?Student
    {
        return Auth::guard('student')->user();
    }

    /** The ask he is waiting on, if any. */
    #[Computed]
    public function waiting(): ?StudentPlacementRequest
    {
        return StudentPlacementRequest::pending()
            ->with('circle')
            ->where('student_id', $this->student()?->id ?? 0)
            ->latest('id')
            ->first();
    }

    /** The last ask a sheikh turned down, so he knows to choose again. */
    #[Computed]
    public function declined(): ?StudentPlacementRequest
    {
        return StudentPlacementRequest::query()
            ->with('circle')
            ->where('student_id', $this->student()?->id ?? 0)
            ->where('status', StudentPlacementRequest::REJECTED)
            ->whereNotNull('decided_by')
            ->latest('id')
            ->first();
    }

    public function send(): void
    {
        $student = $this->student() ?? abort(403);

        $this->validate(['picked' => ['required', 'integer']], ['picked.required' => __('اختر مجموعة أولاً')]);

        StudentPlacementService::askToJoin($student, Circle::findOrFail($this->picked));

        $this->changing = false;
        unset($this->waiting);
    }

    public function change(): void
    {
        $this->changing = true;
        $this->picked = $this->waiting?->circle_id;
    }

    /** The group's name as a student knows it: his sheikh's, else the cohort's. */
    public function groupName(?Circle $circle, array $sheikhs = []): string
    {
        if ($sheikhs !== []) {
            return __('الشيخ').' '.implode(__(' و '), $sheikhs);
        }

        return $circle ? trim($circle->name) : '';
    }

    /** The letter on a group's tile: its sheikh's family name, without «ال». */
    public function initialOf(array $group): string
    {
        $name = $group['sheikhs'] !== [] ? $group['sheikhs'][0] : $group['circle']->name;
        $word = (string) collect(preg_split('/\s+/u', trim($name)))->last();

        if (mb_strlen($word) > 2 && str_starts_with($word, 'ال')) {
            $word = mb_substr($word, 2);
        }

        return mb_substr($word, 0, 1);
    }

    public function studentsLabel(int $count): string
    {
        return match (true) {
            $count === 0 => __('لا طلاب بعد'),
            $count === 1 => __('طالب واحد'),
            $count === 2 => __('طالبان'),
            $count <= 10 => $count.' '.__('طلاب'),
            default => $count.' '.__('طالباً'),
        };
    }

    public function with(): array
    {
        $student = $this->student();
        $groups = StudentPlacementService::groupsToChoose();

        return [
            'placed' => ! $student || $student->circle_id !== null,
            'groups' => $groups,
            'sheikhsOfWaiting' => $this->waiting
                ? ($groups->firstWhere('circle.id', $this->waiting->circle_id)['sheikhs'] ?? [])
                : [],
        ];
    }
};
?>

<div dir="rtl">
    @unless($placed)
        @if($this->waiting && ! $changing)
            <section class="mb-6 rounded-2xl border border-[#EDE5D5] bg-white p-5 sm:p-6 shadow-[0_1px_2px_rgba(63,26,25,.04)]">
                <div class="flex flex-col items-center text-center">
                    <span class="flex size-16 items-center justify-center rounded-full bg-maroon/10 text-maroon">
                        <flux:icon icon="check" class="size-8" />
                    </span>
                    <h2 class="mt-4 text-xl font-bold text-zinc-900">{{ __('وصل طلبك') }}</h2>
                    <p class="mt-1 text-zinc-600">{{ __('بانتظار قبول') }} {{ $this->groupName($this->waiting->circle, $sheikhsOfWaiting) }}</p>
                </div>

                <ol class="mx-auto mt-6 flex max-w-sm flex-col gap-4 rounded-2xl border border-[#EDE5D5] p-4">
                    <li class="flex items-center gap-3">
                        <span class="flex size-7 items-center justify-center rounded-full bg-emerald-50 text-emerald-700"><flux:icon icon="check" variant="mini" class="size-4" /></span>
                        <span>{{ __('سجّلت حسابك') }}</span>
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="flex size-7 items-center justify-center rounded-full bg-emerald-50 text-emerald-700"><flux:icon icon="check" variant="mini" class="size-4" /></span>
                        <span>{{ __('اخترت مجموعتك') }}</span>
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="flex size-7 items-center justify-center rounded-full bg-maroon"><span class="size-2 rounded-full bg-white"></span></span>
                        <span class="font-semibold">{{ __('قبول الشيخ') }}</span>
                        <span class="ms-auto text-sm text-zinc-500">{{ __('الآن') }}</span>
                    </li>
                </ol>

                <p class="mt-4 text-center text-sm text-zinc-500">{{ __('تبدأ خطتك وحضورك فور قبولك.') }}</p>

                <div class="mt-5 flex justify-center">
                    <flux:button wire:click="change">{{ __('غيّر المجموعة') }}</flux:button>
                </div>
            </section>
        @else
            <section class="mb-6 rounded-2xl border border-[#EDE5D5] bg-white p-5 sm:p-6 shadow-[0_1px_2px_rgba(63,26,25,.04)]">
                <h2 class="text-2xl font-bold text-zinc-900">{{ __('اختر مجموعتك') }}</h2>
                <p class="mt-1 text-zinc-600">{{ __('اختر الشيخ الذي ستدرس عنده. يصلك إشعار حين يقبلك.') }}</p>

                @if($this->declined && ! $this->waiting)
                    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        {{ __('لم يُقبل طلبك في') }} {{ trim($this->declined->circle?->name ?? '') }}@if($this->declined->note) — {{ $this->declined->note }}@endif. {{ __('اختر مجموعة أخرى.') }}
                    </div>
                @endif

                <fieldset class="mt-5">
                    <legend class="sr-only">{{ __('المجموعات') }}</legend>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @forelse($groups as $group)
                            <label wire:key="group-{{ $group['circle']->id }}"
                                class="group flex min-h-[72px] cursor-pointer items-center gap-3 rounded-2xl border border-[#EDE5D5] bg-white px-4 py-3 transition has-checked:border-2 has-checked:border-maroon has-checked:ring-4 has-checked:ring-maroon/10">
                                <input type="radio" name="group" value="{{ $group['circle']->id }}" wire:model="picked" class="sr-only">
                                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-maroon/10 text-lg font-bold text-maroon group-has-checked:bg-maroon group-has-checked:text-white">
                                    {{ $this->initialOf($group) }}
                                </span>
                                <span class="flex flex-1 flex-col">
                                    <span class="font-semibold text-zinc-900">{{ $this->groupName($group['circle'], $group['sheikhs']) }}</span>
                                    <span class="text-sm text-zinc-500">{{ $group['circle']->stage?->name }} · {{ $this->studentsLabel($group['students']) }}</span>
                                </span>
                                <span class="flex size-[22px] shrink-0 items-center justify-center rounded-full border-2 border-zinc-300 group-has-checked:border-maroon">
                                    <span class="size-2.5 rounded-full group-has-checked:bg-maroon"></span>
                                </span>
                            </label>
                        @empty
                            <p class="text-zinc-500">{{ __('لا مجموعات مفتوحة الآن.') }}</p>
                        @endforelse
                    </div>
                </fieldset>

                <flux:error name="picked" class="mt-3" />

                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <flux:button variant="primary" wire:click="send" class="!bg-maroon hover:!bg-burgundy min-h-11 w-full px-6 sm:w-auto">{{ __('أرسل طلبي') }}</flux:button>
                    @if($changing)
                        <flux:button variant="ghost" wire:click="$set('changing', false)">{{ __('تراجع') }}</flux:button>
                    @endif
                </div>
            </section>
        @endif
    @endunless
</div>
