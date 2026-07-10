<?php

use App\Models\Manager;
use App\Models\Student;

it('renders the pending approvals page over http', function () {
    $manager = Manager::factory()->create();
    Student::factory()->create(['is_approved' => false]);

    $this->actingAs($manager, 'manager');

    $this->get(route('manager.pending-approvals'))
        ->assertSuccessful()
        ->assertSee('طلبات التسجيل')
        ->assertSee('بانتظار المراجعة');
});
