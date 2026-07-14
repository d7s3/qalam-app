<?php

use App\Models\Circle;
use App\Models\Student;
use App\Models\Teacher;

it('renders the features section and live stats on the welcome page', function () {
    Student::factory()->count(2)->create();
    Teacher::factory()->count(1)->create();
    Circle::factory()->count(1)->create();

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertSee('لماذا مجمع التاج');
    $response->assertSee('خطط حفظ ومراجعة مخصصة');
    $response->assertSee('data-countup="2"', false);
    $response->assertSee('data-countup="1"', false);
});

it('shows a single unified login entry point instead of four separate portals', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertSee('من نحن');
    $response->assertSee('مجمع التاج القرآني منصة رقمية متكاملة');
    $response->assertSee(route('login'), false);
    $response->assertDontSee('/student/login', false);
    $response->assertDontSee('/teacher/login', false);
    $response->assertDontSee('/supervisor/login', false);
});
