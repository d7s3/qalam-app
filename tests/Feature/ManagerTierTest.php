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
use App\Support\ManagerTier;
use App\Support\RoleTitle;
use App\Support\Scope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

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
    ManagerTier::forget();
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
    expect(RoleTitle::for($this->director, 'manager'))->toBe('مدير البرنامج')
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

    ManagerTier::forget();

    expect(ManagerTier::of($admin))->toBe(ManagerTier::CENTRE)
        ->and(Access::canSee($admin, 'manager', 'manager.settings'))->toBeTrue()
        ->and(Scope::for($admin, 'manager')->reachesAll())->toBeTrue();
});

/**
 * The office comes in three, and the academy runs on برنامج ← دفعة — so a man
 * over a programme and a man over one cohort inside it are two different
 * offices, and the first build called them both «مدير الدفعة».
 */
describe('the three tiers', function () {
    it('names each by how much of the academy it covers', function () {
        $overCohort = Manager::factory()->create();
        $overCohort->roles()->where('role', 'manager')->update([
            'scope_type' => UserRole::SCOPE_CIRCLES,
            'scope_ids' => [$this->myCohort->id],
        ]);
        $overCohort->load('roles');
        ManagerTier::forget();

        expect(ManagerTier::of($this->centre))->toBe(ManagerTier::CENTRE)
            ->and(ManagerTier::of($this->director))->toBe(ManagerTier::PROGRAMME)
            ->and(ManagerTier::of($overCohort))->toBe(ManagerTier::COHORT)
            ->and(RoleTitle::for($this->centre, 'manager'))->toBe('مدير المركز')
            ->and(RoleTitle::for($this->director, 'manager'))->toBe('مدير البرنامج')
            ->and(RoleTitle::for($overCohort, 'manager'))->toBe('مدير الدفعة');
    });

    it('gives a cohort manager his cohort and nothing beside it', function () {
        $second = Circle::factory()->create(['stage_id' => $this->mine->id]);
        Student::factory()->create(['name' => 'ماجد', 'circle_id' => $second->id, 'stage_id' => $this->mine->id]);

        $overCohort = Manager::factory()->create();
        $overCohort->roles()->where('role', 'manager')->update([
            'scope_type' => UserRole::SCOPE_CIRCLES,
            'scope_ids' => [$this->myCohort->id],
        ]);
        $overCohort->load('roles');
        ManagerTier::forget();
        Scope::forget();

        // His programme's other cohort is not his, though the programme is one.
        expect(Scope::for($overCohort, 'manager')->applyToStudents(Student::query())->pluck('name')->all())
            ->toBe(['سالم']);
    });
});

/**
 * Supervisors and teachers could be created from the manager's area; managers
 * could not, so the tiers existed and nobody could be made into one.
 */
describe('making them', function () {
    it('makes a manager at the reach that was chosen', function () {
        Livewire::actingAs($this->centre, 'manager')
            ->test('manager.managers')
            ->set('name', 'مدير برنامج أ')
            ->set('email', 'director@example.com')
            ->set('tier', ManagerTier::PROGRAMME)
            ->set('reaches', [$this->mine->id])
            ->call('create')
            ->assertHasNoErrors();

        $made = Manager::where('email', 'director@example.com')->firstOrFail();
        ManagerTier::forget();

        expect(ManagerTier::of($made))->toBe(ManagerTier::PROGRAMME)
            ->and($made->is_approved)->toBeTrue()
            ->and(Scope::for($made, 'manager')->stageIds()->all())->toBe([$this->mine->id])
            // No password passes through anybody: he sets his own.
            ->and($made->password)->not->toBeEmpty();
    });

    it('refuses to make one below the centre reaching nothing', function () {
        Livewire::actingAs($this->centre, 'manager')
            ->test('manager.managers')
            ->set('name', 'بلا مدى')
            ->set('email', 'nowhere@example.com')
            ->set('tier', ManagerTier::COHORT)
            ->set('reaches', [])
            ->call('create');

        expect(Manager::where('email', 'nowhere@example.com')->exists())->toBeFalse();
    });

    it('keeps the making of managers with the centre', function () {
        expect(Access::canSee($this->director, 'manager', 'manager.managers'))->toBeFalse()
            ->and(Access::canSee($this->centre, 'manager', 'manager.managers'))->toBeTrue();

        Livewire::actingAs($this->director, 'manager')
            ->test('manager.managers')
            ->assertStatus(403);
    });
});

/**
 * A `@php` block inside a loop writes into the template's own scope, so naming
 * the loop's variable after a component property silently overwrites it — the
 * reach panel read the last manager in the list instead of the reach chosen,
 * and offered the centre's description whatever was picked.
 */
it('lets the chosen reach survive the list above it', function () {
    Stage::factory()->create(['name' => 'برنامج ج']);

    Livewire::actingAs($this->centre, 'manager')
        ->test('manager.managers')
        ->set('tier', ManagerTier::PROGRAMME)
        ->assertSee('برامجه')
        ->assertDontSee('يرى الأكاديمية كلّها')
        ->set('tier', ManagerTier::COHORT)
        ->assertSee('دفعاته')
        ->set('tier', ManagerTier::CENTRE)
        ->assertSee('يرى الأكاديمية كلّها');
});

/**
 * Where an administrator actually looks for it.
 *
 * Supervisors, teachers, guardians and students each had a tab on «المستخدمون»
 * with a «new» button on it; the managers had neither, so the office nobody
 * could create was also the office with nowhere to look. The tab is the answer,
 * and it is the centre's own — a manager over one programme does not make
 * managers, so he is not shown it.
 */
describe('where the managers are found', function () {
    it('gives the managers a tab beside the other offices', function () {
        Livewire::actingAs($this->centre, 'manager')
            ->test('manager.user-directory')
            ->assertSee('المديرون')
            ->call('setTab', 'managers')
            ->assertSet('activeTab', 'managers');
    });

    it('does not offer that tab below the centre', function () {
        Livewire::actingAs($this->director, 'manager')
            ->test('manager.user-directory')
            ->assertDontSee('المديرون')
            // Nor may he stand on it by asking for it.
            ->call('setTab', 'managers')
            ->assertSet('activeTab', 'students');
    });

    it('opens the page directly for the centre', function () {
        $this->actingAs($this->centre, 'manager')
            ->get(route('manager.managers'))
            ->assertSuccessful()
            ->assertSee('مدير جديد');
    });
});
