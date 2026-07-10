<?php

use App\Models\Ayah;
use App\Models\ExamLevel;
use App\Models\Manager;
use App\Models\Supervisor;
use App\Models\Surah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Surah::create([
        'id' => 1, 'number' => 1, 'name_arabic' => 'الفاتحة', 'name_simple' => 'Al-Fatihah',
        'revelation_place' => 'makkah', 'revelation_order' => 1, 'verses_count' => 7,
        'start_page' => 1, 'end_page' => 1,
    ]);
    Ayah::create([
        'id' => 1, 'surah_id' => 1, 'verse_number' => 1, 'page_number' => 1,
        'line_number_start' => 1, 'line_number_end' => 1, 'verse_key' => '1:1',
        'juz_number' => 1, 'hizb_number' => 1, 'rub_number' => 1, 'ruku_number' => 1,
        'manzil_number' => 1, 'text_uthmani' => 'بسم الله',
    ]);
    Ayah::create([
        'id' => 2, 'surah_id' => 1, 'verse_number' => 2, 'page_number' => 1,
        'line_number_start' => 2, 'line_number_end' => 2, 'verse_key' => '1:2',
        'juz_number' => 1, 'hizb_number' => 1, 'rub_number' => 1, 'ruku_number' => 1,
        'manzil_number' => 1, 'text_uthmani' => 'الحمد لله',
    ]);
});

it('creates a new exam level', function () {
    $manager = Manager::factory()->create();
    $this->actingAs($manager, 'manager');

    Livewire::test('manager.exam-levels')
        ->set('name', 'مستوى تجريبي')
        ->set('direction', 'nas_to_baqarah')
        ->set('startAyahId', 1)
        ->set('endAyahId', 2)
        ->call('save')
        ->assertHasNoErrors();

    expect(ExamLevel::where('name', 'مستوى تجريبي')->exists())->toBeTrue();
});

it('creates a quick supervisor account from the manager supervisors page', function () {
    $manager = Manager::factory()->create();
    $this->actingAs($manager, 'manager');

    Livewire::test(\App\Livewire\Manager\Supervisors::class)
        ->set('quickName', 'مشرف جديد')
        ->call('createQuickSupervisor')
        ->assertHasNoErrors();

    expect(Supervisor::where('name', 'مشرف جديد')->exists())->toBeTrue();
});

it('updates an existing supervisor', function () {
    $manager = Manager::factory()->create();
    $supervisor = Supervisor::factory()->create(['name' => 'اسم قديم', 'is_approved' => true]);

    $this->actingAs($manager, 'manager');

    Livewire::test(\App\Livewire\Manager\Supervisors::class)
        ->call('edit', $supervisor->id)
        ->set('name', 'اسم جديد')
        ->call('save')
        ->assertHasNoErrors();

    expect($supervisor->fresh()->name)->toBe('اسم جديد');
});
