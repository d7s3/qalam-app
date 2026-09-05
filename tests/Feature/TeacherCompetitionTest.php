<?php

use App\Livewire\Supervisor\TeacherCompetitionManage;
use App\Livewire\Supervisor\TeacherCompetitions;
use App\Models\Circle;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Teacher;
use App\Models\TeacherCompetition;
use App\Models\TeacherCompetitionCriterion;
use App\Models\TeacherCompetitionScore;
use App\Services\TeacherCompetitionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function makeSupervisorWithTeacher(): array
{
    $stage = Stage::create(['name' => 'المرحلة الأولى']);
    $circle = Circle::create(['name' => 'دفعة', 'stage_id' => $stage->id]);
    $supervisor = Supervisor::factory()->create(['is_approved' => true]);
    $supervisor->stages()->attach($stage->id);
    $teacher = Teacher::factory()->create(['is_approved' => true]);
    $teacher->circles()->attach($circle->id);

    return [$supervisor, $teacher, $circle, $stage];
}

it('lets a supervisor create a teacher competition', function () {
    [$supervisor] = makeSupervisorWithTeacher();
    $this->actingAs($supervisor, 'supervisor');

    Livewire::test(TeacherCompetitions::class)
        ->call('create')
        ->set('name', 'مسابقة التميز')
        ->set('start_date', now()->format('Y-m-d'))
        ->set('end_date', now()->addDays(10)->format('Y-m-d'))
        ->call('save')
        ->assertHasNoErrors();

    $competition = TeacherCompetition::where('name', 'مسابقة التميز')->first();
    expect($competition)->not->toBeNull();
    expect($competition->supervisor_id)->toBe($supervisor->id);
});

it('only shows a supervisor their own competitions', function () {
    [$supervisorA] = makeSupervisorWithTeacher();
    $supervisorB = Supervisor::factory()->create(['is_approved' => true]);

    TeacherCompetition::create([
        'name' => 'مسابقة أ', 'start_date' => now(), 'end_date' => now()->addDays(5), 'supervisor_id' => $supervisorA->id,
    ]);
    TeacherCompetition::create([
        'name' => 'مسابقة ب', 'start_date' => now(), 'end_date' => now()->addDays(5), 'supervisor_id' => $supervisorB->id,
    ]);

    $this->actingAs($supervisorA, 'supervisor');

    $competitions = Livewire::test(TeacherCompetitions::class)->get('competitions');

    expect($competitions)->toHaveCount(1);
    expect($competitions->first()->name)->toBe('مسابقة أ');
});

it('does not let a supervisor manage another supervisor\'s competition', function () {
    [$supervisorA] = makeSupervisorWithTeacher();
    $supervisorB = Supervisor::factory()->create(['is_approved' => true]);

    $competition = TeacherCompetition::create([
        'name' => 'مسابقة أ', 'start_date' => now(), 'end_date' => now()->addDays(5), 'supervisor_id' => $supervisorA->id,
    ]);

    $this->actingAs($supervisorB, 'supervisor');

    Livewire::test(TeacherCompetitionManage::class, ['competitionId' => $competition->id]);
})->throws(ModelNotFoundException::class);

it('lets a supervisor add participants scoped to their own teachers', function () {
    [$supervisor, $teacher] = makeSupervisorWithTeacher();
    $outsideTeacher = Teacher::factory()->create(['is_approved' => true]);

    $competition = TeacherCompetition::create([
        'name' => 'مسابقة', 'start_date' => now(), 'end_date' => now()->addDays(5), 'supervisor_id' => $supervisor->id,
    ]);

    $this->actingAs($supervisor, 'supervisor');

    Livewire::test(TeacherCompetitionManage::class, ['competitionId' => $competition->id])
        ->set('selectedParticipants', [$teacher->id, $outsideTeacher->id])
        ->call('saveParticipants');

    $participantIds = $competition->fresh()->participants()->pluck('users.id')->all();
    expect($participantIds)->toContain($teacher->id);
    expect($participantIds)->not->toContain($outsideTeacher->id);
});

it('lets a supervisor create, edit, and delete criteria before scoring starts', function () {
    [$supervisor] = makeSupervisorWithTeacher();
    $competition = TeacherCompetition::create([
        'name' => 'مسابقة', 'start_date' => now(), 'end_date' => now()->addDays(5), 'supervisor_id' => $supervisor->id,
    ]);

    $this->actingAs($supervisor, 'supervisor');

    Livewire::test(TeacherCompetitionManage::class, ['competitionId' => $competition->id])
        ->call('addCriterion')
        ->set('criteria.0.name', 'الالتزام بالمواعيد')
        ->set('criteria.0.max_points', 20)
        ->call('saveCriteria')
        ->assertHasNoErrors();

    expect($competition->fresh()->criteria)->toHaveCount(1);
    expect($competition->fresh()->criteria->first()->name)->toBe('الالتزام بالمواعيد');
});

it('locks criteria editing once a score has been recorded', function () {
    [$supervisor, $teacher] = makeSupervisorWithTeacher();
    $competition = TeacherCompetition::create([
        'name' => 'مسابقة', 'start_date' => now(), 'end_date' => now()->addDays(5), 'supervisor_id' => $supervisor->id,
    ]);
    $criterion = TeacherCompetitionCriterion::create([
        'teacher_competition_id' => $competition->id, 'name' => 'بند', 'max_points' => 10,
    ]);
    TeacherCompetitionScore::create([
        'teacher_competition_id' => $competition->id, 'teacher_id' => $teacher->id,
        'criterion_id' => $criterion->id, 'score' => 8,
    ]);

    expect($competition->criteriaAreLocked())->toBeTrue();

    $this->actingAs($supervisor, 'supervisor');

    Livewire::test(TeacherCompetitionManage::class, ['competitionId' => $competition->id])
        ->set('criteria.0.name', 'اسم معدّل')
        ->call('saveCriteria');

    // The criterion must be unchanged since editing is locked.
    expect($criterion->fresh()->name)->toBe('بند');
});

