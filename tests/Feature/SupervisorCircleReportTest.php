<?php

use App\Livewire\Supervisor\CircleReport as SupervisorCircleReport;
use App\Livewire\Supervisor\StageReport;
use App\Models\Attendance;
use App\Models\Circle;
use App\Models\Hadith;
use App\Models\HadithLine;
use App\Models\HadithPath;
use App\Models\HadithPathDay;
use App\Models\HadithText;
use App\Models\Ode;
use App\Models\OdePath;
use App\Models\OdePathDay;
use App\Models\OdeVerse;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentHadithAchievement;
use App\Models\StudentHadithPlan;
use App\Models\StudentOdeAchievement;
use App\Models\StudentOdePlan;
use App\Models\StudentPlan;
use App\Models\StudentPlanDay;
use App\Models\Supervisor;
use App\Models\Surah;
use App\Models\Teacher;
use App\Services\CircleReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-07-08 10:00:00'); // Wednesday; the week starts Saturday 2026-07-04.

    $this->stage = Stage::factory()->create();
    $this->supervisor = Supervisor::factory()->create();
    $this->supervisor->stages()->attach($this->stage->id);
    $this->circle = Circle::factory()->create(['stage_id' => $this->stage->id]);
    $this->teacher = Teacher::factory()->create();
    $this->student = Student::factory()->create([
        'name' => 'طالب التقرير',
        'circle_id' => $this->circle->id,
        'status' => 'active',
    ]);
});

it('resolves the date range presets', function () {
    [$from, $to] = CircleReportService::resolveRange('this_week');
    expect($from->toDateString())->toBe('2026-07-04');
    expect($to->toDateString())->toBe('2026-07-08');

    [$from, $to] = CircleReportService::resolveRange('last_week');
    expect($from->toDateString())->toBe('2026-07-02');
    expect($to->toDateString())->toBe('2026-07-08');

    [$from, $to] = CircleReportService::resolveRange('this_month');
    expect($from->toDateString())->toBe('2026-07-01');

    [$from, $to] = CircleReportService::resolveRange('last_month');
    expect($from->toDateString())->toBe('2026-06-09');

    // A reversed custom range is swapped into order.
    [$from, $to] = CircleReportService::resolveRange('custom', '2026-07-05', '2026-07-01');
    expect($from->toDateString())->toBe('2026-07-01');
    expect($to->toDateString())->toBe('2026-07-05');
});

