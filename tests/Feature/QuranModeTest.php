<?php

use App\Models\Circle;
use App\Models\SelfProgramItem;
use App\Models\SelfProgramTrack;
use App\Models\SelfProgramWeek;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentPlan;
use App\Models\StudentPlanDay;
use App\Models\StudentSelfProgramEntry;
use App\Models\Teacher;
use App\Services\SelfProgramBridge;
use App\Services\SelfProgramService;
use App\Support\QuranicStudent;
use App\Support\QuranMode;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * The three ways a cohort runs its Quran.
 *
 * `is_quranic` was answering two questions at once — does it recite, and does it
 * keep a day-by-day plan — so a حلقة that hears its students without writing
 * such a plan had nowhere to stand.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-09-06 08:00:00');

    $this->stage = Stage::factory()->create();

    $surahId = DB::table('surahs')->insertGetId([
        'number' => 1, 'name_arabic' => 'الاختبار', 'name_simple' => 'test',
        'revelation_place' => 'makkah', 'revelation_order' => 1, 'verses_count' => 20,
        'start_page' => 1, 'end_page' => 4,
    ]);

    for ($i = 1; $i <= 20; $i++) {
        DB::table('ayahs')->insert([
            'id' => $i, 'surah_id' => $surahId, 'verse_number' => $i, 'verse_key' => "1:{$i}",
            'juz_number' => 1, 'hizb_number' => 1, 'rub_number' => 1,
            'page_number' => (int) ceil($i / 5), 'ruku_number' => 1, 'manzil_number' => 1,
            'text_uthmani' => 'نص',
        ]);
    }

    $this->week = SelfProgramWeek::create([
        'stage_id' => $this->stage->id,
        'program_type' => SelfProgramWeek::TYPE_SELF,
        'week_number' => 1,
        'starts_on' => '2026-09-06',
        'ends_on' => '2026-09-12',
    ]);

    $this->wird = SelfProgramItem::create([
        'self_program_week_id' => $this->week->id,
        'track' => SelfProgramTrack::QURAN_WIRD,
        'target_amount' => 10,
        'unit' => 'صفحة',
    ]);
});

function cohortOn(QuranMode $mode, Stage $stage): Circle
{
    return Circle::factory()->create(['stage_id' => $stage->id, 'quran_mode' => $mode]);
}

it('leaves every cohort where it already was', function () {
    // A cohort written without a word about the mode is what it has always
    // been: a حلقة that keeps a plan.
    $said = Circle::factory()->create(['stage_id' => $this->stage->id]);

    expect($said->fresh()->quranMode())->toBe(QuranMode::Detailed);

    // And one written with the old switch off lands on «إجمالي», not on «بلا»:
    // it still had its wird and still wrote it itself, which is what «إجمالي»
    // is. Nobody is in «بلا» until somebody chooses it.
    $cohort = Circle::factory()->create(['stage_id' => $this->stage->id, 'is_quranic' => false]);

    expect($cohort->fresh()->quranMode())->toBe(QuranMode::Summary);
});

it('keeps the old switch answering the question it always answered', function () {
    // `is_quranic` meant «does it keep a day-by-day plan», and every reader of
    // it goes on reading it correctly.
    $circle = cohortOn(QuranMode::Summary, $this->stage);
    expect($circle->is_quranic)->toBeFalse();

    $circle->update(['quran_mode' => QuranMode::Detailed]);
    expect($circle->fresh()->is_quranic)->toBeTrue();

    $circle->update(['quran_mode' => QuranMode::None]);
    expect($circle->fresh()->is_quranic)->toBeFalse();

    // And a screen that still writes the old switch does not leave the mode lying.
    $circle->update(['is_quranic' => true]);
    expect($circle->fresh()->quranMode())->toBe(QuranMode::Detailed);
});

it('shows the day-by-day pages to the detailed cohort alone', function () {
    $detailed = Student::factory()->create(['circle_id' => cohortOn(QuranMode::Detailed, $this->stage)->id]);
    $summary = Student::factory()->create(['circle_id' => cohortOn(QuranMode::Summary, $this->stage)->id]);
    $none = Student::factory()->create(['circle_id' => cohortOn(QuranMode::None, $this->stage)->id]);

    QuranicStudent::forget();
    expect(QuranicStudent::applies($detailed))->toBeTrue();

    QuranicStudent::forget();
    // He recites, but there is no plan to show him — the page would be empty
    // and would ask him to grade days nobody wrote.
    expect(QuranicStudent::applies($summary))->toBeFalse();
    expect(QuranicStudent::mode($summary))->toBe(QuranMode::Summary);

    QuranicStudent::forget();
    expect(QuranicStudent::applies($none))->toBeFalse();
});

it('withholds the plan pages from a summary cohort', function () {
    $student = Student::factory()->create(['circle_id' => cohortOn(QuranMode::Summary, $this->stage)->id]);

    QuranicStudent::forget();

    expect(QuranicStudent::withholds($student, 'student.plan'))->toBeTrue();
    expect(QuranicStudent::withholds($student, 'student.hifz'))->toBeTrue();

    // The self programme is his, and is not withheld from anybody.
    expect(QuranicStudent::withholds($student, 'student.self-program'))->toBeFalse();
});