it('saves scores and computes standings correctly', function () {
    [$supervisor, $teacher] = makeSupervisorWithTeacher();
    $teacher2 = Teacher::factory()->create(['is_approved' => true]);

    $competition = TeacherCompetition::create([
        'name' => 'مسابقة', 'start_date' => now(), 'end_date' => now()->addDays(5),
        'supervisor_id' => $supervisor->id, 'is_active' => true,
    ]);
    $competition->participants()->sync([$teacher->id, $teacher2->id]);

    $criterionA = TeacherCompetitionCriterion::create(['teacher_competition_id' => $competition->id, 'name' => 'بند أ', 'max_points' => 10]);
    $criterionB = TeacherCompetitionCriterion::create(['teacher_competition_id' => $competition->id, 'name' => 'بند ب', 'max_points' => 10]);

    $this->actingAs($supervisor, 'supervisor');

    Livewire::test(TeacherCompetitionManage::class, ['competitionId' => $competition->id])
        ->set("scores.{$teacher->id}.{$criterionA->id}", 9)
        ->set("scores.{$teacher->id}.{$criterionB->id}", 8)
        ->set("scores.{$teacher2->id}.{$criterionA->id}", 5)
        ->set("scores.{$teacher2->id}.{$criterionB->id}", 5)
        ->call('saveScores');

    $standings = (new TeacherCompetitionService)->getStandings($competition->fresh(['participants', 'criteria']));

    expect($standings->first()['teacher']->id)->toBe($teacher->id);
    expect($standings->first()['score'])->toBe(17);
    expect($standings->first()['max_score'])->toBe(20);
    expect($standings->first()['percentage'])->toBe(85.0);
    expect($standings->first()['rank'])->toBe(1);
    expect($standings->last()['rank'])->toBe(2);
});

it('shows the competition override on the teacher dashboard when active and participating', function () {
    [$supervisor, $teacher] = makeSupervisorWithTeacher();
    $competition = TeacherCompetition::create([
        'name' => 'مسابقة التميز', 'start_date' => now()->subDay(), 'end_date' => now()->addDays(5),
        'supervisor_id' => $supervisor->id, 'is_active' => true,
    ]);
    $competition->participants()->sync([$teacher->id]);

    $this->actingAs($teacher, 'teacher');

    $this->get(route('teacher.dashboard'))
        ->assertSuccessful()
        ->assertSee('مسابقة التميز')
        ->assertDontSee('التحضير اليومي');
});

it('shows the normal dashboard for a teacher not participating in the active competition', function () {
    [$supervisor, $teacher] = makeSupervisorWithTeacher();
    $otherTeacher = Teacher::factory()->create(['is_approved' => true]);

    $competition = TeacherCompetition::create([
        'name' => 'مسابقة التميز', 'start_date' => now()->subDay(), 'end_date' => now()->addDays(5),
        'supervisor_id' => $supervisor->id, 'is_active' => true,
    ]);
    $competition->participants()->sync([$otherTeacher->id]);

    $this->actingAs($teacher, 'teacher');

    $this->get(route('teacher.dashboard'))
        ->assertSuccessful()
        ->assertDontSee('مسابقة التميز')
        ->assertSee('التحضير اليومي');
});

it('shows the normal dashboard once the competition end date has passed', function () {
    [$supervisor, $teacher] = makeSupervisorWithTeacher();
    $competition = TeacherCompetition::create([
        'name' => 'مسابقة منتهية', 'start_date' => now()->subDays(10), 'end_date' => now()->subDay(),
        'supervisor_id' => $supervisor->id, 'is_active' => true,
    ]);
    $competition->participants()->sync([$teacher->id]);

    $this->actingAs($teacher, 'teacher');

    $this->get(route('teacher.dashboard'))
        ->assertSuccessful()
        ->assertDontSee('مسابقة منتهية')
        ->assertSee('التحضير اليومي');
});

it('shows the normal dashboard once the supervisor manually ends the competition', function () {
    [$supervisor, $teacher] = makeSupervisorWithTeacher();
    $competition = TeacherCompetition::create([
        'name' => 'مسابقة منتهية يدويًا', 'start_date' => now()->subDay(), 'end_date' => now()->addDays(5),
        'supervisor_id' => $supervisor->id, 'is_active' => false,
    ]);
    $competition->participants()->sync([$teacher->id]);

    $this->actingAs($teacher, 'teacher');

    $this->get(route('teacher.dashboard'))
        ->assertSuccessful()
        ->assertDontSee('مسابقة منتهية يدويًا')
        ->assertSee('التحضير اليومي');
});

it('does not affect the student dashboard or its active-competition system at all', function () {
    [$supervisor, $teacher] = makeSupervisorWithTeacher();
    $competition = TeacherCompetition::create([
        'name' => 'مسابقة معلمين', 'start_date' => now()->subDay(), 'end_date' => now()->addDays(5),
        'supervisor_id' => $supervisor->id, 'is_active' => true,
    ]);
    $competition->participants()->sync([$teacher->id]);

    $student = Student::factory()->create(['is_approved' => true]);

    $this->actingAs($student, 'student');

    $this->get(route('student.dashboard'))
        ->assertSuccessful()
        ->assertDontSee('مسابقة معلمين');
});
