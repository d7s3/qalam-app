<?php

use App\Livewire\Auth\Student\Register;
use App\Livewire\Supervisor\FormResponses;
use App\Models\Circle;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Teacher;
use Livewire\Livewire;

/**
 * However a student arrives, he starts تحت التسجيل and his history starts
 * with him. A student who signed himself up used to start مشارك, from the
 * column's default, and so counted in competitions, public results and every
 * tally of active students before anyone had approved him; and neither he nor
 * a student made from a form response had a first row in his history.
 */
beforeEach(function () {
    Carbon\Carbon::setTestNow('2026-10-05 09:00:00');

    $this->programme = Stage::factory()->create();
    $this->cohort = Circle::factory()->create(['stage_id' => $this->programme->id]);
});

/**
 * @return array{status: string, start_date: string, notes: ?string, changed_by_role: ?string}
 */
function arrivalRow(Student $student): array
{
    $row = $student->statusHistories()->sole();

    return [
        'status' => $row->status,
        'start_date' => $row->start_date->format('Y-m-d H:i:s'),
        'notes' => $row->notes,
        'changed_by_role' => $row->changed_by_role,
    ];
}

it('starts a student who signs himself up تحت التسجيل, with his history', function () {
    Livewire::test(Register::class)
        ->set('name', 'طالب سجّل نفسه')
        ->set('email', 'self@example.com')
        ->set('phone', '0512345678')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('terms', true)
        ->call('register')
        ->assertHasNoErrors();

    $student = Student::where('email', 'self@example.com')->sole();

    expect($student->status)->toBe('registering')
        ->and(arrivalRow($student))->toMatchArray([
            'status' => 'registering',
            'start_date' => '2026-10-05 00:00:00',
            'notes' => 'أنشأ حسابه بنفسه',
        ])
        ->and(Student::where('status', 'active')->whereKey($student->id)->exists())->toBeFalse();
});

it('starts a student made from a form response with his history', function () {
    $supervisor = Supervisor::factory()->create();
    $supervisor->stages()->attach($this->programme->id);

    $form = Form::create([
        'supervisor_id' => $supervisor->id,
        'title' => 'استمارة التسجيل',
        'slug' => 'arrival-form',
        'color' => '#14b8a6',
        'fields' => [['id' => 'f_name', 'type' => 'text', 'label' => 'الاسم الكامل', 'is_student_name' => true]],
    ]);
    $response = FormResponse::create(['form_id' => $form->id, 'answers' => ['f_name' => 'طالب من نموذج']]);

    $this->actingAs($supervisor, 'supervisor');

    Livewire::test(FormResponses::class, ['formId' => $form->id])
        ->call('openCreateModal', $response->id)
        ->set('newStudentRandomEmail', true)
        ->set('newStudentPassword', 'password123')
        ->set('targetCircleId', $this->cohort->id)
        ->call('createStudentAccount')
        ->assertHasNoErrors();

    $student = Student::where('name', 'طالب من نموذج')->sole();

    expect($student->status)->toBe('registering')
        ->and(arrivalRow($student))->toMatchArray([
            'status' => 'registering',
            'changed_by_role' => 'supervisor',
        ]);
});

it('writes the walk-in a teacher adds through the same door, dated by the day and signed', function () {
    $teacher = Teacher::factory()->create();
    $teacher->circles()->attach($this->cohort->id);

    Livewire::actingAs($teacher, 'teacher')
        ->test('teacher.student-manager')
        ->set('name', 'طالب حضر بنفسه')
        ->set('phone', '0500000000')
        ->call('createStudent');

    $student = Student::where('name', 'طالب حضر بنفسه')->sole();

    // It was written straight to the table with the time of day as its start
    // and nobody's name against it.
    expect(arrivalRow($student))->toMatchArray([
        'status' => 'registering',
        'start_date' => '2026-10-05 00:00:00',
        'changed_by_role' => 'teacher',
    ]);
});
