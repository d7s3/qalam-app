<?php

use App\Livewire\Supervisor\CircleReport;
use App\Livewire\Supervisor\Circles as SupervisorCircles;
use App\Livewire\Supervisor\Students as SupervisorStudents;
use App\Models\Circle;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Teacher;
use App\Models\UserRole;
use App\Services\MessagingService;
use App\Services\SurveyAssignmentService;
use App\Support\Scope;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

/**
 * A sheikh supervises his own group.
 *
 * A supervisor holds a programme, and every cohort in it used to be his: five
 * sheikhs over one programme each saw the others' students. A reach written on
 * his role — cohorts, rather than the programme — narrows him to his own group,
 * and every supervisor screen has to honour it rather than work his reach out
 * from his programmes again on its own.
 */
beforeEach(function () {
    $this->programme = Stage::factory()->create(['name' => 'تأصيل']);
    $this->ownGroup = Circle::factory()->create(['stage_id' => $this->programme->id, 'name' => 'مجموعة الأهدل']);
    $this->otherGroup = Circle::factory()->create(['stage_id' => $this->programme->id, 'name' => 'مجموعة فلمبان']);

    $this->ownStudent = Student::factory()->create(['name' => 'طالب الأهدل', 'circle_id' => $this->ownGroup->id, 'stage_id' => $this->programme->id, 'status' => 'active', 'is_approved' => true]);
    $this->otherStudent = Student::factory()->create(['name' => 'طالب فلمبان', 'circle_id' => $this->otherGroup->id, 'stage_id' => $this->programme->id, 'status' => 'active', 'is_approved' => true]);

    $this->otherTeacher = Teacher::factory()->create();
    $this->otherTeacher->circles()->attach($this->otherGroup->id);

    // As on the server: the sheikh holds the programme, and his role is
    // narrowed to his own group.
    $this->sheikh = Supervisor::factory()->create();
    $this->sheikh->stages()->attach($this->programme->id);
    $this->sheikh->roles()->where('role', 'supervisor')->update([
        'scope_type' => UserRole::SCOPE_CIRCLES,
        'scope_ids' => [$this->ownGroup->id],
    ]);
    $this->sheikh->load('roles');

    Scope::forget();
    $this->actingAs($this->sheikh, 'supervisor');
});

it('lists only his group\'s students', function () {
    Livewire::test(SupervisorStudents::class)
        ->assertSee('طالب الأهدل')
        ->assertDontSee('طالب فلمبان');
});

it('lists only his group among the cohorts', function () {
    Livewire::test(SupervisorCircles::class)
        ->assertSee('مجموعة الأهدل')
        ->assertDontSee('مجموعة فلمبان');
});

it('counts only his group on his home page', function () {
    $counts = Livewire::test('supervisor.dashboard')->viewData('activeStudents');

    expect($counts)->toBe(1);
});

it('opens no report on another sheikh\'s group', function () {
    expect(fn () => Livewire::test(CircleReport::class, ['circleId' => $this->otherGroup->id]))
        ->toThrow(ModelNotFoundException::class);
});

it('keeps his messages and forms to his own group', function () {
    expect(MessagingService::isAllowedToMessage('supervisor', $this->sheikh->id, 'teacher', $this->otherTeacher->id))->toBeFalse();

    $named = SurveyAssignmentService::clampToAuthor(['circle_ids_for_students' => [$this->ownGroup->id, $this->otherGroup->id]], $this->sheikh, 'supervisor');

    expect($named['circle_ids_for_students'] ?? [])->toBe([$this->ownGroup->id]);

    // «All students» was widened to his whole programme — the other sheikhs'
    // groups with it.
    $everyone = SurveyAssignmentService::clampToAuthor(['all_students' => true], $this->sheikh, 'supervisor');

    expect($everyone)->toBe(['circle_ids_for_students' => [$this->ownGroup->id]]);
});

it('still holds the whole programme when no group is written on his role', function () {
    $this->sheikh->roles()->where('role', 'supervisor')->update(['scope_type' => null, 'scope_ids' => null]);
    $this->sheikh->load('roles');
    Scope::forget();

    Livewire::test(SupervisorStudents::class)
        ->assertSee('طالب الأهدل')
        ->assertSee('طالب فلمبان');
});

it('leaves working a supervisor\'s reach out to Scope alone', function () {
    // Each of these worked it out from his programmes on its own, and so went
    // on showing a sheikh every group of his programme.
    $offenders = collect(File::allFiles([app_path(), resource_path('views')]))
        ->reject(fn ($file) => $file->getRealPath() === app_path('Support/Scope.php'))
        ->filter(fn ($file) => str_contains($file->getContents(), "stages()->pluck('stages.id')"))
        ->map(fn ($file) => $file->getRelativePathname())
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});
