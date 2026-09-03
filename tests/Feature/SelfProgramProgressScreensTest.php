<?php

use App\Models\Circle;
use App\Models\Guardian;
use App\Models\Manager;
use App\Models\SelfProgramItem;
use App\Models\SelfProgramWeek;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use App\Services\SelfProgramService;
use App\Support\SelfProgramTrack;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * One table answers for three roles. What changes between them is only which
 * students it may show — and that is the part worth testing, since getting it
 * wrong shows a guardian somebody else's child.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-09-06 08:00:00');

    $this->stage = Stage::factory()->create(['name' => 'المرحلة الأولى']);
    $this->circle = Circle::factory()->create(['stage_id' => $this->stage->id]);
    $this->student = Student::factory()->create(['name' => 'سالم', 'circle_id' => $this->circle->id]);

    $this->otherStage = Stage::factory()->create(['name' => 'المرحلة الثانية']);
    $this->otherCircle = Circle::factory()->create(['stage_id' => $this->otherStage->id]);
    $this->otherStudent = Student::factory()->create(['name' => 'زياد', 'circle_id' => $this->otherCircle->id]);

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

    $this->service = app(SelfProgramService::class);
});

describe('the supervisor view', function () {
    beforeEach(function () {
        $this->supervisor = Supervisor::factory()->create();
        $this->supervisor->stages()->attach($this->stage->id);
    });

    it('renders its page', function () {
        $this->actingAs($this->supervisor, 'supervisor')
            ->get(route('supervisor.self-program-progress'))
            ->assertOk()
            ->assertSee('سالم');
    });

    it('shows only the stages it holds', function () {
        Livewire::actingAs($this->supervisor, 'supervisor')
            ->test('shared.self-program-progress', ['role' => 'supervisor'])
            ->assertSee('سالم')
            ->assertDontSee('زياد');
    });

    it('reports how far a student has got', function () {
        // Five pages of the ten asked for, and the wird is the only field set.
        $this->service->record($this->student, $this->wird, 5);

        Livewire::actingAs($this->supervisor, 'supervisor')
            ->test('shared.self-program-progress', ['role' => 'supervisor'])
            ->assertSee('50%');
    });

    it('narrows by name', function () {
        Student::factory()->create(['name' => 'خالد', 'circle_id' => $this->circle->id]);

        Livewire::actingAs($this->supervisor, 'supervisor')
            ->test('shared.self-program-progress', ['role' => 'supervisor'])
            ->set('search', 'خالد')
            ->assertSee('خالد')
            ->assertDontSee('سالم');
    });
});

describe('the manager view', function () {
    it('reaches every stage', function () {
        $manager = Manager::factory()->create();

        $component = Livewire::actingAs($manager, 'manager')
            ->test('shared.self-program-progress', ['role' => 'manager']);

        $component->assertSee('سالم');
        $component->set('stageId', $this->otherStage->id)->assertSee('زياد');
    });
});

describe('the guardian view', function () {
    beforeEach(function () {
        $this->guardian = Guardian::factory()->create();
        $this->student->update(['guardian_id' => $this->guardian->id]);
    });

    it('shows his own child', function () {
        Livewire::actingAs($this->guardian, 'guardian')
            ->test('shared.self-program-progress', ['role' => 'guardian'])
            ->assertSee('سالم');
    });

    it('shows nobody else\'s', function () {
        Livewire::actingAs($this->guardian, 'guardian')
            ->test('shared.self-program-progress', ['role' => 'guardian'])
            ->assertDontSee('زياد');
    });

    it('renders its page', function () {
        $this->actingAs($this->guardian, 'guardian')
            ->get(route('guardian.self-program-progress'))
            ->assertOk()
            ->assertSee('سالم');
    });
});
