<?php

use App\Jobs\SendGuardianWhatsappJob;
use App\Models\Attendance;
use App\Models\Circle;
use App\Models\Guardian;
use App\Models\GuardianNotification;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Teacher;
use App\Services\GuardianNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon\Carbon::setTestNow('2026-06-10 10:00:00');

    $this->stage = Stage::factory()->create();
    $this->circle = Circle::factory()->create(['stage_id' => $this->stage->id]);
    $this->teacher = Teacher::factory()->create();
    $this->supervisor = Supervisor::factory()->create(['is_approved' => true]);
    $this->supervisor->stages()->attach($this->stage->id);

    $this->guardianWithPhone = Guardian::factory()->create(['is_approved' => true, 'phone' => '0501234567']);
    $this->guardianNoPhone = Guardian::factory()->create(['is_approved' => true, 'phone' => null]);

    $this->linkedStudent = Student::factory()->create([
        'name' => 'طالب مرتبط',
        'guardian_id' => $this->guardianWithPhone->id,
        'circle_id' => $this->circle->id,
    ]);
    $this->noGuardianStudent = Student::factory()->create([
        'name' => 'طالب بلا ولي أمر',
        'guardian_id' => null,
        'circle_id' => $this->circle->id,
    ]);
    $this->noPhoneStudent = Student::factory()->create([
        'name' => 'طالب ولي أمره بلا جوال',
        'guardian_id' => $this->guardianNoPhone->id,
        'circle_id' => $this->circle->id,
    ]);

    foreach ([
        [$this->linkedStudent, 'absent'],
        [$this->noGuardianStudent, 'absent'],
        [$this->noPhoneStudent, 'late'],
    ] as [$student, $status]) {
        Attendance::create([
            'student_id' => $student->id,
            'teacher_id' => $this->teacher->id,
            'circle_id' => $this->circle->id,
            'date' => '2026-06-10',
            'status' => $status,
        ]);
    }

    $this->actingAs($this->supervisor, 'supervisor');
});

it('builds a report separating eligible, unlinked, and phoneless students', function () {
    Livewire::test('supervisor.⚡absence-broadcast')
        ->set('broadcastDate', '2026-06-10')
        ->call('prepareReport')
        ->assertSet('showReportModal', true)
        ->assertSet('eligibleCount', 1)
        ->assertSet('absentCount', 1)
        ->assertSet('lateCount', 0)
        ->assertSet('noGuardianStudents', ['طالب بلا ولي أمر'])
        ->assertSet('noPhoneStudents', ['طالب ولي أمره بلا جوال']);
});

it('opens the report directly when a calendar day is clicked', function () {
    Livewire::test('supervisor.⚡absence-broadcast')
        ->call('selectDate', '2026-06-10', 14, 'ذو الحجة')
        ->assertSet('broadcastDate', '2026-06-10')
        ->assertSet('showReportModal', true)
        ->assertSet('eligibleCount', 1);
});

it('renders the hijri year calendar with circle attendance counts scoped to the supervisor', function () {
    $otherStage = Stage::factory()->create();
    $otherCircle = Circle::factory()->create(['stage_id' => $otherStage->id]);
    $outsideStudent = Student::factory()->create(['circle_id' => $otherCircle->id]);
    Attendance::create([
        'student_id' => $outsideStudent->id,
        'teacher_id' => $this->teacher->id,
        'circle_id' => $otherCircle->id,
        'date' => '2026-06-10',
        'status' => 'present',
    ]);

    $component = Livewire::test('supervisor.⚡absence-broadcast');

    $months = $component->viewData('months');
    expect($months)->toHaveCount(12);

    // Only the supervisor's single circle counts, not the outside circle.
    expect($component->viewData('totalCirclesCount'))->toBe(1);

    $allDays = collect($months)->flatMap(fn ($month) => $month['days'])->filter();
    $targetDay = $allDays->firstWhere('gregorianDate', '2026-06-10');

    expect($targetDay)->not->toBeNull();
    expect($targetDay['completedCount'])->toBe(1);
    expect($targetDay['completionRate'])->toBe(100.0);
});

