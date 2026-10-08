<?php

use App\Models\Circle;
use App\Models\Stage;
use App\Models\Teacher;

/**
 * An Arabic keyboard types ٠٥٥ where a phone or number field expects 055. The
 * browser drops the Eastern digits from a number field without a word, so they
 * are turned into Latin ones as they are typed. Measured in a real browser,
 * since only a browser's own keystrokes show whether the field kept them.
 */
it('turns Arabic digits typed into a phone field into Latin ones', function () {
    $teacher = Teacher::factory()->create(['is_approved' => true]);
    $teacher->circles()->attach(Circle::factory()->create(['stage_id' => Stage::factory()->create()->id])->id);

    $this->actingAs($teacher, 'teacher');

    visit('/teacher/students')
        ->on()->mobile()
        ->assertNoJavaScriptErrors()
        ->typeSlowly('input[inputmode=tel]', '٠٥٥١٢٣')
        ->assertValue('input[inputmode=tel]', '055123');
});
