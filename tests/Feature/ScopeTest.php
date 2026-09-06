<?php

use App\Models\Attendance;
use App\Models\Circle;
use App\Models\Guardian;
use App\Models\Manager;
use App\Models\Setting;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Teacher;
use App\Models\User;
use App\Models\UserRole;
use App\Support\Scope;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * How much of the academy a page shows depends on the page, not on which guard
 * happens to answer first.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-09-10 08:00:00');

    // Two programmes, a cohort in each, a student in each cohort.
    $this->programmeA = Stage::factory()->create(['name' => 'البرنامج الأول']);
    $this->programmeB = Stage::factory()->create(['name' => 'البرنامج الثاني']);

    $this->cohortA = Circle::factory()->create(['stage_id' => $this->programmeA->id]);
    $this->cohortB = Circle::factory()->create(['stage_id' => $this->programmeB->id]);

    $this->studentA = Student::factory()->create(['name' => 'سالم', 'circle_id' => $this->cohortA->id]);
    $this->studentB = Student::factory()->create(['name' => 'زياد', 'circle_id' => $this->cohortB->id]);

    $this->teacher = Teacher::factory()->create();
    $this->teacher->circles()->attach($this->cohortA->id);

    $this->supervisor = Supervisor::factory()->create();
    $this->supervisor->stages()->attach($this->programmeA->id);

    $this->manager = Manager::factory()->create();
});

function reachedNames(User $user, string $role): array
{
    return Scope::for($user, $role)
        ->applyToStudents(Student::query())
        ->pluck('name')
        ->all();
}

it('gives a cohort teacher the cohorts he teaches', function () {
    expect(reachedNames($this->teacher, 'teacher'))->toEqualCanonicalizing(['سالم']);
});

it('gives a cohort supervisor every cohort of his programme', function () {
    $second = Circle::factory()->create(['stage_id' => $this->programmeA->id]);
    Student::factory()->create(['name' => 'ماجد', 'circle_id' => $second->id]);

    expect(reachedNames($this->supervisor, 'supervisor'))->toEqualCanonicalizing(['سالم', 'ماجد']);
});

it('gives the centre manager the whole academy', function () {
    expect(reachedNames($this->manager, 'manager'))->toEqualCanonicalizing(['سالم', 'زياد']);
});

it('gives a student himself and nobody else', function () {
    expect(reachedNames($this->studentA, 'student'))->toEqualCanonicalizing(['سالم']);
});

it('gives a guardian his own children', function () {
    $guardian = Guardian::factory()->create();
    $this->studentB->update(['guardian_id' => $guardian->id]);

    expect(reachedNames($guardian, 'guardian'))->toEqualCanonicalizing(['زياد']);
});

it('gives a super administrator everything, whatever page he stands on', function () {
    $admin = Teacher::factory()->create(['is_super_admin' => true]);
    $admin->circles()->attach($this->cohortA->id);

    // A teacher's reach would be one cohort; the mark overrides it.
    expect(reachedNames($admin, 'teacher'))->toEqualCanonicalizing(['سالم', 'زياد']);
});

it('answers for the page, not for whichever guard replies first', function () {
    // The fault this replaces: one person holding two roles, and a chain of
    // guard checks in a fixed order giving him the wrong reach. Here he teaches
    // one cohort and supervises a different programme entirely.
    $both = Teacher::factory()->create();
    $both->circles()->attach($this->cohortA->id);
    $both->roles()->create(['role' => 'supervisor', 'is_approved' => true]);
    $both->stages()->attach($this->programmeB->id);

    expect(reachedNames($both, 'teacher'))->toEqualCanonicalizing(['سالم'])
        ->and(reachedNames($both, 'supervisor'))->toEqualCanonicalizing(['زياد']);
});

describe('the exceeded-limits page', function () {
    beforeEach(function () {
        Setting::setVal('absence_limit', 2);
        Setting::setVal('calculation_period_days', 30);

        foreach ([$this->studentA, $this->studentB] as $student) {
            foreach (['2026-09-01', '2026-09-02', '2026-09-03'] as $date) {
                Attendance::create([
                    'student_id' => $student->id,
                    'circle_id' => $student->circle_id,
                    'teacher_id' => $this->teacher->id,
                    'date' => $date,
                    'status' => 'absent',
                ]);
            }
        }
    });

    it('shows a teacher only his own cohort', function () {
        $this->actingAs($this->teacher, 'teacher')
            ->get(route('teacher.exceeded-limits'))
            ->assertOk()
            ->assertSee('سالم')
            ->assertDontSee('زياد');
    });

    it('shows a supervisor only his own programme', function () {
        $this->actingAs($this->supervisor, 'supervisor')
            ->get(route('supervisor.exceeded-limits'))
            ->assertOk()
            ->assertSee('سالم')
            ->assertDontSee('زياد');
    });

    it('shows the manager everyone', function () {
        $this->actingAs($this->manager, 'manager')
            ->get(route('manager.exceeded-limits'))
            ->assertOk()
            ->assertSee('سالم')
            ->assertSee('زياد');
    });
});

