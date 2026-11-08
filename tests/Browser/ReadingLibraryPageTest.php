<?php

use App\Models\BookQuote;
use App\Models\Circle;
use App\Models\ReadingSession;
use App\Models\Student;
use App\Models\StudentBook;

it('shows the student his shelves, streak and cohort, and runs a focus session', function () {
    $circle = Circle::factory()->create();
    $student = Student::factory()->create(['name' => 'عمر محمد', 'circle_id' => $circle->id]);
    $friend = Student::factory()->create(['name' => 'خالد سعيد', 'circle_id' => $circle->id]);

    $book = StudentBook::factory()->create(['student_id' => $student->id, 'title' => 'العواصم من القواصم', 'current_page' => 120, 'total_pages' => 395]);
    StudentBook::factory()->create(['student_id' => $friend->id, 'title' => 'مدخل لفهم الأدب', 'current_page' => 40, 'total_pages' => 150]);
    BookQuote::create(['student_book_id' => $book->id, 'body' => 'العلم صيد والكتابة قيده', 'page' => 12]);

    foreach (range(0, 3) as $back) {
        ReadingSession::factory()->create(['student_id' => $student->id, 'student_book_id' => $book->id, 'ended_at' => now()->subDays($back), 'pages' => 10 + $back * 5]);
    }
    ReadingSession::factory()->create(['student_id' => $friend->id, 'pages' => 30]);

    $this->actingAs($student, 'student');

    $page = visit('/student/library')
        ->assertNoJavaScriptErrors()
        ->assertSee('العواصم من القواصم')
        ->assertSee('دفعتك هذا الأسبوع')
        ->screenshot(fullPage: true, filename: 'student-library');

    $page->click('جلسة قراءة')
        ->assertSee('وصلت إلى صفحة')
        ->screenshot(filename: 'student-focus-session');
});
