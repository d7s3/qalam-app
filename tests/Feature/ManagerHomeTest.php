<?php

use App\Livewire\Manager\Dashboard;
use App\Models\Circle;
use App\Models\Manager;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\UserRole;
use App\Services\HomeFiguresService;
use App\Support\Scope;
use Carbon\Carbon;
use Livewire\Livewire;

/**
 * The manager's home page, on the design the other homes took.
 *
 * It opened on six noughts and four «لا قياس بعد» before it said anything
 * about the academy. It now leads with the four figures a manager acts on,
 * the memorisation of the last months and of this week, and every cohort's
 * week side by side — sortable, and filterable by programme.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-10-07 05:00:00');

    $this->taseel = Stage::factory()->create(['name' => 'تأصيل']);
    $this->other = Stage::factory()->create(['name' => 'تمهيدي']);

    $this->ahdal = Circle::factory()->create(['stage_id' => $this->taseel->id, 'name' => 'الأهدل']);
    $this->falamban = Circle::factory()->create(['stage_id' => $this->taseel->id, 'name' => 'فلمبان']);
    $this->elsewhere = Circle::factory()->create(['stage_id' => $this->other->id, 'name' => 'دفعة التمهيدي']);

    $teacher = Teacher::factory()->create(['name' => 'خالد العمري']);
    $teacher->circles()->attach($this->ahdal->id);

    Student::factory()->count(2)->create(['circle_id' => $this->ahdal->id, 'status' => 'active', 'is_approved' => true]);
    Student::factory()->create(['circle_id' => $this->ahdal->id, 'status' => 'left', 'is_approved' => true]);

    $this->manager = Manager::factory()->create(['name' => 'فيصل بخش']);
    Scope::forget();
});

it('leads with the four figures a manager acts on', function () {
    $this->actingAs($this->manager, 'manager')
        ->get(route('manager.dashboard'))
        ->assertSuccessful()
        ->assertSeeInOrder(['الطلاب المشاركون', 'نسبة الحضور', 'صفحات الحفظ', 'طلبات التسجيل'])
        ->assertSee('تقدّم الحفظ')
        ->assertSee('الحفظ اليومي')
        ->assertSee('حالات الطلاب')
        ->assertSee('في 3 دفعات');
});

it('counts the registration requests waiting for him, and links to them', function () {
    Student::factory()->count(2)->create(['is_approved' => false, 'is_rejected' => false]);
    Teacher::factory()->create(['is_approved' => false, 'is_rejected' => false]);
    Student::factory()->create(['is_approved' => false, 'is_rejected' => true]);

    $this->actingAs($this->manager, 'manager');

    expect(Livewire::test(Dashboard::class)->viewData('home')['pendingRequests'])->toBe(3);

    $this->get(route('manager.dashboard'))->assertSee(route('manager.pending-approvals'), false);
});

it('sets every cohort\'s week side by side, an empty one and one without a teacher included', function () {
    $html = $this->actingAs($this->manager, 'manager')->get(route('manager.dashboard'))->getContent();

    expect($html)->toContain('أداء الدفعات')
        ->toContain('الأهدل')
        ->toContain('خالد العمري')
        ->toContain('فلمبان')
        ->toContain('بلا معلم');
});

it('counts a cohort\'s students taking part, not the ones who left', function () {
    $ahdal = collect(HomeFiguresService::figures($this->manager, 'manager')['cohorts'])->firstWhere('id', $this->ahdal->id);
    $falamban = collect(HomeFiguresService::figures($this->manager, 'manager')['cohorts'])->firstWhere('id', $this->falamban->id);

    expect($ahdal)->toMatchArray(['name' => 'الأهدل', 'programme' => 'تأصيل', 'teachers' => 'خالد العمري', 'students' => 2, 'attendance' => null])
        ->and($falamban['students'])->toBe(0);
});

it('lists only the cohorts of the programme a manager is narrowed to', function () {
    $this->manager->roles()->where('role', 'manager')->update(['scope_type' => UserRole::SCOPE_STAGES, 'scope_ids' => [$this->other->id]]);
    $this->manager->load('roles');

    $cohorts = collect(HomeFiguresService::figures($this->manager, 'manager')['cohorts'])->pluck('name')->all();

    expect($cohorts)->toBe(['دفعة التمهيدي']);
});

it('greets him by his first name over the academy\'s week', function () {
    $this->actingAs($this->manager, 'manager')
        ->get(route('manager.dashboard'))
        ->assertSee('صباح الخير')
        ->assertSee('فيصل، هذه الأكاديمية هذا الأسبوع.');
});
