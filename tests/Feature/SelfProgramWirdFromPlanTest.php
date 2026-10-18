<?php

use App\Models\Circle;
use App\Models\SelfProgramItem;
use App\Models\SelfProgramTrack;
use App\Models\SelfProgramWeek;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentPlan;
use App\Models\StudentSelfProgramEntry;
use App\Models\Surah;
use App\Services\SelfProgramService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * The Quran wird of the self programme is the student's own plan.
 *
 * Decided with Faisal on 18 Oct 2026: once a student has made his Quran plan,
 * the wird asks for what the plan asks that week, day by day, and he moves
 * the bar either by reciting to his teacher or by writing it in himself.
 * Before, the wird stood at «لم يُطلب هذا المجال» unless a supervisor typed a
 * figure that knew nothing of any one boy's plan.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-10-18 09:00:00');

    $this->stage = Stage::factory()->create();
    $this->circle = Circle::factory()->create(['stage_id' => $this->stage->id, 'quran_mode' => 'detailed', 'is_quranic' => true]);
    $this->student = Student::factory()->create(['circle_id' => $this->circle->id, 'stage_id' => $this->stage->id, 'status' => 'active']);

    $surah = Surah::create([
        'number' => 1, 'name_arabic' => 'سورة الاختبار', 'name_simple' => 'Test', 'revelation_place' => 'makkah',
        'revelation_order' => 1, 'verses_count' => 9, 'start_page' => 1, 'end_page' => 3,
    ]);
    foreach (range(1, 9) as $id) {
        DB::table('ayahs')->insert([
            'id' => $id, 'surah_id' => $surah->id, 'verse_number' => $id, 'verse_key' => "1:{$id}",
            'juz_number' => 1, 'hizb_number' => 1, 'rub_number' => 1, 'page_number' => intdiv($id - 1, 3) + 1,
            'ruku_number' => 1, 'manzil_number' => 1, 'text_uthmani' => 'نص', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $this->week = SelfProgramWeek::create([
        'stage_id' => $this->stage->id, 'program_type' => SelfProgramWeek::TYPE_SELF, 'week_number' => 1,
        'starts_on' => '2026-10-18', 'ends_on' => '2026-10-24',
    ]);
    $this->wird = SelfProgramItem::create(['self_program_week_id' => $this->week->id, 'track' => SelfProgramTrack::QURAN_WIRD, 'description' => '', 'target_amount' => 0, 'unit' => 'صفحة']);
    $this->reading = SelfProgramItem::create(['self_program_week_id' => $this->week->id, 'track' => SelfProgramTrack::MAQROU, 'description' => 'كتاب', 'target_amount' => 10, 'unit' => 'صفحة']);

    $this->service = app(SelfProgramService::class);
});

function wirdPlan(Student $student): StudentPlan
{
    $plan = StudentPlan::create([
        'student_id' => $student->id, 'start_date' => '2026-10-18', 'days_count' => 2, 'active_days' => ['Sunday', 'Monday'],
        'plan_type' => 'hifz_review', 'status' => 'active', 'is_approved' => true, 'created_by_role' => 'student',
        'direction' => 'forward', 'review_direction' => 'forward',
    ]);
    // Sunday: page 1 memorised and page 2 revised; Monday: page 3 memorised.
    $plan->days()->create(['date' => '2026-10-18', 'day_name' => 'الأحد', 'from_ayah_id' => 1, 'to_ayah_id' => 3, 'review_from_ayah_id' => 4, 'review_to_ayah_id' => 6]);
    $plan->days()->create(['date' => '2026-10-19', 'day_name' => 'الإثنين', 'from_ayah_id' => 7, 'to_ayah_id' => 9]);

    return $plan;
}

it('asks of the wird what the student\'s plan asks that week', function () {
    wirdPlan($this->student);

    $wird = collect($this->service->weekProgress($this->student, $this->week->fresh('items'))['tracks'])->firstWhere('item.id', $this->wird->id);

    expect($this->service->targetFor($this->wird, $this->student))->toBe(3.0)
        ->and($wird['target'])->toBe(3.0);
});

it('suggests each day the pages that day of his plan holds', function () {
    wirdPlan($this->student);

    $plan = $this->service->dailyPlan($this->wird, $this->student);

    expect($plan['2026-10-18'])->toBe(2.0)
        ->and($plan['2026-10-19'])->toBe(1.0)
        ->and($plan['2026-10-20'])->toBe(0.0);
});

it('leaves the wird to the supervisor\'s figure for a student without a plan', function () {
    expect($this->service->targetFor($this->wird, $this->student))->toBe(0.0);
});

it('lets the student write his wird in himself, and the bar moves', function () {
    wirdPlan($this->student);

    Livewire::actingAs($this->student, 'student')
        ->test('student.self-program')
        ->set('day', '2026-10-18')
        ->set("amounts.{$this->wird->id}", 2)
        ->call('save', $this->wird->id)
        ->assertStatus(200);

    $wird = collect($this->service->weekProgress($this->student, $this->week->fresh('items'))['tracks'])->firstWhere('item.id', $this->wird->id);

    expect($wird['done'])->toBe(2.0);
});

it('counts a day once when he wrote it in and his teacher then graded it', function () {
    $plan = wirdPlan($this->student);
    $this->service->record($this->student, $this->wird, 2, Carbon::parse('2026-10-18'), by: $this->student);

    $plan->days()->whereDate('date', '2026-10-18')->first()->update(['hifz_achievement' => 3, 'review_achievement' => 3]);

    expect(StudentSelfProgramEntry::where('self_program_item_id', $this->wird->id)->sum('amount_done'))->toEqual(2);
});
