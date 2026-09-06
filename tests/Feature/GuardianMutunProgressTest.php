<?php

use App\Models\Circle;
use App\Models\Guardian;
use App\Models\Hadith;
use App\Models\HadithChapter;
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
use App\Services\MutunProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->stage = Stage::create(['name' => 'مرحلة المتون']);
    $this->circle = Circle::create(['name' => 'دفعة المتون', 'stage_id' => $this->stage->id]);

    $this->guardian = Guardian::factory()->create(['is_approved' => true]);
    $this->student = Student::factory()->create([
        'name' => 'طالب المتون',
        'guardian_id' => $this->guardian->id,
        'circle_id' => $this->circle->id,
    ]);
});

function seedHadithPath(): array
{
    $text = HadithText::create(['name' => 'الأربعون النووية', 'description' => 'متن']);
    $chapter = HadithChapter::create(['hadith_text_id' => $text->id, 'name' => 'كتاب الإيمان']);

    $hadiths = collect();
    foreach (['الأعمال بالنيات', 'مراتب الدين', 'أركان الإسلام'] as $index => $name) {
        $hadith = Hadith::create([
            'hadith_chapter_id' => $chapter->id,
            'name' => $name,
            'sanad' => 'سند الحديث',
            'ruling' => 'صحيح',
        ]);
        foreach ([1, 2] as $lineNumber) {
            HadithLine::create([
                'hadith_id' => $hadith->id,
                'line_number' => $lineNumber,
                'text' => "نص السطر {$lineNumber} من الحديث {$index}",
            ]);
        }
        $hadiths->push($hadith);
    }

    $path = HadithPath::create([
        'name' => 'مسار الأربعين',
        'hadith_text_id' => $text->id,
        'memorize_type' => 'hadiths',
        'memorize_amount' => 1,
        'start_date' => '2026-07-01',
    ]);

    return [$path, $hadiths];
}

function seedOdePath(): array
{
    $ode = Ode::create(['name' => 'تحفة الأطفال']);
    foreach (range(1, 10) as $i) {
        OdeVerse::create([
            'ode_id' => $ode->id,
            'verse_number' => $i,
            'sadr' => "صدر البيت {$i}",
            'ajuz' => "عجز البيت {$i}",
        ]);
    }

    $path = OdePath::create([
        'ode_id' => $ode->id,
        'name' => 'مسار التحفة',
        'start_date' => '2026-07-01',
    ]);

    return [$ode, $path];
}

it('marks hadiths in graded ranges as completed', function () {
    [$path, $hadiths] = seedHadithPath();

    $plan = StudentHadithPlan::create([
        'student_id' => $this->student->id,
        'hadith_path_id' => $path->id,
        'start_date' => '2026-07-01',
        'status' => 'active',
        'created_by_role' => 'supervisor',
    ]);

    $gradedDay = HadithPathDay::create([
        'hadith_path_id' => $path->id,
        'day_number' => 1,
        'date' => '2026-07-01',
        'memorize_type' => 'hadiths',
        'from_hadith_id' => $hadiths[0]->id,
        'to_hadith_id' => $hadiths[1]->id,
    ]);
    $ungradedDay = HadithPathDay::create([
        'hadith_path_id' => $path->id,
        'day_number' => 2,
        'date' => '2026-07-02',
        'memorize_type' => 'hadiths',
        'from_hadith_id' => $hadiths[2]->id,
        'to_hadith_id' => $hadiths[2]->id,
    ]);

    StudentHadithAchievement::create([
        'student_hadith_plan_id' => $plan->id,
        'hadith_path_day_id' => $gradedDay->id,
        'hifz_achievement' => 3,
    ]);
    StudentHadithAchievement::create([
        'student_hadith_plan_id' => $plan->id,
        'hadith_path_day_id' => $ungradedDay->id,
        'hifz_achievement' => null,
    ]);

    $progress = MutunProgressService::hadithPlansProgress($this->student);

    expect($progress)->toHaveCount(1);
    $item = $progress->first();
    expect($item['completedHadithIds'])->toBe([$hadiths[0]->id, $hadiths[1]->id]);
    expect($item['completedCount'])->toBe(2);
    expect($item['totalCount'])->toBe(3);
});

