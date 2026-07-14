<?php

use App\Models\Guardian;
use App\Models\Manager;
use App\Models\RoleScreenPermission;
use App\Models\Screen;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Teacher;

dataset('guards', [
    'manager' => [Manager::class, 'manager'],
    'supervisor' => [Supervisor::class, 'supervisor'],
    'teacher' => [Teacher::class, 'teacher'],
    'student' => [Student::class, 'student'],
    'guardian' => [Guardian::class, 'guardian'],
]);

it('renders the user guide page for every role with real page descriptions', function (string $model, string $guard) {
    $user = $model::factory()->create(['is_approved' => true]);

    $this->actingAs($user, $guard);

    $response = $this->get(route("{$guard}.guide"));

    $response->assertSuccessful();
    $response->assertSee('دليل الاستخدام');
    $response->assertSee('الرئيسية');
})->with('guards');

it('shows the guide button in the header linking to the correct role guide', function (string $model, string $guard) {
    $user = $model::factory()->create(['is_approved' => true]);

    $this->actingAs($user, $guard);

    $response = $this->get(route("{$guard}.dashboard"));

    $response->assertSuccessful();
    $response->assertSee(route("{$guard}.guide"), false);
})->with('guards');

it('hides a disabled page from the guide instead of listing a dead link', function () {
    $teacher = Teacher::factory()->create(['is_approved' => true]);

    $screen = Screen::where('route_name', 'teacher.pairs')->firstOrFail();
    RoleScreenPermission::where('screen_id', $screen->id)->delete();

    $this->actingAs($teacher, 'teacher');

    $response = $this->get(route('teacher.guide'));

    $response->assertSuccessful();
    $response->assertDontSee('التسميع المتبادل');
});
