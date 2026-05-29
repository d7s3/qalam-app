<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Student;
use App\Models\StudentPlanDay;

new class extends Component {
    use WithPagination;

    public $studentId;
    public $studentName;

    public function mount($studentId)
    {
        $this->studentId = $studentId;
        $student = Student::findOrFail($studentId);
        $this->studentName = $student->name;
    }

    public function with()
    {
        $logs = StudentPlanDay::with([
            'plan',
            'fromAyah.surah',
            'toAyah.surah',
            'reviewFromAyah.surah',
            'reviewToAyah.surah'
        ])
            ->whereHas('plan', function($q) {
                $q->where('student_id', $this->studentId);
            })
            ->where(function($q) {
                $q->whereNotNull('hifz_achievement')
                  ->orWhereNotNull('review_achievement');
            })
            ->orderByRaw('
                CASE 
                    WHEN hifz_graded_at IS NOT NULL AND review_graded_at IS NOT NULL THEN
                        CASE WHEN hifz_graded_at > review_graded_at THEN hifz_graded_at ELSE review_graded_at END
                    ELSE COALESCE(hifz_graded_at, review_graded_at, date)
                END DESC
            ')
            ->paginate(20);

        return [
            'logs' => $logs
        ];
    }
};
?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl" level="1">{{ __('سجل التسميع الفعلي') }}</flux:heading>
            <flux:subheading>{{ __('يعرض هذا السجل ما تم تسميعه فعلياً للطالب ') }} <span class="font-bold">{{ $studentName }}</span> {{ __('مع تواريخ التقييم الدقيقة.') }}</flux:subheading>
        </div>
        <flux:button href="{{ route('teacher.students') }}" icon="arrow-right" variant="ghost">{{ __('العودة للطلاب') }}</flux:button>
    </div>

    @php
        $groupedLogs = $logs->groupBy(function($log) {
            $recitationDate = $log->hifz_graded_at;
            if ($log->review_graded_at) {
                if (!$recitationDate || $log->review_graded_at->gt($recitationDate)) {
                    $recitationDate = $log->review_graded_at;
                }
            }
            return ($recitationDate ?? $log->date)->format('Y-m-d');
        });
    @endphp

    <div class="space-y-6">
        @forelse ($groupedLogs as $dateStr => $dayLogs)
            @php
                $carbonDate = \Carbon\Carbon::parse($dateStr);
            @endphp
            <flux:card class="p-0 overflow-hidden shadow-sm">
                <!-- Date Header -->
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
                    <flux:badge size="sm" variant="subtle" color="indigo">{{ $dayLogs->count() }} {{ __('تسميعات') }}</flux:badge>
                </div>

                <!-- Recitations list -->
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($dayLogs as $log)
                        <div class="p-5 flex flex-col gap-4">
                            <!-- Plan & metadata -->
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <span class="font-semibold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-500/10 px-2.5 py-0.5 rounded-md">
                                    {{ $log->plan->plan_type === 'hifz_review' ? __('حفظ ومراجعة') : ($log->plan->plan_type === 'hifz' ? __('حفظ') : __('مراجعة')) }}
                                </span>
                                <span class="text-zinc-400 dark:text-zinc-500">
                                    {{ __('خطة:') }} {{ $log->date->format('Y-m-d') }} ({{ $log->day_name }})
                                </span>
                            </div>

                            <!-- Graded parts -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Hifz Part -->
                                @if($log->hifz_achievement !== null)
                                    @php
                                        $hifzLabels = [
                                            3 => ['label' => 'ممتاز', 'color' => 'green'],
                                            2 => ['label' => 'جيد', 'color' => 'blue'],
                                            1 => ['label' => 'مقبول', 'color' => 'amber'],
                                            0 => ['label' => 'لم يُسمع', 'color' => 'red'],
                                        ];
                                        $hStatus = $hifzLabels[$log->hifz_achievement] ?? ['label' => 'غير معروف', 'color' => 'zinc'];
                                    @endphp
                                    <div class="bg-zinc-50/50 dark:bg-zinc-950/20 p-4 rounded-xl border border-zinc-100/80 dark:border-zinc-800/80 flex flex-col gap-2">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-medium text-zinc-400 dark:text-zinc-500">{{ __('الحفظ') }}</span>
                                            <flux:badge color="{{ $hStatus['color'] }}" size="sm" variant="subtle">{{ $hStatus['label'] }}</flux:badge>
                                        </div>
                                        <span class="font-semibold text-sm text-zinc-800 dark:text-zinc-200">
                                            {{ $log->formatRange('hifz', false) ?? __('لا يوجد مقرر حفظ') }}
                                        </span>
                                        @if($log->hifz_graded_at)
                                            <span class="text-[10px] text-zinc-400 self-end" dir="ltr">
                                                {{ $log->hifz_graded_at->format('h:i A') }}
                                            </span>
                                        @endif
                                    </div>
                                @else
                                    <div class="border border-dashed border-zinc-100 dark:border-zinc-800/50 rounded-xl p-4 flex items-center justify-center text-zinc-300 dark:text-zinc-700 text-xs">
                                        {{ __('لا يوجد تسميع حفظ لهذا اليوم') }}
                                    </div>
                                @endif

                                <!-- Review Part -->
                                @if($log->review_achievement !== null)
                                    @php
                                        $reviewLabels = [
                                            3 => ['label' => 'ممتاز', 'color' => 'green'],
                                            2 => ['label' => 'جيد', 'color' => 'blue'],
                                            1 => ['label' => 'مقبول', 'color' => 'amber'],
                                            0 => ['label' => 'لم يُسمع', 'color' => 'red'],
                                        ];
                                        $rStatus = $reviewLabels[$log->review_achievement] ?? ['label' => 'غير معروف', 'color' => 'zinc'];
                                    @endphp
                                    <div class="bg-zinc-50/50 dark:bg-zinc-950/20 p-4 rounded-xl border border-zinc-100/80 dark:border-zinc-800/80 flex flex-col gap-2">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-medium text-zinc-400 dark:text-zinc-500">{{ __('المراجعة') }}</span>
                                            <flux:badge color="{{ $rStatus['color'] }}" size="sm" variant="subtle">{{ $rStatus['label'] }}</flux:badge>
                                        </div>
                                        <span class="font-semibold text-sm text-zinc-800 dark:text-zinc-200">
                                            {{ $log->formatRange('review', false) ?? __('لا يوجد مقرر مراجعة') }}
                                        </span>
                                        @if($log->review_graded_at)
                                            <span class="text-[10px] text-zinc-400 self-end" dir="ltr">
                                                {{ $log->review_graded_at->format('h:i A') }}
                                            </span>
                                        @endif
                                    </div>
                                @else
                                    <div class="border border-dashed border-zinc-100 dark:border-zinc-800/50 rounded-xl p-4 flex items-center justify-center text-zinc-300 dark:text-zinc-700 text-xs">
                                        {{ __('لا يوجد تسميع مراجعة لهذا اليوم') }}
                                    </div>
                                @endif
                            </div>
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

        @if ($logs->hasPages())
            <div class="p-4 bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-100 dark:border-zinc-800 mt-6 shadow-sm">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>