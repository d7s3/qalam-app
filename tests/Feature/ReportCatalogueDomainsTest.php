<?php

use App\Models\Circle;
use App\Models\ExamLevel;
use App\Models\GamificationTransaction;
use App\Models\Guardian;
use App\Models\GuardianNotification;
use App\Models\Leaderboard;
use App\Models\Manager;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentExam;
use App\Models\StudentPlan;
use App\Models\StudentPlanDay;
use App\Models\StudentStatusHistory;
use App\Models\Supervisor;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Reports\ReportCatalogue;
use App\Services\Reports\ReportQuery;
use App\Services\Reports\ReportResult;
use App\Support\Scope;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The eight domains added after the first three.
 *
 * Each answers in the same three parts, so each is checked the same way: that
 * it counts what it says it counts, and that it never reaches past the reader.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-09-20 08:00:00');

    $this->stage = Stage::factory()->create();
    $this->circle = Circle::factory()->create(['stage_id' => $this->stage->id]);
    $this->student = Student::factory()->create(['name' => 'سالم', 'circle_id' => $this->circle->id]);

    $this->otherCircle = Circle::factory()->create(['stage_id' => Stage::factory()->create()->id]);
    $this->outsider = Student::factory()->create(['name' => 'زياد', 'circle_id' => $this->otherCircle->id]);

    $this->teacher = Teacher::factory()->create(['name' => 'أستاذ سعيد']);
    $this->teacher->circles()->attach($this->circle->id);

    $this->manager = Manager::factory()->create();
});

function runReport(string $key, User $user, string $role): ReportResult
{
    return ReportCatalogue::find($key)->run(new ReportQuery(
        scope: Scope::for($user, $role),
        from: Carbon::parse('2026-09-01'),
        to: Carbon::parse('2026-09-30'),
    ));
}

it('counts examinations that were sat, and leaves the unsat alone', function () {
    $level = ExamLevel::create(['name' => 'المستوى الأول']);

    StudentExam::create(['student_id' => $this->student->id, 'exam_level_id' => $level->id,
        'date_time' => '2026-09-05 10:00:00', 'score_percentage' => 90, 'status' => 'completed']);
    StudentExam::create(['student_id' => $this->student->id, 'exam_level_id' => $level->id,
        'date_time' => '2026-09-06 10:00:00', 'score_percentage' => 40, 'status' => 'completed']);
    // Scheduled, not yet sat: a nought here would read as a failure.
    StudentExam::create(['student_id' => $this->student->id, 'exam_level_id' => $level->id,
        'date_time' => '2026-09-28 10:00:00', 'status' => 'scheduled']);

    $row = runReport('exams', $this->manager, 'manager')->rows;
    $salem = collect($row)->firstWhere('name', 'سالم');

    expect($salem['sat'])->toBe(2)
        ->and($salem['passed'])->toBe(1)
        ->and($salem['average'])->toBe('65%')
        ->and($salem['pass_rate'])->toBe('50%');
});

it('separates what was earned from what was spent', function () {
    $board = Leaderboard::create(['title' => 'مسابقة', 'circle_id' => $this->circle->id,
        'competition_type' => 'gamification', 'is_active' => true, 'start_date' => '2026-09-01']);

    GamificationTransaction::create(['leaderboard_id' => $board->id, 'student_id' => $this->student->id,
        'type' => 'earn', 'amount' => 50, 'xp_amount' => 30, 'description' => 'اكتساب']);
    GamificationTransaction::create(['leaderboard_id' => $board->id, 'student_id' => $this->student->id,
        'type' => 'spend', 'amount' => 20, 'xp_amount' => 0, 'description' => 'شراء']);

    $salem = collect(runReport('gamification', $this->manager, 'manager')->rows)->firstWhere('name', 'سالم');

    expect($salem['earned'])->toBe(50)
        ->and($salem['spent'])->toBe(20)
        ->and($salem['balance'])->toBe(30)
        ->and($salem['xp'])->toBe(30);
});

