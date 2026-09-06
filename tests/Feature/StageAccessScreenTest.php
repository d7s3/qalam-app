<?php

use App\Models\Circle;
use App\Models\Manager;
use App\Models\Role;
use App\Models\Screen;
use App\Models\Stage;
use App\Models\StageScreenPermission;
use App\Models\Teacher;
use App\Support\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The screen the manager actually uses: pick a programme, pick a role, and say
 * for each page whether it follows the role or is decided here.
 */
beforeEach(function () {
    $this->manager = Manager::factory()->create(['is_super_admin' => true]);

    $this->programme = Stage::factory()->create(['name' => 'برنامج الحفظ']);
    $this->cohort = Circle::factory()->create(['stage_id' => $this->programme->id]);

    $this->teacher = Teacher::factory()->create();
    $this->teacher->circles()->attach($this->cohort->id);

    $this->teacherRole = Role::where('key', 'teacher')->firstOrFail();
    $this->tasmeeh = Screen::where('route_name', 'teacher.tasmeeh')->firstOrFail();
});

it('opens for a manager', function () {
    $this->actingAs($this->manager, 'manager')
        ->get(route('manager.stage-access'))
        ->assertSuccessful()
        ->assertSee('صلاحيات البرامج')
        ->assertSee('برنامج الحفظ')
        // The deepest markup in the template. An inline @php in a single-file
        // component silently cuts everything below it and still returns 200, so
        // the last thing drawn is what proves the page is whole.
        ->assertSee('يتبع الدور')
        ->assertSee('تعطيل');
});

it('closes a page for one programme and lets it back', function () {
    $component = Livewire::actingAs($this->manager, 'manager')
        ->test('manager.stage-access')
        ->call('setStage', $this->programme->id)
        ->call('setRole', $this->teacherRole->id);

    expect(Access::canSee($this->teacher, 'teacher', 'teacher.tasmeeh'))->toBeTrue();

    $component->call('setState', $this->tasmeeh->id, 'off');

    expect(StageScreenPermission::count())->toBe(1);
    expect(Access::canSee($this->teacher, 'teacher', 'teacher.tasmeeh'))->toBeFalse();

    // Returning it to the role removes the row rather than storing a third
    // value — the absence is what lets a later central grant through.
    $component->call('setState', $this->tasmeeh->id, 'inherit');

    expect(StageScreenPermission::count())->toBe(0);
    expect(Access::canSee($this->teacher, 'teacher', 'teacher.tasmeeh'))->toBeTrue();
});

it('refuses to decide a protected page', function () {
    $protected = Screen::where('is_protected', true)->firstOrFail();

    Livewire::actingAs($this->manager, 'manager')
        ->test('manager.stage-access')
        ->call('setStage', $this->programme->id)
        ->call('setRole', $this->teacherRole->id)
        ->call('setState', $protected->id, 'off');

    expect(StageScreenPermission::count())->toBe(0);
});
