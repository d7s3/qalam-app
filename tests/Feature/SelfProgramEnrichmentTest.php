<?php

use App\Models\Circle;
use App\Models\SelfProgramDayOverride;
use App\Models\SelfProgramItem;
use App\Models\SelfProgramTrack;
use App\Models\SelfProgramWeek;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\SelfProgramService;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The enrichment programme is the teacher's to write and the student's to earn:
 * it is set for the whole circle, and shows only to the students who are half
 * way through their own week.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-09-06 08:00:00');

    $this->stage = Stage::factory()->create();
    $this->circle = Circle::factory()->create(['stage_id' => $this->stage->id]);
    $this->teacher = Teacher::factory()->create();
    $this->teacher->circles()->attach($this->circle->id);
    $this->student = Student::factory()->create(['circle_id' => $this->circle->id]);

    $this->selfWeek = SelfProgramWeek::create([
        'stage_id' => $this->stage->id,
        'program_type' => SelfProgramWeek::TYPE_SELF,
        'week_number' => 1,
        'starts_on' => '2026-09-06',
        'ends_on' => '2026-09-12',
    ]);
    $this->wird = SelfProgramItem::create([
        'self_program_week_id' => $this->selfWeek->id,
        'track' => SelfProgramTrack::QURAN_WIRD,
        'target_amount' => 10,
        'unit' => 'صفحة',
    ]);

    $this->enrichmentWeek = SelfProgramWeek::create([
        'circle_id' => $this->circle->id,
        'program_type' => SelfProgramWeek::TYPE_ENRICHMENT,
        'week_number' => 1,
        'starts_on' => '2026-09-06',
        'ends_on' => '2026-09-12',
    ]);
    $this->extra = SelfProgramItem::create([
        'self_program_week_id' => $this->enrichmentWeek->id,
        'track' => SelfProgramTrack::MAHFOUDH,
        'description' => 'خمسة أحاديث',
        'target_amount' => 5,
        'unit' => 'حديث',
    ]);

    $this->service = app(SelfProgramService::class);
});

it('keeps the enrichment week from a student below half', function () {
    $this->service->record($this->student, $this->wird, 4);

    expect($this->service->currentEnrichmentWeek($this->student))->toBeNull();
});

it('opens the enrichment week at half the self programme', function () {
    $this->service->record($this->student, $this->wird, 5);

    expect($this->service->currentEnrichmentWeek($this->student)?->id)->toBe($this->enrichmentWeek->id);
});

it('closes it again if the self programme is undone', function () {
    $this->service->record($this->student, $this->wird, 5);
    expect($this->service->currentEnrichmentWeek($this->student))->not->toBeNull();

    $this->service->record($this->student, $this->wird, 0);

    expect($this->service->currentEnrichmentWeek($this->student))->toBeNull();
});

it('shows the enrichment content on the student page once earned', function () {
    $this->service->record($this->student, $this->wird, 5);

    $this->actingAs($this->student, 'student')
        ->get(route('student.self-program'))
        ->assertOk()
        ->assertSee('البرنامج الإثرائي')
        ->assertSee('خمسة أحاديث');
});

it('hides it while it is not earned', function () {
    $this->actingAs($this->student, 'student')
        ->get(route('student.self-program'))
        ->assertOk()
        ->assertDontSee('خمسة أحاديث');
});

it('lets a student record against an enrichment track he has earned', function () {
    $this->service->record($this->student, $this->wird, 5);

    Livewire::actingAs($this->student, 'student')
        ->test('student.self-program')
        ->set("amounts.{$this->extra->id}", 3)
        ->call('save', $this->extra->id)
        ->assertHasNoErrors();

    expect($this->service->weekProgress($this->student, $this->enrichmentWeek->fresh('items'))['overall'])
        ->toBe(60.0);
});

it('refuses a student recording against enrichment he has not earned', function () {
    Livewire::actingAs($this->student, 'student')
        ->test('student.self-program')
        ->set("amounts.{$this->extra->id}", 3)
        ->call('save', $this->extra->id)
        ->assertStatus(404);
});

