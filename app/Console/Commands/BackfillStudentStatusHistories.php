<?php

namespace App\Console\Commands;

use App\Models\Student;
use Illuminate\Console\Command;

class BackfillStudentStatusHistories extends Command
{
    protected $signature = 'students:backfill-status-history';

    protected $description = 'ينشئ سجل حالة افتتاحياً لكل طالب لا يملك أي سجلات، بحالته الحالية بدءاً من تاريخ التحاقه';

    public function handle(): int
    {
        $students = Student::doesntHave('statusHistories')->get();

        if ($students->isEmpty()) {
            $this->info('جميع الطلاب لديهم سجلات حالة بالفعل، لا شيء لتوليده.');

            return self::SUCCESS;
        }

        foreach ($students as $student) {
            $startDate = $student->joined_at
                ? $student->joined_at->format('Y-m-d')
                : $student->created_at->format('Y-m-d');

            $student->statusHistories()->create([
                'status' => $student->status ?: 'active',
                'start_date' => $startDate,
                'notes' => 'سجل افتتاحي مولّد آلياً',
            ]);
        }

        $this->info("تم توليد سجل افتتاحي لـ {$students->count()} طالباً.");

        return self::SUCCESS;
    }
}
