<?php

use App\Livewire\Supervisor\Students as SupervisorStudents;
use App\Models\Circle;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentPlacementRequest;
use App\Models\Supervisor;
use App\Models\Teacher;
use App\Models\UserRole;
use App\Services\NotificationService;
use App\Services\StudentPlacementService;
use App\Support\Scope;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

/**
 * A sheikh places students with one press.
 *
 * Who asked for his group waits at the top of his students page; he accepts
 * or turns each down there. Below them, folded, the students who have asked
 * for no group at all — the ones nobody could see before — and he can take
 * any of them into his group.
 */
beforeEach(function () {
    $programme = Stage::factory()->create(['name' => 'تأصيل']);
    $this->own = Circle::factory()->create(['stage_id' => $programme->id, 'name' => 'الأهدل']);
    $this->other = Circle::factory()->create(['stage_id' => $programme->id, 'name' => 'فلمبان']);

    $this->sheikh = Supervisor::factory()->create(['name' => 'عبدالرحمن الأهدل']);
    $this->sheikh->stages()->attach($programme->id);
    $this->sheikh->roles()->where('role', 'supervisor')->update(['scope_type' => UserRole::SCOPE_CIRCLES, 'scope_ids' => [$this->own->id]]);
    $this->sheikh->load('roles');

    $this->asker = Student::factory()->create(['name' => 'أنس الشريف', 'circle_id' => null, 'stage_id' => null, 'status' => 'registering', 'is_approved' => true]);
    $this->askerElsewhere = Student::factory()->create(['name' => 'عمر باعشن', 'circle_id' => null, 'stage_id' => null, 'status' => 'registering', 'is_approved' => true]);
    $this->unchosen = Student::factory()->create(['name' => 'زياد المالكي', 'circle_id' => null, 'stage_id' => null, 'status' => 'registering', 'is_approved' => true]);
    $this->awaitingApproval = Student::factory()->create(['name' => 'حساب ينتظر الموافقة', 'circle_id' => null, 'stage_id' => null, 'status' => 'registering', 'is_approved' => false]);

    $this->ask = StudentPlacementService::askToJoin($this->asker, $this->own);
    StudentPlacementService::askToJoin($this->askerElsewhere, $this->other);

    Scope::forget();
    $this->actingAs($this->sheikh, 'supervisor');
});

it('shows who asked for his group, and who asked for none', function () {
    Livewire::test(SupervisorStudents::class)
        ->assertSee('ينتظرون التسكين')
        ->assertSee('أنس الشريف')
        ->assertSee('لم يختاروا مجموعة بعد')
        ->assertSee('زياد المالكي')
        ->assertDontSee('عمر باعشن')
        ->assertDontSee('حساب ينتظر الموافقة');
});

it('accepts a student into his group with one press', function () {
    Livewire::test(SupervisorStudents::class)
        ->call('acceptAsk', $this->ask->id)
        ->assertHasNoErrors();

    $student = $this->asker->fresh();

    expect([$student->circle_id, $student->status])->toBe([$this->own->id, 'active'])
        ->and($this->ask->fresh()->status)->toBe(StudentPlacementRequest::APPROVED)
        ->and(NotificationService::unreadCountFor('student', $this->asker->id))->toBe(1);
});

it('turns an ask down so the student chooses again', function () {
    Livewire::test(SupervisorStudents::class)->call('declineAsk', $this->ask->id);

    expect($this->ask->fresh()->status)->toBe(StudentPlacementRequest::REJECTED)
        ->and($this->asker->fresh()->circle_id)->toBeNull();
});

it('answers no ask made for another sheikh\'s group', function () {
    $elsewhere = StudentPlacementRequest::where('student_id', $this->askerElsewhere->id)->sole();

    expect(fn () => Livewire::test(SupervisorStudents::class)->call('acceptAsk', $elsewhere->id))
        ->toThrow(ModelNotFoundException::class);

    expect($this->askerElsewhere->fresh()->circle_id)->toBeNull();
});

it('takes a student who asked for nothing into his group, and tells him', function () {
    Livewire::test(SupervisorStudents::class)->call('takeIntoGroup', $this->unchosen->id);

    $student = $this->unchosen->fresh();

    expect([$student->circle_id, $student->status])->toBe([$this->own->id, 'active'])
        ->and($student->statusHistories()->latest('id')->value('notes'))->toContain('عبدالرحمن الأهدل')
        ->and(NotificationService::unreadCountFor('student', $this->unchosen->id))->toBe(1);
});

it('takes in nobody already placed, asking elsewhere, or not yet approved', function (string $who) {
    $student = match ($who) {
        'placed' => Student::factory()->create(['circle_id' => $this->other->id, 'is_approved' => true]),
        'asking' => $this->askerElsewhere,
        'unapproved' => $this->awaitingApproval,
    };

    expect(fn () => Livewire::test(SupervisorStudents::class)->call('takeIntoGroup', $student->id))
        ->toThrow(ModelNotFoundException::class);
})->with(['placed', 'asking', 'unapproved']);

it('places only into a group of his own', function () {
    expect(fn () => Livewire::test(SupervisorStudents::class)
        ->set('groupFor', [$this->unchosen->id => $this->other->id])
        ->call('takeIntoGroup', $this->unchosen->id))
        ->toThrow(ModelNotFoundException::class);

    expect($this->unchosen->fresh()->circle_id)->toBeNull();
});

it('names the teacher who suggested a student', function () {
    $teacher = Teacher::factory()->create(['name' => 'المعلم خالد']);
    $teacher->circles()->attach($this->own->id);
    StudentPlacementService::request($this->unchosen, $this->own, $teacher);

    Livewire::test(SupervisorStudents::class)->assertSee('اقترحه المعلم خالد');
});

it('heads his students page with his own group\'s name', function () {
    Livewire::test(SupervisorStudents::class)->assertSee('مجموعة الأهدل');
});
