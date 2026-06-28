<?php

namespace App\Console\Commands;

use App\Models\GamificationTransaction;
use App\Models\Student;
use App\Models\StudentPlanDay;
use App\Services\GamificationService;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('gamification:diagnose-student-points {student : Student id}')]
#[Description('Explain why a student is or is not earning hifz/review gamification points.')]
class DiagnoseStudentPoints extends Command
{
    public function handle(): int
    {
        $student = Student::find($this->argument('student'));

        if (! $student) {
            $this->error('الطالب غير موجود.');

            return self::FAILURE;
        }

        $this->info("الطالب: {$student->name} (#{$student->id})");
        $this->line('الحلقة: '.($student->circle_id ?? 'بلا حلقة').' | المرحلة المباشرة: '.($student->stage_id ?? '—'));

        if (! $student->circle_id) {
            $this->warn('✗ الطالب بلا حلقة → لا يُطابق أي مسار تلعيب. النقاط تُمنح فقط لطلاب الحلقات.');

            return self::SUCCESS;
        }

        $boards = GamificationService::getActiveLeaderboards($student);
        $this->line('مسارات تلعيب نشطة اليوم تغطّي حلقته: '.$boards->count());

        if ($boards->isEmpty()) {
            $this->warn('✗ لا يوجد مسار تلعيب نشط يغطّي حلقة الطالب اليوم (تحقّق من is_active/التواريخ/ضمّ الحلقة للمسار).');

            return self::SUCCESS;
        }

        foreach ($boards as $board) {
            $s = $board->settings ?? [];
            $hifz = ! empty($s['hifz_enabled']);
            $review = ! empty($s['review_enabled']);
            $manual = ! empty($s['manual_claim_enabled']);
            $this->line(" - مسار #{$board->id} «{$board->title}»: "
                .'حفظ مُفعّل='.($hifz ? 'نعم' : 'لا')
                .' | مراجعة مُفعّلة='.($review ? 'نعم' : 'لا')
                .' | مطالبة يدوية='.($manual ? 'نعم' : 'لا'));

            if (! $hifz && ! $review) {
                $this->warn('   ✗ الحفظ والمراجعة غير مُفعّلين في إعدادات هذا المسار → لا نقاط تسميع.');
            }
        }

        $gradedDayRows = StudentPlanDay::whereHas('plan', fn ($q) => $q->where('student_id', $student->id))
            ->where(fn ($q) => $q->whereNotNull('hifz_achievement')->orWhereNotNull('review_achievement'))
            ->get(['id', 'date', 'hifz_graded_at', 'review_graded_at']);
        $gradedDays = $gradedDayRows->count();

        // Count graded days whose grading date actually falls inside an active
        // competition (the gating rule).
        $inWindow = 0;
        $dateCovered = [];
        foreach ($gradedDayRows as $row) {
            $gradeDate = $row->hifz_graded_at ?? $row->review_graded_at ?? $row->date;
            $key = Carbon::parse($gradeDate)->format('Y-m-d');
            if (! array_key_exists($key, $dateCovered)) {
                $dateCovered[$key] = GamificationService::getActiveLeaderboards($student, $key)->isNotEmpty();
            }
            if ($dateCovered[$key]) {
                $inWindow++;
            }
        }
        $this->line("أيام مُقيَّمة (حفظ/مراجعة): {$gradedDays} — منها {$inWindow} تاريخ تقييمها ضمن مسابقة نشطة");

        $base = GamificationTransaction::where('student_id', $student->id)
            ->where('reference_type', StudentPlanDay::class);
        $total = (clone $base)->count();
        $pending = (clone $base)->whereNull('claimed_at')->count();
        $claimed = (clone $base)->whereNotNull('claimed_at')->count();
        $this->line("معاملات تسميع: {$total} (مُطالَب بها: {$claimed} | بانتظار المطالبة: {$pending})");

        $this->newLine();
        if ($gradedDays > 0 && $inWindow === 0) {
            $this->warn('الخلاصة: كل تواريخ التقييم خارج نطاق المسابقة → لا نقاط (هذا صحيح). قيّم خلال فترة المسابقة لتُحتسب النقاط.');
        } elseif ($total === 0 && $inWindow > 0) {
            $this->warn('الخلاصة: توجد تقييمات ضمن المسابقة بلا معاملات. شغّل: php artisan gamification:recompute-plan-day-points');
        } elseif ($pending > 0 && $claimed === 0) {
            $this->warn('الخلاصة: النقاط موجودة لكنها «بانتظار المطالبة» (المطالبة اليدوية مفعّلة) — على الطالب الضغط على «مطالبة» لتُضاف لرصيده.');
        } elseif ($total > 0) {
            $this->info('الخلاصة: النقاط محتسبة بشكل صحيح لهذا الطالب.');
        } else {
            $this->warn('الخلاصة: لا توجد تقييمات حفظ/مراجعة لهذا الطالب بعد.');
        }

        return self::SUCCESS;
    }
}
