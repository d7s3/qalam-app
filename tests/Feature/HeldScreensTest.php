<?php

use App\Livewire\Supervisor\WhatsappSettings;
use App\Models\Circle;
use App\Models\Guardian;
use App\Models\Leaderboard;
use App\Models\Manager;
use App\Models\Role;
use App\Models\Screen;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Teacher;
use App\Models\UserScreenOverride;
use App\Support\Access;
use App\Support\RoleHierarchy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * A screen held by seniority, actually opened.
 *
 * Being permitted and being able to open are two things: the teacher's pages sit
 * behind the teacher's guard, and a supervisor is not a teacher. He reaches them
 * under his own prefix instead, with his own navigation and his own reach.
 */
beforeEach(function () {
    RoleHierarchy::forget();
    Access::forget();

    $this->stage = Stage::factory()->create();
    $this->circle = Circle::factory()->create(['name' => 'دفعة أ', 'stage_id' => $this->stage->id]);

    $this->supervisor = Supervisor::factory()->create();
    $this->supervisor->stages()->attach($this->stage->id);

    $this->teacher = Teacher::factory()->create();
    $this->teacher->circles()->attach($this->circle->id);

    $this->student = Student::factory()->create(['name' => 'سالم', 'circle_id' => $this->circle->id]);

    $this->manager = Manager::factory()->create();
});

it('opens a teacher\'s page for the supervisor who carries it', function () {
    $this->actingAs($this->supervisor, 'supervisor')
        ->get(route('supervisor.held', ['screen' => 'teacher.self-program']))
        ->assertOk();
});

it('opens it for the manager, who carries it through the supervisor', function () {
    $this->actingAs($this->manager, 'manager')
        ->get(route('manager.held', ['screen' => 'teacher.self-program']))
        ->assertOk();
});

it('shows the reader his own navigation, not the page owner\'s', function () {
    $html = $this->actingAs($this->supervisor, 'supervisor')
        ->get(route('supervisor.held', ['screen' => 'teacher.self-program']))
        ->assertOk()
        ->getContent();

    // His own pages are in the sidebar; the teacher's dashboard is not.
    expect($html)->toContain(route('supervisor.dashboard'))
        ->and($html)->not->toContain(route('teacher.dashboard'));
});

it('shows him his own reach on the page he is standing in on', function () {
    $far = Circle::factory()->create(['name' => 'دفعة بعيدة', 'stage_id' => Stage::factory()->create()->id]);

    $html = $this->actingAs($this->supervisor, 'supervisor')
        ->get(route('supervisor.held', ['screen' => 'teacher.self-program']))
        ->assertOk()
        ->getContent();

    // The cohorts of his programme, not the cohorts of the teacher who owns the
    // page, and not the whole academy.
    expect($html)->toContain('دفعة أ')
        ->and($html)->not->toContain('دفعة بعيدة');
});

it('refuses a screen the chain does not carry', function () {
    // Seniority includes; juniority does not, and there is no way in by address.
    Role::where('key', 'supervisor')->first()->screenPermissions()->delete();
    Access::forget();

    $this->actingAs($this->supervisor, 'supervisor')
        ->get(route('supervisor.held', ['screen' => 'manager.settings']))
        ->assertForbidden();
});

it('honours an exception written for one person', function () {
    UserScreenOverride::create([
        'user_id' => $this->supervisor->id,
        'screen_id' => Screen::where('route_name', 'teacher.self-program')->value('id'),
        'is_allowed' => false,
    ]);

    $this->actingAs($this->supervisor, 'supervisor')
        ->get(route('supervisor.held', ['screen' => 'teacher.self-program']))
        ->assertForbidden();
});

it('answers nothing for a screen that is not registered', function () {
    $this->actingAs($this->supervisor, 'supervisor')
        ->get(route('supervisor.held', ['screen' => 'teacher.invented']))
        ->assertNotFound();
});