it('sees a student who left and one who came back', function () {
    foreach ([['active', '2026-09-02'], ['inactive', '2026-09-08'], ['active', '2026-09-15']] as [$status, $date]) {
        StudentStatusHistory::create([
            'student_id' => $this->student->id, 'status' => $status, 'start_date' => $date,
        ]);
    }
    StudentStatusHistory::create([
        'student_id' => $this->outsider->id, 'status' => 'inactive', 'start_date' => '2026-09-10',
    ]);

    $rows = collect(runReport('retention', $this->manager, 'manager')->rows);

    expect($rows->firstWhere('name', 'سالم')['returned'])->toBe(1)
        ->and($rows->firstWhere('name', 'سالم')['left'])->toBe(0)
        ->and($rows->firstWhere('name', 'زياد')['left'])->toBe(1);
});

it('measures what reached a family against what it read', function () {
    $guardian = Guardian::factory()->create();
    $this->student->update(['guardian_id' => $guardian->id]);

    GuardianNotification::create(['guardian_id' => $guardian->id, 'student_id' => $this->student->id,
        'type' => 'note', 'title' => 'إشعار', 'body' => 'نص', 'read_at' => now()]);
    GuardianNotification::create(['guardian_id' => $guardian->id, 'student_id' => $this->student->id,
        'type' => 'note', 'title' => 'إشعار آخر', 'body' => 'نص']);

    $salem = collect(runReport('family-contact', $this->manager, 'manager')->rows)->firstWhere('name', 'سالم');

    expect($salem['sent'])->toBe(2)
        ->and($salem['read'])->toBe(1)
        ->and($salem['read_rate'])->toBe('50%');
});

it('names a student nobody wrote to', function () {
    $salem = collect(runReport('family-contact', $this->manager, 'manager')->rows)->firstWhere('name', 'سالم');

    expect($salem['silent'])->toBe('نعم');
});

it('measures a teacher by what he did, not by what his students achieved', function () {
    $plan = StudentPlan::create(['student_id' => $this->student->id, 'teacher_id' => $this->teacher->id,
        'start_date' => '2026-09-01', 'days_count' => 5, 'active_days' => [0, 1, 2, 3, 4, 5, 6],
        'status' => 'active', 'is_approved' => true, 'created_by_role' => 'teacher']);

    foreach (['2026-09-03', '2026-09-04'] as $date) {
        StudentPlanDay::create(['student_plan_id' => $plan->id, 'date' => $date,
            'day_name' => 'الخميس', 'hifz_achievement' => 3]);
    }

    $row = collect(runReport('teacher-performance', $this->manager, 'manager')->rows)
        ->firstWhere('name', 'أستاذ سعيد');

    expect($row['recitation_days'])->toBe(2)
        ->and($row['gradings'])->toBe(2)
        ->and($row['circles'])->toBe(1);
});

it('shows a supervisor only the teachers of his own programme', function () {
    $stranger = Teacher::factory()->create(['name' => 'أستاذ غريب']);
    $stranger->circles()->attach($this->otherCircle->id);

    $supervisor = Supervisor::factory()->create();
    $supervisor->stages()->attach($this->stage->id);

    $names = array_column(runReport('teacher-performance', $supervisor, 'supervisor')->rows, 'name');

    expect($names)->toContain('أستاذ سعيد')
        ->and($names)->not->toContain('أستاذ غريب');
});

it('answers every report in the shape the outputs read', function () {
    // One writer and one printed sheet serve them all, so each must answer in
    // the same three parts.
    foreach (ReportCatalogue::all() as $report) {
        $result = $report->run(new ReportQuery(
            scope: Scope::for($this->manager, 'manager'),
            from: Carbon::parse('2026-09-01'),
            to: Carbon::parse('2026-09-30'),
        ));

        expect($result->title)->not->toBe('')
            ->and($result->columns)->not->toBeEmpty()
            ->and($report->description())->not->toBe('');

        foreach ($result->columns as $column) {
            expect($column)->toHaveKeys(['key', 'label']);
        }

        // Every column named must be answerable for every row.
        foreach ($result->rows as $row) {
            expect($result->cells($row))->toHaveCount(count($result->columns));
        }
    }
});
