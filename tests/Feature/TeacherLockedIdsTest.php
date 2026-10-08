<?php

use App\Models\Circle;
use App\Models\Stage;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The record a teacher's screen is working on is the one the server opened
 * for him. Saving an exam wrote over whatever exam id the browser named, and
 * the self programme loaded whatever week it was handed.
 */
beforeEach(function () {
    $circle = Circle::factory()->create(['stage_id' => Stage::factory()->create()->id]);
    $this->teacher = Teacher::factory()->create();
    $this->teacher->circles()->attach($circle->id);
    $this->actingAs($this->teacher, 'teacher');
});

it('keeps the exam being edited to the one opened', function () {
    Livewire::test('teacher.student-exams')->set('editingId', 999);
})->throws(CannotUpdateLockedPropertyException::class);

it('keeps the self-programme week to the one opened', function () {
    Livewire::test('teacher.self-program-manager')->set('weekId', 999);
})->throws(CannotUpdateLockedPropertyException::class);
