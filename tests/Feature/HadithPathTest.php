<?php

use App\Livewire\Shared\HadithPlanCreator;
use App\Models\Circle;
use App\Models\Hadith;
use App\Models\HadithChapter;
use App\Models\HadithLine;
use App\Models\HadithPath;
use App\Models\HadithPathDay;
use App\Models\HadithText;
use App\Models\Stage;
use App\Models\Supervisor;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->stage = Stage::create(['name' => 'مرحلة الخطط']);
    $this->circle = Circle::create(['name' => 'حلقة الخطط', 'stage_id' => $this->stage->id]);

    $this->supervisor = Supervisor::factory()->create();
    $this->supervisor->stages()->attach($this->stage->id);

    $this->teacher = Teacher::factory()->create();
    $this->teacher->circles()->attach($this->circle->id);

    $this->hadithText = HadithText::create([
        'name' => 'الأربعون النووية',
        'description' => 'شرح متن النووية',
    ]);

    $this->chapter = HadithChapter::create([
        'hadith_text_id' => $this->hadithText->id,
        'name' => 'كتاب الإيمان',
    ]);

    $this->hadith = Hadith::create([
        'hadith_chapter_id' => $this->chapter->id,
        'name' => 'الأعمال بالنيات',
        'sanad' => 'عمر بن الخطاب',
        'ruling' => 'صحيح',
    ]);

    // Create 15 lines
    for ($i = 1; $i <= 15; $i++) {
        HadithLine::create([
            'hadith_id' => $this->hadith->id,
            'line_number' => $i,
            'text' => "نص السطر {$i}",
        ]);
    }

    $this->hadithPath = HadithPath::create([
        'hadith_text_id' => $this->hadithText->id,
        'name' => 'مسار الأربعين النووية',
        'memorize_type' => 'lines',
        'memorize_amount' => 2,
        'start_date' => '2026-06-18',
    ]);
});

it('allows supervisor to create a path schedule template', function () {
    $this->actingAs($this->supervisor, 'supervisor');

    Livewire::test(HadithPlanCreator::class)
        ->set('hadithPathId', $this->hadithPath->id)
        ->set('startDate', '2026-06-18')
        ->set('activeDays', ['Sunday', 'Monday', 'Tuesday', 'Wednesday'])
        ->set('memorizeType', 'lines')
        ->set('memorizeAmount', 2)
        ->call('generatePreview')
        ->assertHasNoErrors()
        ->call('savePlan')
        ->assertHasNoErrors();

    expect(HadithPathDay::where('hadith_path_id', $this->hadithPath->id)->count())->toBe(8);
});

it('allows teacher to edit a path schedule template', function () {
    $this->actingAs($this->teacher, 'teacher');

    Livewire::test(HadithPlanCreator::class)
        ->set('hadithPathId', $this->hadithPath->id)
        ->set('startDate', '2026-06-18')
        ->set('activeDays', ['Sunday', 'Monday', 'Tuesday', 'Wednesday'])
        ->set('memorizeType', 'lines')
        ->set('memorizeAmount', 3)
        ->call('generatePreview')
        ->assertHasNoErrors()
        ->call('savePlan')
        ->assertHasNoErrors();

    expect(HadithPathDay::where('hadith_path_id', $this->hadithPath->id)->count())->toBe(5);
});
