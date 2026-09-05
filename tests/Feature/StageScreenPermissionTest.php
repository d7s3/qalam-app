<?php

use App\Models\Circle;
use App\Models\Manager;
use App\Models\Role;
use App\Models\Screen;
use App\Models\Stage;
use App\Models\StageScreenPermission;
use App\Models\Teacher;
use App\Models\UserScreenOverride;
use App\Support\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A role is not the same job in every programme. The memorisation programme
 * wants its teachers holding pages the beginners' programme would rather they
 * did not, and the manager decides that per programme rather than for every
 * teacher in the academy at once.
 *
 * Only differences are stored, so a programme created tomorrow works the moment
 * it exists, and a grant made centrally next month reaches every programme that
 * did not refuse it.
 */
beforeEach(function () {
    $this->programmeA = Stage::factory()->create(['name' => 'برنامج الحفظ']);
    $this->programmeB = Stage::factory()->create(['name' => 'برنامج المبتدئين']);

    $this->cohortA = Circle::factory()->create(['stage_id' => $this->programmeA->id]);
    $this->cohortB = Circle::factory()->create(['stage_id' => $this->programmeB->id]);

    $this->teacherA = Teacher::factory()->create();
    $this->teacherA->circles()->attach($this->cohortA->id);

    $this->teacherB = Teacher::factory()->create();
    $this->teacherB->circles()->attach($this->cohortB->id);

    $this->teacherRole = Role::where('key', 'teacher')->firstOrFail();

    // One page the teacher holds centrally, and one he does not.
    $this->held = Screen::where('route_name', 'teacher.tasmeeh')->firstOrFail();
    $this->withheld = Screen::where('route_name', 'teacher.reports.supervision')->firstOrFail();

    Access::forget();
});

/** Write one programme's word about one screen. */
function grantInStage(Stage $stage, Role $role, Screen $screen, bool $allowed): void
{
    StageScreenPermission::create([
        'stage_id' => $stage->id,
        'role_id' => $role->id,
        'screen_id' => $screen->id,
        'is_allowed' => $allowed,
    ]);
}

it('inherits the central grant when the programme says nothing', function () {
    expect(Access::canSee($this->teacherA, 'teacher', 'teacher.tasmeeh'))->toBeTrue();
    expect(Access::canSee($this->teacherA, 'teacher', 'teacher.reports.supervision'))->toBeFalse();

    expect(StageScreenPermission::count())->toBe(0);
});

it('lets a programme close a page the role holds everywhere else', function () {
    grantInStage($this->programmeA, $this->teacherRole, $this->held, false);

    expect(Access::canSee($this->teacherA, 'teacher', 'teacher.tasmeeh'))->toBeFalse();
});

it('lets a programme open a page the role is not granted centrally', function () {
    grantInStage($this->programmeA, $this->teacherRole, $this->withheld, true);

    expect(Access::canSee($this->teacherA, 'teacher', 'teacher.reports.supervision'))->toBeTrue();
});

it('keeps one programme decision out of another programme', function () {
    grantInStage($this->programmeA, $this->teacherRole, $this->held, false);

    // The teacher of the other programme is untouched — this is the whole point.
    expect(Access::canSee($this->teacherB, 'teacher', 'teacher.tasmeeh'))->toBeTrue();
});

it('holds what either programme grants when a teacher is in two', function () {
    // Closed where he teaches on Sunday, open where he teaches on Tuesday. The
    // page is one page and he has one sidebar, so refusing it here must not take
    // it from him there: what he may reach inside it is Scope's question, asked
    // separately.
    $this->teacherA->circles()->attach($this->cohortB->id);

    grantInStage($this->programmeA, $this->teacherRole, $this->held, false);

    expect(Access::canSee($this->teacherA, 'teacher', 'teacher.tasmeeh'))->toBeTrue();
});

it('closes the page when every programme he is in refuses it', function () {
    $this->teacherA->circles()->attach($this->cohortB->id);

    grantInStage($this->programmeA, $this->teacherRole, $this->held, false);
    grantInStage($this->programmeB, $this->teacherRole, $this->held, false);

    expect(Access::canSee($this->teacherA, 'teacher', 'teacher.tasmeeh'))->toBeFalse();
});

it('does not narrow someone whose reach is the whole centre', function () {
    // A manager holds no particular programme, so no programme answers for him.
    $manager = Manager::factory()->create();

    grantInStage($this->programmeA, Role::where('key', 'manager')->firstOrFail(),
        Screen::where('route_name', 'manager.circles')->firstOrFail(), false);

    expect(Access::canSee($manager, 'manager', 'manager.circles'))->toBeTrue();
});

it('lets a personal exception beat the programme', function () {
    grantInStage($this->programmeA, $this->teacherRole, $this->held, false);

    UserScreenOverride::create([
        'user_id' => $this->teacherA->id,
        'screen_id' => $this->held->id,
        'is_allowed' => true,
    ]);

    expect(Access::canSee($this->teacherA, 'teacher', 'teacher.tasmeeh'))->toBeTrue();
});

it('leaves the super administrator above all of it', function () {
    $admin = Manager::factory()->create(['is_super_admin' => true]);
    $admin->circles()->attach($this->cohortA->id);

    grantInStage($this->programmeA, $this->teacherRole, $this->held, false);

    expect(Access::canSee($admin, 'teacher', 'teacher.tasmeeh'))->toBeTrue();
});
