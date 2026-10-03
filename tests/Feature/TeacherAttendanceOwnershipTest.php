<?php

use App\Livewire\Teacher\Attendance;
use App\Models\Attendance as AttendanceModel;
use App\Models\Circle;
use App\Models\Guardian;
use App\Models\GuardianNotification;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * A teacher takes the register of his own cohorts. A student id or a cohort
 * sent from the browser for someone else's must write nothing — and above all
 * must not send another family an absence notice.
 */
beforeEach(function () {
    Carbon\Carbon::setTestNow('2026-09-10 08:00:00');

    $stage = Stage::factory()->create();
    $this->circle = Circle::factory()->create(['stage_id' => $stage->id]);
    $this->otherCircle = Circle::factory()->create(['stage_id' => $stage->id]);

    $this->teacher = Teacher::factory()->create();
    $this->teacher->circles()->attach($this->circle->id);

    $this->student = Student::factory()->create(['circle_id' => $this->circle->id, 'status' => 'active', 'is_approved' => true]);
    $this->stranger = Student::factory()->create([
        'circle_id' => $this->otherCircle->id,
        'status' => 'active',
        'is_approved' => true,
        'guardian_id' => Guardian::factory()->create()->id,
    ]);

    $this->actingAs($this->teacher, 'teacher');
});

it('marks only a student of the cohort on screen', function (string $action) {
    Livewire::test(Attendance::class)
        ->set('selectedCircle', $this->circle->id)
        ->call($action, $this->stranger->id, 'absent')
        ->assertForbidden();

    expect(AttendanceModel::where('student_id', $this->stranger->id)->exists())->toBeFalse()
        ->and(GuardianNotification::where('student_id', $this->stranger->id)->exists())->toBeFalse();
})->with(['markStatus', 'updateStatus']);

it('still marks his own student', function () {
    Livewire::test(Attendance::class)
        ->set('selectedCircle', $this->circle->id)
        ->call('markStatus', $this->student->id, 'present');

    expect(AttendanceModel::where('student_id', $this->student->id)->value('status'))->toBe('present');
});

it('does not open, fill or clear another cohort\'s register', function () {
    AttendanceModel::create([
        'student_id' => $this->stranger->id,
        'circle_id' => $this->otherCircle->id,
        'date' => '2026-09-10',
        'status' => 'present',
    ]);

    Livewire::test(Attendance::class)
        ->set('selectedCircle', $this->otherCircle->id)
        ->assertSet('selectedCircle', null)
        ->call('clearDayAttendance')
        ->call('markAllPresent');

    expect(AttendanceModel::where('circle_id', $this->otherCircle->id)->count())->toBe(1);
});

it('cannot be handed a different list of cohorts from the browser', function () {
    Livewire::test(Attendance::class)
        ->set('circles', [['id' => $this->otherCircle->id]]);
})->throws(Exception::class);

it('gives each mark a thumb-sized target on a phone, and writes the family\'s number for WhatsApp', function () {
    $this->student->update(['guardian_id' => Guardian::factory()->create(['phone' => '0501234567'])->id]);

    AttendanceModel::create([
        'student_id' => $this->student->id,
        'circle_id' => $this->circle->id,
        'date' => '2026-09-10',
        'status' => 'absent',
    ]);

    Livewire::test(Attendance::class)
        ->set('selectedCircle', $this->circle->id)
        ->assertSeeHtml('min-h-11')
        ->assertSeeHtml('https://wa.me/966501234567/');
});

it('writes a student\'s number for WhatsApp on the students screen', function () {
    $this->student->update(['phone' => '0551234567']);

    Livewire::test('teacher.student-manager')
        ->assertSeeHtml('https://wa.me/966551234567');
});

it('keeps the students list within a phone\'s width and asks for a phone with the phone keypad', function () {
    Livewire::test('teacher.student-manager')
        ->assertSeeHtml('hidden sm:table-cell')
        ->assertSeeHtml('inputmode="tel"');
});

it('opens on the cohort the teacher last took the register of', function () {
    $secondCircle = Circle::factory()->create(['stage_id' => $this->circle->stage_id]);
    $this->teacher->circles()->attach($secondCircle->id);

    Livewire::test(Attendance::class)->set('selectedCircle', $secondCircle->id);

    Livewire::test(Attendance::class)->assertSet('selectedCircle', $secondCircle->id);
});

it('forgets a remembered cohort the teacher no longer teaches', function () {
    $secondCircle = Circle::factory()->create(['stage_id' => $this->circle->stage_id]);
    $this->teacher->circles()->attach($secondCircle->id);

    Livewire::test(Attendance::class)->set('selectedCircle', $secondCircle->id);

    $this->teacher->circles()->detach($secondCircle->id);

    Livewire::test(Attendance::class)->assertSet('selectedCircle', $this->circle->id);
});
