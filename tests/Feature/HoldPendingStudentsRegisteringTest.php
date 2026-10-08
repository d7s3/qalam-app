<?php

use App\Models\Student;

/**
 * Students who signed themselves up before October 2026 started مشارك while
 * still waiting to be approved. The command puts those back تحت التسجيل, each
 * with a row in his history, and touches nobody else.
 */
beforeEach(function () {
    Carbon\Carbon::setTestNow('2026-10-05 09:00:00');

    $this->waiting = Student::factory()->create(['name' => 'ينتظر الموافقة', 'status' => 'active', 'is_approved' => false]);
    $this->approved = Student::factory()->create(['name' => 'موافَق عليه', 'status' => 'active', 'is_approved' => true]);
    $this->turnedDown = Student::factory()->create(['name' => 'مرفوض', 'status' => 'active', 'is_approved' => false, 'is_rejected' => true]);
    $this->alreadyRegistering = Student::factory()->create(['name' => 'تحت التسجيل أصلاً', 'status' => 'registering', 'is_approved' => false]);
});

it('puts a student still waiting for approval تحت التسجيل, and records why', function () {
    $this->artisan('students:hold-pending')->assertSuccessful();

    $row = $this->waiting->statusHistories()->sole();

    expect($this->waiting->fresh()->status)->toBe('registering')
        ->and([$row->status, $row->start_date->toDateString()])->toBe(['registering', '2026-10-05'])
        ->and($row->notes)->toContain('بانتظار الموافقة');
});

it('leaves the approved, the turned down and the already registering as they are', function () {
    $this->artisan('students:hold-pending')->assertSuccessful();

    expect($this->approved->fresh()->status)->toBe('active')
        ->and($this->turnedDown->fresh()->status)->toBe('active')
        ->and($this->alreadyRegistering->fresh()->status)->toBe('registering')
        ->and($this->alreadyRegistering->statusHistories()->count())->toBe(0);
});

it('only counts when asked for a dry run', function () {
    $this->artisan('students:hold-pending', ['--dry-run' => true])
        ->expectsOutputToContain('1 طالب')
        ->assertSuccessful();

    expect($this->waiting->fresh()->status)->toBe('active')
        ->and($this->waiting->statusHistories()->count())->toBe(0);
});

it('changes nothing the second time', function () {
    $this->artisan('students:hold-pending')->assertSuccessful();
    $this->artisan('students:hold-pending')->expectsOutputToContain('لا يوجد')->assertSuccessful();

    expect($this->waiting->statusHistories()->count())->toBe(1);
});
