<?php

use App\Models\AcademicCalendarEvent;
use App\Models\Attendance;
use App\Models\Circle;
use App\Models\Manager;
use App\Models\OdePath;
use App\Models\OdePathDay;
use App\Models\SelfProgramItem;
use App\Models\SelfProgramWeek;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentOdeAchievement;
use App\Models\StudentOdePlan;
use App\Models\StudentSelfProgramEntry;
use App\Models\Teacher;
use App\Services\AcademyActivity;
use App\Support\Scope;
use App\Support\SelfProgramUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * What the academy did, counted.
 *
 * Every figure here was already in the database and none was ever added up.
 * The tests below are written against hand-counted expectations rather than
 * against the service's own arithmetic — a test that recomputes the sum the
 * same way the code does proves only that the code is consistent with itself.
 */
beforeEach(function () {
    Scope::forget();

    $this->today = now('Asia/Riyadh')->toDateString();
    $this->programme = Stage::factory()->create();

    $this->cohortA = Circle::factory()->create(['stage_id' => $this->programme->id]);
    $this->cohortB = Circle::factory()->create(['stage_id' => $this->programme->id]);

    $this->teacher = Teacher::factory()->create();
    $this->manager = Manager::factory()->create();

    // Two sittings a day, three hours and a half in total.
    AcademicCalendarEvent::create([
        'event_name' => 'الفصل الأول',
        'start_date' => now('Asia/Riyadh')->subMonth()->toDateString(),
        'end_date' => now('Asia/Riyadh')->addMonth()->toDateString(),
        'is_attendance_period' => true,
        'weekdays' => [0, 1, 2, 3, 4, 5, 6],
        'stage_ids' => [$this->programme->id],
        'sessions' => [
            ['from' => '17:00', 'to' => '19:00', 'label' => 'الأولى'],
            ['from' => '19:30', 'to' => '21:00', 'label' => 'الثانية'],
        ],
    ]);

    AcademicCalendarEvent::forgetPeriodCache();
});

function mark(Circle $circle, string $date, array $byStatus): void
{
    foreach ($byStatus as $status => $howMany) {
        foreach (range(1, $howMany) as $i) {
            Attendance::create([
                'student_id' => Student::factory()->create(['circle_id' => $circle->id])->id,
                'circle_id' => $circle->id,
                'date' => $date,
                'status' => $status,
            ]);
        }
    }
}

it('counts a lesson as a cohort and a day somebody was marked on', function () {
    // Nothing in the database says a lesson happened; a register taken is the
    // only evidence that a teacher stood in front of a cohort.
    mark($this->cohortA, $this->today, ['present' => 3]);
    mark($this->cohortB, $this->today, ['present' => 2, 'absent' => 1]);
    mark($this->cohortA, now('Asia/Riyadh')->subDay()->toDateString(), ['present' => 1]);

    $activity = new AcademyActivity(Scope::for($this->manager, 'manager'), $this->today);

    // Two cohorts marked today. Yesterday's is a different day and not asked for.
    expect($activity->lessonsHeld())->toBe(2);
});

it('reads the hours from the sittings the stage keeps, once per lesson', function () {
    mark($this->cohortA, $this->today, ['present' => 5]);
    mark($this->cohortB, $this->today, ['present' => 4]);

    // 17:00→19:00 and 19:30→21:00 is three and a half hours, twice over.
    expect((new AcademyActivity(Scope::for($this->manager, 'manager'), $this->today))->lessonHours())
        ->toBe(7.0);
});

it('separates the registers by what they say', function () {
    mark($this->cohortA, $this->today, ['present' => 4, 'late' => 2, 'absent' => 3, 'excused' => 1]);

    $figures = (new AcademyActivity(Scope::for($this->manager, 'manager'), $this->today))->all();

    expect($figures['present'])->toBe(4)
        ->and($figures['late'])->toBe(2)
        ->and($figures['absent'])->toBe(3)
        ->and($figures['excused'])->toBe(1)
        // Late is attendance. A boy who came at ten past is in the room.
        ->and($figures['attended'])->toBe(6)
        ->and($figures['attendance_rate'])->toBe(0.6);
});

it('counts the lines of the mutun that were graded, memorised and revised alike', function () {
    $student = Student::factory()->create(['circle_id' => $this->cohortA->id]);
    $path = OdePath::factory()->create();

    $day = OdePathDay::create([
        'ode_path_id' => $path->id,
        'day_number' => 1,
        'date' => $this->today,
        'from_verse_number' => 1,
        'to_verse_number' => 10,
        'review_from_verse_number' => 20,
        'review_to_verse_number' => 24,
    ]);

    StudentOdeAchievement::create([
        'student_ode_plan_id' => StudentOdePlan::factory()->create([
            'student_id' => $student->id,
            'ode_path_id' => $path->id,
        ])->id,
        'ode_path_day_id' => $day->id,
        'hifz_achievement' => 'ممتاز',
        'hifz_graded_at' => now('Asia/Riyadh'),
        'review_achievement' => 'جيد',
        'review_graded_at' => now('Asia/Riyadh'),
    ]);

    // Ten memorised, five revised — counted inclusively, both ends said aloud.
    expect((new AcademyActivity(Scope::for($this->manager, 'manager'), $this->today))->versesRecited())
        ->toBe(15);
});

