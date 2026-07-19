<?php

use App\Livewire\Public\ResultsDisplay;
use App\Models\Circle;
use App\Models\GamificationTrack;
use App\Models\GamificationTransaction;
use App\Models\Leaderboard;
use App\Models\Stage;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-07-15 10:00:00'); // Wednesday; week started Sunday 2026-07-12

    $this->stage = Stage::create(['name' => 'المرحلة الأولى']);
    $this->circle = Circle::create(['name' => 'حلقة النور', 'stage_id' => $this->stage->id]);

    $makeStudent = fn (string $email, string $name) => Student::create([
        'name' => $name,
        'email' => $email,
        'password' => bcrypt('password'),
        'circle_id' => $this->circle->id,
        'is_approved' => true,
        'status' => 'active',
    ]);

    $this->studentA = $makeStudent('a@example.com', 'أحمد الأول');
    $this->studentB = $makeStudent('b@example.com', 'باسم الثاني');

    $this->leaderboard = Leaderboard::create([
        'circle_id' => $this->circle->id,
        'title' => 'مسابقة الفضاء',
        'competition_type' => 'gamification',
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-30',
        'is_active' => true,
        'settings' => [],
    ]);
    $this->leaderboard->circles()->attach($this->circle->id);

    // A earned 100 XP early in the competition (before this week) and 30 this week.
    GamificationTransaction::create([
        'leaderboard_id' => $this->leaderboard->id,
        'student_id' => $this->studentA->id,
        'type' => 'earn',
        'amount' => 100,
        'description' => 'نقاط قديمة',
        'created_at' => '2026-07-05 09:00:00',
    ]);
    GamificationTransaction::create([
        'leaderboard_id' => $this->leaderboard->id,
        'student_id' => $this->studentA->id,
        'type' => 'earn',
        'amount' => 30,
        'description' => 'نقاط هذا الأسبوع',
        'created_at' => '2026-07-13 09:00:00',
    ]);

    // B earned 50 XP, all inside the current week.
    GamificationTransaction::create([
        'leaderboard_id' => $this->leaderboard->id,
        'student_id' => $this->studentB->id,
        'type' => 'earn',
        'amount' => 50,
        'description' => 'نقاط هذا الأسبوع',
        'created_at' => '2026-07-13 10:00:00',
    ]);
});

function displayComponent(): Testable
{
    return Livewire::withQueryParams(['leaderboard' => test()->leaderboard->id])
        ->test(ResultsDisplay::class);
}

function slidesByKey($component): array
{
    return collect($component->viewData('slides'))->keyBy('key')->all();
}

it('rejects an unsigned display link', function () {
    $this->get(route('results.display', ['leaderboard' => $this->leaderboard->id]))
        ->assertForbidden();
});

it('opens with a signed link and shows the competition title', function () {
    $this->get(URL::signedRoute('results.display', ['leaderboard' => $this->leaderboard->id]))
        ->assertOk()
        ->assertSee('مسابقة الفضاء')
        ->assertSee('أحمد الأول');
});

it('ranks the full period and the current week separately', function () {
    $slides = slidesByKey(displayComponent());

    $total = $slides['general-total']['rows'];
    expect($total[0]['name'])->toBe('أحمد الأول')
        ->and($total[0]['value'])->toBe(130)
        ->and($total[1]['value'])->toBe(50);

    // This week: B (50) beats A (30) — the ranking reshuffles per range.
    $week = $slides['general-week']['rows'];
    expect($week[0]['name'])->toBe('باسم الثاني')
        ->and($week[0]['value'])->toBe(50)
        ->and($week[1]['value'])->toBe(30);
});

it('builds a pair of slides per track when tracks exist', function () {
    $track = GamificationTrack::create([
        'leaderboard_id' => $this->leaderboard->id,
        'name' => 'مسار المتقدمين',
        'sort_order' => 1,
    ]);
    $track->students()->attach($this->studentA->id);

    $slides = slidesByKey(displayComponent());

    expect($slides)->toHaveKeys(["track-{$track->id}-total", "track-{$track->id}-week"])
        ->and(collect($slides["track-{$track->id}-total"]['rows'])->pluck('name'))
        ->toContain('أحمد الأول')
        ->not->toContain('باسم الثاني');
});

it('ranks attendance discipline by fewest absences', function () {
    foreach (['2026-07-05', '2026-07-06', '2026-07-07'] as $date) {
        DB::table('attendances')->insert([
            ['student_id' => $this->studentA->id, 'circle_id' => $this->circle->id, 'date' => $date, 'status' => 'present', 'created_at' => now(), 'updated_at' => now()],
            ['student_id' => $this->studentB->id, 'circle_id' => $this->circle->id, 'date' => $date, 'status' => $date === '2026-07-05' ? 'absent' : 'present', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    $rows = slidesByKey(displayComponent())['attendance']['rows'];

    expect($rows[0]['name'])->toBe('أحمد الأول')
        ->and($rows[0]['absences'])->toBe(0)
        ->and($rows[1]['name'])->toBe('باسم الثاني')
        ->and($rows[1]['absences'])->toBe(1);
});

it('hides toggled-off slides from the deck', function () {
    $component = displayComponent();

    expect(array_keys(slidesByKey($component)))->toContain('general-week');

    $component->call('toggleSlide', 'general-week');

    expect(array_keys(slidesByKey($component)))->not->toContain('general-week')
        ->and(array_keys(slidesByKey($component)))->toContain('general-total');
});

it('rejects a non-gamification competition', function () {
    $other = Leaderboard::create([
        'circle_id' => $this->circle->id,
        'title' => 'مسابقة عادية',
        'competition_type' => 'standard',
        'start_date' => '2026-07-01',
        'is_active' => true,
        'settings' => [],
    ]);

    $this->get(URL::signedRoute('results.display', ['leaderboard' => $other->id]))
        ->assertNotFound();
});