it('aggregates quran, attendance, hadith and ode achievements within the range', function () {
    // Seed a surah with ayah ids 1-10: ids 1-5 sit on page 1 and ids 6-10 on page 2.
    $surah = Surah::create([
        'number' => 1, 'name_arabic' => 'سورة', 'name_simple' => 'Surah',
        'revelation_place' => 'makkah', 'revelation_order' => 1, 'verses_count' => 10,
        'start_page' => 1, 'end_page' => 2,
    ]);
    foreach (range(1, 10) as $id) {
        DB::table('ayahs')->insert([
            'id' => $id, 'surah_id' => $surah->id, 'verse_number' => $id, 'verse_key' => "1:{$id}",
            'juz_number' => 1, 'hizb_number' => 1, 'rub_number' => 1, 'page_number' => $id <= 5 ? 1 : 2,
            'ruku_number' => 1, 'manzil_number' => 1, 'text_uthmani' => 'نص',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // Quran: a day graded in range (hifz page 1 + review pages 1-2) and one graded before it.
    $plan = StudentPlan::create([
        'student_id' => $this->student->id,
        'plan_type' => 'hifz',
        'direction' => 'forward',
        'start_date' => '2026-07-01',
        'is_approved' => true,
        'days_count' => 30,
        'active_days' => [0, 1, 2, 3, 4, 5, 6],
    ]);
    StudentPlanDay::create([
        'student_plan_id' => $plan->id, 'date' => '2026-07-06', 'day_name' => 'يوم',
        'from_ayah_id' => 1, 'to_ayah_id' => 5, 'hifz_achievement' => 3, 'hifz_graded_at' => '2026-07-06 09:00:00',
        'review_from_ayah_id' => 1, 'review_to_ayah_id' => 10, 'review_achievement' => 2, 'review_graded_at' => '2026-07-06 09:00:00',
    ]);
    StudentPlanDay::create([
        'student_plan_id' => $plan->id, 'date' => '2026-06-01', 'day_name' => 'يوم',
        'from_ayah_id' => 6, 'to_ayah_id' => 9, 'hifz_achievement' => 3, 'hifz_graded_at' => '2026-06-01 09:00:00',
    ]);

    // Attendance: present + absent in range, one record out of range.
    foreach ([['2026-07-06', 'present'], ['2026-07-07', 'absent'], ['2026-06-01', 'present']] as [$date, $status]) {
        Attendance::create([
            'student_id' => $this->student->id,
            'circle_id' => $this->circle->id,
            'teacher_id' => $this->teacher->id,
            'date' => $date,
            'status' => $status,
        ]);
    }

    // Mutun: a path day covering two hadiths, graded in range.
    $text = HadithText::create(['name' => 'الأربعون النووية']);
    $firstHadith = Hadith::create(['hadith_text_id' => $text->id, 'name' => 'الحديث الأول']);
    $secondHadith = Hadith::create(['hadith_text_id' => $text->id, 'name' => 'الحديث الثاني']);
    HadithLine::create(['hadith_id' => $firstHadith->id, 'line_number' => 1, 'text' => 'نص']);
    HadithLine::create(['hadith_id' => $secondHadith->id, 'line_number' => 1, 'text' => 'نص']);
    $hadithPath = HadithPath::create([
        'hadith_text_id' => $text->id, 'name' => 'مسار المتون', 'memorize_type' => 'hadiths',
        'memorize_amount' => 2, 'start_date' => '2026-07-01', 'end_date' => '2026-08-01',
    ]);
    $hadithDay = HadithPathDay::create([
        'hadith_path_id' => $hadithPath->id, 'day_number' => 1, 'date' => '2026-07-06', 'day_name' => 'يوم',
        'memorize_type' => 'hadiths', 'memorize_amount' => 2,
        'from_hadith_id' => $firstHadith->id, 'to_hadith_id' => $secondHadith->id,
    ]);
    $hadithPlan = StudentHadithPlan::create([
        'student_id' => $this->student->id, 'hadith_path_id' => $hadithPath->id,
        'start_date' => '2026-07-01', 'status' => 'active', 'created_by_role' => 'supervisor',
    ]);
    StudentHadithAchievement::create([
        'student_hadith_plan_id' => $hadithPlan->id, 'hadith_path_day_id' => $hadithDay->id,
        'hifz_achievement' => 3, 'hifz_graded_at' => '2026-07-06 09:30:00',
    ]);

    // Odes: verses 1-3 graded in range, verses 4-5 graded before the range.
    $ode = Ode::create(['name' => 'منظومة الاختبار']);
    foreach (range(1, 5) as $number) {
        OdeVerse::create(['ode_id' => $ode->id, 'verse_number' => $number, 'sadr' => 'صدر', 'ajuz' => 'عجز']);
    }
    $odePath = OdePath::create(['ode_id' => $ode->id, 'name' => 'مسار المنظومة', 'start_date' => '2026-07-01', 'end_date' => '2026-08-01']);
    $inRangeOdeDay = OdePathDay::create([
        'ode_path_id' => $odePath->id, 'day_number' => 1, 'date' => '2026-07-06', 'day_name' => 'يوم',
        'from_verse_number' => 1, 'to_verse_number' => 3,
    ]);
    $outOfRangeOdeDay = OdePathDay::create([
        'ode_path_id' => $odePath->id, 'day_number' => 2, 'date' => '2026-06-01', 'day_name' => 'يوم',
        'from_verse_number' => 4, 'to_verse_number' => 5,
    ]);
    $odePlan = StudentOdePlan::create([
        'student_id' => $this->student->id, 'ode_path_id' => $odePath->id,
        'start_date' => '2026-07-01', 'status' => 'active', 'created_by_role' => 'supervisor',
    ]);
    StudentOdeAchievement::create([
        'student_ode_plan_id' => $odePlan->id, 'ode_path_day_id' => $inRangeOdeDay->id,
        'hifz_achievement' => 3, 'hifz_graded_at' => '2026-07-06 10:00:00',
    ]);
    StudentOdeAchievement::create([
        'student_ode_plan_id' => $odePlan->id, 'ode_path_day_id' => $outOfRangeOdeDay->id,
        'hifz_achievement' => 3, 'hifz_graded_at' => '2026-06-01 10:00:00',
    ]);

    [$from, $to] = CircleReportService::resolveRange('this_week');
    $report = CircleReportService::build(CircleReportService::studentsForCircle($this->circle), $from, $to);

    expect($report['totals']['hifz'])->toMatchArray(['pages' => 1, 'days' => 1, 'average' => 3.0]);
    expect($report['totals']['review'])->toMatchArray(['pages' => 2, 'days' => 1, 'average' => 2.0]);
    expect($report['totals']['attendance'])->toMatchArray(['present' => 1, 'absent' => 1, 'total' => 2, 'rate' => 50]);
    expect($report['totals']['hadiths'])->toBe(2);
    expect($report['totals']['verses'])->toBe(3);

    $row = $report['perStudent']->first(fn ($r) => $r['student']->id === $this->student->id);
    expect($row)->toMatchArray(['hifz_pages' => 1, 'review_pages' => 2, 'hadiths' => 2, 'verses' => 3]);
});

it('opens the report page for a circle within the supervisor stages', function () {
    $this->actingAs($this->supervisor, 'supervisor');

    $this->get(route('supervisor.circles.report', $this->circle->id))
        ->assertSuccessful()
        ->assertSee('تقرير الإنجاز')
        ->assertSee($this->circle->name)
        ->assertSee('نسخ رابط المشاركة');
});

it('rejects a circle outside the supervisor stages', function () {
    $otherCircle = Circle::factory()->create(['stage_id' => Stage::factory()->create()->id]);

    $this->actingAs($this->supervisor, 'supervisor');

    $this->get(route('supervisor.circles.report', $otherCircle->id))->assertNotFound();
});

it('includes circle-less stage students when scoped to the whole stage', function () {
    Student::factory()->create([
        'name' => 'طالب بلا حلقة',
        'circle_id' => null,
        'stage_id' => $this->stage->id,
        'status' => 'active',
    ]);

    $this->actingAs($this->supervisor, 'supervisor');

    Livewire::test(SupervisorCircleReport::class, ['circleId' => $this->circle->id])
        ->set('scope', 'stage')
        ->assertSee('طالب بلا حلقة')
        ->assertSee('طالب التقرير');
});

it('filters the report to a single student', function () {
    $other = Student::factory()->create([
        'name' => 'طالب آخر',
        'circle_id' => $this->circle->id,
        'status' => 'active',
    ]);

    $this->actingAs($this->supervisor, 'supervisor');

    Livewire::test(SupervisorCircleReport::class, ['circleId' => $this->circle->id])
        ->set('studentId', (string) $other->id)
        ->assertSee('الطالب: طالب آخر');
});

it('opens the stage report page for a supervisor stage', function () {
    $this->actingAs($this->supervisor, 'supervisor');

    $this->get(route('supervisor.stages.report', $this->stage->id))
        ->assertSuccessful()
        ->assertSee('تقرير الإنجاز')
        ->assertSee('مرحلة '.$this->stage->name)
        ->assertSee('طالب التقرير')
        ->assertSee('نسخ رابط المشاركة');
});

it('rejects a stage report outside the supervisor stages', function () {
    $foreignStage = Stage::factory()->create();

    $this->actingAs($this->supervisor, 'supervisor');

    $this->get(route('supervisor.stages.report', $foreignStage->id))->assertNotFound();
});

it('narrows the stage report to a single circle', function () {
    $secondCircle = Circle::factory()->create(['stage_id' => $this->stage->id]);
    Student::factory()->create([
        'name' => 'طالب الحلقة الثانية',
        'circle_id' => $secondCircle->id,
        'status' => 'active',
    ]);

    $this->actingAs($this->supervisor, 'supervisor');

    Livewire::test(StageReport::class, ['stageId' => $this->stage->id])
        ->assertSee('طالب الحلقة الثانية')
        ->set('circleId', (string) $this->circle->id)
        ->assertSee('طالب التقرير')
        ->assertDontSee('طالب الحلقة الثانية');
});

it('forces light mode on the report pages only', function () {
    $this->actingAs($this->supervisor, 'supervisor');

    $forceLightScript = 'observer.observe(document.documentElement';

    $this->get(route('supervisor.circles.report', $this->circle->id))
        ->assertSuccessful()
        ->assertSee($forceLightScript, false);

    $this->get(route('supervisor.stages.report', $this->stage->id))
        ->assertSuccessful()
        ->assertSee($forceLightScript, false);

    $this->get(route('supervisor.circles'))
        ->assertSuccessful()
        ->assertDontSee($forceLightScript, false);
});

it('renders the shared public stage report with a valid signature', function () {
    [$from, $to] = CircleReportService::resolveRange('this_week');

    $signedUrl = URL::signedRoute('reports.circle', [
        'stage' => $this->stage->id,
        'from' => $from->toDateString(),
        'to' => $to->toDateString(),
    ]);

    $this->get($signedUrl)
        ->assertSuccessful()
        ->assertSee('مرحلة '.$this->stage->name)
        ->assertSee('طالب التقرير');
});

it('renders the shared public report only with a valid signature', function () {
    [$from, $to] = CircleReportService::resolveRange('this_week');

    $signedUrl = URL::signedRoute('reports.circle', [
        'circle' => $this->circle->id,
        'scope' => 'circle',
        'from' => $from->toDateString(),
        'to' => $to->toDateString(),
    ]);

    $this->get($signedUrl)
        ->assertSuccessful()
        ->assertSee('تقرير الإنجاز')
        ->assertSee($this->circle->name)
        ->assertSee('طالب التقرير');

    $this->get(route('reports.circle', [
        'circle' => $this->circle->id,
        'scope' => 'circle',
        'from' => $from->toDateString(),
        'to' => $to->toDateString(),
    ]))->assertForbidden();
});
