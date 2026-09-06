<?php

use App\Livewire\Supervisor\Competitions;
use App\Models\Circle;
use App\Models\Leaderboard;
use App\Models\Stage;
use App\Models\Supervisor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Regression test for a reported bug: creating a new competition from the
 * supervisor's competitions page appeared to do nothing on "save".
 */
it('creates a new normal competition and persists it', function () {
    $stage = Stage::create(['name' => 'المرحلة الأولى']);
    $circle = Circle::create(['name' => 'دفعة النور', 'stage_id' => $stage->id]);

    $supervisor = Supervisor::factory()->create(['is_approved' => true]);
    $supervisor->stages()->attach($stage->id);

    $this->actingAs($supervisor, 'supervisor');

    Livewire::test(Competitions::class)
        ->call('create')
        ->set('competition_type', 'normal')
        ->set('title', 'مسابقة الفصل الدراسي')
        ->set('start_date', now()->format('Y-m-d'))
        ->set('end_date', now()->addDays(10)->format('Y-m-d'))
        ->set('selectedCircles', [$circle->id])
        ->call('save')
        ->assertHasNoErrors();

    $competition = Leaderboard::where('title', 'مسابقة الفصل الدراسي')->first();

    expect($competition)->not->toBeNull();
    expect($competition->supervisor_id)->toBe($supervisor->id);
    expect($competition->circles()->pluck('circles.id')->all())->toBe([$circle->id]);
});

it('updates an existing competition', function () {
    $stage = Stage::create(['name' => 'المرحلة الأولى']);
    $circle = Circle::create(['name' => 'دفعة النور', 'stage_id' => $stage->id]);

    $supervisor = Supervisor::factory()->create(['is_approved' => true]);
    $supervisor->stages()->attach($stage->id);

    $competition = Leaderboard::create([
        'supervisor_id' => $supervisor->id,
        'circle_id' => $circle->id,
        'title' => 'اسم قديم',
        'competition_type' => 'normal',
        'start_date' => now(),
        'end_date' => now()->addDays(5),
        'is_active' => true,
        'settings' => [],
    ]);
    $competition->circles()->attach($circle->id);

    $this->actingAs($supervisor, 'supervisor');

    Livewire::test(Competitions::class)
        ->call('edit', $competition->id)
        ->set('title', 'اسم جديد')
        ->call('save')
        ->assertHasNoErrors();

    expect($competition->fresh()->title)->toBe('اسم جديد');
});

it('rejects saving a competition with no title', function () {
    $stage = Stage::create(['name' => 'المرحلة الأولى']);
    $circle = Circle::create(['name' => 'دفعة النور', 'stage_id' => $stage->id]);

    $supervisor = Supervisor::factory()->create(['is_approved' => true]);
    $supervisor->stages()->attach($stage->id);

    $this->actingAs($supervisor, 'supervisor');

    Livewire::test(Competitions::class)
        ->call('create')
        ->set('competition_type', 'normal')
        ->set('title', '')
        ->set('start_date', now()->format('Y-m-d'))
        ->set('end_date', now()->addDays(10)->format('Y-m-d'))
        ->set('selectedCircles', [$circle->id])
        ->call('save')
        ->assertHasErrors('title');

    expect(Leaderboard::count())->toBe(0);
});