it('completes a hadith memorized line-by-line only when every line is graded', function () {
    [$path, $hadiths] = seedHadithPath();

    $plan = StudentHadithPlan::create([
        'student_id' => $this->student->id,
        'hadith_path_id' => $path->id,
        'start_date' => '2026-07-01',
        'status' => 'active',
        'created_by_role' => 'supervisor',
    ]);

    // Hadith 1: both lines graded. Hadith 2: only line 1 graded.
    $fullDay = HadithPathDay::create([
        'hadith_path_id' => $path->id,
        'day_number' => 1,
        'memorize_type' => 'lines',
        'from_hadith_id' => $hadiths[0]->id,
        'from_line_number' => 1,
        'to_line_number' => 2,
    ]);
    $partialDay = HadithPathDay::create([
        'hadith_path_id' => $path->id,
        'day_number' => 2,
        'memorize_type' => 'lines',
        'from_hadith_id' => $hadiths[1]->id,
        'from_line_number' => 1,
        'to_line_number' => 1,
    ]);

    foreach ([$fullDay, $partialDay] as $day) {
        StudentHadithAchievement::create([
            'student_hadith_plan_id' => $plan->id,
            'hadith_path_day_id' => $day->id,
            'hifz_achievement' => 3,
        ]);
    }

    $item = MutunProgressService::hadithPlansProgress($this->student)->first();

    expect($item['completedHadithIds'])->toBe([$hadiths[0]->id]);
    expect($item['completedLines'][$hadiths[1]->id])->toBe([1]);
});

it('marks ode verses in graded ranges as completed', function () {
    [$ode, $path] = seedOdePath();

    $plan = StudentOdePlan::create([
        'student_id' => $this->student->id,
        'ode_path_id' => $path->id,
        'start_date' => '2026-07-01',
        'status' => 'active',
        'created_by_role' => 'supervisor',
    ]);

    $gradedDay = OdePathDay::create([
        'ode_path_id' => $path->id,
        'day_number' => 1,
        'from_verse_number' => 1,
        'to_verse_number' => 4,
    ]);
    $ungradedDay = OdePathDay::create([
        'ode_path_id' => $path->id,
        'day_number' => 2,
        'from_verse_number' => 5,
        'to_verse_number' => 8,
    ]);

    StudentOdeAchievement::create([
        'student_ode_plan_id' => $plan->id,
        'ode_path_day_id' => $gradedDay->id,
        'hifz_achievement' => 2,
    ]);
    StudentOdeAchievement::create([
        'student_ode_plan_id' => $plan->id,
        'ode_path_day_id' => $ungradedDay->id,
        'hifz_achievement' => null,
    ]);

    $progress = MutunProgressService::odePlansProgress($this->student);

    expect($progress)->toHaveCount(1);
    $item = $progress->first();
    expect($item['completedVerseNumbers'])->toBe([1, 2, 3, 4]);
    expect($item['completedCount'])->toBe(4);
    expect($item['totalCount'])->toBe(10);
});

it('ignores inactive hadith and ode plans', function () {
    [$hadithPath] = seedHadithPath();
    [, $odePath] = seedOdePath();

    StudentHadithPlan::create([
        'student_id' => $this->student->id,
        'hadith_path_id' => $hadithPath->id,
        'start_date' => '2026-07-01',
        'status' => 'completed',
        'created_by_role' => 'supervisor',
    ]);
    StudentOdePlan::create([
        'student_id' => $this->student->id,
        'ode_path_id' => $odePath->id,
        'start_date' => '2026-07-01',
        'status' => 'stopped',
        'created_by_role' => 'supervisor',
    ]);

    expect(MutunProgressService::hadithPlansProgress($this->student))->toBeEmpty();
    expect(MutunProgressService::odePlansProgress($this->student))->toBeEmpty();
});

it('renders the mutun and odes section on the guardian student page', function () {
    [$hadithPath, $hadiths] = seedHadithPath();
    [, $odePath] = seedOdePath();

    $hadithPlan = StudentHadithPlan::create([
        'student_id' => $this->student->id,
        'hadith_path_id' => $hadithPath->id,
        'start_date' => '2026-07-01',
        'status' => 'active',
        'created_by_role' => 'supervisor',
    ]);
    StudentOdePlan::create([
        'student_id' => $this->student->id,
        'ode_path_id' => $odePath->id,
        'start_date' => '2026-07-01',
        'status' => 'active',
        'created_by_role' => 'supervisor',
    ]);

    $gradedDay = HadithPathDay::create([
        'hadith_path_id' => $hadithPath->id,
        'day_number' => 1,
        'memorize_type' => 'hadiths',
        'from_hadith_id' => $hadiths[0]->id,
        'to_hadith_id' => $hadiths[0]->id,
    ]);
    StudentHadithAchievement::create([
        'student_hadith_plan_id' => $hadithPlan->id,
        'hadith_path_day_id' => $gradedDay->id,
        'hifz_achievement' => 3,
    ]);

    $this->actingAs($this->guardian, 'guardian');

    $this->get(route('guardian.student', $this->student->id))
        ->assertSuccessful()
        ->assertSee('المتون والمنظومات')
        ->assertSee('مسار الأربعين')
        ->assertSee('إظهار متن الحديث')
        ->assertSee('مسار التحفة')
        ->assertSee('إظهار المنظومة')
        ->assertSee('تم حفظه')
        ->assertSee('صدر البيت 1');
});

it('hides the mutun and odes section when the student has no active plans', function () {
    $this->actingAs($this->guardian, 'guardian');

    $this->get(route('guardian.student', $this->student->id))
        ->assertSuccessful()
        ->assertDontSee('المتون والمنظومات');
});
