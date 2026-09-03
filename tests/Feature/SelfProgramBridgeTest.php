<?php

use App\Models\AcademicCalendarEvent;
use App\Models\Circle;
use App\Models\SelfProgramItem;
use App\Models\SelfProgramWeek;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentPlan;
use App\Models\StudentPlanDay;
use App\Models\StudentSelfProgramEntry;
use App\Models\Teacher;
use App\Services\SelfProgramService;
use App\Support\SelfProgramTrack;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * A student in a Quranic circle confirms his wird once, to his teacher. What the
 * teacher grades is written into his self programme for him — and stays true to
 * the grade, so revising it revises the figure and withdrawing it removes it.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-09-06 08:00:00');

    $this->stage = Stage::factory()->create();
    $this->circle = Circle::factory()->create(['stage_id' => $this->stage->id]);
    $this->student = Student::factory()->create(['circle_id' => $this->circle->id]);
    $this->teacher = Teacher::factory()->create();

    // Four pages of five ayahs each, so a range's page count is easy to read.
    $surahId = DB::table('surahs')->insertGetId([
        'number' => 1,
        'name_arabic' => 'الاختبار',
        'name_simple' => 'test',
        'revelation_place' => 'makkah',
        'revelation_order' => 1,
        'verses_count' => 20,
        'start_page' => 1,
        'end_page' => 4,
    ]);
    $rows = [];
    for ($i = 1; $i <= 20; $i++) {
        $rows[] = [
            'id' => $i,
            'surah_id' => $surahId,
            'verse_number' => $i,
            'verse_key' => "1:{$i}",
            'juz_number' => 1,
            'hizb_number' => 1,
            'rub_number' => 1,
            'page_number' => (int) ceil($i / 5),
            'ruku_number' => 1,
            'manzil_number' => 1,
            'text_uthmani' => 'نص',
        ];
    }
    DB::table('ayahs')->insert($rows);

    // Ayah ids run 1..20 alongside the verse numbers, so a verse number is its id.
    $this->ayah = fn (int $n) => $n;

    $this->week = SelfProgramWeek::create([
        'stage_id' => $this->stage->id,
        'program_type' => SelfProgramWeek::TYPE_SELF,
        'week_number' => 1,
        'starts_on' => '2026-09-06',
        'ends_on' => '2026-09-12',
    ]);

    $this->wird = SelfProgramItem::create([
        'self_program_week_id' => $this->week->id,
        'track' => SelfProgramTrack::QuranWird->value,
        'target_amount' => 10,
        'unit' => 'صفحة',
    ]);

    $this->plan = StudentPlan::create([
        'student_id' => $this->student->id,
        'teacher_id' => $this->teacher->id,
        'start_date' => '2026-09-06',
        'days_count' => 7,
        'active_days' => [0, 1, 2, 3, 4, 5, 6],
        'status' => 'active',
        'is_approved' => true,
        'created_by_role' => 'teacher',
    ]);

    $this->service = app(SelfProgramService::class);
});

function bridgePlanDay(array $attributes = []): StudentPlanDay
{
    return StudentPlanDay::create(array_merge([
        'student_plan_id' => test()->plan->id,
        'date' => '2026-09-06',
        'day_name' => 'الأحد',
    ], $attributes));
}

it('writes nothing while the day is only planned', function () {
    bridgePlanDay([
        'from_ayah_id' => ($this->ayah)(1),
        'to_ayah_id' => ($this->ayah)(10),
    ]);

    expect(StudentSelfProgramEntry::count())->toBe(0);
});

it('writes the pages a graded day covers into the wird', function () {
    // Ayahs 1-10 span pages 1 and 2.
    bridgePlanDay([
        'from_ayah_id' => ($this->ayah)(1),
        'to_ayah_id' => ($this->ayah)(10),
        'hifz_achievement' => 3,
    ]);

    $entry = StudentSelfProgramEntry::first();

    expect($entry)->not->toBeNull()
        ->and((float) $entry->amount_done)->toBe(2.0)
        ->and($entry->source)->toBe(StudentSelfProgramEntry::SOURCE_TASMEEH);
});

it('adds memorisation and revision into the one wird figure', function () {
    bridgePlanDay([
        'from_ayah_id' => ($this->ayah)(1),
        'to_ayah_id' => ($this->ayah)(5),      // page 1
        'hifz_achievement' => 3,
        'review_from_ayah_id' => ($this->ayah)(11),
        'review_to_ayah_id' => ($this->ayah)(20), // pages 3 and 4
        'review_achievement' => 2,
    ]);

    expect((float) StudentSelfProgramEntry::first()->amount_done)->toBe(3.0);
});

it('counts only the graded half of a day', function () {
    bridgePlanDay([
        'from_ayah_id' => ($this->ayah)(1),
        'to_ayah_id' => ($this->ayah)(5),
        'hifz_achievement' => 3,
        // Planned for revision but not yet heard.
        'review_from_ayah_id' => ($this->ayah)(11),
        'review_to_ayah_id' => ($this->ayah)(20),
    ]);

    expect((float) StudentSelfProgramEntry::first()->amount_done)->toBe(1.0);
});

it('follows the teacher when he revises the range', function () {
    $day = bridgePlanDay([
        'from_ayah_id' => ($this->ayah)(1),
        'to_ayah_id' => ($this->ayah)(5),
        'hifz_achievement' => 3,
    ]);
    expect((float) StudentSelfProgramEntry::first()->amount_done)->toBe(1.0);

    $day->update(['to_ayah_id' => ($this->ayah)(20)]);

    expect((float) StudentSelfProgramEntry::first()->amount_done)->toBe(4.0);
});

