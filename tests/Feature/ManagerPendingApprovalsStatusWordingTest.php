<?php

use App\Livewire\Manager\PendingApprovals;
use App\Models\Manager;
use App\Models\Student;
use Livewire\Livewire;

it('shows the pending status as "قيد المراجعة" in the requests table', function () {
    $manager = Manager::factory()->create();
    $this->actingAs($manager, 'manager');

    $student = Student::factory()->create(['is_approved' => false]);

    Livewire::test(PendingApprovals::class)
        ->assertSee($student->name)
        ->assertSee('قيد المراجعة');
});

it('shows "تمت الموافقة" and "تم الرفض" after a decision', function () {
    $manager = Manager::factory()->create();
    $this->actingAs($manager, 'manager');

    $approved = Student::factory()->create(['is_approved' => true]);
    $rejected = Student::factory()->create(['is_approved' => false, 'is_rejected' => true]);

    $component = Livewire::test(PendingApprovals::class)->set('statusFilter', 'all');

    $component->assertSee('تمت الموافقة')
        ->assertSee('تم الرفض');
});
