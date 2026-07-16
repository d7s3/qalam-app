<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Covers 2026_07_16_055115_run_legacy_users_copy_and_repair_fk_backfill:
 * reproduces the "migrations ran before the Phase 2 command" state (legacy
 * rows present, users/id_migration_map empty, swapped FK columns NULL with
 * the original ids in *_legacy) and asserts the migration repairs it.
 */
function legacyCopyRepairMigration(): object
{
    return require database_path('migrations/2026_07_16_055115_run_legacy_users_copy_and_repair_fk_backfill.php');
}

function seedLegacyRowsWithNullFks(): array
{
    $guardianId = DB::table('guardians')->insertGetId([
        'name' => 'Legacy Guardian',
        'email' => 'guardian@example.com',
        'password' => bcrypt('password'),
        'is_approved' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $teacherId = DB::table('teachers')->insertGetId([
        'name' => 'Legacy Teacher',
        'email' => 'teacher@example.com',
        'password' => bcrypt('password'),
        'is_approved' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $studentId = DB::table('students')->insertGetId([
        'name' => 'Legacy Student',
        'email' => 'student@example.com',
        'password' => bcrypt('password'),
        'is_approved' => true,
        'guardian_id' => $guardianId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $planId = DB::table('student_plans')->insertGetId([
        'student_id' => null,
        'teacher_id' => null,
        'student_id_legacy' => $studentId,
        'teacher_id_legacy' => $teacherId,
        'start_date' => now()->toDateString(),
        'days_count' => 5,
        'active_days' => json_encode([0, 1, 2, 3, 4]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [$guardianId, $teacherId, $studentId, $planId];
}

it('copies legacy users and re-backfills NULL swapped FK columns', function () {
    [$guardianId, $teacherId, $studentId, $planId] = seedLegacyRowsWithNullFks();

    expect(DB::table('users')->count())->toBe(0)
        ->and(DB::table('id_migration_map')->count())->toBe(0);

    legacyCopyRepairMigration()->up();

    expect(DB::table('users')->count())->toBe(3)
        ->and(DB::table('user_roles')->pluck('role')->sort()->values()->all())
        ->toBe(['guardian', 'student', 'teacher']);

    $newStudentId = DB::table('id_migration_map')
        ->where('old_table', 'students')->where('old_id', $studentId)->value('new_id');
    $newTeacherId = DB::table('id_migration_map')
        ->where('old_table', 'teachers')->where('old_id', $teacherId)->value('new_id');
    $newGuardianId = DB::table('id_migration_map')
        ->where('old_table', 'guardians')->where('old_id', $guardianId)->value('new_id');

    $plan = DB::table('student_plans')->find($planId);

    expect($plan->student_id)->toBe($newStudentId)
        ->and($plan->teacher_id)->toBe($newTeacherId)
        ->and($plan->student_id_legacy)->toBe($studentId)
        ->and($plan->teacher_id_legacy)->toBe($teacherId)
        ->and(DB::table('users')->find($newStudentId)->guardian_id)->toBe($newGuardianId);
});

it('is a no-op when run again after the copy already happened', function () {
    [, , $studentId, $planId] = seedLegacyRowsWithNullFks();

    $migration = legacyCopyRepairMigration();
    $migration->up();

    $usersBefore = DB::table('users')->count();
    $rolesBefore = DB::table('user_roles')->count();
    $planStudentIdBefore = DB::table('student_plans')->find($planId)->student_id;

    $migration->up();

    expect(DB::table('users')->count())->toBe($usersBefore)
        ->and(DB::table('user_roles')->count())->toBe($rolesBefore)
        ->and(DB::table('student_plans')->find($planId)->student_id)->toBe($planStudentIdBefore)
        ->and(DB::table('students')->where('id', $studentId)->exists())->toBeTrue();
});

it('does not overwrite FK columns that were already backfilled correctly', function () {
    [, , , $planId] = seedLegacyRowsWithNullFks();

    // Simulate the correct ordering for one row: copy first, backfill later.
    Artisan::call('users:migrate-legacy');

    $correctId = DB::table('id_migration_map')->where('old_table', 'students')->value('new_id');
    DB::table('student_plans')->where('id', $planId)->update(['student_id' => $correctId]);

    legacyCopyRepairMigration()->up();

    expect(DB::table('student_plans')->find($planId)->student_id)->toBe($correctId);
});
