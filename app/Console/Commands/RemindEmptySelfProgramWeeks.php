<?php

namespace App\Console\Commands;

use App\Models\Stage;
use App\Services\NotificationService;
use App\Services\SelfProgramService;
use App\Support\HijriDate;
use App\Support\Scope;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('self-program:remind-empty-weeks')]
#[Description('Tell the supervisors of a programme whose coming self-programme week is still empty.')]
class RemindEmptySelfProgramWeeks extends Command
{
    /**
     * Run on Thursday, so there is a weekend to write the week before its
     * students open it. Only the supervisors who write for the whole programme
     * are told: a sheikh narrowed to his own cohort does not write it.
     */
    public function handle(SelfProgramService $service): int
    {
        $sent = 0;

        foreach (Stage::with('supervisors')->get() as $stage) {
            $coming = $service->comingWeek($stage->id);

            if (! $coming || $coming['written']) {
                continue;
            }

            foreach ($stage->supervisors as $supervisor) {
                if (Scope::for($supervisor, 'supervisor')->narrowsToCohorts()) {
                    continue;
                }

                NotificationService::notify(
                    recipientType: 'supervisor',
                    recipientId: $supervisor->id,
                    type: 'self_program_week_empty',
                    title: "الأسبوع القادم في {$stage->name} فارغ",
                    body: 'يبدأ '.HijriDate::withGregorian($coming['starts_on']).'، ولم يُكتب له برنامج ذاتي بعد.',
                    url: route('supervisor.self-program-weeks'),
                    data: ['stage_id' => $stage->id, 'starts_on' => $coming['starts_on']],
                );
                $sent++;
            }
        }

        $this->info("أُرسل {$sent} تنبيهاً.");

        return self::SUCCESS;
    }
}
