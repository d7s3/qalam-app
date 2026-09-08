<?php

use App\Models\Circle;
use App\Models\Manager;
use App\Models\Screen;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\UserRole;
use App\Models\UserScreenOverride;
use App\Support\Access;
use App\Support\CohortManager;
use App\Support\RoleTitle;
use App\Support\Scope;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The tier between the centre's manager and a cohort's supervisor.
 *
 * He is a manager held over programmes rather than over the academy, which the
 * reach could already express — so what is tested here is the tier itself: that
 * he sees his programme whole and the next one not at all, that he is called by
 * his own name rather than the centre manager's, and that the centre keeps the
 * pages that are the centre's own.
 */
beforeEach(function () {
    CohortManager::forget();
    Scope::forget();

    $this->mine = Stage::factory()->create(['name' => 'برنامج أ']);
    $this->theirs = Stage::factory()->create(['name' => 'برنامج ب']);

    $this->myCohort = Circle::factory()->create(['stage_id' => $this->mine->id]);
    $this->theirCohort = Circle::factory()->create(['stage_id' => $this->theirs->id]);

    Student::factory()->create(['name' => 'سالم', 'circle_id' => $this->myCohort->id, 'stage_id' => $this->mine->id]);
    Student::factory()->create(['name' => 'زياد', 'circle_id' => $this->theirCohort->id, 'stage_id' => $this->theirs->id]);

    $this->mySupervisor = Supervisor::factory()->create(['name' => 'مشرف أ']);
    $this->mySupervisor->stages()->attach($this->mine->id);

    $this->director = Manager::factory()->create(['name' => 'مدير الدفعة أ']);
    $this->director->roles()->where('role', 'manager')->update([
        'scope_type' => UserRole::SCOPE_STAGES,
        'scope_ids' => [$this->mine->id],
    ]);
    $this->director->load('roles');

    $this->centre = Manager::factory()->create(['name' => 'مدير المركز']);
});

it('sees his own programme whole and the next one not at all', function () {
    $reach = Scope::for($this->director, 'manager');

    expect($reach->reachesAll())->toBeFalse()
        ->and($reach->stageIds()->all())->toBe([$this->mine->id])
        ->and($reach->circleQuery()->pluck('id')->all())->toBe([$this->myCohort->id])
        ->and($reach->applyToStudents(Student::query())->pluck('name')->all())->toBe(['سالم']);
});

it('leaves the centre manager reaching everything, as before', function () {
    $reach = Scope::for($this->centre, 'manager');

    expect($reach->reachesAll())->toBeTrue()
        ->and($reach->applyToStudents(Student::query())->pluck('name')->all())
        ->toEqualCanonicalizing(['سالم', 'زياد']);
});

it('is called by his own name, not the centre manager\'s', function () {
    expect(RoleTitle::for($this->director, 'manager'))->toBe('مدير الدفعة')
        ->and(RoleTitle::for($this->centre, 'manager'))->toBe('مدير المركز')
        ->and(RoleTitle::for($this->mySupervisor, 'supervisor'))->toBe('مشرف دفعة');
});

it('keeps the centre\'s own pages with the centre', function () {
    foreach (['manager.settings', 'manager.role-permissions', 'manager.user-access', 'manager.stages'] as $page) {
        expect(Access::canSee($this->director, 'manager', $page))->toBeFalse($page)
            ->and(Access::canSee($this->centre, 'manager', $page))->toBeTrue($page);
    }
});

it('gives him everything else a manager has', function () {
    foreach (['manager.dashboard', 'manager.students', 'manager.self-program-weeks', 'manager.academic-calendar'] as $page) {
        expect(Access::canSee($this->director, 'manager', $page))->toBeTrue($page);
    }
});

it('holds even where the academy has not described the page yet', function () {
    // The centre's pages are subtracted before the screens table is consulted,
    // for the reason the other two narrowings are: an undescribed page is open
    // to everyone, and this has to hold before anybody gets round to describing
    // it. Nor does an exception written for him reopen one — the way to give a
    // man these is to make him a manager of the centre.
    $screen = Screen::firstOrCreate(
        ['route_name' => 'manager.settings'],
        ['name' => 'الإعدادات', 'is_protected' => false],
    );

    UserScreenOverride::create([
        'user_id' => $this->director->id,
        'screen_id' => $screen->id,
        'is_allowed' => true,
    ]);

    Access::forget();

    expect(Access::canSee($this->director, 'manager', 'manager.settings'))->toBeFalse()
        ->and(Access::canSee($this->director, 'manager', 'manager.backup.download.store'))->toBeFalse();
});

it('never narrows the administrator, whatever is written on his holding', function () {
    $admin = Manager::factory()->create(['is_super_admin' => true]);
    $admin->roles()->where('role', 'manager')->update([
        'scope_type' => UserRole::SCOPE_STAGES,
        'scope_ids' => [$this->mine->id],
    ]);
    $admin->load('roles');

    CohortManager::forget();

    expect(CohortManager::is($admin))->toBeFalse()
        ->and(Access::canSee($admin, 'manager', 'manager.settings'))->toBeTrue()
        ->and(Scope::for($admin, 'manager')->reachesAll())->toBeTrue();
});
