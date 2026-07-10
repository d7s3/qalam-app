<?php

use App\Models\Manager;
use App\Models\Student;

it('shows the pending approvals link with a count badge in the manager sidebar', function () {
    $manager = Manager::factory()->create();
    Student::factory()->count(3)->create(['is_approved' => false]);

    $this->actingAs($manager, 'manager');

    $this->get(route('manager.dashboard'))
        ->assertSuccessful()
        ->assertSee('طلبات التسجيل')
        ->assertSee('3');
});

it('hides the badge when there are no pending requests', function () {
    $manager = Manager::factory()->create();

    $this->actingAs($manager, 'manager');

    $response = $this->get(route('manager.dashboard'));
    $response->assertSuccessful();
    $response->assertSee('طلبات التسجيل');
});
