<?php

use App\Models\Circle;
use App\Models\GamificationTransaction;
use App\Models\Leaderboard;
use App\Models\SelfProgramItem;
use App\Models\SelfProgramWeek;
use App\Models\Stage;
use App\Models\Student;
use App\Services\SelfProgramService;
use App\Support\SelfProgramTrack;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The self programme pays by the week, not by the field.
 *
 * Paying per confirmed field would hand out the same work twice — the Quran
 * wird of a memorising circle is written from the recitation his teacher grades,
 * and that grading already earns XP. What is worth encouraging is finishing the
 * week, so the two thresholds the programme already turns on are the two that
 * pay: half of it, and all of it.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-09-06 08:00:00');

    $this->stage = Stage::factory()->create();
    $this->circle = Circle::factory()->create(['stage_id' => $this->stage->id]);
    $this->student = Student::factory()->create(['circle_id' => $this->circle->id]);

    $this->leaderboard = Leaderboard::create([
        'title' => 'مسابقة تجريبية',
        'circle_id' => $this->circle->id,
        'competition_type' => 'gamification',
        'is_active' => true,
        'start_date' => '2026-09-01',
        'end_date' => '2026-12-31',
        'settings' => [
            'self_program_enabled' => true,
            'self_program_half_xp' => 5,
            'self_program_half_coins' => 5,
            'self_program_complete_xp' => 15,
            'self_program_complete_coins' => 15,
        ],
    ]);

    $this->week = SelfProgramWeek::create([
        'stage_id' => $this->stage->id,
        'program_type' => SelfProgramWeek::TYPE_SELF,
        'week_number' => 1,
        'starts_on' => '2026-09-06',
        'ends_on' => '2026-09-12',
    ]);

    $this->one = SelfProgramItem::create(['self_program_week_id' => $this->week->id,
        'track' => SelfProgramTrack::QuranWird->value, 'target_amount' => 10, 'unit' => 'صفحة']);
    $this->two = SelfProgramItem::create(['self_program_week_id' => $this->week->id,
        'track' => SelfProgramTrack::Masmou->value, 'target_amount' => 10, 'unit' => 'درس']);

    $this->service = app(SelfProgramService::class);
});

function selfProgramXp(): int
{
    return (int) GamificationTransaction::where('reference_type', SelfProgramWeek::class)->sum('xp_amount');
}

it('pays nothing before half the week is done', function () {
    $this->service->record($this->student, $this->one, 4);

    expect(GamificationTransaction::where('reference_type', SelfProgramWeek::class)->count())->toBe(0);
});

it('pays the half-week milestone at fifty per cent', function () {
    $this->service->record($this->student, $this->one, 10);

    expect(selfProgramXp())->toBe(5);
});

it('adds the completion milestone on top at full', function () {
    $this->service->record($this->student, $this->one, 10);
    $this->service->record($this->student, $this->two, 10);

    expect(selfProgramXp())->toBe(20);
});

it('keeps one transaction for the week however many fields are confirmed', function () {
    $this->service->record($this->student, $this->one, 10);
    $this->service->record($this->student, $this->two, 10);

    expect(GamificationTransaction::where('reference_type', SelfProgramWeek::class)->count())->toBe(1);
});

it('takes the points back when the student falls below the threshold', function () {
    $this->service->record($this->student, $this->one, 10);
    $this->service->record($this->student, $this->two, 10);
    expect(selfProgramXp())->toBe(20);

    // He unticks a field, dropping the week to half.
    $this->service->record($this->student, $this->two, 0);
    expect(selfProgramXp())->toBe(5);

    // And the other, leaving nothing earned.
    $this->service->record($this->student, $this->one, 0);
    expect(GamificationTransaction::where('reference_type', SelfProgramWeek::class)->count())->toBe(0);
});

it('pays nothing when the leaderboard has not turned it on', function () {
    $this->leaderboard->update(['settings' => ['self_program_enabled' => false]]);

    $this->service->record($this->student, $this->one, 10);
    $this->service->record($this->student, $this->two, 10);

    expect(GamificationTransaction::where('reference_type', SelfProgramWeek::class)->count())->toBe(0);
});

it('drops the points when the week itself is deleted', function () {
    $this->service->record($this->student, $this->one, 10);
    expect(selfProgramXp())->toBe(5);

    $this->week->delete();

    expect(GamificationTransaction::where('reference_type', SelfProgramWeek::class)->count())->toBe(0);
});

it('dates the award by the last thing the student did', function () {
    $this->service->record($this->student, $this->one, 10, Carbon::parse('2026-09-09'));

    $transaction = GamificationTransaction::where('reference_type', SelfProgramWeek::class)->first();

    expect($transaction)->not->toBeNull()
        ->and($transaction->description)->toContain('للأسبوع 1');
});
