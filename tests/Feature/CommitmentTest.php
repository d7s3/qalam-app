<?php

use App\Models\AppNotification;
use App\Models\Ayah;
use App\Models\Circle;
use App\Models\CommitmentAlert;
use App\Models\Manager;
use App\Models\SelfProgramItem;
use App\Models\SelfProgramTrack;
use App\Models\SelfProgramWeek;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentPlan;
use App\Models\StudentPlanDay;
use App\Models\StudentSelfProgramEntry;
use App\Models\Supervisor;
use App\Models\Surah;
use App\Models\Teacher;
use App\Services\CommitmentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Asked for by Faisal, 17 Nov 2026: a bar for the Quran plan and one for the
 * self programme, for each student and each group, over a period he chooses;
 * below 70% in a week, the teacher is asked to act, and two days without a
 * word the supervisor is told.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-11-17 08:00:00'); // a Tuesday; last week is 8–14 Nov

    $this->stage = Stage::factory()->create();
    $this->circle = Circle::factory()->create(['stage_id' => $this->stage->id]);
    $this->teacher = Teacher::factory()->create();
    $this->teacher->circles()->attach($this->circle->id);
    $this->supervisor = Supervisor::factory()->create();
    $this->supervisor->stages()->attach($this->stage->id);
    $this->student = Student::factory()->create(['circle_id' => $this->circle->id, 'stage_id' => $this->stage->id]);

    $surah = Surah::create([
        'number' => 1, 'name_arabic' => 'الفاتحة', 'name_simple' => 'Al-Fatihah',
        'revelation_place' => 'makkah', 'revelation_order' => 5, 'verses_count' => 7,
        'start_page' => 1, 'end_page' => 1,
    ]);
    $this->ayah = Ayah::create([
        'surah_id' => $surah->id, 'verse_number' => 1, 'verse_key' => '1:1',
        'juz_number' => 1, 'hizb_number' => 1, 'rub_number' => 1,
        'page_number' => 1, 'ruku_number' => 1, 'manzil_number' => 1,
        'text_uthmani' => '…',
    ]);

    /** Plan days for a student: each date has memorisation and review; $graded of them fully recited. */
    $this->plan = function (Student $student, array $dates, int $graded, string $status = 'active') {
        $plan = StudentPlan::create([
            'student_id' => $student->id, 'teacher_id' => $this->teacher->id,
            'start_date' => $dates[0], 'days_count' => count($dates),
            'active_days' => json_encode([0, 1, 2, 3, 4]), 'plan_type' => 'hifz_review',
            'status' => $status, 'is_approved' => true,
        ]);

        foreach ($dates as $i => $date) {
            StudentPlanDay::create([
                'student_plan_id' => $plan->id, 'date' => $date, 'day_name' => 'يوم',
                'from_ayah_id' => $this->ayah->id, 'to_ayah_id' => $this->ayah->id,
                'review_from_ayah_id' => $this->ayah->id, 'review_to_ayah_id' => $this->ayah->id,
                'hifz_achievement' => $i < $graded ? 3 : null,
                'review_achievement' => $i < $graded ? 3 : null,
            ]);
        }
    };

    $this->week = SelfProgramWeek::create([
        'stage_id' => $this->stage->id, 'program_type' => SelfProgramWeek::TYPE_SELF,
        'week_number' => 9, 'starts_on' => '2026-11-08', 'ends_on' => '2026-11-14',
    ]);

    $this->item = fn (string $track, float $target, float $done = 0) => tap(
        SelfProgramItem::create(['self_program_week_id' => $this->week->id, 'track' => $track, 'description' => $track, 'target_amount' => $target, 'unit' => 'صفحة']),
        fn (SelfProgramItem $item) => $done > 0 ? StudentSelfProgramEntry::create([
            'student_id' => $this->student->id, 'self_program_item_id' => $item->id,
            'entry_date' => '2026-11-10', 'amount_done' => $done,
        ]) : null,
    );
});

it('counts the plan days due in the period and the ones recited', function () {
    // Four days due last week, one recited; a day this week and an inactive plan do not count.
    ($this->plan)($this->student, ['2026-11-09', '2026-11-10', '2026-11-11', '2026-11-14', '2026-11-17'], 1);
    ($this->plan)($this->student, ['2026-11-09'], 0, 'inactive');

    $row = app(CommitmentService::class)->forStudents(collect([$this->student]), ...CommitmentService::lastWeek())[$this->student->id];

    expect($row['plan'])->toBe(['due' => 8, 'done' => 2, 'owed' => 6, 'percent' => 25.0]);
});

it('counts the self programme without its Quran wird, which the plan measures', function () {
    ($this->item)(SelfProgramTrack::MAQROU, 100, 50);
    ($this->item)(SelfProgramTrack::MASMOU, 60, 60);
    ($this->item)(SelfProgramTrack::QURAN_WIRD, 20, 0);

    $row = app(CommitmentService::class)->forStudents(collect([$this->student]), ...CommitmentService::lastWeek())[$this->student->id];

    expect($row['self'])->toBe(['weeks' => 1, 'owed' => 1, 'percent' => 75.0])
        ->and($row['plan'])->toBeNull();
});

