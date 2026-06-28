<?php

use App\Livewire\Supervisor\FormResponses;
use App\Livewire\Supervisor\Students as SupervisorStudents;
use App\Models\Circle;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->stage = Stage::create(['name' => 'المرحلة الابتدائية']);
    $this->otherStage = Stage::create(['name' => 'المرحلة المتوسطة']);
    $this->circle = Circle::create(['name' => 'حلقة الفجر', 'stage_id' => $this->stage->id]);

    $this->supervisor = Supervisor::factory()->create();
    $this->supervisor->stages()->attach([$this->stage->id, $this->otherStage->id]);

    $this->form = Form::create([
        'supervisor_id' => $this->supervisor->id,
        'title' => 'استمارة التسجيل',
        'slug' => 'reg-form',
        'color' => '#14b8a6',
        'fields' => [
            ['id' => 'f_name', 'type' => 'text', 'label' => 'الاسم الكامل', 'is_student_name' => true],
            ['id' => 'f_email', 'type' => 'text', 'label' => 'البريد', 'is_student_username' => true],
            ['id' => 'f_phone', 'type' => 'text', 'label' => 'رقم الجوال'],
            ['id' => 'f_birth', 'type' => 'date', 'label' => 'تاريخ الميلاد'],
            ['id' => 'f_nat', 'type' => 'text', 'label' => 'الجنسية'],
            ['id' => 'f_id', 'type' => 'text', 'label' => 'رقم الهوية'],
        ],
    ]);

    $this->actingAs($this->supervisor, 'supervisor');
});

it('maps all guessed fields from a response into the new student account', function () {
    $response = FormResponse::create([
        'form_id' => $this->form->id,
        'answers' => [
            'f_name' => 'عمر فاروق',
            'f_email' => 'omar',
            'f_phone' => '050-123-4567',
            'f_birth' => '2010-03-15',
            'f_nat' => 'سعودي',
            'f_id' => '1122334455',
        ],
    ]);

    Livewire::test(FormResponses::class, ['formId' => $this->form->id])
        ->call('openCreateModal', $response->id)
        ->assertSet('newStudentName', 'عمر فاروق')
        ->assertSet('newStudentEmail', 'omar')
        ->assertSet('newStudentPhone', '0501234567') // normalized (digits only)
        ->assertSet('newStudentBirthDate', '2010-03-15')
        ->assertSet('newStudentNationality', 'سعودي')
        ->assertSet('newStudentNationalId', '1122334455')
        ->call('createStudentAccount')
        ->assertHasNoErrors();

    $student = Student::where('name', 'عمر فاروق')->first();
    expect($student)->not->toBeNull();
    expect($student->email)->toBe('omar@altag-student.com');
    expect($student->phone)->toBe('0501234567');
    expect($student->birth_date->toDateString())->toBe('2010-03-15');
    expect($student->nationality)->toBe('سعودي');
    expect($student->national_id)->toBe('1122334455');
    expect($student->status)->toBe('registering');
});

it('generates a random email when the random toggle is on', function () {
    $response = FormResponse::create([
        'form_id' => $this->form->id,
        'answers' => ['f_name' => 'طالب بلا بريد'],
    ]);

    Livewire::test(FormResponses::class, ['formId' => $this->form->id])
        ->call('openCreateModal', $response->id)
        ->set('newStudentRandomEmail', true)
        ->call('createStudentAccount')
        ->assertHasNoErrors();

    $student = Student::where('name', 'طالب بلا بريد')->first();
    expect($student->email)->toStartWith('std_');
    expect($student->email)->toEndWith('@altag-student.com');
});

it('creates a student in a stage without a circle and resolves the effective stage', function () {
    $response = FormResponse::create([
        'form_id' => $this->form->id,
        'answers' => ['f_name' => 'طالب بلا حلقة', 'f_email' => 'noc'],
    ]);

    Livewire::test(FormResponses::class, ['formId' => $this->form->id])
        ->call('openCreateModal', $response->id)
        ->set('targetStageId', $this->stage->id)
        ->call('createStudentAccount')
        ->assertHasNoErrors();

    $student = Student::where('name', 'طالب بلا حلقة')->first();
    expect($student->circle_id)->toBeNull();
    expect($student->stage_id)->toBe($this->stage->id);
    expect($student->effective_stage_id)->toBe($this->stage->id);
});

