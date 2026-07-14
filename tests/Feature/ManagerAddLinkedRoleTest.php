<?php

use App\Models\Manager;
use App\Models\Teacher;
use App\Models\UserRole;
use Livewire\Livewire;

it('shows an add-role button for the teacher inside the edit modal', function () {
    $manager = Manager::factory()->create();
    $teacher = Teacher::factory()->create(['is_approved' => true]);
    $this->actingAs($manager, 'manager');

    $this->get(route('manager.teachers'))
        ->assertSuccessful();

    Livewire::test('manager.add-linked-role', [
        'sourceGuard' => 'teacher',
        'sourceId' => $teacher->id,
        'sourceName' => $teacher->name,
    ])->assertSee('مشرف')->assertSee('ولي أمر');
});

it('grants an additional role to the same account without creating a new one', function () {
    $manager = Manager::factory()->create();
    $teacher = Teacher::factory()->create(['is_approved' => true, 'name' => 'أحمد المعلم']);
    $this->actingAs($manager, 'manager');

    Livewire::test('manager.add-linked-role', [
        'sourceGuard' => 'teacher',
        'sourceId' => $teacher->id,
        'sourceName' => $teacher->name,
    ])->call('grant', 'supervisor');

    $role = UserRole::where('user_id', $teacher->id)->where('role', 'supervisor')->first();

    expect($role)->not->toBeNull();
    expect($role->is_approved)->toBeTrue();
    expect($role->approved_by)->toBe($manager->id);
    expect($teacher->fresh()->hasRole('supervisor'))->toBeTrue();
});

it('shows already-held roles and hides them from the add-role options', function () {
    $manager = Manager::factory()->create();
    $teacher = Teacher::factory()->create(['is_approved' => true]);
    UserRole::create(['user_id' => $teacher->id, 'role' => 'supervisor', 'is_approved' => true, 'approved_by' => $manager->id]);

    $this->actingAs($manager, 'manager');

    Livewire::test('manager.add-linked-role', [
        'sourceGuard' => 'teacher',
        'sourceId' => $teacher->id,
        'sourceName' => $teacher->name,
    ])
        ->assertSee('مشرف')
        ->assertDontSee("grant('supervisor')", false)
        ->assertSee("grant('guardian')", false);
});

it('ignores an attempt to grant the same guard as the source', function () {
    $manager = Manager::factory()->create();
    $teacher = Teacher::factory()->create(['is_approved' => true]);
    $this->actingAs($manager, 'manager');

    Livewire::test('manager.add-linked-role', [
        'sourceGuard' => 'teacher',
        'sourceId' => $teacher->id,
        'sourceName' => $teacher->name,
    ])->call('grant', 'teacher');

    expect(UserRole::where('user_id', $teacher->id)->where('role', 'teacher')->count())->toBe(1);
});
