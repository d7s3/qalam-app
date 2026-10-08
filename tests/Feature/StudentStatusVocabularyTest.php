<?php

use App\Ai\Tools\getPeopleDirectory;
use App\Livewire\Manager\Students as ManagerStudents;
use App\Livewire\Supervisor\Students as SupervisorStudents;
use App\Models\Circle;
use App\Models\Manager;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Teacher;
use App\Services\StudentPlacementService;
use App\Services\StudentStatusService;
use App\Support\StudentStatus;
use Laravel\Ai\Tools\Request;
use Livewire\Livewire;

/**
 * A student's status is one of five words, decided once in StudentStatus. A
 * fifth word, «inactive», used to be written by placement and known to nothing
 * else: lists called him «غادر الدفعات», his file showed the English word, and
 * the status form had no option for where he stood.
 */
beforeEach(function () {
    $this->programme = Stage::factory()->create();
    $this->cohort = Circle::factory()->create(['stage_id' => $this->programme->id]);

    $this->teacher = Teacher::factory()->create();
    $this->teacher->circles()->attach($this->cohort->id);

    $this->student = Student::factory()->create([
        'name' => 'طالب في دفعته',
        'circle_id' => $this->cohort->id,
        'stage_id' => $this->programme->id,
        'status' => 'active',
        'is_approved' => true,
    ]);
});

it('knows five statuses, each with its own word', function () {
    expect(StudentStatus::values())->toBe(['active', 'registering', 'suspended', 'inactive', 'left'])
        ->and(collect(StudentStatus::cases())->map->label()->unique())->toHaveCount(5)
        ->and(StudentStatus::labelOf('inactive'))->toBe('غير فعّال')
        ->and(StudentStatus::labelOf('left'))->toBe('غادر الأكاديمية')
        ->and(StudentStatus::labelOf(null))->toBe('مشارك');
});

it('calls a student taken out of his cohort «غير فعّال» in the manager\'s list', function () {
    StudentPlacementService::deactivate($this->student);

    expect($this->student->refresh()->status)->toBe('inactive');

    $this->actingAs(Manager::factory()->create(), 'manager');

    Livewire::test(ManagerStudents::class)
        ->assertSee('غير فعّال')
        ->assertDontSee('غادر الدفعات');
});

it('calls him «غير فعّال» in the supervisor\'s list too', function () {
    StudentPlacementService::deactivate($this->student);

    $supervisor = Supervisor::factory()->create();
    $supervisor->stages()->attach($this->programme->id);
    $this->actingAs($supervisor, 'supervisor');

    Livewire::test(SupervisorStudents::class)
        ->assertSee('غير فعّال')
        ->assertDontSee('غادر الدفعات');
});

it('offers every status in the status form, and takes «غير فعّال»', function () {
    $this->actingAs(Manager::factory()->create(), 'manager');

    Livewire::test('shared.⚡student-status-manager')
        ->call('open', $this->student->id)
        ->assertSee(collect(StudentStatus::cases())->map->label()->all())
        ->set('newStatus', 'inactive')
        ->set('reason', 'انتقل إلى مدينة أخرى')
        ->call('saveStatus')
        ->assertHasNoErrors();

    expect($this->student->refresh()->status)->toBe('inactive');
});

it('refuses a status outside the five, in the form and in the service', function () {
    $this->actingAs(Manager::factory()->create(), 'manager');

    Livewire::test('shared.⚡student-status-manager')
        ->call('open', $this->student->id)
        ->set('newStatus', 'graduated')
        ->set('reason', 'تخرّج من البرنامج')
        ->call('saveStatus')
        ->assertHasErrors('newStatus');

    expect(fn () => StudentStatusService::changeStatus($this->student, 'graduated'))
        ->toThrow(InvalidArgumentException::class);

    expect($this->student->refresh()->status)->toBe('active');
});

it('lets the supervisor set «غير فعّال» on many students at once', function () {
    $supervisor = Supervisor::factory()->create();
    $supervisor->stages()->attach($this->programme->id);
    $this->actingAs($supervisor, 'supervisor');

    Livewire::test(SupervisorStudents::class)
        ->set('selectedStudentIds', [(string) $this->student->id])
        ->set('bulkStatus', 'inactive')
        ->call('applyBulkStatus')
        ->assertHasNoErrors();

    expect($this->student->refresh()->status)->toBe('inactive');
});

it('shows a teacher who may not change it the status\'s word, not its key', function () {
    $this->student->update(['status' => 'suspended']);

    Livewire::actingAs($this->teacher, 'teacher')
        ->test('teacher.student-manager')
        ->call('viewStudent', $this->student->id)
        ->assertSee('موقوف')
        ->assertDontSee('>suspended<', false);
});

it('finds students for the assistant by the Arabic word of their status', function () {
    StudentPlacementService::deactivate($this->student);

    $this->actingAs(Manager::factory()->create(), 'manager');

    $result = (string) (new getPeopleDirectory)->handle(new Request(['role' => 'student', 'status' => 'غير فعّال']));

    expect($result)->toContain('طالب في دفعته');
});