it('lists what his office carries in his own navigation', function () {
    $html = $this->actingAs($this->supervisor, 'supervisor')
        ->get(route('supervisor.dashboard'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('صلاحيات يحملها منصبك')
        ->and($html)->toContain(route('supervisor.held', ['screen' => 'teacher.tasmeeh']));
});

it('does not offer a page he already owns under his own prefix', function () {
    // Both offices have a page of exceedances of their own; his own is the one
    // that belongs in his navigation, not the teacher's carried alongside it.
    $html = $this->actingAs($this->supervisor, 'supervisor')
        ->get(route('supervisor.dashboard'))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain(route('supervisor.held', ['screen' => 'teacher.exceeded-limits']));
});

describe('the six that are tabs of one shell', function () {
    it('opens each of them for the supervisor who carries them', function () {
        // A page of each of their names also exists, left from before the shell
        // and showing something else — so a screen says how it renders when its
        // name does not.
        foreach (['attendance', 'students', 'plan-creator', 'tasmeeh', 'leaderboards', 'grade-items'] as $tab) {
            $this->actingAs($this->supervisor, 'supervisor')
                ->get(route('supervisor.held', ['screen' => 'teacher.'.$tab]))
                ->assertOk();
        }
    });

    it('opens the shell on the tab that was asked for', function () {
        $html = $this->actingAs($this->supervisor, 'supervisor')
            ->get(route('supervisor.held', ['screen' => 'teacher.tasmeeh']))
            ->assertOk()
            ->getContent();

        expect($html)->toContain("activeTab: 'tasmeeh'")
            ->and($html)->toContain('teacher-app-shell');
    });

    it('does not fall over when the reader teaches nothing', function () {
        // The shell used to ask the teacher guard for its cohorts, which is a
        // call on nothing at all when a supervisor is the one standing there.
        expect($this->supervisor->circles()->count())->toBe(0);

        $this->actingAs($this->supervisor, 'supervisor')
            ->get(route('supervisor.held', ['screen' => 'teacher.attendance']))
            ->assertOk();
    });

    it('shows the shell his own reach', function () {
        $far = Circle::factory()->create(['name' => 'دفعة بعيدة', 'stage_id' => Stage::factory()->create()->id]);

        $html = $this->actingAs($this->supervisor, 'supervisor')
            ->get(route('supervisor.held', ['screen' => 'teacher.attendance']))
            ->assertOk()
            ->getContent();

        expect($html)->not->toContain('دفعة بعيدة');
    });

    it('leaves the teacher\'s own route showing the same thing it always did', function () {
        $html = $this->actingAs($this->teacher, 'teacher')
            ->get(route('teacher.tasmeeh'))
            ->assertOk()
            ->getContent();

        expect($html)->toContain("activeTab: 'tasmeeh'");
    });
});

describe('a screen that needs a student or a record to open', function () {
    // The held address carries only the screen's name, so a page whose own
    // address asks for a student cannot be opened from it.
    it('is not offered among the carried screens', function () {
        $html = $this->actingAs($this->supervisor, 'supervisor')
            ->get(route('supervisor.dashboard'))
            ->assertOk()
            ->getContent();

        expect($html)->not->toContain(route('supervisor.held', ['screen' => 'teacher.student-recitation-log']));
    });

    it('answers not found rather than failing when its address is typed', function () {
        $this->actingAs($this->supervisor, 'supervisor')
            ->get(route('supervisor.held', ['screen' => 'teacher.student-recitation-log']))
            ->assertNotFound();
    });
});

describe('the name in the browser tab', function () {
    it('is the screen\'s own label when the page does not name itself', function () {
        $html = $this->actingAs($this->supervisor, 'supervisor')
            ->get(route('supervisor.circles'))
            ->assertOk()
            ->getContent();

        expect(substr_count($html, '<title>'))->toBe(1)
            ->and($html)->toMatch('/<title>\s*الدفعات - /u');
    });

    it('is the carried screen\'s label, not the shell it renders in', function () {
        $html = $this->actingAs($this->supervisor, 'supervisor')
            ->get(route('supervisor.held', ['screen' => 'teacher.attendance']))
            ->assertOk()
            ->getContent();

        $label = Screen::where('route_name', 'teacher.attendance')->value('label');

        expect($html)->toMatch('/<title>\s*'.preg_quote($label, '/').' - /u');
    });
});

it('sends the support card to the academy\'s own number', function () {
    config(['brand.contact.phone' => '0508822794']);

    $this->actingAs($this->supervisor, 'supervisor')
        ->get(route('supervisor.dashboard'))
        ->assertOk()
        ->assertSee('https://wa.me/966508822794', false)
        ->assertDontSee('966500000000');
});

it('links the sidebar to the supervisor\'s own gamification, never another\'s', function () {
    $others = Leaderboard::create([
        'supervisor_id' => Supervisor::factory()->create()->id,
        'circle_id' => Circle::factory()->create()->id,
        'title' => 'تلعيب غيره',
        'competition_type' => 'gamification',
        'start_date' => now(),
        'is_active' => true,
    ]);

    $this->actingAs($this->supervisor, 'supervisor')
        ->get(route('supervisor.dashboard'))
        ->assertOk()
        ->assertDontSee(route('supervisor.competitions.gamification', $others->id), false);

    $his = Leaderboard::create([
        'supervisor_id' => $this->supervisor->id,
        'circle_id' => $this->circle->id,
        'title' => 'تلعيبه',
        'competition_type' => 'gamification',
        'start_date' => now(),
        'is_active' => true,
    ]);

    $this->actingAs($this->supervisor, 'supervisor')
        ->get(route('supervisor.dashboard'))
        ->assertSee(route('supervisor.competitions.gamification', $his->id), false);
});

it('names each tab of the teacher\'s shell for itself', function () {
    $html = $this->actingAs($this->teacher, 'teacher')
        ->get(route('teacher.tasmeeh'))
        ->assertOk()
        ->getContent();

    $label = Screen::where('route_name', 'teacher.tasmeeh')->value('label');

    expect($html)->toMatch('/<title>\s*'.preg_quote($label, '/').' - /u')
        ->and($html)->toContain('document.title');
});

it('names the form pages in the browser tab', function () {
    $this->actingAs($this->supervisor, 'supervisor')
        ->get(route('supervisor.forms.create'))
        ->assertOk()
        ->assertSee('إنشاء نموذج - ', false);
});

it('opens a carried screen whose page is named differently from its route', function () {
    // supervisor.odes.paths is drawn by the view supervisor.ode-paths; the
    // route says so, and the carried address must follow it.
    $this->actingAs($this->manager, 'manager')
        ->get(route('manager.held', ['screen' => 'supervisor.odes.paths']))
        ->assertOk();
});

it('gives a supervisor no way to start server processes from the WhatsApp screen', function () {
    Http::fake(['*' => Http::response([], 500)]);

    Livewire::actingAs($this->supervisor, 'supervisor')
        ->test(WhatsappSettings::class)
        ->assertSee('تواصل مع إدارة المركز')
        ->assertDontSee('startNodeServer');

    expect(method_exists(WhatsappSettings::class, 'startNodeServer'))->toBeFalse();
});

it('opens the supervisor\'s and the teacher\'s home pages on their own summary', function () {
    $supervisorPage = $this->actingAs($this->supervisor, 'supervisor')->get(route('supervisor.dashboard'))->getContent();
    $teacherPage = $this->actingAs($this->teacher, 'teacher')->get(route('teacher.dashboard'))->getContent();

    expect(strpos($supervisorPage, 'wire:name="supervisor.dashboard"'))->toBeLessThan(strpos($supervisorPage, 'wire:name="shared.activity-pulse"'))
        ->and(strpos($teacherPage, 'wire:name="teacher.dashboard"'))->toBeLessThan(strpos($teacherPage, 'wire:name="shared.activity-pulse"'));
});

it('gives the report filters a Hijri calendar, and a parent only the dates', function () {
    $this->actingAs($this->supervisor, 'supervisor')
        ->get(route('supervisor.reports.attendance'))
        ->assertOk()
        ->assertSee('النطاق')
        ->assertSee('popover="manual"', false)
        ->assertDontSee('type="date"', false);

    $guardian = Guardian::factory()->create(['is_approved' => true]);
    $this->student->update(['guardian_id' => $guardian->id]);

    $this->actingAs($guardian, 'guardian')
        ->get(route('guardian.reports.attendance'))
        ->assertOk()
        ->assertDontSee('التجميع')
        ->assertSee('تصدير PDF');
});

it('counts a cohort\'s students in good Arabic on the supervisor\'s home page', function () {
    Student::factory()->count(4)->create(['circle_id' => $this->circle->id]);

    $this->actingAs($this->supervisor, 'supervisor')
        ->get(route('supervisor.dashboard'))
        ->assertOk()
        ->assertSee('5 طلاب');
});