it('takes the figure away when the grade is withdrawn', function () {
    $day = bridgePlanDay([
        'from_ayah_id' => ($this->ayah)(1),
        'to_ayah_id' => ($this->ayah)(10),
        'hifz_achievement' => 3,
    ]);
    expect(StudentSelfProgramEntry::count())->toBe(1);

    $day->update(['hifz_achievement' => null]);

    expect(StudentSelfProgramEntry::count())->toBe(0);
});

it('takes the figure away when the day is deleted', function () {
    $day = bridgePlanDay([
        'from_ayah_id' => ($this->ayah)(1),
        'to_ayah_id' => ($this->ayah)(10),
        'hifz_achievement' => 3,
    ]);

    $day->delete();

    expect(StudentSelfProgramEntry::count())->toBe(0);
});

it('leaves a circle that does not memorise to confirm its own wird', function () {
    $this->circle->update(['is_quranic' => false]);
    $this->student->refresh();

    bridgePlanDay([
        'from_ayah_id' => ($this->ayah)(1),
        'to_ayah_id' => ($this->ayah)(10),
        'hifz_achievement' => 3,
    ]);

    expect(StudentSelfProgramEntry::count())->toBe(0);
});

it('keeps what the student confirmed himself apart from what the grade wrote', function () {
    $this->service->record($this->student, $this->wird, 3, Carbon::parse('2026-09-07'));

    bridgePlanDay([
        'date' => '2026-09-06',
        'from_ayah_id' => ($this->ayah)(1),
        'to_ayah_id' => ($this->ayah)(10),
        'hifz_achievement' => 3,
    ]);

    expect(StudentSelfProgramEntry::count())->toBe(2)
        ->and($this->service->weekProgress($this->student, $this->week->fresh('items'))['overall'])
        // Three pages of his own plus two from the recitation, out of ten.
        ->toBe(50.0);
});

it('writes nothing when no week covers the day', function () {
    bridgePlanDay([
        'date' => '2026-10-20',
        'from_ayah_id' => ($this->ayah)(1),
        'to_ayah_id' => ($this->ayah)(10),
        'hifz_achievement' => 3,
    ]);

    expect(StudentSelfProgramEntry::count())->toBe(0);
});

it('does not let the student add the reading his teacher already recorded', function () {
    // Ayahs 1-10 span two mushaf pages; the bridge writes them.
    bridgePlanDay([
        'from_ayah_id' => ($this->ayah)(1),
        'to_ayah_id' => ($this->ayah)(10),
        'hifz_achievement' => 3,
    ]);

    $before = $this->service->weekProgress($this->student, $this->week->fresh('items'))['tracks'][0]['done'];

    // He opens his page and presses confirm on the wird without changing it.
    Livewire::actingAs($this->student, 'student')
        ->test('student.self-program')
        ->call('save', $this->wird->id)
        ->assertStatus(403);

    $after = $this->service->weekProgress($this->student, $this->week->fresh('items'))['tracks'][0]['done'];

    expect($before)->toBe(2.0)->and($after)->toBe(2.0);
});

it('counts work done on a mid-week holiday towards the days that follow', function () {
    // Tuesday is closed, so it is not one of the week's working days — but a
    // recitation graded on it is still work the student did.
    AcademicCalendarEvent::create([
        'event_name' => 'إجازة',
        'start_date' => '2026-09-01',
        'end_date' => '2026-12-01',
        'is_attendance_period' => true,
        'weekdays' => [1, 2, 3, 4, 5],
        'stage_ids' => [$this->stage->id],
        'excluded_dates' => ['2026-09-08'],
    ]);
    AcademicCalendarEvent::forgetPeriodCache();

    // Ayahs 1-20: four mushaf pages of the week's ten.
    bridgePlanDay([
        'date' => '2026-09-08',
        'from_ayah_id' => ($this->ayah)(1),
        'to_ayah_id' => ($this->ayah)(20),
        'hifz_achievement' => 3,
    ]);

    $plan = $this->service->dailyPlan($this->wird->fresh(), $this->student);

    // Sunday, Monday, Wednesday, Thursday — Tuesday is out.
    expect(array_keys($plan))->toBe(['2026-09-06', '2026-09-07', '2026-09-09', '2026-09-10'])
        // Six pages left over Wednesday and Thursday, not the whole ten again.
        ->and($plan['2026-09-09'])->toBe(3.0);
});

describe('arrears', function () {
    it('shows what a finished week still owes', function () {
        Carbon::setTestNow('2026-09-15 08:00:00');

        $this->service->record($this->student, $this->wird, 4, Carbon::parse('2026-09-08'));

        $arrears = $this->service->arrears($this->student);

        expect($arrears)->toHaveCount(1)
            ->and($arrears[0]['remaining'])->toBe(6.0)
            ->and($arrears[0]['week']->id)->toBe($this->week->id);
    });

    it('says nothing of a week still running', function () {
        expect($this->service->arrears($this->student))->toBe([]);
    });

    it('drops the arrear once it is settled', function () {
        Carbon::setTestNow('2026-09-15 08:00:00');

        $this->service->record($this->student, $this->wird, 10, Carbon::parse('2026-09-15'));

        expect($this->service->arrears($this->student))->toBe([]);
    });

    it('owes nothing for a track that was never asked for', function () {
        Carbon::setTestNow('2026-09-15 08:00:00');

        SelfProgramItem::create([
            'self_program_week_id' => $this->week->id,
            'track' => SelfProgramTrack::Masmou->value,
            'target_amount' => 0,
            'unit' => 'درس',
        ]);
        $this->service->record($this->student, $this->wird, 10, Carbon::parse('2026-09-08'));

        expect($this->service->arrears($this->student))->toBe([]);
    });
});
