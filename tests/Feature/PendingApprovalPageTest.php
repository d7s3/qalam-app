<?php

use App\Models\Student;

it('renders the pending-approval page with the confirmation card and journey stepper', function () {
    $student = Student::factory()->create(['is_approved' => false]);
    $this->actingAs($student, 'student');

    $response = $this->get(route('pending-approval'));

    $response->assertSuccessful();
    $response->assertSee('تم استلام طلب التسجيل');
    $response->assertSee('رحلة طلب التسجيل');
    $response->assertSee('إنشاء حساب');
    $response->assertSee('مراجعة الطلب');
    $response->assertSee('قبول الطلب');
    $response->assertSee('إشعار المستخدم');
});

it('redirects an unapproved student to the pending-approval page', function () {
    $student = Student::factory()->create(['is_approved' => false]);
    $this->actingAs($student, 'student');

    $this->get(route('student.dashboard'))
        ->assertRedirect(route('pending-approval'));
});