it('reads each period as days ending yesterday', function () {
    expect(array_map(fn ($d) => $d->toDateString(), CommitmentService::range('week')))->toBe(['2026-11-10', '2026-11-16'])
        ->and(array_map(fn ($d) => $d->toDateString(), CommitmentService::range('two_weeks')))->toBe(['2026-11-03', '2026-11-16'])
        ->and(array_map(fn ($d) => $d->toDateString(), CommitmentService::range('program')))->toBe(['2026-11-08', '2026-11-16'])
        ->and(array_map(fn ($d) => $d->toDateString(), CommitmentService::range('custom', '2026-11-01', '2026-12-30')))->toBe(['2026-11-01', '2026-11-16']);
});

it('asks the teacher to act once for a student under the line, and tells the supervisor two days later', function () {
    ($this->plan)($this->student, ['2026-11-09', '2026-11-10'], 1); // 50%
    $keeping = Student::factory()->create(['circle_id' => $this->circle->id]);
    ($this->plan)($keeping, ['2026-11-09'], 1); // 100%

    $this->artisan('commitment:follow-up')->assertSuccessful();
    $this->artisan('commitment:follow-up')->assertSuccessful();

    $alert = CommitmentAlert::sole();

    expect($alert->student_id)->toBe($this->student->id)
        ->and($alert->plan_percent)->toBe(50.0)
        ->and($alert->week_ends_on->toDateString())->toBe('2026-11-14')
        ->and(AppNotification::where('recipient_type', 'teacher')->where('recipient_id', $this->teacher->id)->count())->toBe(1);

    Carbon::setTestNow('2026-11-18 05:45:00');
    $this->artisan('commitment:follow-up');
    expect($alert->fresh()->escalated_at)->toBeNull();

    Carbon::setTestNow('2026-11-19 05:45:00');
    $this->artisan('commitment:follow-up');

    expect($alert->fresh()->escalated_at)->not->toBeNull()
        ->and(AppNotification::where('recipient_type', 'supervisor')->where('recipient_id', $this->supervisor->id)->count())->toBe(1);
});

it('does not tell the supervisor about a student his teacher acted on', function () {
    CommitmentAlert::factory()->create([
        'student_id' => $this->student->id, 'circle_id' => $this->circle->id,
        'notified_at' => now()->subDays(3), 'action' => 'guardian', 'acted_at' => now(),
    ]);

    $this->artisan('commitment:follow-up');

    expect(CommitmentAlert::sole()->escalated_at)->toBeNull();
});

it('shows the teacher his students with both bars and takes what he did', function () {
    ($this->plan)($this->student, ['2026-11-11', '2026-11-12'], 1);
    $alert = CommitmentAlert::factory()->create(['student_id' => $this->student->id, 'circle_id' => $this->circle->id]);

    Livewire::actingAs($this->teacher, 'teacher')
        ->test('shared.commitment', ['role' => 'teacher'])
        ->assertSee($this->student->name)
        ->assertSeeHtml('data-commitment-bar="50"')
        ->assertSee('يحتاج إجراء')
        ->call('act', $alert->id)
        ->assertHasErrors("acts.{$alert->id}.action")
        ->set("acts.{$alert->id}.action", 'guardian')
        ->set("acts.{$alert->id}.note", 'وعد بالتسميع غداً')
        ->call('act', $alert->id)
        ->assertHasNoErrors()
        ->assertSee('تواصلت مع ولي الأمر');

    expect($alert->fresh())
        ->action->toBe('guardian')
        ->note->toBe('وعد بالتسميع غداً')
        ->acted_by->toBe($this->teacher->id);
});

it('lets only the student\'s own teacher record the action', function () {
    $alert = CommitmentAlert::factory()->create(['student_id' => $this->student->id, 'circle_id' => $this->circle->id]);
    $other = Teacher::factory()->create();
    $other->circles()->attach(Circle::factory()->create(['stage_id' => $this->stage->id])->id);

    Livewire::actingAs($other, 'teacher')
        ->test('shared.commitment', ['role' => 'teacher'])
        ->set("acts.{$alert->id}.action", 'guardian')
        ->call('act', $alert->id)
        ->assertForbidden();

    Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('shared.commitment', ['role' => 'supervisor'])
        ->assertSee('بانتظار المعلم')
        ->set("acts.{$alert->id}.action", 'guardian')
        ->call('act', $alert->id)
        ->assertForbidden();

    expect($alert->fresh()->acted_at)->toBeNull();
});

it('opens the page for each of the three offices', function (string $role, string $guard) {
    $user = match ($role) {
        'teacher' => $this->teacher,
        'supervisor' => $this->supervisor,
        default => Manager::factory()->create(),
    };

    $this->actingAs($user, $guard)->get(route("{$role}.commitment"))->assertOk()->assertSee('التزام الطلاب');
})->with([['teacher', 'teacher'], ['supervisor', 'supervisor'], ['manager', 'manager']]);
