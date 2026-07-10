<?php

it('renders the student login page with the new split layout', function () {
    $response = $this->get(route('student.login'));

    $response->assertOk();
    $response->assertSee('مجمع التاج القرآني');
    $response->assertSee('تسجيل الدخول');
});

it('renders the teacher login page with the new split layout', function () {
    $response = $this->get(route('teacher.login'));

    $response->assertOk();
    $response->assertSee('مجمع التاج القرآني');
});

it('renders the supervisor login page with the new split layout', function () {
    $response = $this->get(route('supervisor.login'));

    $response->assertOk();
});

it('renders the guardian login page with the new split layout', function () {
    $response = $this->get(route('parent.login'));

    $response->assertOk();
});
