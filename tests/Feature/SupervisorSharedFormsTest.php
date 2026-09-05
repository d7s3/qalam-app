<?php

use App\Livewire\Supervisor\FormBuilder;
use App\Livewire\Supervisor\FormResponses;
use App\Livewire\Supervisor\ManageForms;
use App\Models\Circle;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->stageA = Stage::create(['name' => 'مرحلة المالك']);
    $this->circleA = Circle::create(['name' => 'دفعة المالك', 'stage_id' => $this->stageA->id]);
    $this->owner = Supervisor::factory()->create();
    $this->owner->stages()->attach($this->stageA->id);

    $this->stageB = Stage::create(['name' => 'مرحلة الآخر']);
    $this->circleB = Circle::create(['name' => 'دفعة الآخر', 'stage_id' => $this->stageB->id]);
    $this->other = Supervisor::factory()->create();
    $this->other->stages()->attach($this->stageB->id);

    $fields = [
        ['id' => 'f_name', 'type' => 'text', 'label' => 'الاسم', 'is_student_name' => true],
        ['id' => 'f_email', 'type' => 'text', 'label' => 'البريد', 'is_student_username' => true],
    ];

    $this->sharedForm = Form::create([
        'supervisor_id' => $this->owner->id,
        'title' => 'نموذج مشترك',
        'slug' => 'shared-form',
        'color' => '#14b8a6',
        'is_supervisor_shared' => true,
        'fields' => $fields,
    ]);

    $this->privateForm = Form::create([
        'supervisor_id' => $this->owner->id,
        'title' => 'نموذج خاص',
        'slug' => 'private-form',
        'color' => '#14b8a6',
        'is_supervisor_shared' => false,
        'fields' => $fields,
    ]);
});

it('lets another supervisor open the responses of a shared form', function () {
    $this->actingAs($this->other, 'supervisor');

    Livewire::test(FormResponses::class, ['formId' => $this->sharedForm->id])->assertOk();
});

it('blocks another supervisor from a private form', function () {
    $this->actingAs($this->other, 'supervisor');

    expect(fn () => Livewire::test(FormResponses::class, ['formId' => $this->privateForm->id]))
        ->toThrow(ModelNotFoundException::class);
});

it('lets another supervisor create accounts from a shared form into their own circle', function () {
    $response = FormResponse::create(['form_id' => $this->sharedForm->id, 'answers' => ['f_name' => 'طالب الآخر', 'f_email' => 'other1']]);

    $this->actingAs($this->other, 'supervisor');

    Livewire::test(FormResponses::class, ['formId' => $this->sharedForm->id])
        ->call('openCreateModal', $response->id)
        ->set('targetCircleId', $this->circleB->id)
        ->call('createStudentAccount')
        ->assertHasNoErrors();

    $student = Student::where('name', 'طالب الآخر')->first();
    expect($student)->not->toBeNull();
    expect($student->circle_id)->toBe($this->circleB->id);
});

it('forbids another supervisor from targeting the owner circle on a shared form', function () {
    $response = FormResponse::create(['form_id' => $this->sharedForm->id, 'answers' => ['f_name' => 'محاولة', 'f_email' => 'attempt']]);

    $this->actingAs($this->other, 'supervisor');

    Livewire::test(FormResponses::class, ['formId' => $this->sharedForm->id])
        ->call('openCreateModal', $response->id)
        ->set('targetCircleId', $this->circleA->id) // owner's circle, out of scope
        ->call('createStudentAccount')
        ->assertHasErrors('targetCircleId');

    expect(Student::where('name', 'محاولة')->exists())->toBeFalse();
});

it('lets another supervisor edit a shared form without duplicating it or changing the owner', function () {
    $this->actingAs($this->other, 'supervisor');

    Livewire::test(FormBuilder::class, ['formId' => $this->sharedForm->id])
        ->set('title', 'عنوان معدّل من مشرف آخر')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('supervisor.forms'));

    $fresh = Form::find($this->sharedForm->id);
    expect($fresh->title)->toBe('عنوان معدّل من مشرف آخر');
    expect($fresh->supervisor_id)->toBe($this->owner->id); // ownership preserved
    expect(Form::where('slug', 'shared-form')->count())->toBe(1); // no duplicate
});

it('shows shared forms from others but not their private forms in the list', function () {
    $this->actingAs($this->other, 'supervisor');

    Livewire::test(ManageForms::class)
        ->assertSee('نموذج مشترك')
        ->assertDontSee('نموذج خاص');
});
