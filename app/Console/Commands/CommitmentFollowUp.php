<?php

namespace App\Console\Commands;

use App\Models\Circle;
use App\Models\CommitmentAlert;
use App\Models\Student;
use App\Services\CommitmentService;
use App\Services\NotificationService;
use App\Support\Scope;
use App\Support\StudentStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('commitment:follow-up {--on= : اليوم الذي يُحسب له، افتراضه اليوم}')]
#[Description('Ask teachers to act on students below the line last week, and tell supervisors of those left two days.')]
class CommitmentFollowUp extends Command
{
    public function handle(CommitmentService $commitment): int
    {
        $today = Carbon::parse($this->option('on') ?: Carbon::today())->startOfDay();

        $raised = $this->raise($commitment, $today);
        $escalated = $this->escalate($today);

        $this->info("طلاب يحتاجون إجراء: {$raised}، رُفع منهم للمشرف: {$escalated}.");

        return self::SUCCESS;
    }

    /**
     * One row per student per week, so running twice asks once.
     */
    private function raise(CommitmentService $commitment, Carbon $today): int
    {
        [$from, $to] = CommitmentService::lastWeek($today);

        $students = Student::query()
            ->where('status', StudentStatus::Active->value)
            ->whereNotNull('circle_id')
            ->whereDoesntHave('commitmentAlerts', fn ($q) => $q->whereDate('week_ends_on', $to))
            ->get();

        $byCircle = [];

        foreach ($commitment->forStudents($students, $from, $to) as $studentId => $row) {
            if (! CommitmentService::isBehind($row)) {
                continue;
            }

            $student = $students->firstWhere('id', $studentId);

            CommitmentAlert::create([
                'student_id' => $studentId,
                'circle_id' => $student->circle_id,
                'week_starts_on' => $from,
                'week_ends_on' => $to,
                'plan_percent' => $row['plan']['percent'] ?? null,
                'self_percent' => $row['self']['percent'] ?? null,
                'notified_at' => now(),
            ]);

            $byCircle[$student->circle_id][] = $student->name;
        }

        foreach ($byCircle as $circleId => $names) {
            NotificationService::notifyCircleTeachers(
                circleId: $circleId,
                type: 'commitment_alert',
                title: count($names).' من طلابك تحت '.(int) CommitmentService::THRESHOLD.'% الأسبوع الماضي',
                body: 'سجّل ما فعلته مع: '.implode('، ', array_slice($names, 0, 5)).(count($names) > 5 ? ' وغيرهم' : '').'. إن مضى يومان دون إجراء يُرفع الأمر للمشرف.',
                url: route('teacher.commitment'),
            );
        }

        return array_sum(array_map('count', $byCircle));
    }

    /**
     * Each supervisor who reaches the cohort is told once, with the count.
     */
    private function escalate(Carbon $today): int
    {
        $alerts = CommitmentAlert::awaiting()
            ->whereNull('escalated_at')
            ->where('notified_at', '<=', $today->copy()->subDays(CommitmentAlert::DAYS_TO_ACT)->endOfDay())
            ->with('student')
            ->get();

        if ($alerts->isEmpty()) {
            return 0;
        }

        $circles = Circle::with('stage.supervisors')->findMany($alerts->pluck('circle_id')->filter()->unique());
        $perSupervisor = [];

        foreach ($alerts as $alert) {
            foreach ($circles->find($alert->circle_id)?->stage?->supervisors ?? [] as $supervisor) {
                if (Scope::for($supervisor, 'supervisor')->reachableCircleIds()->contains($alert->circle_id)) {
                    $perSupervisor[$supervisor->id][] = $alert->student?->name;
                }
            }
        }

        foreach ($perSupervisor as $supervisorId => $names) {
            NotificationService::notify(
                recipientType: 'supervisor',
                recipientId: $supervisorId,
                type: 'commitment_escalated',
                title: count($names).' طلاب متأخرون لم يسجّل معلموهم إجراءً',
                body: 'مضى يومان على تنبيه المعلم: '.implode('، ', array_slice(array_filter($names), 0, 5)).(count($names) > 5 ? ' وغيرهم' : '').'.',
                url: route('supervisor.commitment'),
            );
        }

        CommitmentAlert::whereKey($alerts->pluck('id'))->update(['escalated_at' => now()]);

        return $alerts->count();
    }
}
