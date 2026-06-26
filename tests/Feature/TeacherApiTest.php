<?php

use App\Models\Attendance;
use App\Models\Circle;
use App\Models\GamificationStudentState;
use App\Models\Leaderboard;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\GamificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->stage = Stage::create(['name' => 'المرحلة الثانوية']);
    $this->circle = Circle::create(['name' => 'حلقة الإيمان', 'stage_id' => $this->stage->id]);

    $this->teacher = Teacher::create([
        'name' => 'معلم تجريبي',
        'email' => 'teacher@example.com',
        'phone' => '123456789',
        'password' => Hash::make('password'),
        'is_approved' => true,
    ]);

    $this->teacher->circles()->attach($this->circle->id);

    $this->student = Student::create([
        'name' => 'طالب تجريبي',
        'email' => 'student@example.com',
        'password' => bcrypt('password'),
        'circle_id' => $this->circle->id,
        'is_approved' => true,
        'status' => 'active',
    ]);

    // Setup active leaderboard for gamification testing
    $this->leaderboard = Leaderboard::create([
        'circle_id' => $this->circle->id,
        'title' => 'مسابقة التلعيب',
        'competition_type' => 'gamification',
        'start_date' => now()->subDays(5),
        'end_date' => now()->addDays(5),
        'is_active' => true,
        'settings' => [
            'attendance_enabled' => true,
            'attendance_present_xp' => 10,
            'attendance_present_coins' => 15,
            'attendance_enthusiasm_trigger' => true,
            'enthusiasm_enabled' => true,
        ],
    ]);
    $this->leaderboard->circles()->attach($this->circle->id);
});

it('authenticates approved teacher and returns token', function () {
    $response = $this->postJson('/api/teacher/login', [
        'email' => 'teacher@example.com',
        'password' => 'password',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'token',
            'teacher' => ['id', 'name', 'email', 'phone', 'is_approved'],
        ]);
});

it('fails to authenticate unapproved teacher', function () {
    $unapprovedTeacher = Teacher::create([
        'name' => 'معلم غير معتمد',
        'email' => 'unapproved@example.com',
        'phone' => '987654321',
        'password' => Hash::make('password'),
        'is_approved' => false,
    ]);

    $response = $this->postJson('/api/teacher/login', [
        'email' => 'unapproved@example.com',
        'password' => 'password',
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'message' => 'لم يتم تفعيل حسابك من قبل الإدارة بعد.',
        ]);
});

it('fails to authenticate with invalid credentials', function () {
    $response = $this->postJson('/api/teacher/login', [
        'email' => 'teacher@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'message' => 'بيانات الدخول غير صحيحة.',
        ]);
});

it('returns circles of teacher', function () {
    $token = $this->teacher->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/teacher/attendance');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'circles' => [
                '*' => ['id', 'name', 'description'],
            ],
            'selected_circle',
            'students',
        ])
        ->assertJsonFragment([
            'name' => 'حلقة الإيمان',
        ]);
});

it('returns students in a circle with their attendance status', function () {
    $token = $this->teacher->createToken('test')->plainTextToken;

    // Create attendance record
    Attendance::create([
        'student_id' => $this->student->id,
        'teacher_id' => $this->teacher->id,
        'circle_id' => $this->circle->id,
        'date' => now()->format('Y-m-d'),
        'status' => 'present',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/teacher/attendance?circle_id='.$this->circle->id);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'circles',
            'selected_circle' => ['id', 'name'],
            'students' => [
                '*' => ['id', 'name', 'circle_id', 'attendance_status'],
            ],
        ])
        ->assertJsonFragment([
            'attendance_status' => 'present',
        ]);
});

it('prevents teachers from accessing other circles', function () {
    $otherCircle = Circle::create(['name' => 'حلقة أخرى', 'stage_id' => $this->stage->id]);
    $token = $this->teacher->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/teacher/attendance?circle_id='.$otherCircle->id);

    $response->assertStatus(403)
        ->assertJson([
            'message' => 'غير مصرح لك بالوصول لهذه الحلقة.',
        ]);
});

