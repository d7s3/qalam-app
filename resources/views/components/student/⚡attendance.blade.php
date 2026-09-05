<?php

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use App\Models\Setting;

new class extends Component {
    use WithPagination;

    public function with()
    {
        $student = Auth::guard('student')->user();

        $attendances = $student->attendances()
            ->orderBy('date', 'desc')
            ->paginate(15);
            
        $absenceLimit = (int) Setting::getVal('absence_limit', 3);
        $latenessLimit = (int) Setting::getVal('lateness_limit', 5);

        $absencesCount = $student->getAbsencesInLast30DaysCount();
        $latenessCount = $student->getLatenessInPeriodCount();

        return [
            'attendances' => $attendances,
            'absencesCount' => $absencesCount,
            'latenessCount' => $latenessCount,
            'absenceLimit' => $absenceLimit,
            'latenessLimit' => $latenessLimit,
            'absenceDanger' => $absencesCount >= $absenceLimit,
            'latenessDanger' => $latenessCount >= $latenessLimit,
            'absenceWarning' => $absencesCount == ($absenceLimit - 1),
            'latenessWarning' => $latenessCount == ($latenessLimit - 1),
        ];
    }
};
?>

<div class="space-y-6" dir="rtl">
    <div>
        <flux:heading size="xl" class="font-bold text-zinc-900 dark:text-white">
            {{ __('سجل الانضباط') }}
        </flux:heading>
        <flux:subheading class="text-zinc-500 dark:text-zinc-400 mt-1">
            {{ __('متابعة حضورك وغيابك في الدفعة ومدى انضباطك.') }}
        </flux:subheading>
    </div>

    <!-- Alert Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <!-- Absence Card -->
        <flux:card class="border-t-4 {{ $absenceDanger ? 'border-t-red-500 bg-red-50 dark:bg-red-950/20' : ($absenceWarning ? 'border-t-amber-500 bg-amber-50 dark:bg-amber-950/20' : 'border-t-emerald-500') }} overflow-hidden relative">
            <div class="relative z-10 flex flex-col gap-2">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold {{ $absenceDanger ? 'text-red-700 dark:text-red-400' : ($absenceWarning ? 'text-amber-700 dark:text-amber-400' : 'text-emerald-700 dark:text-emerald-400') }}">{{ __('الغياب (خلال آخر 30 يوماً)') }}</h3>
                    <flux:badge color="{{ $absenceDanger ? 'red' : ($absenceWarning ? 'amber' : 'emerald') }}">{{ $absencesCount }} / {{ $absenceLimit }}</flux:badge>
                </div>
                
                @if($absenceDanger)
                    <p class="text-sm text-red-600 dark:text-red-400 font-medium">{{ __('لقد تجاوزت الحد المسموح للغياب، نرجو منك الالتزام بالحضور لتفادي الإجراءات الإدارية.') }}</p>
                @elseif($absenceWarning)
                    <p class="text-sm text-amber-600 dark:text-amber-400 font-medium">{{ __('تنبيه: أنت على بُعد غياب واحد من تجاوز الحد المسموح.') }}</p>
                @else
                    <p class="text-sm text-emerald-600 dark:text-emerald-400 font-medium">{{ __('شُكراً لالتزامك ومواظبتك على الحضور.') }}</p>
                @endif
            </div>
        </flux:card>

        <!-- Lateness Card -->
        <flux:card class="border-t-4 {{ $latenessDanger ? 'border-t-red-500 bg-red-50 dark:bg-red-950/20' : ($latenessWarning ? 'border-t-amber-500 bg-amber-50 dark:bg-amber-950/20' : 'border-t-emerald-500') }} overflow-hidden relative">
            <div class="relative z-10 flex flex-col gap-2">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold {{ $latenessDanger ? 'text-red-700 dark:text-red-400' : ($latenessWarning ? 'text-amber-700 dark:text-amber-400' : 'text-emerald-700 dark:text-emerald-400') }}">{{ __('التأخر (الفترة المحددة)') }}</h3>
                    <flux:badge color="{{ $latenessDanger ? 'red' : ($latenessWarning ? 'amber' : 'emerald') }}">{{ $latenessCount }} / {{ $latenessLimit }}</flux:badge>
                </div>
                
                @if($latenessDanger)
                    <p class="text-sm text-red-600 dark:text-red-400 font-medium">{{ __('لقد تجاوزت الحد المسموح في تكرار التأخير، نرجو منك الحرص على الحضور مبكراً.') }}</p>
                @elseif($latenessWarning)
                    <p class="text-sm text-amber-600 dark:text-amber-400 font-medium">{{ __('تنبيه: تأخير إضافي سيعرضك لتجاوز الحد المسموح.') }}</p>
                @else
                    <p class="text-sm text-emerald-600 dark:text-emerald-400 font-medium">{{ __('شُكراً لالتزامك الدائم بالوقت المحدد.') }}</p>
                @endif
            </div>
        </flux:card>
    </div>

    <flux:card class="p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('التاريخ') }}</flux:table.column>
                    <flux:table.column>{{ __('حالة الحضور') }}</flux:table.column>
                    <flux:table.column>{{ __('عذر') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse($attendances as $attendance)
                        <flux:table.row>
                            <flux:table.cell class="font-medium">
                                <x-hijri-date :date="\Carbon\Carbon::parse($attendance->date)" style="weekday" />
                            </flux:table.cell>
                            
                            <flux:table.cell class="first:ps-3" >
                                @php
                                    $statusDetails = match($attendance->status) {
                                        'present' => ['color' => 'green', 'label' => 'حاضر', 'icon' => 'check-circle'],
                                        'absent' => ['color' => 'red', 'label' => 'غائب', 'icon' => 'x-circle'],
                                        'late' => ['color' => 'amber', 'label' => 'متأخر', 'icon' => 'clock'],
                                        'excused' => ['color' => 'blue', 'label' => 'مستأذن', 'icon' => 'information-circle'],
                                        default => ['color' => 'zinc', 'label' => 'غير مسجل', 'icon' => 'question-mark-circle'],
                                    };
                                @endphp
                                <div class="flex items-center gap-1 text-{{ $statusDetails['color'] }}-600 font-semibold">
                                    <flux:icon :icon="$statusDetails['icon']" class="w-4 h-4" />
                                    <span>{{ __($statusDetails['label']) }}</span>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell class="first:ps-3" >
                                @if($attendance->notes)
                                    <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $attendance->notes }}</span>
                                @else
                                    <span class="text-zinc-300">-</span>
                                @endif
                            </flux:table.cell>

                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3" class="text-center text-zinc-500 py-6">
                                {{ __('لا يوجد سجل حضور بعد.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
        
        @if($attendances->hasPages())
            <div class="p-4 border-t border-zinc-100 dark:border-zinc-800">
                {{ $attendances->links() }}
            </div>
        @endif
    </flux:card>
</div>