it('lets the circle win over a directly chosen stage (no divergence)', function () {
    $response = FormResponse::create([
        'form_id' => $this->form->id,
        'answers' => ['f_name' => 'طالب بحلقة', 'f_email' => 'wc'],
    ]);

    Livewire::test(FormResponses::class, ['formId' => $this->form->id])
        ->call('openCreateModal', $response->id)
        ->set('targetCircleId', $this->circle->id)
        ->set('targetStageId', $this->otherStage->id) // should be ignored in favor of the circle
        ->call('createStudentAccount')
        ->assertHasNoErrors();

    $student = Student::where('name', 'طالب بحلقة')->first();
    expect($student->circle_id)->toBe($this->circle->id);
    expect($student->stage_id)->toBeNull();
    expect($student->fresh()->effective_stage_id)->toBe($this->stage->id); // the circle's stage
});

it('applies one shared password to all bulk-created accounts', function () {
    FormResponse::create(['form_id' => $this->form->id, 'answers' => ['f_name' => 'أحمد', 'f_email' => 'ahmad']]);
    FormResponse::create(['form_id' => $this->form->id, 'answers' => ['f_name' => 'سالم', 'f_email' => 'salim']]);

    Livewire::test(FormResponses::class, ['formId' => $this->form->id])
        ->call('openBulkModal')
        ->set('bulkPassword', 'shared-secret')
        ->call('analyzeBulk')
        ->call('createReadyStudents')
        ->assertHasNoErrors();

    $ahmad = Student::where('name', 'أحمد')->first();
    $salim = Student::where('name', 'سالم')->first();
    expect(Hash::check('shared-secret', $ahmad->password))->toBeTrue();
    expect(Hash::check('shared-secret', $salim->password))->toBeTrue();
});

it('sorts bulk responses into ready and needs-review, and creates after manual review', function () {
    $valid = FormResponse::create(['form_id' => $this->form->id, 'answers' => ['f_name' => 'صالح', 'f_email' => 'saleh']]);
    $noName = FormResponse::create(['form_id' => $this->form->id, 'answers' => ['f_email' => 'noname']]);
    $badDate = FormResponse::create(['form_id' => $this->form->id, 'answers' => ['f_name' => 'تاريخ خاطئ', 'f_birth' => 'ليس تاريخًا']]);

    $component = Livewire::test(FormResponses::class, ['formId' => $this->form->id])
        ->call('openBulkModal')
        ->call('analyzeBulk');

    expect($component->get('bulkReady'))->toHaveCount(1);
    expect($component->get('bulkNeedsReview'))->toHaveCount(2);

    // Create only the ready one first.
    $component->call('createReadyStudents');
    expect(Student::where('name', 'صالح')->exists())->toBeTrue();
    expect($noName->fresh()->student_id)->toBeNull();

    // Fix the missing name and create that reviewed response.
    $component->set("reviewEdits.{$noName->id}.name", 'اسم مُصحّح')
        ->call('createReviewedStudent', $noName->id)
        ->assertHasNoErrors();

    expect(Student::where('name', 'اسم مُصحّح')->exists())->toBeTrue();
    expect($noName->fresh()->student_id)->not->toBeNull();
});

it('shows circle-less stage-assigned students in the supervisor students list', function () {
    $student = Student::create([
        'name' => 'طالب مرحلة فقط',
        'email' => 'stageonly@altag-student.com',
        'password' => bcrypt('password'),
        'circle_id' => null,
        'stage_id' => $this->stage->id,
        'status' => 'registering',
        'is_approved' => false,
    ]);

    $listed = Livewire::test(SupervisorStudents::class)->get('students');

    expect($listed->pluck('id'))->toContain($student->id);
});