it('writes the wird from a graded day only where a plan is kept', function () {
    foreach ([QuranMode::Detailed, QuranMode::Summary] as $mode) {
        StudentSelfProgramEntry::query()->delete();

        $student = Student::factory()->create([
            'circle_id' => cohortOn($mode, $this->stage)->id,
            'stage_id' => $this->stage->id,
        ]);

        $plan = StudentPlan::create([
            'student_id' => $student->id,
            'teacher_id' => Teacher::factory()->create()->id,
            'start_date' => '2026-09-06',
            'days_count' => 5,
            'active_days' => json_encode([0, 1, 2, 3, 4]),
            'plan_type' => 'hifz',
            'status' => 'active',
            'is_approved' => true,
        ]);

        $day = StudentPlanDay::create([
            'student_plan_id' => $plan->id,
            'date' => '2026-09-06',
            'day_name' => 'الأحد',
            'from_ayah_id' => 1,
            'to_ayah_id' => 10,
            'hifz_achievement' => 3,
        ]);

        app(SelfProgramBridge::class)->syncFromPlanDay($day->fresh());

        $written = StudentSelfProgramEntry::where('student_id', $student->id)->count();

        $mode === QuranMode::Detailed
            ? expect($written)->toBe(1, 'التفصيلي يكتب الورد من التقييم')
            : expect($written)->toBe(0, 'الإجمالي لا يُكتب من تقييمٍ لا وجود له');
    }
});

it('lets the student and his teacher write one wird, not two', function () {
    $circle = cohortOn(QuranMode::Summary, $this->stage);
    $student = Student::factory()->create(['circle_id' => $circle->id, 'stage_id' => $this->stage->id]);
    $teacher = Teacher::factory()->create();
    $teacher->circles()->attach($circle->id);

    $service = app(SelfProgramService::class);

    $service->record($student, $this->wird, 3, Carbon::today(), by: $student);
    $service->record($student, $this->wird, 5, Carbon::today(), by: $teacher);

    // Two hands, one reading: a second row would add up and count the same
    // pages twice.
    $entries = StudentSelfProgramEntry::where('student_id', $student->id)->get();

    expect($entries)->toHaveCount(1);
    expect((float) $entries->first()->amount_done)->toBe(5.0);

    // And the row says whose hand wrote it last.
    expect($entries->first()->recordedBy->is($teacher))->toBeTrue();
});

/**
 * One cohort per test on purpose: `Scope` answers «whose cohorts are these»
 * once per container, so three actors inside one test all get the first one's.
 */
it('offers the teacher the wird box only where there is no plan', function (QuranMode $mode, bool $offered) {
    $circle = cohortOn($mode, $this->stage);
    $teacher = Teacher::factory()->create();
    $teacher->circles()->attach($circle->id);

    $screen = Livewire\Livewire::actingAs($teacher, 'teacher')
        ->test('teacher.self-program-manager')
        ->set('circleId', $circle->id);

    expect($screen->instance()->wirdItem !== null)->toBe(
        $offered,
        "الوضع {$mode->value}: خانة الورد ".($offered ? 'يجب أن تظهر' : 'يجب ألا تظهر'),
    );
})->with([
    'تفصيلي — الجسر يكتبه، فيدٌ ثانية تحسبه مرّتين' => [QuranMode::Detailed, false],
    'إجمالي — لا خطة تُقرأ منها، فالمعلّم يكتبه' => [QuranMode::Summary, true],
    'بلا برنامج قرآني — لا ورد أصلاً' => [QuranMode::None, false],
]);

it('lets the teacher choose the mode, and the old switch follows', function () {
    $circle = cohortOn(QuranMode::Detailed, $this->stage);
    $teacher = Teacher::factory()->create();
    $teacher->circles()->attach($circle->id);

    Livewire\Livewire::actingAs($teacher, 'teacher')
        ->test('teacher.self-program-manager')
        ->set('circleId', $circle->id)
        ->set('mode', QuranMode::Summary->value)
        ->call('saveSettings')
        ->assertHasNoErrors();

    expect($circle->fresh()->quranMode())->toBe(QuranMode::Summary);

    // Written on the model rather than by the screen, so one fact has one hand.
    expect($circle->fresh()->is_quranic)->toBeFalse();
});

it('refuses a mode it does not have', function () {
    $circle = cohortOn(QuranMode::Detailed, $this->stage);
    $teacher = Teacher::factory()->create();
    $teacher->circles()->attach($circle->id);

    Livewire\Livewire::actingAs($teacher, 'teacher')
        ->test('teacher.self-program-manager')
        ->set('circleId', $circle->id)
        ->set('mode', 'whatever')
        ->call('saveSettings')
        ->assertHasErrors('mode');

    expect($circle->fresh()->quranMode())->toBe(QuranMode::Detailed);
});
