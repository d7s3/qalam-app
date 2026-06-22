<?php

use App\Livewire\Supervisor\ManageHadiths;
use App\Models\Circle;
use App\Models\Hadith;
use App\Models\HadithChapter;
use App\Models\HadithLine;
use App\Models\Stage;
use App\Models\Supervisor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->stage = Stage::create(['name' => 'مرحلة اختبار الأحاديث']);
    $this->circle = Circle::create(['name' => 'حلقة اختبار الأحاديث', 'stage_id' => $this->stage->id]);

    $this->supervisor = Supervisor::factory()->create();
    $this->supervisor->stages()->attach($this->stage->id);
});

it('renders the hadiths management page for supervisors', function () {
    $this->actingAs($this->supervisor, 'supervisor');

    $response = $this->get(route('supervisor.hadiths'));
    $response->assertSuccessful();
    $response->assertSee('إدارة الأحاديث الشريفة');
});

it('can create a new hadith', function () {
    $this->actingAs($this->supervisor, 'supervisor');

    Livewire::test(ManageHadiths::class)
        ->call('createHadith')
        ->set('name', 'إنما الأعمال بالنيات')
        ->set('newChapterName', 'باب بدء الوحي')
        ->set('sanad', 'عن أمير المؤمنين')
        ->set('ruling', 'متفق عليه')
        ->call('saveHadith')
        ->assertHasNoErrors();

    expect(Hadith::where('name', 'إنما الأعمال بالنيات')->exists())->toBeTrue();
});

it('can create a new hadith and automatically parse bulk lines from textarea', function () {
    $this->actingAs($this->supervisor, 'supervisor');

    Livewire::test(ManageHadiths::class)
        ->call('createHadith')
        ->set('name', 'حديث النيات')
        ->set('linesText', "إنما الأعمال بالنيات\nوإنما لكل امرئ ما نوى\nفمن كانت هجرته إلى الله ورسوله")
        ->call('saveHadith')
        ->assertHasNoErrors();

    $hadith = Hadith::where('name', 'حديث النيات')->first();
    expect($hadith)->not->toBeNull();

    $lines = HadithLine::where('hadith_id', $hadith->id)->orderBy('line_number')->get();
    expect($lines)->toHaveCount(3);
    expect($lines[0]->line_number)->toBe(1);
    expect($lines[0]->text)->toBe('إنما الأعمال بالنيات');
    expect($lines[1]->line_number)->toBe(2);
    expect($lines[1]->text)->toBe('وإنما لكل امرئ ما نوى');
    expect($lines[2]->line_number)->toBe(3);
    expect($lines[2]->text)->toBe('فمن كانت هجرته إلى الله ورسوله');
});

it('can edit an existing hadith', function () {
    $this->actingAs($this->supervisor, 'supervisor');

    $chapter = HadithChapter::create(['name' => 'باب الإيمان']);
    $hadith = Hadith::create([
        'name' => 'الحديث الأول',
        'hadith_chapter_id' => $chapter->id,
    ]);

    Livewire::test(ManageHadiths::class)
        ->call('editHadith', $hadith->id)
        ->set('name', 'الحديث الأول المعدل')
        ->call('saveHadith')
        ->assertHasNoErrors();

    expect($hadith->fresh()->name)->toBe('الحديث الأول المعدل');
});

it('can delete a hadith', function () {
    $this->actingAs($this->supervisor, 'supervisor');

    $hadith = Hadith::create([
        'name' => 'حديث للحذف',
    ]);

    Livewire::test(ManageHadiths::class)
        ->call('deleteHadith', $hadith->id)
        ->assertHasNoErrors();

    expect(Hadith::where('id', $hadith->id)->exists())->toBeFalse();
});

it('can add a single line to a hadith', function () {
    $this->actingAs($this->supervisor, 'supervisor');

    $hadith = Hadith::create([
        'name' => 'حديث الصدق',
    ]);

    Livewire::test(ManageHadiths::class)
        ->call('selectHadith', $hadith->id)
        ->set('newLineNumber', 1)
        ->set('newLineText', 'عليكم بالصدق فإن الصدق يهدي إلى البر')
        ->call('saveLine')
        ->assertHasNoErrors();

    expect(HadithLine::where('hadith_id', $hadith->id)->where('line_number', 1)->exists())->toBeTrue();
});

it('can bulk import lines into a hadith', function () {
    $this->actingAs($this->supervisor, 'supervisor');

    $hadith = Hadith::create([
        'name' => 'حديث الصدق البلك',
    ]);

    Livewire::test(ManageHadiths::class)
        ->call('selectHadith', $hadith->id)
        ->call('openBulkImport')
        ->set('bulkText', "السطر الأول من الحديث\nالسطر الثاني من الحديث")
        ->call('importBulkLines')
        ->assertHasNoErrors();

    $lines = HadithLine::where('hadith_id', $hadith->id)->orderBy('line_number')->get();
    expect($lines)->toHaveCount(2);
    expect($lines[0]->text)->toBe('السطر الأول من الحديث');
    expect($lines[1]->text)->toBe('السطر الثاني من الحديث');
});

