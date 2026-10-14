<?php

use App\Models\Attendance;
use App\Models\Circle;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentPlacementRequest;
use App\Models\Supervisor;
use App\Models\UserRole;
use App\Services\StudentPlacementService;
use App\Support\HijriDate;
use App\Support\Scope;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

/**
 * A sheikh's home page says how his group's week went, where its
 * memorisation stands, and who is waiting for him — and lets him answer them
 * where he sees them.
 */
beforeEach(function () {
    Carbon\Carbon::setTestNow('2026-10-07 05:00:00'); // A Wednesday, eight in the morning in Riyadh.

    $programme = Stage::factory()->create(['name' => 'تأصيل']);
    $this->own = Circle::factory()->create(['stage_id' => $programme->id, 'name' => 'الأهدل']);
    $this->other = Circle::factory()->create(['stage_id' => $programme->id, 'name' => 'فلمبان']);

    $this->sheikh = Supervisor::factory()->create(['name' => 'عبدالرحمن الأهدل']);
    $this->sheikh->stages()->attach($programme->id);
    $this->sheikh->roles()->where('role', 'supervisor')->update(['scope_type' => UserRole::SCOPE_CIRCLES, 'scope_ids' => [$this->own->id]]);
    $this->sheikh->load('roles');

    $this->mine = Student::factory()->create(['circle_id' => $this->own->id, 'status' => 'active', 'joined_at' => '2026-09-01']);
    $this->theirs = Student::factory()->create(['circle_id' => $this->other->id, 'status' => 'active', 'joined_at' => '2026-09-01']);

    Scope::forget();
    $this->actingAs($this->sheikh, 'supervisor');
});

it('greets him by name, and counts his own group\'s week', function () {
    Attendance::create(['student_id' => $this->mine->id, 'circle_id' => $this->own->id, 'date' => '2026-10-05', 'status' => 'present']);
    Attendance::create(['student_id' => $this->theirs->id, 'circle_id' => $this->other->id, 'date' => '2026-10-05', 'status' => 'absent']);

    Livewire::test('supervisor.dashboard')
        ->assertSee('صباح الخير يا شيخ عبدالرحمن')
        ->assertViewHas('activeStudents', 1)
        ->assertViewHas('attendance', fn ($rate) => $rate > 0 && $rate <= 100)
        ->assertSee('تقدّم الحفظ')
        ->assertSee('الحفظ اليومي');
});

it('shows the three last Hijri months, this one last', function () {
    $labels = collect(Livewire::test('supervisor.dashboard')->viewData('months'))->pluck('label')->all();

    expect($labels)->toHaveCount(3)
        ->and(end($labels))->toBe(HijriDate::format('2026-10-07', 'MMMM'));
});

it('lets him accept or turn down a waiting student from his home page', function () {
    $asker = Student::factory()->create(['name' => 'أنس الشريف', 'circle_id' => null]);
    $ask = StudentPlacementService::askToJoin($asker, $this->own);

    Livewire::test('supervisor.dashboard')
        ->assertSee('أنس الشريف')
        ->call('acceptAsk', $ask->id);

    expect($asker->fresh()->circle_id)->toBe($this->own->id);

    $another = Student::factory()->create(['circle_id' => null]);
    $second = StudentPlacementService::askToJoin($another, $this->own);

    Livewire::test('supervisor.dashboard')->call('declineAsk', $second->id);

    expect($second->fresh()->status)->toBe(StudentPlacementRequest::REJECTED);
});

it('answers no ask for another sheikh\'s group from his home page', function () {
    $ask = StudentPlacementService::askToJoin(Student::factory()->create(['circle_id' => null]), $this->other);

    expect(fn () => Livewire::test('supervisor.dashboard')->call('acceptAsk', $ask->id))
        ->toThrow(ModelNotFoundException::class);
});

it('says when nobody is waiting', function () {
    Livewire::test('supervisor.dashboard')->assertSee('لا أحد ينتظر');
});

it('sets this week\'s attendance against last week only when last week has any', function () {
    Attendance::create(['student_id' => $this->mine->id, 'circle_id' => $this->own->id, 'date' => '2026-10-05', 'status' => 'present']);

    Livewire::test('supervisor.dashboard')->assertViewHas('attendanceChange', null);

    Attendance::create(['student_id' => $this->mine->id, 'circle_id' => $this->own->id, 'date' => '2026-09-28', 'status' => 'absent']);
    Cache::flush();

    Livewire::test('supervisor.dashboard')->assertViewHas('attendanceChange', fn ($change) => $change['up'] === true);
});
