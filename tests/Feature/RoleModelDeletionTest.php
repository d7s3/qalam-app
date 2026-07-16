<?php

use App\Models\Student;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Facades\DB;

it('deleting a role model keeps the user when they hold other roles', function () {
    $user = Student::factory()->create();
    UserRole::create(['user_id' => $user->id, 'role' => 'manager', 'is_approved' => true]);

    Student::findOrFail($user->id)->delete();

    expect(User::find($user->id))->not->toBeNull()
        ->and(DB::table('user_roles')->where('user_id', $user->id)->pluck('role')->all())
        ->toBe(['manager'])
        ->and(Student::find($user->id))->toBeNull();
});

it('deleting a role model removes the user entirely when it was their only role', function () {
    $user = Student::factory()->create();

    Student::findOrFail($user->id)->delete();

    expect(User::find($user->id))->toBeNull()
        ->and(DB::table('user_roles')->where('user_id', $user->id)->count())->toBe(0);
});