describe('when no page names the role', function () {
    it('falls back to the guard actually signed in', function () {
        // A Livewire update is its own route and names no role; the reach must
        // still be the reader's own.
        $this->actingAs($this->teacher, 'teacher');

        expect(Scope::forRoute('livewire.update')->role())->toBe('teacher');
    });

    it('answers for the narrower role when two are signed in at once', function () {
        // Ambiguity about how much someone may see resolves towards less.
        $this->actingAs($this->manager, 'manager');
        $this->actingAs($this->teacher, 'teacher');

        expect(Scope::forRoute('livewire.update')->role())->toBe('teacher')
            ->and(Scope::forRoute('livewire.update')->reachesAll())->toBeFalse();
    });

    it('still lets the page override the guards when it names a role', function () {
        $this->actingAs($this->manager, 'manager');
        $this->actingAs($this->teacher, 'teacher');

        expect(Scope::forRoute('manager.exceeded-limits')->role())->toBe('manager')
            ->and(Scope::forRoute('manager.exceeded-limits')->reachesAll())->toBeTrue();
    });
});

describe('the status manager', function () {
    it('will not let a teacher reach a student outside his cohorts', function () {
        // Guarded by scope, not by which account he happens to also hold: the
        // acting role used to be read from the guards with the manager first,
        // so a teacher who also held a manager account reached every student
        // here — and skipped the teacher permission check with them.
        expect(Scope::for($this->teacher, 'teacher')
            ->applyToStudents(Student::query())
            ->find($this->studentB->id))->toBeNull();

        expect(Scope::for($this->teacher, 'teacher')
            ->applyToStudents(Student::query())
            ->find($this->studentA->id))->not->toBeNull();
    });
});

it('leaves no component deciding a role by asking the guards in turn', function () {
    // The fault that ran through this whole pass: a chain of guard checks in a
    // fixed order answers for whichever replies first, not for the page. Every
    // one was replaced by Scope, and this keeps them from creeping back.
    $offenders = [];

    foreach (['app', 'resources/views'] as $dir) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dir)));

        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }

            $path = str_replace(base_path().'/', '', $file->getPathname());

            // Scope itself is where the guards are allowed to be asked, and the
            // header layout is unreferenced dead code.
            if (str_contains($path, 'Support/Scope.php') || str_contains($path, 'layouts/app/header')) {
                continue;
            }

            if (preg_match('/guard\(.(teacher|supervisor|manager).\)->check\(\)/', (string) file_get_contents($file->getPathname()))) {
                $offenders[] = $path;
            }
        }
    }

    expect($offenders)->toBe([]);
});

describe('a reach written on the holding', function () {
    it('makes a programme director out of the manager role, with no code', function () {
        // The tier جمعية قلم asked for: a manager's role held over one
        // programme instead of the whole centre.
        $director = Manager::factory()->create(['name' => 'مدير البرنامج']);
        $director->roles()->where('role', 'manager')->update([
            'scope_type' => UserRole::SCOPE_STAGES,
            'scope_ids' => [$this->programmeA->id],
        ]);
        $director->load('roles');

        expect(Scope::for($director, 'manager')->reachesAll())->toBeFalse()
            ->and(reachedNames($director, 'manager'))->toEqualCanonicalizing(['سالم']);
    });

    it('can narrow a supervisor to single cohorts', function () {
        $second = Circle::factory()->create(['stage_id' => $this->programmeA->id]);
        Student::factory()->create(['name' => 'ماجد', 'circle_id' => $second->id]);

        // Without a written reach he holds the whole programme.
        expect(reachedNames($this->supervisor, 'supervisor'))->toEqualCanonicalizing(['سالم', 'ماجد']);

        $this->supervisor->roles()->where('role', 'supervisor')->update([
            'scope_type' => UserRole::SCOPE_CIRCLES,
            'scope_ids' => [$second->id],
        ]);
        $this->supervisor->load('roles');

        expect(reachedNames($this->supervisor, 'supervisor'))->toEqualCanonicalizing(['ماجد']);
    });

    it('can widen a teacher to the whole centre', function () {
        $this->teacher->roles()->where('role', 'teacher')->update([
            'scope_type' => UserRole::SCOPE_ALL,
            'scope_ids' => null,
        ]);
        $this->teacher->load('roles');

        expect(Scope::for($this->teacher, 'teacher')->reachesAll())->toBeTrue()
            ->and(reachedNames($this->teacher, 'teacher'))->toEqualCanonicalizing(['سالم', 'زياد']);
    });

    it('leaves everyone already assigned exactly as they were', function () {
        // Nothing is written on any of these holdings, so the role decides as
        // it always did.
        expect(reachedNames($this->teacher, 'teacher'))->toEqualCanonicalizing(['سالم'])
            ->and(reachedNames($this->manager, 'manager'))->toEqualCanonicalizing(['سالم', 'زياد']);
    });

    it('does not let one role\'s reach spill into another he holds', function () {
        $both = Teacher::factory()->create();
        $both->circles()->attach($this->cohortA->id);
        $both->roles()->create(['role' => 'supervisor', 'is_approved' => true]);
        $both->stages()->attach($this->programmeB->id);

        // Written only on his teaching, and only his teaching widens.
        $both->roles()->where('role', 'teacher')->update([
            'scope_type' => UserRole::SCOPE_ALL,
        ]);
        $both->load('roles');

        expect(Scope::for($both, 'teacher')->reachesAll())->toBeTrue()
            ->and(Scope::for($both, 'supervisor')->reachesAll())->toBeFalse()
            ->and(reachedNames($both, 'supervisor'))->toEqualCanonicalizing(['زياد']);
    });
});
