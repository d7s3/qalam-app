<?php

use App\Models\Circle;
use App\Models\Manager;
use App\Models\Stage;
use App\Models\Supervisor;
use App\Models\Teacher;
use App\Support\Access;
use App\Support\RecitationOnlyTeacher;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A حلقة and a دفعة sit at the same level and differ in that the حلقة's content
 * is Quranic. Its teacher is there for the memorisation and the review, and
 * sees those of his student and nothing else.
 */
beforeEach(function () {
    $this->programme = Stage::factory()->create();

    $this->halaqah = Circle::factory()->create(['stage_id' => $this->programme->id, 'is_quranic' => true]);
    $this->cohort = Circle::factory()->create(['stage_id' => $this->programme->id, 'is_quranic' => false]);

    RecitationOnlyTeacher::forget();
});

it('narrows nobody until somebody says so', function () {
    // `is_quranic` defaults to true, so every circle in the academy is Quranic
    // today. Inferring the designation would have narrowed every teacher at
    // once, which is why it is declared.
    $quranic = Teacher::factory()->create();
    $quranic->circles()->attach($this->halaqah->id);

    expect(RecitationOnlyTeacher::applies($quranic))->toBeFalse();

    $quranic->update(['is_recitation_only' => true]);
    RecitationOnlyTeacher::forget($quranic->id);

    expect(RecitationOnlyTeacher::applies($quranic))->toBeTrue();
});

it('offers the reading his circles give, without acting on it', function () {
    $quranic = Teacher::factory()->create();
    $quranic->circles()->attach($this->halaqah->id);

    $both = Teacher::factory()->create();
    $both->circles()->attach([$this->halaqah->id, $this->cohort->id]);

    expect(RecitationOnlyTeacher::suggests($quranic))->toBeTrue();

    // He needs the wider view for his other cohort.
    expect(RecitationOnlyTeacher::suggests($both))->toBeFalse();

    // And nothing has been decided about a teacher with no cohort yet.
    expect(RecitationOnlyTeacher::suggests(Teacher::factory()->create()))->toBeFalse();

    // None of it narrows anybody by itself.
    expect(RecitationOnlyTeacher::applies($quranic))->toBeFalse();
});

it('keeps the memorisation pages open to him', function () {
    $quranic = Teacher::factory()->create(['is_recitation_only' => true]);
    $quranic->circles()->attach($this->halaqah->id);

    foreach (['teacher.tasmeeh', 'teacher.student-plans', 'teacher.plan-creator', 'teacher.attendance'] as $page) {
        expect(Access::canSee($quranic, 'teacher', $page))->toBeTrue();
    }
});

it('withholds his student work beyond the memorisation', function () {
    $quranic = Teacher::factory()->create(['is_recitation_only' => true]);
    $quranic->circles()->attach($this->halaqah->id);

    foreach (RecitationOnlyTeacher::WITHHELD as $page) {
        expect(Access::canSee($quranic, 'teacher', $page))->toBeFalse();
    }

    // And the teacher of an ordinary cohort keeps every one of them.
    $general = Teacher::factory()->create();
    $general->circles()->attach($this->cohort->id);

    expect(Access::canSee($general, 'teacher', 'teacher.self-program'))->toBeTrue();
});

it('is a narrowing his own role cannot undo', function () {
    $quranic = Teacher::factory()->create(['is_recitation_only' => true]);
    $quranic->circles()->attach($this->halaqah->id);

    // Granting the page to teachers centrally does not hand it back to him;
    // this is asked before the grants are.
    expect(Access::canSee($quranic, 'teacher', 'teacher.self-program'))->toBeFalse();
});

it('leaves the super administrator above it', function () {
    $admin = Manager::factory()->create(['is_super_admin' => true, 'is_recitation_only' => true]);

    RecitationOnlyTeacher::forget($admin->id);

    expect(Access::canSee($admin, 'teacher', 'teacher.self-program'))->toBeTrue();
});

it('is designated from the screen where teachers are managed', function () {
    $supervisor = Supervisor::factory()->create();
    $supervisor->stages()->attach($this->programme->id);

    $teacher = Teacher::factory()->create();
    $teacher->circles()->attach($this->halaqah->id);

    Livewire\Livewire::actingAs($supervisor, 'supervisor')
        ->test('supervisor.teachers')
        ->call('toggleRecitationOnly', $teacher->id);

    expect($teacher->fresh()->is_recitation_only)->toBeTrue();
    expect(Access::canSee($teacher->fresh(), 'teacher', 'teacher.self-program'))->toBeFalse();

    // And back again, without anything else about him changing.
    Livewire\Livewire::actingAs($supervisor, 'supervisor')
        ->test('supervisor.teachers')
        ->call('toggleRecitationOnly', $teacher->id);

    RecitationOnlyTeacher::forget($teacher->id);

    expect($teacher->fresh()->is_recitation_only)->toBeFalse();
    expect(Access::canSee($teacher->fresh(), 'teacher', 'teacher.self-program'))->toBeTrue();
});