describe('the teacher screen', function () {
    it('renders its page', function () {
        $this->actingAs($this->teacher, 'teacher')
            ->get(route('teacher.self-program'))
            ->assertOk()
            ->assertSee('إعدادات الدفعة')
            ->assertSee($this->circle->name);
    });

    it('saves the circle settings', function () {
        Livewire::actingAs($this->teacher, 'teacher')
            ->test('teacher.self-program-manager')
            ->set('isQuranic', false)
            ->set('unlockOnCompletion', true)
            ->call('saveSettings');

        $circle = $this->circle->fresh();

        expect($circle->is_quranic)->toBeFalse()
            ->and($circle->self_program_unlock_on_completion)->toBeTrue();
    });

    it('writes an enrichment week for its circle', function () {
        Livewire::actingAs($this->teacher, 'teacher')
            ->test('teacher.self-program-manager')
            ->call('openWeek', $this->enrichmentWeek->id)
            ->set('rows.masmou.description', 'درسان صوتيان')
            ->set('rows.masmou.target_amount', 2)
            ->call('saveWeek')
            ->assertHasNoErrors();

        $items = $this->enrichmentWeek->fresh('items')->items->keyBy(fn ($i) => $i->track->value);

        expect((float) $items['masmou']->target_amount)->toBe(2.0)
            ->and($items['masmou']->description)->toBe('درسان صوتيان');
    });

    it('refuses an enrichment week of another circle', function () {
        $other = SelfProgramWeek::create([
            'circle_id' => Circle::factory()->create()->id,
            'program_type' => SelfProgramWeek::TYPE_ENRICHMENT,
            'week_number' => 1,
            'starts_on' => '2026-09-06',
            'ends_on' => '2026-09-12',
        ]);

        Livewire::actingAs($this->teacher, 'teacher')
            ->test('teacher.self-program-manager')
            ->call('openWeek', $other->id)
            ->assertStatus(403);
    });

    it('overrules a day for the whole circle', function () {
        Livewire::actingAs($this->teacher, 'teacher')
            ->test('teacher.self-program-manager')
            ->set('overrideItemId', $this->wird->id)
            ->set('overrideStudentId', null)
            ->set('overrideDay', '2026-09-06')
            ->set('overrideAmount', 1)
            ->call('saveOverride')
            ->assertHasNoErrors();

        expect($this->service->dailyPlan($this->wird->fresh(), $this->student)['2026-09-06'])->toBe(1.0);
    });

    it('overrules a day for one student, beating the circle', function () {
        SelfProgramDayOverride::create([
            'self_program_item_id' => $this->wird->id,
            'circle_id' => $this->circle->id,
            'day_date' => '2026-09-06',
            'amount' => 1,
        ]);

        Livewire::actingAs($this->teacher, 'teacher')
            ->test('teacher.self-program-manager')
            ->set('overrideItemId', $this->wird->id)
            ->set('overrideStudentId', $this->student->id)
            ->set('overrideDay', '2026-09-06')
            ->set('overrideAmount', 3)
            ->call('saveOverride')
            ->assertHasNoErrors();

        expect($this->service->dailyPlan($this->wird->fresh(), $this->student)['2026-09-06'])->toBe(3.0);
    });

    it('refuses to overrule for a student of another circle', function () {
        $stranger = Student::factory()->create(['circle_id' => Circle::factory()->create()->id]);

        Livewire::actingAs($this->teacher, 'teacher')
            ->test('teacher.self-program-manager')
            ->set('overrideItemId', $this->wird->id)
            ->set('overrideStudentId', $stranger->id)
            ->set('overrideDay', '2026-09-06')
            ->set('overrideAmount', 3)
            ->call('saveOverride')
            ->assertStatus(403);
    });

    it('will not hold two overrides for the same day and scope', function () {
        SelfProgramDayOverride::create([
            'self_program_item_id' => $this->wird->id,
            'circle_id' => $this->circle->id,
            'day_date' => '2026-09-06',
            'amount' => 1,
        ]);

        // The database itself refuses the second, rather than leaving the split
        // to pick whichever row came back last.
        expect(fn () => SelfProgramDayOverride::create([
            'self_program_item_id' => $this->wird->id,
            'circle_id' => $this->circle->id,
            'day_date' => '2026-09-06',
            'amount' => 9,
        ]))->toThrow(UniqueConstraintViolationException::class);
    });

    it('removes an override it wrote', function () {
        $override = SelfProgramDayOverride::create([
            'self_program_item_id' => $this->wird->id,
            'circle_id' => $this->circle->id,
            'day_date' => '2026-09-06',
            'amount' => 1,
        ]);

        Livewire::actingAs($this->teacher, 'teacher')
            ->test('teacher.self-program-manager')
            ->call('removeOverride', $override->id);

        expect(SelfProgramDayOverride::count())->toBe(0)
            // Back to the plain split: ten pages over five working days.
            ->and($this->service->dailyPlan($this->wird->fresh(), $this->student)['2026-09-06'])->toBe(2.0);
    });

    it('shows how far each of its students has got', function () {
        $this->service->record($this->student, $this->wird, 5);

        Livewire::actingAs($this->teacher, 'teacher')
            ->test('teacher.self-program-manager')
            ->set('tab', 'progress')
            ->assertSee($this->student->name)
            ->assertSee('50');
    });
});