it('saves student attendance and awards gamification XP', function () {
    $token = $this->teacher->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/teacher/attendance', [
            'circle_id' => $this->circle->id,
            'date' => now()->format('Y-m-d'),
            'records' => [
                $this->student->id => 'present',
            ],
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'تم تسجيل حضور الطلاب بنجاح.',
        ]);

    // Check attendance record created in database
    $attendance = Attendance::where('student_id', $this->student->id)
        ->whereDate('date', now())
        ->first();

    expect($attendance)->not->toBeNull();
    expect($attendance->status)->toBe('present');

    // Check XP points awarded
    $xp = GamificationService::getStudentXP($this->student->id, $this->leaderboard->id);
    expect($xp)->toBe(10); // from leaderboard settings

    $state = GamificationStudentState::where('student_id', $this->student->id)
        ->where('leaderboard_id', $this->leaderboard->id)
        ->first();
    expect($state->coins)->toBe(15); // from leaderboard settings
});

it('performs incremental sync when last_synced_at is passed', function () {
    $token = $this->teacher->createToken('test')->plainTextToken;

    // Create an attendance record
    $attendance = Attendance::create([
        'student_id' => $this->student->id,
        'teacher_id' => $this->teacher->id,
        'circle_id' => $this->circle->id,
        'date' => now()->format('Y-m-d'),
        'status' => 'present',
    ]);

    // Let's call with last_synced_at of 1 hour ago
    $lastSynced = now()->subHour()->toISOString();

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/teacher/attendance?last_synced_at='.$lastSynced);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'circles',
            'students',
            'attendances' => [
                '*' => ['id', 'student_id', 'status', 'date', 'updated_at'],
            ],
            'server_time',
        ]);

    expect($response->json('attendances'))->toHaveCount(1);
    expect($response->json('attendances.0.id'))->toBe($attendance->id);

    // Call with last_synced_at of now
    $lastSyncedNow = now()->addMinute()->toISOString();
    $response2 = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/teacher/attendance?last_synced_at='.$lastSyncedNow);

    $response2->assertStatus(200);
    expect($response2->json('attendances'))->toHaveCount(0);
});

it('saves student attendance in batch mode with Last-Write-Wins conflict resolution', function () {
    $token = $this->teacher->createToken('test')->plainTextToken;

    // 1. Save new attendance via batch mode (sequential array)
    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/teacher/attendance', [
            'records' => [
                [
                    'student_id' => $this->student->id,
                    'date' => now()->format('Y-m-d'),
                    'status' => 'absent',
                    'updated_at' => now()->subMinutes(10)->toISOString(),
                ],
            ],
        ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['message', 'synced_attendances']);

    $attendance = Attendance::where('student_id', $this->student->id)->whereDate('date', now())->first();
    expect($attendance)->not->toBeNull();
    expect($attendance->status)->toBe('absent');

    // 2. Attempt to update with an OLDER updated_at timestamp (Conflict: should ignore)
    $responseConflict = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/teacher/attendance', [
            'records' => [
                [
                    'student_id' => $this->student->id,
                    'date' => now()->format('Y-m-d'),
                    'status' => 'present',
                    'updated_at' => now()->subMinutes(20)->toISOString(), // older than subMinutes(10)
                ],
            ],
        ]);

    $responseConflict->assertStatus(200);
    $attendance->refresh();
    expect($attendance->status)->toBe('absent'); // Kept 'absent' due to LWW

    // 3. Update with a NEWER updated_at timestamp (LWW: should update)
    $responseUpdate = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/teacher/attendance', [
            'records' => [
                [
                    'student_id' => $this->student->id,
                    'date' => now()->format('Y-m-d'),
                    'status' => 'present',
                    'updated_at' => now()->toISOString(), // newer
                ],
            ],
        ]);

    $responseUpdate->assertStatus(200);
    $attendance->refresh();
    expect($attendance->status)->toBe('present'); // Updated to 'present'
});
