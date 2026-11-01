<?php

use App\Models\Circle;
use App\Models\Stage;
use App\Models\Student;

/**
 * The first time a student opens a page, a short walk points at what is on it.
 * Steps whose target is not on the page are passed over, it is not shown again
 * once ended, and the guide page brings it back.
 */
beforeEach(function () {
    config(['app.page_tours' => true]);

    $programme = Stage::factory()->create();
    $this->actingAs(Student::factory()->create([
        'circle_id' => Circle::factory()->create(['stage_id' => $programme->id])->id,
        'stage_id' => $programme->id,
        'is_approved' => true,
        'is_data_completed' => true,
    ]), 'student');
});

it('walks a page the first time only, and again after the guide brings it back', function () {
    // A phone-sized page loses its localStorage on navigate() in Pest, so this one is desktop-sized.
    $page = visit('/student/reports');

    $page->assertNoJavaScriptErrors()
        ->assertSee('ملخّص تقدّمك على مدار الوقت')
        ->assertSee('1 من 3')
        ->click('[data-tour-next]')
        ->assertSee('آخر أربعة عشر تقييماً لك')
        ->click('[data-tour-next]')
        ->click('[data-tour-next]')
        ->assertDontSee('نسبة حضورك في آخر ثمانية أسابيع')
        ->assertScript("localStorage.getItem('qalam.tour.student.reports')", '1');

    $page->navigate('/student/reports')->wait(1.5)->assertDontSee('1 من 3');

    $page->navigate('/student/guide')->click('[data-replay-tours]')
        ->assertSee('ستظهر الجولة الإرشادية من جديد')
        ->assertScript("localStorage.getItem('qalam.tour.student.reports')", null)
        ->navigate('/student/reports')->assertSee('1 من 3');
});

it('shows nothing when tours are off', function () {
    config(['app.page_tours' => false]);

    visit('/student/reports')->on()->mobile()
        ->wait(1.5)
        ->assertDontSee('ملخّص تقدّمك على مدار الوقت');
});
