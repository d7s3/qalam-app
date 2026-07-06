<?php

use App\Models\Circle;
use App\Models\Stage;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the idle-based stale data warning on the teacher app shell', function () {
    $stage = Stage::factory()->create();
    $circle = Circle::factory()->create(['stage_id' => $stage->id]);
    $teacher = Teacher::factory()->create();
    $teacher->circles()->attach($circle->id);

    $response = $this->actingAs($teacher, 'teacher')->get(route('teacher.attendance'));

    $response->assertSuccessful();

    // The warning is armed by inactivity (not page age) and can be dismissed.
    $response->assertSee('armStaleTimer', false);
    $response->assertSee('registerActivity', false);
    $response->assertSee('dismissStaleWarning', false);
    $response->assertSee('مر وقت دون استخدام الصفحة وقد تكون البيانات غير محدثة');
});
