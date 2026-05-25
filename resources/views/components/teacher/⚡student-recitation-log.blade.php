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

    <flux:card class="p-0 overflow-hidden">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('تاريخ التسميع') }}</flux:table.column>
                <flux:table.column>{{ __('الحفظ') }}</flux:table.column>
                <flux:table.column>{{ __('المراجعة') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($logs as $log)
                    @php
                        $recitationDate = $log->hifz_graded_at;
                        if ($log->review_graded_at) {
                            if (!$recitationDate || $log->review_graded_at->gt($recitationDate)) {
                                $recitationDate = $log->review_graded_at;
                            }
                        }
                        $recitationDate = $recitationDate ?? $log->date;
                    @endphp
                    <flux:table.row>
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <span class="font-medium">{{ $recitationDate->format('Y-m-d') }}</span>
                                <span class="text-xs text-zinc-500 mt-1">
                                    {{ $recitationDate->translatedFormat('l') }}
                                </span>
                                <span class="text-[10px] text-zinc-400 mt-1">
                                    {{ __('تاريخ الخطة:') }} {{ $log->date->format('Y-m-d') }} ({{ $log->day_name }})
                                </span>
                                <span class="text-[10px] text-indigo-500 bg-indigo-50 dark:bg-indigo-500/10 px-1.5 py-0.5 rounded w-fit mt-1">
                                    {{ $log->plan->plan_type === 'hifz_review' ? __('حفظ ومراجعة') : ($log->plan->plan_type === 'hifz' ? __('حفظ') : __('مراجعة')) }} ({{ $log->plan->start_date->format('Y-m-d') }})
                                </span>
                            </div>
                        </flux:table.cell>
                        
                        <flux:table.cell>
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
                                <div class="flex flex-col items-start gap-1">
                                    <span class="font-medium text-sm">{{ $log->formatRange('hifz', false) ?? __('لا يوجد مقرر حفظ') }}</span>
                                    <div class="flex items-center gap-2">
                                        <flux:badge color="{{ $hStatus['color'] }}" size="sm">{{ $hStatus['label'] }}</flux:badge>
                                        <span class="text-xs text-zinc-500" dir="ltr">
                                            {{ $log->hifz_graded_at ? $log->hifz_graded_at->format('Y-m-d h:i A') : $log->date->format('Y-m-d') }}
                                        </span>
                                    </div>
                                </div>
                            @else
                                <span class="text-zinc-400 text-sm">-</span>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
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
                                <div class="flex flex-col items-start gap-1">
                                    <span class="font-medium text-sm">{{ $log->formatRange('review', false) ?? __('لا يوجد مقرر مراجعة') }}</span>
                                    <div class="flex items-center gap-2">
                                        <flux:badge color="{{ $rStatus['color'] }}" size="sm">{{ $rStatus['label'] }}</flux:badge>
                                        <span class="text-xs text-zinc-500" dir="ltr">
                                            {{ $log->review_graded_at ? $log->review_graded_at->format('Y-m-d h:i A') : $log->date->format('Y-m-d') }}
                                        </span>
                                    </div>
                                </div>
                            @else
                                <span class="text-zinc-400 text-sm">-</span>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3" class="text-center text-zinc-500 py-8">
                            {{ __('لم يقم الطالب بأي تسميع حتى الآن.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        <div class="p-4 border-t border-zinc-100 dark:border-zinc-800">
            {{ $logs->links() }}
        </div>
    </flux:card>
</div>