it('counts only what was written in pages', function () {
    $student = Student::factory()->create(['circle_id' => $this->cohortA->id]);
    $week = SelfProgramWeek::create([
        'stage_id' => $this->programme->id,
        'circle_id' => $this->cohortA->id,
        'program_type' => 'weekly',
        'week_number' => 1,
        'starts_on' => $this->today,
        'ends_on' => $this->today,
    ]);

    $inPages = SelfProgramItem::create([
        'self_program_week_id' => $week->id, 'track' => 'المقروء',
        'description' => 'قراءة', 'target_amount' => 10, 'unit' => SelfProgramUnit::PAGE,
    ]);

    $inMinutes = SelfProgramItem::create([
        'self_program_week_id' => $week->id, 'track' => 'المسموع',
        'description' => 'استماع', 'target_amount' => 30, 'unit' => SelfProgramUnit::MINUTE,
    ]);

    // A second reading item rather than a second entry on the first: the table
    // holds one entry per item per day, which is the shape of the programme —
    // a boy records what he did of a thing, once.
    $alsoPages = SelfProgramItem::create([
        'self_program_week_id' => $week->id, 'track' => 'الورد',
        'description' => 'ورد', 'target_amount' => 5, 'unit' => SelfProgramUnit::PAGE,
    ]);

    foreach ([[$inPages, 7], [$alsoPages, 5], [$inMinutes, 45]] as [$item, $amount]) {
        StudentSelfProgramEntry::create([
            'student_id' => $student->id,
            'self_program_item_id' => $item->id,
            'entry_date' => $this->today,
            'amount_done' => $amount,
        ]);
    }

    // Twelve pages. The forty-five minutes are not pages and adding them would
    // be adding a duration to a count.
    expect((new AcademyActivity(Scope::for($this->manager, 'manager'), $this->today))->pagesRead())
        ->toBe(12);
});

/**
 * The figures that only mean something beside another.
 *
 * «Two hundred pages» says nothing; «two hundred pages by sixty students across
 * twelve lessons» is a sentence somebody can act on. These are the reason the
 * six live in one class.
 */
it('relates the figures to each other', function () {
    mark($this->cohortA, $this->today, ['present' => 8, 'absent' => 2]);

    $student = Student::factory()->create(['circle_id' => $this->cohortA->id]);
    $week = SelfProgramWeek::create([
        'stage_id' => $this->programme->id,
        'circle_id' => $this->cohortA->id,
        'program_type' => 'weekly',
        'week_number' => 1,
        'starts_on' => $this->today,
        'ends_on' => $this->today,
    ]);
    $item = SelfProgramItem::create([
        'self_program_week_id' => $week->id, 'track' => 'المقروء',
        'description' => 'قراءة', 'target_amount' => 10, 'unit' => SelfProgramUnit::PAGE,
    ]);

    StudentSelfProgramEntry::create([
        'student_id' => $student->id, 'self_program_item_id' => $item->id,
        'entry_date' => $this->today, 'amount_done' => 24,
    ]);

    $figures = (new AcademyActivity(Scope::for($this->manager, 'manager'), $this->today))->all();

    expect($figures['lessons_held'])->toBe(1)
        ->and($figures['attended'])->toBe(8)
        ->and($figures['attended_per_lesson'])->toBe(8.0)
        ->and($figures['pages_read'])->toBe(24)
        ->and($figures['pages_per_attendee'])->toBe(3.0);
});

/**
 * A day nobody attended is silence, not failure.
 *
 * Zero pages a head reads as a verdict on the students. There were no students.
 */
it('says nothing rather than zero when there is nothing to divide by', function () {
    $figures = (new AcademyActivity(Scope::for($this->manager, 'manager'), $this->today))->all();

    expect($figures['lessons_held'])->toBe(0)
        ->and($figures['attended_per_lesson'])->toBeNull()
        ->and($figures['pages_per_attendee'])->toBeNull()
        ->and($figures['attendance_rate'])->toBeNull();
});

/**
 * Every figure is counted inside the reach of whoever asked.
 *
 * This is the failure the class exists to make impossible: six numbers, each
 * with its own query, each of which could have forgotten to narrow. A teacher
 * shown the academy's attendance reads his own cohort's rate as catastrophic or
 * excellent depending on everybody else's week.
 */
it('counts each figure inside the asker\'s reach', function () {
    $mine = $this->cohortA;
    $somebody_elses = $this->cohortB;

    mark($mine, $this->today, ['present' => 3]);
    mark($somebody_elses, $this->today, ['present' => 9]);

    $teacher = Teacher::factory()->create();
    $teacher->circles()->attach($mine->id);

    Scope::forget();

    $his = (new AcademyActivity(Scope::for($teacher, 'teacher'), $this->today))->all();
    $whole = (new AcademyActivity(Scope::for($this->manager, 'manager'), $this->today))->all();

    expect($his['attended'])->toBe(3)
        ->and($his['lessons_held'])->toBe(1)
        // And the manager, who answers for the academy, sees both.
        ->and($whole['attended'])->toBe(12)
        ->and($whole['lessons_held'])->toBe(2);
});