it('can edit and delete a line', function () {
    $this->actingAs($this->supervisor, 'supervisor');

    $hadith = Hadith::create(['name' => 'حديث النيات']);
    $line = HadithLine::create([
        'hadith_id' => $hadith->id,
        'line_number' => 1,
        'text' => 'النص الأصلي',
    ]);

    // Edit Line
    Livewire::test(ManageHadiths::class)
        ->call('selectHadith', $hadith->id)
        ->call('startEditLine', $line->id)
        ->set('editingLineText', 'النص المعدل')
        ->call('saveEditingLine')
        ->assertHasNoErrors();

    expect($line->fresh()->text)->toBe('النص المعدل');

    // Delete Line
    Livewire::test(ManageHadiths::class)
        ->call('selectHadith', $hadith->id)
        ->call('deleteLine', $line->id)
        ->assertHasNoErrors();

    expect(HadithLine::where('id', $line->id)->exists())->toBeFalse();
});

it('re-sequences the remaining lines when a line is deleted', function () {
    $this->actingAs($this->supervisor, 'supervisor');

    $hadith = Hadith::create(['name' => 'حديث النيات']);
    $line1 = HadithLine::create([
        'hadith_id' => $hadith->id,
        'line_number' => 1,
        'text' => 'السطر الأول',
    ]);
    $line2 = HadithLine::create([
        'hadith_id' => $hadith->id,
        'line_number' => 2,
        'text' => 'السطر الثاني',
    ]);
    $line3 = HadithLine::create([
        'hadith_id' => $hadith->id,
        'line_number' => 3,
        'text' => 'السطر الثالث',
    ]);

    // Delete the second line (line_number = 2)
    Livewire::test(ManageHadiths::class)
        ->call('selectHadith', $hadith->id)
        ->call('deleteLine', $line2->id)
        ->assertHasNoErrors();

    // Verify line1 is still number 1, and line3 is now number 2
    expect($line1->fresh()->line_number)->toBe(1);
    expect($line3->fresh()->line_number)->toBe(2);
    expect(HadithLine::where('hadith_id', $hadith->id)->count())->toBe(2);
});

it('shifts other lines up when a new line is added with a conflicting number', function () {
    $this->actingAs($this->supervisor, 'supervisor');

    $hadith = Hadith::create(['name' => 'حديث النيات']);
    $line1 = HadithLine::create([
        'hadith_id' => $hadith->id,
        'line_number' => 1,
        'text' => 'السطر الأول',
    ]);
    $line2 = HadithLine::create([
        'hadith_id' => $hadith->id,
        'line_number' => 2,
        'text' => 'السطر الثاني',
    ]);

    // Add a new line at line_number = 2 (should shift line2 to 3)
    Livewire::test(ManageHadiths::class)
        ->call('selectHadith', $hadith->id)
        ->set('newLineNumber', 2)
        ->set('newLineText', 'السطر الجديد')
        ->call('saveLine')
        ->assertHasNoErrors();

    expect($line1->fresh()->line_number)->toBe(1);
    expect($line2->fresh()->line_number)->toBe(3); // Shifted up

    $newLine = HadithLine::where('hadith_id', $hadith->id)->where('line_number', 2)->first();
    expect($newLine)->not->toBeNull();
    expect($newLine->text)->toBe('السطر الجديد');
});

it('shifts other lines up when a line is updated with a conflicting number', function () {
    $this->actingAs($this->supervisor, 'supervisor');

    $hadith = Hadith::create(['name' => 'حديث النيات']);
    $line1 = HadithLine::create([
        'hadith_id' => $hadith->id,
        'line_number' => 1,
        'text' => 'السطر الأول',
    ]);
    $line2 = HadithLine::create([
        'hadith_id' => $hadith->id,
        'line_number' => 2,
        'text' => 'السطر الثاني',
    ]);
    $line3 = HadithLine::create([
        'hadith_id' => $hadith->id,
        'line_number' => 3,
        'text' => 'السطر الثالث',
    ]);

    // Update line3 to be number 2 (should shift line2 to 3, and old line3 becomes 2)
    Livewire::test(ManageHadiths::class)
        ->call('selectHadith', $hadith->id)
        ->call('startEditLine', $line3->id)
        ->set('editingLineNumber', 2)
        ->call('saveEditingLine')
        ->assertHasNoErrors();

    expect($line1->fresh()->line_number)->toBe(1);
    expect($line3->fresh()->line_number)->toBe(2); // Updated to 2
    expect($line2->fresh()->line_number)->toBe(3); // Shifted up to 3
});

it('automatically closes gaps and re-sequences lines on save', function () {
    $this->actingAs($this->supervisor, 'supervisor');

    $hadith = Hadith::create(['name' => 'حديث النيات']);

    $line1 = HadithLine::create([
        'hadith_id' => $hadith->id,
        'line_number' => 1,
        'text' => 'السطر الأول',
    ]);

    // Add a new line and explicitly set its number to 5 (creating a gap)
    Livewire::test(ManageHadiths::class)
        ->call('selectHadith', $hadith->id)
        ->set('newLineNumber', 5)
        ->set('newLineText', 'السطر الجديد')
        ->call('saveLine')
        ->assertHasNoErrors();

    // Verify it automatically re-sequenced 5 to become 2 to close the gap
    $line1->refresh();
    expect($line1->line_number)->toBe(1);

    $line2 = HadithLine::where('hadith_id', $hadith->id)->where('id', '!=', $line1->id)->first();
    expect($line2->line_number)->toBe(2); // Automatically re-sequenced to 2 to close the gap
});
