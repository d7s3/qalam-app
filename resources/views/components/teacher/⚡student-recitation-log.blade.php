<?php

use App\Models\Circle;
use App\Models\Student;
use App\Models\StudentHadithAchievement;
use App\Models\StudentOdeAchievement;
use App\Models\StudentPlanDay;
use App\Services\GamificationService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public $studentId;

    public $studentName;

    /** Key of the entry currently being edited, e.g. "quran:123:hifz". */
    public ?string $editKey = null;

    public ?string $editDate = null;

    public function mount($studentId)
    {
        $this->studentId = $studentId;
        $student = Student::findOrFail($studentId);
        $this->studentName = $student->name;
    }

    public function startEdit(string $key, ?string $currentDate): void
    {
        $this->editKey = $key;
        $this->editDate = $currentDate ?: now('Asia/Riyadh')->format('Y-m-d');
    }

    public function cancelEdit(): void
    {
        $this->reset('editKey', 'editDate');
    }

    /**
     * Move a single grading (hifz/review of a quran/ode/hadith record) to a new
     * date, keeping its original time of day, then re-sync the student's points,
     * competitions and streak — which are all credited by the grading date.
     */
    public function saveGradingDate(): void
    {
        $this->validate(
            ['editDate' => ['required', 'date']],
            ['editDate.required' => __('يرجى اختيار تاريخ.'), 'editDate.date' => __('تاريخ غير صالح.')],
        );

        [$kind, $id, $part] = array_pad(explode(':', (string) $this->editKey), 3, null);
        $field = $part === 'review' ? 'review_graded_at' : 'hifz_graded_at';

        $record = match ($kind) {
            'quran' => StudentPlanDay::with('plan.student')->find($id),
            'ode' => StudentOdeAchievement::with('plan.student', 'pathDay')->find($id),
            'hadith' => StudentHadithAchievement::with('plan.student', 'pathDay')->find($id),
            default => null,
        };

        if (! $record || $record->{$field} === null) {
            $this->cancelEdit();

            return;
        }

        $student = $record->plan?->student;

        if (! $student || ! $this->teacherOwns($student)) {
            abort(403);
        }

        $original = $record->{$field};
        $record->{$field} = Carbon::parse($this->editDate)->setTimeFromTimeString($original->format('H:i:s'));
        $record->save();

        match ($kind) {
            'quran' => GamificationService::syncStudentPlanDayXP($record->fresh('plan.student')),
            'ode' => GamificationService::syncStudentOdeAchievementXP($record->fresh(['plan.student', 'pathDay'])),
            'hadith' => GamificationService::syncStudentHadithAchievementXP($record->fresh(['plan.student', 'pathDay'])),
        };

        $this->cancelEdit();

        Flux::toast(__('تم تحديث تاريخ التقييم وإعادة احتساب النقاط والاستريك.'), variant: 'success');
    }

    protected function teacherOwns(Student $student): bool
    {
        $teacher = Auth::guard('teacher')->user();

        return $teacher
            && $student->circle_id
            && Circle::where('id', $student->circle_id)
                ->whereHas('teachers', fn ($q) => $q->whereKey($teacher->id))
                ->exists();
    }

    /**
     * @return array{key: string, type_label: string, type_color: string, part: string, part_label: string, achievement: int, graded_at: ?CarbonInterface, range: ?string, scheduled: ?CarbonInterface}
     */
    protected function pushParts($entries, string $kind, string $typeLabel, string $typeColor, $record, ?CarbonInterface $scheduled, callable $formatRange): void
    {
        $partLabels = ['hifz' => __('الحفظ'), 'review' => __('المراجعة')];

        foreach (['hifz', 'review'] as $part) {
            if ($record->{$part.'_achievement'} === null) {
                continue;
            }

            $entries->push([
                'key' => "{$kind}:{$record->id}:{$part}",
                'type_label' => $typeLabel,
                'type_color' => $typeColor,
                'part' => $part,
                'part_label' => $partLabels[$part],
                'achievement' => (int) $record->{$part.'_achievement'},
                'graded_at' => $record->{$part.'_graded_at'},
                'range' => $formatRange($record, $part),
                'scheduled' => $scheduled,
            ]);
        }
    }

    public function with()
    {
        $entries = collect();

        StudentPlanDay::with('plan', 'fromAyah.surah', 'toAyah.surah', 'reviewFromAyah.surah', 'reviewToAyah.surah')
            ->whereHas('plan', fn ($q) => $q->where('student_id', $this->studentId))
            ->where(fn ($q) => $q->whereNotNull('hifz_achievement')->orWhereNotNull('review_achievement'))
            ->get()
            ->each(fn ($day) => $this->pushParts($entries, 'quran', __('قرآن'), 'indigo', $day, $day->date, fn ($r, $p) => $r->formatRange($p, false)));

        StudentOdeAchievement::with('plan', 'pathDay')
            ->whereHas('plan', fn ($q) => $q->where('student_id', $this->studentId))
            ->where(fn ($q) => $q->whereNotNull('hifz_achievement')->orWhereNotNull('review_achievement'))
            ->get()
            ->each(fn ($ach) => $this->pushParts($entries, 'ode', __('منظومة'), 'purple', $ach, null, fn ($r, $p) => $r->formatOdeRange($p)));

        StudentHadithAchievement::with('plan', 'pathDay')
            ->whereHas('plan', fn ($q) => $q->where('student_id', $this->studentId))
            ->where(fn ($q) => $q->whereNotNull('hifz_achievement')->orWhereNotNull('review_achievement'))
            ->get()
            ->each(fn ($ach) => $this->pushParts($entries, 'hadith', __('متن'), 'teal', $ach, null, fn ($r, $p) => $r->formatHadithRange($p)));

        $grouped = $entries
            ->sortByDesc(fn ($e) => optional($e['graded_at'] ?? $e['scheduled'])->getTimestamp() ?? 0)
            ->groupBy(fn ($e) => optional($e['graded_at'] ?? $e['scheduled'])->format('Y-m-d') ?? __('غير مؤرّخ'));

        return ['grouped' => $grouped];
    }
};
?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl" level="1">{{ __('سجل التسميع الفعلي') }}</flux:heading>
            <flux:subheading>{{ __('كل ما تم تسميعه للطالب') }} <span class="font-bold">{{ $studentName }}</span> {{ __('مع تاريخ التقييم — يمكنك تعديل التاريخ ليُحتسب في اليوم الصحيح.') }}</flux:subheading>
        </div>
        <flux:button href="{{ route('teacher.students') }}" icon="arrow-right" variant="ghost">{{ __('العودة للطلاب') }}</flux:button>
    </div>

    @php
        $gradeLabels = [
            3 => ['label' => __('ممتاز'), 'color' => 'green'],
            2 => ['label' => __('جيد'), 'color' => 'blue'],
            1 => ['label' => __('مقبول'), 'color' => 'amber'],
            0 => ['label' => __('لم يُسمع'), 'color' => 'red'],
        ];
    @endphp

    <div class="space-y-6">
        @forelse ($grouped as $dateStr => $dayEntries)
            @php
                $carbonDate = \Carbon\Carbon::parse($dateStr);
            @endphp
            <flux:card class="p-0 overflow-hidden shadow-sm">
                <div class="bg-zinc-50/80 dark:bg-zinc-900/50 px-5 py-4 border-b border-zinc-100 dark:border-zinc-800 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 rounded-xl">
                            <flux:icon.calendar size="sm" />
                        </div>
                        <div>
                            <span class="font-bold text-zinc-800 dark:text-zinc-100">{{ $carbonDate->format('Y-m-d') }}</span>
                            <span class="text-sm text-zinc-500 dark:text-zinc-400 mr-2">{{ $carbonDate->translatedFormat('l') }}</span>
                        </div>
                    </div>
                    <flux:badge size="sm" variant="subtle" color="indigo">{{ $dayEntries->count() }} {{ __('تقييمات') }}</flux:badge>
                </div>

                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($dayEntries as $entry)
                        @php
                            $g = $gradeLabels[$entry['achievement']] ?? ['label' => __('غير معروف'), 'color' => 'zinc'];
                        @endphp
                        <div class="p-5 flex flex-col gap-3" wire:key="entry-{{ $entry['key'] }}">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:badge color="{{ $entry['type_color'] }}" size="sm">{{ $entry['type_label'] }}</flux:badge>
                                <span class="text-xs font-medium text-zinc-400 dark:text-zinc-500">{{ $entry['part_label'] }}</span>
                                <flux:badge color="{{ $g['color'] }}" size="sm" variant="subtle">{{ $g['label'] }}</flux:badge>
                                @if($entry['graded_at'])
                                    <span class="text-[10px] text-zinc-400" dir="ltr">{{ $entry['graded_at']->format('h:i A') }}</span>
                                @endif
                            </div>

                            <span class="font-semibold text-sm text-zinc-800 dark:text-zinc-200">
                                {{ $entry['range'] ?? __('لا يوجد مقرر') }}
                            </span>

                            @if($editKey === $entry['key'])
                                <div class="flex flex-col gap-2 pt-1">
                                    <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('اختر تاريخ التسميع (هجري) — أيام الدوام مميّزة') }}</span>
                                    <div class="max-w-xs">
                                        <livewire:shared.hijri-datepicker wire:model.live="editDate" :show-attendance-days="true" label="" :key="'editdate-'.$entry['key']" />
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <flux:button wire:click="saveGradingDate" size="sm" variant="primary" icon="check">{{ __('حفظ') }}</flux:button>
                                        <flux:button wire:click="cancelEdit" size="sm" variant="ghost">{{ __('إلغاء') }}</flux:button>
                                    </div>
                                    <flux:error name="editDate" />
                                </div>
                            @else
                                <flux:button
                                    wire:click="startEdit('{{ $entry['key'] }}', '{{ optional($entry['graded_at'])->format('Y-m-d') }}')"
                                    size="xs" variant="ghost" icon="pencil-square" class="self-start">
                                    {{ __('تعديل تاريخ التقييم') }}
                                </flux:button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </flux:card>
        @empty
            <flux:card class="py-12 text-center text-zinc-500">
                <div class="flex flex-col items-center justify-center gap-3">
                    <div class="p-3 bg-zinc-50 dark:bg-zinc-950 text-zinc-400 rounded-full">
                        <flux:icon.calendar size="lg" />
                    </div>
                    <span>{{ __('لم يقم الطالب بأي تسميع حتى الآن.') }}</span>
                </div>
            </flux:card>
        @endforelse
    </div>
</div>
