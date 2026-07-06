<?php

use App\Jobs\SendGuardianWhatsappJob;
use App\Models\Attendance;
use App\Models\Circle;
use App\Models\GuardianNotification;
use App\Services\GuardianNotificationService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Component;

new class extends Component {
    public $broadcastDate;

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
                    $parts['title']."\n".$parts['body'],
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
};
?>

<div class="space-y-6">
    <flux:card class="max-w-2xl">
        <div class="flex items-center gap-3 mb-1">
            <div class="size-10 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                <flux:icon icon="megaphone" class="size-5" />
            </div>
            <div>
                <flux:heading size="lg">{{ __('إرسال تنبيهات الغياب والتأخر') }}</flux:heading>
                <flux:subheading>{{ __('إرسال جماعي لأولياء أمور الطلاب الغائبين والمتأخرين في تاريخ محدد.') }}</flux:subheading>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row items-end gap-4 mt-6">
            <div class="w-full sm:w-64">
                <livewire:shared.hijri-datepicker wire:model="broadcastDate" label="{{ __('تاريخ الغياب') }}" />
            </div>
            <flux:button wire:click="prepareReport" variant="primary" icon="document-magnifying-glass" class="w-full sm:w-auto">
                {{ __('معاينة وإرسال') }}
            </flux:button>
        </div>

        <p class="text-xs text-zinc-400 mt-4 flex items-center gap-1.5">
            <flux:icon icon="information-circle" class="size-4 shrink-0" />
            {{ __('تُرسل الرسائل تدريجياً بفواصل زمنية لحماية رقم الواتساب من الحظر.') }}
        </p>
    </flux:card>

    <flux:modal wire:model="showReportModal" class="md:w-[520px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('تقرير ما قبل الإرسال') }}</flux:heading>
                <flux:subheading>{{ __('تاريخ') }}: {{ $broadcastDate }}</flux:subheading>
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
