<?php

use App\Models\Challenge;
use App\Models\Circle;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentPlan;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * A parent sets a reward for his own child, on his own child's plan, and the
 * reward he describes is checked before it is written down.
 */
function rewardPlanFor(Student $student): StudentPlan
{
    return StudentPlan::create([
        'student_id' => $student->id,
        'start_date' => '2026-09-01',
        'days_count' => 1,
        'active_days' => ['Sunday'],
        'plan_type' => 'hifz',
        'status' => 'active',
        'is_approved' => true,
        'created_by_role' => 'teacher',
        'direction' => 'reverse',
        'review_direction' => 'reverse',
    ]);
}

beforeEach(function () {
    $circle = Circle::factory()->create();

    $this->guardian = Guardian::factory()->create(['is_approved' => true]);
    $this->child = Student::factory()->create(['circle_id' => $circle->id, 'guardian_id' => $this->guardian->id]);
    $this->stranger = Student::factory()->create(['circle_id' => $circle->id, 'guardian_id' => Guardian::factory()->create()->id]);

    $this->actingAs($this->guardian, 'guardian');
});

it('does not open for a child that is not his', function () {
    Livewire::test('guardian.create-challenge', ['studentId' => $this->stranger->id]);
})->throws(ModelNotFoundException::class);

it('cannot be pointed at another child once open', function () {
    Livewire::test('guardian.create-challenge', ['studentId' => $this->child->id])
        ->set('studentId', $this->stranger->id);
})->throws(CannotUpdateLockedPropertyException::class);

it('lists the days only of his own child\'s plans', function () {
    $strangersPlan = rewardPlanFor($this->stranger);

    Livewire::test('guardian.create-challenge', ['studentId' => $this->child->id])
        ->call('loadPlanDays', $strangersPlan->id);
})->throws(ModelNotFoundException::class);

it('refuses a reward that is not filled in properly', function (array $data, string $field) {
    Livewire::test('guardian.create-challenge', ['studentId' => $this->child->id])
        ->call('saveChallenge', $data)
        ->assertHasErrors($field);

    expect(Challenge::count())->toBe(0);
})->with([
    'no reward type' => [['prizeType' => 'material', 'prizeDescription' => 'رحلة'], 'rewardType'],
    'days below one' => [['rewardType' => 'attendance', 'attendanceDays' => -3, 'prizeType' => 'material', 'prizeDescription' => 'رحلة'], 'attendanceDays'],
    'no prize' => [['rewardType' => 'attendance', 'attendanceDays' => 5], 'prizeType'],
    'money without an amount' => [['rewardType' => 'attendance', 'attendanceDays' => 5, 'prizeType' => 'financial'], 'prizeAmount'],
    'an exam score over a hundred' => [['rewardType' => 'exam', 'examLevelId' => 1, 'examPercentage' => 150, 'prizeType' => 'material', 'prizeDescription' => 'رحلة'], 'examPercentage'],
]);

it('sets a reward for his own child', function () {
    Livewire::test('guardian.create-challenge', ['studentId' => $this->child->id])
        ->call('saveChallenge', [
            'rewardType' => 'attendance',
            'attendanceDays' => 10,
            'attendanceNoLateness' => true,
            'prizeType' => 'financial',
            'prizeAmount' => 50,
        ])
        ->assertHasNoErrors()
        ->assertRedirect(route('guardian.dashboard'));

    expect(Challenge::where('student_id', $this->child->id)->value('prize_description'))->toBe('50 ريال سعودي');
});

it('shows the parent what is wrong with the reward', function () {
    Livewire::test('guardian.create-challenge', ['studentId' => $this->child->id])
        ->call('saveChallenge', ['rewardType' => 'attendance', 'attendanceDays' => 5, 'prizeType' => 'financial'])
        ->assertSee('أدخل قيمة المكافأة المالية.');
});
