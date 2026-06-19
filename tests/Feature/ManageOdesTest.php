<?php

use App\Livewire\Supervisor\ManageOdes;
use App\Models\Circle;
use App\Models\Ode;
use App\Models\OdeVerse;
use App\Models\Stage;
use App\Models\Supervisor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->stage = Stage::create(['name' => 'مرحلة اختبار المنظومات']);
    $this->circle = Circle::create(['name' => 'حلقة اختبار المنظومات', 'stage_id' => $this->stage->id]);

    $this->supervisor = Supervisor::factory()->create();
    $this->supervisor->stages()->attach($this->stage->id);
});

it('renders the odes management page for supervisors', function () {
    $this->actingAs($this->supervisor, 'supervisor');

    $response = $this->get(route('supervisor.odes'));
    $response->assertSuccessful();
    $response->assertSee('إدارة المنظومات العلمية والشعرية');
});

it('can create a new ode', function () {
    $this->actingAs($this->supervisor, 'supervisor');

    Livewire::test(ManageOdes::class)
        ->call('createOde')
        ->set('name', 'منظومة الجزرية')
        ->set('description', 'منظومة في التجويد وابن الجزري')
        ->call('saveOde')
        ->assertHasNoErrors();

    expect(Ode::where('name', 'منظومة الجزرية')->exists())->toBeTrue();
});

it('can edit an existing ode', function () {
    $this->actingAs($this->supervisor, 'supervisor');

    $ode = Ode::create([
        'name' => 'تحفة الأطفال',
        'description' => 'للجمزوري',
    ]);

    Livewire::test(ManageOdes::class)
        ->call('editOde', $ode->id)
        ->set('name', 'تحفة الأطفال المعدلة')
        ->call('saveOde')
        ->assertHasNoErrors();

    expect($ode->fresh()->name)->toBe('تحفة الأطفال المعدلة');
});

it('can delete an ode', function () {
    $this->actingAs($this->supervisor, 'supervisor');

    $ode = Ode::create([
        'name' => 'منظومة للحذف',
    ]);

    Livewire::test(ManageOdes::class)
        ->call('deleteOde', $ode->id)
        ->assertHasNoErrors();

    expect(Ode::where('id', $ode->id)->exists())->toBeFalse();
});

it('can add a single verse to an ode', function () {
    $this->actingAs($this->supervisor, 'supervisor');

    $ode = Ode::create([
        'name' => 'البيقونية',
    ]);

    Livewire::test(ManageOdes::class)
        ->call('selectOde', $ode->id)
        ->set('newVerseNumber', 1)
        ->set('newSadr', 'أَبْدَأُ بِالْحَمْدِ مُصَلِّيًا عَلَى')
        ->set('newAjuz', 'مُحَمَّدٍ خَيْرِ نَبِيٍّ أُرْسِلاَ')
        ->call('saveVerse')
        ->assertHasNoErrors();

    expect(OdeVerse::where('ode_id', $ode->id)->where('verse_number', 1)->exists())->toBeTrue();
});

it('can bulk import verses into an ode using hashes or double spaces', function () {
    $this->actingAs($this->supervisor, 'supervisor');

    $ode = Ode::create([
        'name' => 'المنظومة البيقونية البلك',
    ]);

    Livewire::test(ManageOdes::class)
        ->call('selectOde', $ode->id)
        ->call('openBulkImport')
        ->set('bulkText', "البيت الأول شطر أول # البيت الأول شطر ثان\nالبيت الثاني شطر أول  البيت الثاني شطر ثان")
        ->call('importBulkVerses')
        ->assertHasNoErrors();

    $verses = OdeVerse::where('ode_id', $ode->id)->orderBy('verse_number')->get();
    expect($verses)->toHaveCount(2);
    expect($verses[0]->sadr)->toBe('البيت الأول شطر أول');
    expect($verses[0]->ajuz)->toBe('البيت الأول شطر ثان');
    expect($verses[1]->sadr)->toBe('البيت الثاني شطر أول');
    expect($verses[1]->ajuz)->toBe('البيت الثاني شطر ثان');
});

it('can edit and delete a verse', function () {
    $this->actingAs($this->supervisor, 'supervisor');

    $ode = Ode::create(['name' => 'منظومة البيقونية']);
    $verse = OdeVerse::create([
        'ode_id' => $ode->id,
        'verse_number' => 1,
        'sadr' => 'الصدر الأصلي',
        'ajuz' => 'العجز الأصلي',
    ]);

    // Edit Verse
    Livewire::test(ManageOdes::class)
        ->call('selectOde', $ode->id)
        ->call('startEditVerse', $verse->id)
        ->set('editingSadr', 'الصدر المعدل')
        ->call('saveEditingVerse')
        ->assertHasNoErrors();

    expect($verse->fresh()->sadr)->toBe('الصدر المعدل');

    // Delete Verse
    Livewire::test(ManageOdes::class)
        ->call('selectOde', $ode->id)
        ->call('deleteVerse', $verse->id)
        ->assertHasNoErrors();

    expect(OdeVerse::where('id', $verse->id)->exists())->toBeFalse();
});
