<?php

use App\Models\Circle;
use App\Models\Manager;
use App\Models\Student;
use App\Models\StudentNotice;

it('holds a student at his message until he types the code', function () {
    $student = Student::factory()->create(['name' => 'عمر محمد', 'circle_id' => Circle::factory()->create()->id]);
    StudentNotice::factory()->locked('1448')->create([
        'student_id' => $student->id,
        'body' => "السلام عليكم يا {الاسم}،\nنرجو مراجعة مشرفك اليوم بعد العصر ليعطيك رمز فتح حسابك.",
    ]);

    $this->actingAs($student, 'student');

    visit('/student/dashboard')
        ->assertNoJavaScriptErrors()
        ->assertSee('السلام عليكم يا عمر محمد')
        ->screenshot(filename: 'student-notice-locked')
        ->fill('code', '1448')
        ->press('افتح حسابي')
        ->assertPathIs('/student/dashboard');
});

it('lets the manager choose students and write to them', function () {
    $circle = Circle::factory()->create();
    Student::factory()->create(['name' => 'عمر محمد', 'circle_id' => $circle->id]);

    $this->actingAs(Manager::factory()->create(), 'manager');

    visit('/manager/student-notices')
        ->assertNoJavaScriptErrors()
        ->fill('[wire\\:model\\.live\\.debounce\\.300ms="search"]', 'عمر')
        ->click('عمر محمد')
        ->assertSee('عمر محمد')
        ->screenshot(filename: 'manager-student-notices');
});
