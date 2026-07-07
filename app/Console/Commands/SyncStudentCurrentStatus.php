<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Services\StudentStatusService;
use Illuminate\Console\Command;

class SyncStudentCurrentStatus extends Command
{
    protected $signature = 'students:sync-current-status';

    protected $description = 'يزامن عمود حالة الطالب مع سجل الحالات ليوم اليوم (يفعّل العودة التلقائية من الإيقاف)';

    public function handle(): int
    {
        $today = now('Asia/Riyadh')->format('Y-m-d');
        $updated = 0;

        Student::has('statusHistories')->chunkById(200, function ($students) use ($today, &$updated) {
            foreach ($students as $student) {
                $effective = StudentStatusService::statusOn($student, $today);

                if ($student->status !== $effective) {
                    $student->update(['status' => $effective]);
                    $updated++;
                }
            }
        });

        $this->info("تمت مزامنة حالة {$updated} طالباً مع سجل حالاته.");

        return self::SUCCESS;
    }
}
