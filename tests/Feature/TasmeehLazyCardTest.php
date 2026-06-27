<?php

use App\Models\Ayah;
use App\Models\Circle;
use App\Models\Student;
use App\Models\StudentPlan;
use App\Models\StudentPlanDay;
use App\Models\Surah;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Surah::create([
        'id' => 1, 'number' => 1, 'name_arabic' => 'الفاتحة', 'name_simple' => 'Al-Fatihah',
        'revelation_place' => 'makkah', 'revelation_order' => 1, 'verses_count' => 7,
        'start_page' => 1, 'end_page' => 1,
    ]);
    Ayah::create([
        'id' => 1, 'surah_id' => 1, 'verse_number' => 1, 'page_number' => 1,
        'line_number_start' => 1, 'line_number_end' => 1, 'verse_key' => '1:1',
        'juz_number' => 1, 'hizb_number' => 1, 'rub_number' => 1, 'ruku_number' => 1,
        'manzil_number' => 1, 'text_uthmani' => 'بسم الله',
    ]);

    $this->teacher = Teacher::factory()->create();
    $this->circle = Circle::factory()->create();
    $this->teacher->circles()->attach($this->circle->id);

    $this->student = Student::factory()->create([
        'circle_id' => $this->circle->id,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $this->plan = StudentPlan::create([
        'student_id' => $this->student->id,
        'teacher_id' => $this->teacher->id,
        'start_date' => now()->subDays(2),
        'days_count' => 5,
        'active_days' => [0, 1, 2, 3, 4, 5, 6],
        'description' => 'Test Plan',
        'status' => 'active',
        'plan_type' => 'hifz_review',
        'direction' => 'forward',
        'is_approved' => true,
        'created_by_role' => 'teacher',
    ]);

    foreach (range(0, 4) as $i) {
        StudentPlanDay::create([
            'student_plan_id' => $this->plan->id,
            'date' => now()->subDays(2)->addDays($i)->format('Y-m-d'),
            'day_name' => now()->subDays(2)->addDays($i)->dayName,
            'from_ayah_id' => 1,
            'to_ayah_id' => 1,
            'review_from_ayah_id' => 1,
            'review_to_ayah_id' => 1,
            'hifz_achievement' => null,
            'review_achievement' => null,
        ]);
    }

    $this->actingAs($this->teacher, 'teacher');
});

it('renders the student tasmeeh card standalone without the parent preload caches', function () {
    // Simulates a lazy load: the parent's app()->instance(...) caches are NOT set,
    // so the card must fall back to fetching its own plan days.
    expect(app()->bound('tasmeeh_days_cache'))->toBeFalse();

    $sPlans = StudentPlan::where('student_id', $this->student->id)->latest()->get();

    Livewire::test('teacher.⚡student-tasmeeh-card', [
        'student' => $this->student,
        'sPlans' => $sPlans,
        'activePlanId' => $this->plan->id,
    ])->assertOk();
});

it('renders the tasmeeh-manager with lazy card placeholders instead of full cards', function () {
    $html = Livewire::test('teacher.⚡tasmeeh-manager')->html();

    // The lazy placeholder (from the card's placeholder() method) is shown
    // instead of the full, heavy card markup on the initial render.
    expect($html)->toContain('animate-pulse');
});

it('keeps the tasmeeh-manager initial render cheap (no per-card plan-day queries)', function () {
    DB::enableQueryLog();
    Livewire::test('teacher.⚡tasmeeh-manager');
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // No eager preload of every plan's days with the 4 ayah relations anymore.
    $loadedAllPlanDaysWithAyahs = collect($queries)->contains(
        fn ($q) => str_contains($q['query'], 'student_plan_days')
            && str_contains($q['query'], 'review_from_ayah_id')
    );

    expect($loadedAllPlanDaysWithAyahs)->toBeFalse();
});