it('defaults the calendar view to the current hijri month', function () {
    $component = Livewire::test('supervisor.⚡absence-broadcast');

    $cal = IntlCalendar::createInstance('Asia/Riyadh', 'ar_SA@calendar=islamic-umalqura');
    $cal->setTime(now('Asia/Riyadh')->getTimestampMs());

    $component->assertSet('currentMonthIndex', $cal->get(IntlCalendar::FIELD_MONTH));
});

it('does not open the report when the date has no absences', function () {
    Livewire::test('supervisor.⚡absence-broadcast')
        ->set('broadcastDate', '2026-06-11')
        ->call('prepareReport')
        ->assertSet('showReportModal', false);
});

it('queues WhatsApp messages only for eligible students on broadcast', function () {
    Queue::fake();
    Http::fake(['*/status/*' => Http::response(['status' => 'ready'])]);

    Livewire::test('supervisor.⚡absence-broadcast')
        ->set('broadcastDate', '2026-06-10')
        ->call('sendBroadcast');

    Queue::assertPushed(SendGuardianWhatsappJob::class, 1);
    Queue::assertPushed(SendGuardianWhatsappJob::class, function ($job) {
        return $job->senderClientId === 'supervisor_'.$this->supervisor->id
            && str_contains($job->message, 'طالب مرتبط');
    });

    expect(GuardianNotification::where('student_id', $this->linkedStudent->id)->count())->toBe(1);
});

it('still sends WhatsApp for students whose in-app notification already exists without duplicating it', function () {
    GuardianNotificationService::notifyAbsence($this->linkedStudent, 'absent', '2026-06-10');
    expect(GuardianNotification::where('student_id', $this->linkedStudent->id)->count())->toBe(1);

    Queue::fake();
    Http::fake(['*/status/*' => Http::response(['status' => 'ready'])]);

    Livewire::test('supervisor.⚡absence-broadcast')
        ->set('broadcastDate', '2026-06-10')
        ->call('sendBroadcast');

    Queue::assertPushed(SendGuardianWhatsappJob::class, 1);
    expect(GuardianNotification::where('student_id', $this->linkedStudent->id)->count())->toBe(1);
});

it('aborts the broadcast when the supervisor WhatsApp session is not ready', function () {
    Queue::fake();
    Http::fake(['*/status/*' => Http::response(['status' => 'needs_scan'])]);

    Livewire::test('supervisor.⚡absence-broadcast')
        ->set('broadcastDate', '2026-06-10')
        ->call('sendBroadcast');

    Queue::assertNothingPushed();
});

it('ignores absences from circles outside the supervisor stages', function () {
    $otherStage = Stage::factory()->create();
    $otherCircle = Circle::factory()->create(['stage_id' => $otherStage->id]);
    $otherGuardian = Guardian::factory()->create(['is_approved' => true, 'phone' => '0509999999']);
    $otherStudent = Student::factory()->create([
        'guardian_id' => $otherGuardian->id,
        'circle_id' => $otherCircle->id,
    ]);
    Attendance::create([
        'student_id' => $otherStudent->id,
        'teacher_id' => $this->teacher->id,
        'circle_id' => $otherCircle->id,
        'date' => '2026-06-10',
        'status' => 'absent',
    ]);

    Queue::fake();
    Http::fake(['*/status/*' => Http::response(['status' => 'ready'])]);

    Livewire::test('supervisor.⚡absence-broadcast')
        ->set('broadcastDate', '2026-06-10')
        ->call('sendBroadcast');

    Queue::assertPushed(SendGuardianWhatsappJob::class, 1);
    Queue::assertNotPushed(SendGuardianWhatsappJob::class, function ($job) {
        return $job->phone === '966509999999';
    });
});
