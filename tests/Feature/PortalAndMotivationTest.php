<?php

use App\Models\Manager;
use App\Models\Motivation;
use App\Models\PortalMessage;
use App\Models\PortalMessageRead;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Teacher;
use App\Services\PortalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * A word announced downward, and something worth meeting on opening.
 */
beforeEach(function () {
    $this->manager = Manager::factory()->create(['name' => 'المدير']);
    $this->supervisor = Supervisor::factory()->create();
    $this->teacher = Teacher::factory()->create();
    $this->student = Student::factory()->create();
});

it('lets an office address its own level and beneath, never above', function () {
    expect(PortalService::canAddress('manager'))->toContain('manager', 'supervisor', 'teacher');
    expect(PortalService::canAddress('teacher'))->toBe(['teacher']);

    expect(PortalService::mayAddress('supervisor', 'teacher'))->toBeTrue();
    expect(PortalService::mayAddress('supervisor', 'supervisor'))->toBeTrue();
    expect(PortalService::mayAddress('teacher', 'manager'))->toBeFalse();
});

it('drops an audience the sender may not reach', function () {
    $message = PortalService::announce($this->teacher, 'teacher', 'كلمة', ['manager', 'teacher']);

    expect($message->audiences()->pluck('role_key')->all())->toBe(['teacher']);
});

it('refuses a message addressed only upward', function () {
    expect(PortalService::announce($this->teacher, 'teacher', 'كلمة', ['manager']))->toBeNull();
    expect(PortalMessage::count())->toBe(0);
});

it('waits for its reader, and does not come back once read', function () {
    $message = PortalService::announce($this->manager, 'manager', 'ذكّروا طلابكم بالمراجعة', ['teacher']);

    expect(PortalService::waitingFor($this->teacher, 'teacher'))->toHaveCount(1);

    PortalService::markRead($message, $this->teacher);

    expect(PortalService::waitingFor($this->teacher, 'teacher'))->toHaveCount(0);
    expect(PortalMessageRead::count())->toBe(1);
});

it('does not announce a man to himself', function () {
    PortalService::announce($this->manager, 'manager', 'كلمة', ['manager']);

    expect(PortalService::waitingFor($this->manager, 'manager'))->toHaveCount(0);
});

it('keeps the sender name off when he asked', function () {
    $named = PortalService::announce($this->manager, 'manager', 'كلمة', ['teacher'], showSender: true);
    $unnamed = PortalService::announce($this->manager, 'manager', 'أخرى', ['teacher'], showSender: false);

    expect($named->attribution())->toBe('المدير');
    expect($unnamed->attribution())->toBeNull();
});

it('draws nothing that nobody approved', function () {
    Motivation::create(['kind' => 'athar', 'text' => 'قول لم يُراجع', 'status' => 'pending']);

    expect(PortalService::motivationFor())->toBeNull();
});

it('never draws a hadith without a grading the academy accepts', function () {
    // Approved by somebody, and still not shown: the grading is a second lock
    // on the same door, because a review can be given in haste.
    Motivation::create([
        'kind' => 'hadith', 'text' => 'حديث', 'grade' => 'ضعيف', 'status' => 'approved',
    ]);

    expect(PortalService::motivationFor())->toBeNull();

    Motivation::create([
        'kind' => 'hadith', 'text' => 'حديث آخر', 'grade' => 'حسن', 'status' => 'approved',
    ]);

    expect(PortalService::motivationFor()?->text)->toBe('حديث آخر');
});

it('lets a student contribute, waiting on somebody senior', function () {
    Livewire::actingAs($this->student, 'student')
        ->test('shared.motivations')
        ->set('asRole', 'student')
        ->set('kind', 'athar')
        ->set('text', 'قال ابن القيم...')
        ->set('source', 'مدارج السالكين')
        ->call('contribute')
        ->assertHasNoErrors();

    $one = Motivation::firstOrFail();

    expect($one->status)->toBe('pending');
    expect($one->contributed_by)->toBe($this->student->id);

    // And a student cannot pass his own contribution.
    Livewire::actingAs($this->student, 'student')
        ->test('shared.motivations')
        ->set('asRole', 'student')
        ->call('approve', $one->id)
        ->assertStatus(403);
});

it('lets an office that carries another approve', function () {
    $one = Motivation::create(['kind' => 'athar', 'text' => 'أثر', 'status' => 'pending']);

    Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('shared.motivations')
        ->set('asRole', 'supervisor')
        ->call('approve', $one->id);

    expect($one->fresh()->status)->toBe('approved');
    expect($one->fresh()->reviewed_by)->toBe($this->supervisor->id);
});

it('refuses to approve a hadith graded as anything else', function () {
    $one = Motivation::create(['kind' => 'hadith', 'text' => 'حديث', 'grade' => 'ضعيف', 'status' => 'pending']);

    Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('shared.motivations')
        ->set('asRole', 'supervisor')
        ->call('approve', $one->id)
        ->assertStatus(422);

    expect($one->fresh()->status)->toBe('pending');
});
