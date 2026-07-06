<?php

use App\Jobs\SendGuardianWhatsappJob;
use App\Models\Attendance;
use App\Models\Circle;
use App\Models\GuardianNotification;
use App\Services\GuardianNotificationService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Component;

new class extends Component {
    public $year;

    public $currentMonthIndex = 0;

    public $broadcastDate;

    public $selectedDateHijri = '';

    public $showReportModal = false;

    public $eligibleCount = 0;
    public $absentCount = 0;
    public $lateCount = 0;
    public $alreadyNotifiedCount = 0;
    public $noGuardianStudents = [];
    public $noPhoneStudents = [];
    public $alreadyBroadcast = false;

    public function mount()
    {
        $cal = \IntlCalendar::createInstance('Asia/Riyadh', 'ar_SA@calendar=islamic-umalqura');
        $cal->setTime(now('Asia/Riyadh')->getTimestampMs());
        $this->year = $cal->get(\IntlCalendar::FIELD_YEAR);
        $this->currentMonthIndex = $cal->get(\IntlCalendar::FIELD_MONTH);
        $this->broadcastDate = now('Asia/Riyadh')->format('Y-m-d');
    }

    protected function supervisorCircleIds()
    {
        $supervisor = Auth::guard('supervisor')->user();

        return Circle::whereIn('stage_id', $supervisor->stages()->pluck('stages.id'))->pluck('id');
    }

    /**
     * @return \Illuminate\Support\Collection<int, Attendance>
     */
    protected function absencesForDate()
    {
        return Attendance::with('student.guardian')
            ->whereIn('circle_id', $this->supervisorCircleIds())
            ->whereDate('date', $this->broadcastDate)
            ->whereIn('status', ['absent', 'late'])
            ->get()
            ->filter(fn ($attendance) => $attendance->student !== null)
            ->unique(fn ($attendance) => $attendance->student_id)
            ->values();
    }

    public function selectDate($date, $hijriDay, $monthName)
    {
        $this->broadcastDate = $date;
        $this->selectedDateHijri = "$hijriDay $monthName $this->year";
        $this->prepareReport();
    }

    public function prepareReport()
    {
        $absences = $this->absencesForDate();

        if ($absences->isEmpty()) {
            Flux::toast('لا يوجد غياب أو تأخر مسجل في هذا التاريخ.', variant: 'warning');

            return;
        }

        $this->noGuardianStudents = [];
        $this->noPhoneStudents = [];
        $eligible = collect();

        foreach ($absences as $attendance) {
            $student = $attendance->student;

            if (! $student->guardian_id || ! $student->guardian) {
                $this->noGuardianStudents[] = $student->name;
            } elseif (! $student->guardian->phone) {
                $this->noPhoneStudents[] = $student->name;
            } else {
                $eligible->push($attendance);
            }
        }

        $this->eligibleCount = $eligible->count();
        $this->absentCount = $eligible->where('status', 'absent')->count();
        $this->lateCount = $eligible->where('status', 'late')->count();

        $this->alreadyNotifiedCount = $eligible->filter(function ($attendance) {
            $type = $attendance->status === 'late' ? 'late' : 'absence';

            return GuardianNotification::where('guardian_id', $attendance->student->guardian_id)
                ->where('student_id', $attendance->student_id)
                ->where('type', $type)
                ->where('data->date', $this->broadcastDate)
                ->exists();
        })->count();

        $this->alreadyBroadcast = Cache::has($this->broadcastCacheKey());

        $this->showReportModal = true;
    }

    public function sendBroadcast()
    {
        $supervisor = Auth::guard('supervisor')->user();
        $senderClientId = 'supervisor_'.$supervisor->id;

        if (! $this->isWhatsappSessionReady($senderClientId)) {
            Flux::toast('واتساب المشرف غير متصل. اربط الجلسة أعلاه أولاً ثم أعد المحاولة.', variant: 'danger');

            return;
        }

        $queued = 0;

        foreach ($this->absencesForDate() as $attendance) {
            $student = $attendance->student;

            if (! $student->guardian_id || ! $student->guardian || ! $student->guardian->phone) {
                continue;
            }

            $status = $attendance->status === 'late' ? 'late' : 'absent';

            $notification = GuardianNotificationService::notifyAbsence(
                $student,
                $status,
                $this->broadcastDate,
                $senderClientId,
            );

            if (! $notification) {
                $parts = GuardianNotificationService::absenceMessageParts($student, $status, $this->broadcastDate);

                SendGuardianWhatsappJob::dispatch(
                    $student->guardian->phone,
                    $parts['whatsapp'],
                    $senderClientId,
                );
            }

            $queued++;
        }

        Cache::put($this->broadcastCacheKey(), now()->toDateTimeString(), now()->addDay());

        $this->showReportModal = false;

        $avgDelay = (config('services.whatsapp.send_delay_min', 6) + config('services.whatsapp.send_delay_max', 14)) / 2;
        $estimatedMinutes = max(1, (int) ceil($queued * $avgDelay / 60));

        Flux::toast("تمت جدولة {$queued} رسالة، ستكتمل تدريجياً خلال {$estimatedMinutes} دقيقة تقريباً.", variant: 'success');
    }

    protected function broadcastCacheKey(): string
    {
        $supervisor = Auth::guard('supervisor')->user();

        return "absence-broadcast:{$supervisor->id}:{$this->broadcastDate}";
    }

    protected function isWhatsappSessionReady(string $clientId): bool
    {
        try {
            $url = config('services.whatsapp.url');
            $response = Http::withHeaders(['X-Api-Key' => config('services.whatsapp.key')])
                ->timeout(3)
                ->get("{$url}/status/{$clientId}");

            return $response->successful() && ($response->json()['status'] ?? null) === 'ready';
        } catch (\Exception $e) {
            return false;
        }
    }

    public function with()
    {
        $circleIds = $this->supervisorCircleIds();
        $totalCirclesCount = $circleIds->count();

        $cal = \IntlCalendar::createInstance('Asia/Riyadh', 'ar_SA@calendar=islamic-umalqura');
        $cal->set(\IntlCalendar::FIELD_YEAR, $this->year);
        $cal->set(\IntlCalendar::FIELD_MONTH, 0);
        $cal->set(\IntlCalendar::FIELD_DAY_OF_MONTH, 1);
        $startDate = date('Y-m-d', $cal->getTime() / 1000);

        $cal->set(\IntlCalendar::FIELD_YEAR, $this->year);
        $cal->set(\IntlCalendar::FIELD_MONTH, 11);
        $monthLength = $cal->getActualMaximum(\IntlCalendar::FIELD_DAY_OF_MONTH);
        $cal->set(\IntlCalendar::FIELD_DAY_OF_MONTH, $monthLength);
        $endDate = date('Y-m-d', $cal->getTime() / 1000);

        $allAttendances = Attendance::whereIn('circle_id', $circleIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->select('date', DB::raw('count(distinct circle_id) as circles_completed'))
            ->groupBy('date')
            ->get()
            ->mapWithKeys(fn ($item) => [\Carbon\Carbon::parse($item->date)->format('Y-m-d') => $item->circles_completed]);

        $months = [];
        for ($m = 0; $m < 12; $m++) {
            $months[] = $this->getMonthData($this->year, $m, $totalCirclesCount, $allAttendances);
        }

        return [
            'months' => $months,
            'currentYear' => $this->year,
            'totalCirclesCount' => $totalCirclesCount,
        ];
    }

    private function getMonthData($year, $monthIndex, $totalCirclesCount, $allAttendances)
    {
        $cal = \IntlCalendar::createInstance('Asia/Riyadh', 'ar_SA@calendar=islamic-umalqura');
        $cal->set(\IntlCalendar::FIELD_YEAR, $year);
        $cal->set(\IntlCalendar::FIELD_MONTH, $monthIndex);
        $cal->set(\IntlCalendar::FIELD_DAY_OF_MONTH, 1);

        $monthLength = $cal->getActualMaximum(\IntlCalendar::FIELD_DAY_OF_MONTH);
        $startDayOfWeek = $cal->get(\IntlCalendar::FIELD_DAY_OF_WEEK); // 1 = Sunday

        $monthNameFormatter = new \IntlDateFormatter('ar_SA@calendar=islamic-umalqura', \IntlDateFormatter::FULL, \IntlDateFormatter::NONE, 'Asia/Riyadh', \IntlDateFormatter::TRADITIONAL, 'MMMM');
        $monthName = $monthNameFormatter->format($cal->getTime() / 1000);

        $days = [];
        $emptySlots = $startDayOfWeek - 1;

        for ($i = 0; $i < $emptySlots; $i++) {
            $days[] = null;
        }

        for ($i = 1; $i <= $monthLength; $i++) {
            $cal->set(\IntlCalendar::FIELD_DAY_OF_MONTH, $i);
            $gregDate = date('Y-m-d', $cal->getTime() / 1000);

            $completedCirclesCount = $allAttendances->get($gregDate, 0);
            $completionRate = $totalCirclesCount > 0 ? min(100, round(($completedCirclesCount / $totalCirclesCount) * 100)) : 0;

            $colorClass = 'bg-white hover:bg-zinc-50 dark:bg-zinc-800 dark:hover:bg-zinc-700';
            if ($completedCirclesCount > 0 && $totalCirclesCount > 0) {
                $ratio = $completedCirclesCount / $totalCirclesCount;
                if ($ratio >= 1.0) {
                    $colorClass = 'bg-green-100 dark:bg-green-900/40 border-green-200';
                } elseif ($ratio >= 0.5) {
                    $colorClass = 'bg-blue-50 dark:bg-blue-900/20 border-blue-100';
                } else {
                    $colorClass = 'bg-amber-50 dark:bg-amber-900/20 border-amber-100';
                }
            }

            $days[] = [
                'hijriDay' => $i,
                'gregorianDate' => $gregDate,
                'completedCount' => $completedCirclesCount,
                'completionRate' => $completionRate,
                'colorClass' => $colorClass,
                'isToday' => $gregDate === now('Asia/Riyadh')->format('Y-m-d'),
            ];
        }

        return [
            'monthName' => $monthName,
            'days' => $days,
        ];
    }
};
?>

<div class="space-y-6" dir="rtl">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="size-10 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                <flux:icon icon="megaphone" class="size-5" />
            </div>
            <div>
                <flux:heading size="lg">{{ __('إرسال تنبيهات الغياب والتأخر') }}</flux:heading>
                <flux:subheading>{{ __('اضغط على اليوم المطلوب لمعاينة الغياب وإرسال التنبيهات لأولياء الأمور — العام الهجري') }} {{ $currentYear }}</flux:subheading>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <div class="flex items-center gap-1.5 text-xs">
                <div class="size-3 rounded-full bg-green-100 dark:bg-green-900 border border-green-200"></div>
                <span class="text-zinc-500">{{ __('مكتمل') }}</span>
            </div>
            <div class="flex items-center gap-1.5 text-xs">
                <div class="size-3 rounded-full bg-blue-50 dark:bg-blue-900/40 border border-blue-100"></div>
                <span class="text-zinc-500">{{ __('جزئي (>50%)') }}</span>
            </div>
            <div class="flex items-center gap-1.5 text-xs">
                <div class="size-3 rounded-full bg-amber-50 dark:bg-amber-900/40 border border-amber-100"></div>
                <span class="text-zinc-500">{{ __('تحضير بسيط') }}</span>
            </div>
        </div>
    </div>

    <p class="text-xs text-zinc-400 flex items-center gap-1.5">
        <flux:icon icon="information-circle" class="size-4 shrink-0" />
        {{ __('تُرسل الرسائل تدريجياً بفواصل زمنية لحماية رقم الواتساب من الحظر.') }}
    </p>

    <div
        class="max-w-3xl mx-auto"
        x-data="{
            month: {{ $currentMonthIndex }},
            total: 12,
            startX: null,
            next() { this.month = Math.min(this.total - 1, this.month + 1); },
            prev() { this.month = Math.max(0, this.month - 1); },
        }"
        @touchstart.passive="startX = $event.touches[0].clientX"
        @touchend="
            if (startX !== null) {
                const dx = $event.changedTouches[0].clientX - startX;
                if (Math.abs(dx) > 50) { dx > 0 ? next() : prev(); }
                startX = null;
            }
        "
    >
        {{-- Month navigation: RTL — right arrow goes back, left arrow goes forward --}}
        <div class="flex items-center justify-between mb-3">
            <flux:button variant="ghost" size="sm" icon="chevron-right" x-on:click="prev()"
                x-bind:disabled="month === 0" />

            <div class="text-center">
                @foreach($months as $month)
                    <div x-show="month === {{ $loop->index }}" x-cloak wire:key="month-title-{{ $loop->index }}"
                        class="font-bold text-zinc-800 dark:text-zinc-100">
                        {{ $month['monthName'] }} {{ $currentYear }}
                    </div>
                @endforeach
            </div>

            <flux:button variant="ghost" size="sm" icon="chevron-left" x-on:click="next()"
                x-bind:disabled="month === total - 1" />
        </div>

        @foreach($months as $month)
            <div x-show="month === {{ $loop->index }}" x-cloak wire:key="broadcast-month-{{ $loop->index }}"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm overflow-hidden flex flex-col select-none">

                {{-- Weekdays Header --}}
                <div class="grid grid-cols-7 gap-1 px-3 pt-3 pb-1 text-center">
                    @foreach(['أحد', 'إثنين', 'ثلاثاء', 'أربعاء', 'خميس', 'جمعة', 'سبت'] as $day)
                        <div class="text-[0.6rem] font-bold text-zinc-400 dark:text-zinc-500 uppercase">{{ $day }}</div>
                    @endforeach
                </div>

                {{-- Days Grid --}}
                <div class="grid grid-cols-7 gap-0.5 px-1.5 pb-1.5 grow">
                    @foreach($month['days'] as $day)
                        @if($day === null)
                            <div class="h-16 w-full"></div>
                        @else
                            <button
                                wire:key="broadcast-day-{{ $day['gregorianDate'] }}"
                                wire:click="selectDate('{{ $day['gregorianDate'] }}', {{ $day['hijriDay'] }}, '{{ $month['monthName'] }}')"
                                type="button" class="relative flex flex-col justify-between p-1.5 rounded-md border h-16 w-full duration-200
                                    {{ $day['colorClass'] }}
                                    {{ $day['isToday'] ? 'ring-2 ring-indigo-500 ring-offset-1 dark:ring-offset-zinc-900 border-transparent shadow-sm' : 'border-zinc-100 dark:border-zinc-700/50' }}">

                                {{-- Hijri Day Number --}}
                                <div class="flex justify-between items-start w-full leading-none">
                                    <span class="text-xs font-semibold {{ $day['isToday'] ? 'text-indigo-700 dark:text-indigo-400' : 'text-zinc-700 dark:text-zinc-200' }}">
                                        {{ $day['hijriDay'] }}
                                    </span>

                                    @if ($day['isToday'])
                                        <div class="size-1.5 rounded-full bg-indigo-500 mt-0.5"></div>
                                    @endif
                                </div>

                                {{-- Stats Area: Circles Completed / Total Circles --}}
                                <div class="mt-auto w-full space-y-1">
                                    @if ($day['completedCount'] > 0 || $totalCirclesCount > 0)
                                        <div class="text-[0.65rem] font-medium leading-none text-center">
                                            <span class="text-indigo-700 dark:text-indigo-400 font-bold">{{ $day['completedCount'] }}</span>
                                            <span class="mx-0.5 text-zinc-400">/</span>
                                            <span class="text-zinc-500 dark:text-zinc-500 font-semibold">{{ $totalCirclesCount }}</span>
                                        </div>
                                        <div class="w-full bg-black/5 dark:bg-white/10 rounded-full h-1 overflow-hidden">
                                            <div class="h-full rounded-full {{ $day['completionRate'] == 100 ? 'bg-green-500 dark:bg-green-400' : 'bg-indigo-400 dark:bg-indigo-500' }}"
                                                style="width: {{ $day['completionRate'] }}%"></div>
                                        </div>
                                    @endif
                                </div>
                            </button>
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <flux:modal wire:model="showReportModal" class="md:w-[520px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('تقرير ما قبل الإرسال') }}</flux:heading>
                <flux:subheading>
                    {{ $selectedDateHijri ? __('يوم').' '.$selectedDateHijri.' هـ' : __('تاريخ').': '.$broadcastDate }}
                </flux:subheading>
            </div>

            @if($alreadyBroadcast)
                <div class="flex items-start gap-2 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-900/50 rounded-xl p-3 text-sm text-amber-700 dark:text-amber-400">
                    <flux:icon icon="exclamation-triangle" class="size-5 shrink-0" />
                    <span>{{ __('سبق أن أرسلت بثاً لهذا التاريخ. الإرسال مجدداً سيوصل رسالة مكررة لأولياء الأمور.') }}</span>
                </div>
            @endif

            <div class="grid grid-cols-3 gap-3 text-center">
                <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-xl p-3">
                    <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ $eligibleCount }}</div>
                    <div class="text-xs text-zinc-500 mt-1">{{ __('سيصلهم الإشعار') }}</div>
                </div>
                <div class="bg-red-50 dark:bg-red-900/20 rounded-xl p-3">
                    <div class="text-2xl font-black text-red-600 dark:text-red-400">{{ $absentCount }}</div>
                    <div class="text-xs text-zinc-500 mt-1">{{ __('غائب') }}</div>
                </div>
                <div class="bg-amber-50 dark:bg-amber-900/20 rounded-xl p-3">
                    <div class="text-2xl font-black text-amber-600 dark:text-amber-400">{{ $lateCount }}</div>
                    <div class="text-xs text-zinc-500 mt-1">{{ __('متأخر') }}</div>
                </div>
            </div>

            @if($alreadyNotifiedCount > 0)
                <p class="text-xs text-zinc-500">
                    {{ __('منهم') }} {{ $alreadyNotifiedCount }} {{ __('سبق تسجيل إشعار لهم داخل المنصة، وستصلهم رسالة الواتساب عند التأكيد.') }}
                </p>
            @endif

            @if(count($noGuardianStudents) > 0)
                <div class="space-y-2">
                    <div class="flex items-center gap-2 text-sm font-bold text-red-600 dark:text-red-400">
                        <flux:icon icon="user-minus" class="size-4" />
                        {{ __('طلاب بدون ولي أمر مرتبط') }} ({{ count($noGuardianStudents) }})
                    </div>
                    <div class="max-h-32 overflow-y-auto bg-zinc-50 dark:bg-zinc-800/50 rounded-xl p-3 text-sm text-zinc-600 dark:text-zinc-400 space-y-1">
                        @foreach($noGuardianStudents as $name)
                            <div wire:key="no-guardian-{{ $loop->index }}">• {{ $name }}</div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if(count($noPhoneStudents) > 0)
                <div class="space-y-2">
                    <div class="flex items-center gap-2 text-sm font-bold text-amber-600 dark:text-amber-400">
                        <flux:icon icon="phone-x-mark" class="size-4" />
                        {{ __('ولي الأمر بدون رقم جوال') }} ({{ count($noPhoneStudents) }})
                    </div>
                    <div class="max-h-32 overflow-y-auto bg-zinc-50 dark:bg-zinc-800/50 rounded-xl p-3 text-sm text-zinc-600 dark:text-zinc-400 space-y-1">
                        @foreach($noPhoneStudents as $name)
                            <div wire:key="no-phone-{{ $loop->index }}">• {{ $name }}</div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="flex gap-2">
                <flux:spacer />
                <flux:button wire:click="$set('showReportModal', false)">{{ __('إلغاء') }}</flux:button>
                <flux:button wire:click="sendBroadcast" variant="primary" icon="paper-airplane"
                    wire:loading.attr="disabled" :disabled="$eligibleCount === 0">
                    <span wire:loading.remove wire:target="sendBroadcast">{{ __('إرسال الآن') }} ({{ $eligibleCount }})</span>
                    <span wire:loading wire:target="sendBroadcast">{{ __('جاري الجدولة...') }}</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
