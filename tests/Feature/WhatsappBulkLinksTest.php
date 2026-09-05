<?php

use App\Jobs\SendGuardianWhatsappJob;
use App\Livewire\Shared\WhatsappBulkLinks;
use App\Models\Circle;
use App\Models\Guardian;
use App\Models\Manager;
use App\Models\Stage;
use App\Models\Student;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $this->manager = Manager::factory()->create();
    $this->actingAs($this->manager, 'manager');

    $this->stage = Stage::create(['name' => 'المرحلة الأولى']);
    $this->circle = Circle::create(['name' => 'دفعة النور', 'stage_id' => $this->stage->id]);

    $makeGuardian = fn (string $email, ?string $phone) => Guardian::create([
        'name' => "ولي {$email}",
        'email' => $email,
        'password' => bcrypt('password'),
        'phone' => $phone,
        'is_approved' => true,
    ]);

    $makeStudent = fn (string $email, ?string $phone, ?int $guardianId, string $status = 'active') => Student::create([
        'name' => "طالب {$email}",
        'email' => $email,
        'password' => bcrypt('password'),
        'phone' => $phone,
        'guardian_id' => $guardianId,
        'circle_id' => $this->circle->id,
        'status' => $status,
        'is_approved' => true,
    ]);

    $this->guardianShared = $makeGuardian('g1@example.com', '0501111111');
    $this->guardianSecond = $makeGuardian('g2@example.com', '0502222222');
    $this->guardianNoPhone = $makeGuardian('g3@example.com', null);

    // Two siblings under the same guardian — the guardian link must be sent once.
    $this->studentFull = $makeStudent('s1@example.com', '0559999991', $this->guardianShared->id);
    $this->studentSibling = $makeStudent('s1b@example.com', '0559999992', $this->guardianShared->id);
    $this->studentNoPhone = $makeStudent('s2@example.com', null, $this->guardianSecond->id);
    $this->studentNoGuardian = $makeStudent('s3@example.com', '0559999993', null);
    $this->studentSuspended = $makeStudent('s4@example.com', '0559999994', $this->guardianSecond->id, 'suspended');
    $this->studentGuardianNoPhone = $makeStudent('s5@example.com', '0559999995', $this->guardianNoPhone->id);
});

it('reports qualification with reasons for guardian-targeted sending', function () {
    $component = Livewire::test(WhatsappBulkLinks::class, ['clientId' => 'manager_1'])
        ->set('sendType', 'guardian_link_to_guardian')
        ->call('previewReport');

    $report = $component->instance()->buildReport();

    $qualifiedIds = collect($report['qualified'])->pluck('student.id');
    expect($qualifiedIds)->toContain($this->studentFull->id, $this->studentSibling->id, $this->studentNoPhone->id)
        ->and($qualifiedIds)->not->toContain($this->studentNoGuardian->id, $this->studentSuspended->id, $this->studentGuardianNoPhone->id);

    $reasonsByStudent = collect($report['unqualified'])->keyBy(fn ($row) => $row['student']->id);
    expect($reasonsByStudent[$this->studentNoGuardian->id]['reasons'])->toContain('لا يوجد ولي أمر مرتبط بالطالب')
        ->and($reasonsByStudent[$this->studentSuspended->id]['reasons'][0])->toContain('موقوف')
        ->and($reasonsByStudent[$this->studentGuardianNoPhone->id]['reasons'])->toContain('لا يوجد رقم هاتف لولي الأمر');

    // 3 qualified students but only 2 distinct guardians → 2 messages.
    expect($report['messages_count'])->toBe(2);
});

it('reports qualification for student-targeted sending', function () {
    $component = Livewire::test(WhatsappBulkLinks::class, ['clientId' => 'manager_1'])
        ->set('sendType', 'student_link_to_student');

    $report = $component->instance()->buildReport();

    $qualifiedIds = collect($report['qualified'])->pluck('student.id');
    expect($qualifiedIds)->toContain($this->studentFull->id, $this->studentNoGuardian->id, $this->studentGuardianNoPhone->id)
        ->and($qualifiedIds)->not->toContain($this->studentNoPhone->id, $this->studentSuspended->id);

    $reasonsByStudent = collect($report['unqualified'])->keyBy(fn ($row) => $row['student']->id);
    expect($reasonsByStudent[$this->studentNoPhone->id]['reasons'])->toContain('لا يوجد رقم هاتف للطالب')
        ->and($report['messages_count'])->toBe(count($report['qualified']));
});

it('sends the guardian link once per guardian and generates missing tokens', function () {
    Queue::fake();

    Livewire::test(WhatsappBulkLinks::class, ['clientId' => 'manager_1'])
        ->set('sendType', 'guardian_link_to_guardian')
        ->call('previewReport')
        ->call('send');

    Queue::assertPushed(SendGuardianWhatsappJob::class, 2);

    $this->guardianShared->refresh();
    expect($this->guardianShared->access_token)->not->toBeNull();

    Queue::assertPushed(SendGuardianWhatsappJob::class, function (SendGuardianWhatsappJob $job) {
        return $job->phone === '0501111111'
            && str_contains($job->message, route('guardian.magic-link', $this->guardianShared->access_token))
            && str_contains($job->message, 'عدم مشاركته')
            && $job->senderClientId === 'manager_1';
    });
});

it('sends the student link to the guardian phone', function () {
    Queue::fake();

    Livewire::test(WhatsappBulkLinks::class, ['clientId' => 'manager_1'])
        ->set('sendType', 'student_link_to_guardian')
        ->call('send');

    // One message per qualified student, even for siblings of the same guardian.
    Queue::assertPushed(SendGuardianWhatsappJob::class, 3);

    $this->studentFull->refresh();
    Queue::assertPushed(SendGuardianWhatsappJob::class, function (SendGuardianWhatsappJob $job) {
        return $job->phone === '0501111111'
            && str_contains($job->message, route('magic-link', $this->studentFull->access_token))
            && str_contains($job->message, $this->studentFull->name);
    });
});

it('sends the student link to the student phone', function () {
    Queue::fake();

    Livewire::test(WhatsappBulkLinks::class, ['clientId' => 'manager_1'])
        ->set('sendType', 'student_link_to_student')
        ->call('send');

    Queue::assertPushed(SendGuardianWhatsappJob::class, 4);

    $this->studentNoGuardian->refresh();
    Queue::assertPushed(SendGuardianWhatsappJob::class, function (SendGuardianWhatsappJob $job) {
        return $job->phone === '0559999993'
            && str_contains($job->message, route('magic-link', $this->studentNoGuardian->access_token));
    });
});

it('renders the bulk links section on the whatsapp settings page', function () {
    $this->get(route('manager.whatsapp-settings'))
        ->assertOk()
        ->assertSee('إرسال روابط الدخول عبر الواتساب')
        ->assertSee('عرض التقرير قبل الإرسال');
});
