<?php

use App\Livewire\Public\FormReport;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Supervisor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->supervisor = Supervisor::factory()->create();

    $this->form = Form::create([
        'supervisor_id' => $this->supervisor->id,
        'title' => 'استبيان الألوان والبلدان',
        'slug' => 'colors-countries-survey',
        'color' => '#3b82f6',
        'is_public_report' => false,
        'fields' => [
            [
                'id' => 'f_name',
                'type' => 'text',
                'label' => 'الاسم',
                'required' => true,
            ],
            [
                'id' => 'f_color',
                'type' => 'select',
                'label' => 'اللون المفضل',
                'required' => true,
                'options' => ['أحمر', 'أزرق', 'أخضر'],
            ],
            [
                'id' => 'f_hobbies',
                'type' => 'multiselect',
                'label' => 'الهوايات',
                'required' => false,
                'options' => ['القراءة', 'البرمجة', 'الرياضة'],
            ],
        ],
    ]);

    // Create some responses
    $this->response1 = FormResponse::create([
        'form_id' => $this->form->id,
        'answers' => [
            'f_name' => 'أحمد علي',
            'f_color' => 'أحمر',
            'f_hobbies' => ['القراءة', 'البرمجة'],
        ],
    ]);

    $this->response2 = FormResponse::create([
        'form_id' => $this->form->id,
        'answers' => [
            'f_name' => 'عمر فاروق',
            'f_color' => 'أزرق',
            'f_hobbies' => ['البرمجة', 'الرياضة'],
        ],
    ]);
});

it('blocks public access to reports if sharing is disabled', function () {
    $this->get(route('forms.report', [$this->form->slug, $this->form->public_report_token]))
        ->assertStatus(404);
});

it('blocks access if the public token is invalid', function () {
    $this->form->update(['is_public_report' => true]);

    $this->get(route('forms.report', [$this->form->slug, 'invalid-token-123']))
        ->assertStatus(404);
});

it('allows public access to reports if sharing is enabled and token matches', function () {
    $this->form->update(['is_public_report' => true]);

    $this->get(route('forms.report', [$this->form->slug, $this->form->public_report_token]))
        ->assertSuccessful()
        ->assertSee('استبيان الألوان والبلدان');
});

it('filters responses by text input', function () {
    $this->form->update(['is_public_report' => true]);

    Livewire::test(FormReport::class, ['slug' => $this->form->slug, 'token' => $this->form->public_report_token])
        ->set('filters.f_name', 'أحمد')
        ->assertSee('أحمد علي')
        ->assertDontSee('عمر فاروق');
});

it('filters responses by select choice', function () {
    $this->form->update(['is_public_report' => true]);

    Livewire::test(FormReport::class, ['slug' => $this->form->slug, 'token' => $this->form->public_report_token])
        ->set('filters.f_color', 'أزرق')
        ->assertSee('عمر فاروق')
        ->assertDontSee('أحمد علي');
});

it('filters responses by multiselect checkbox', function () {
    $this->form->update(['is_public_report' => true]);

    Livewire::test(FormReport::class, ['slug' => $this->form->slug, 'token' => $this->form->public_report_token])
        ->set('filters.f_hobbies', ['الرياضة'])
        ->assertSee('عمر فاروق')
        ->assertDontSee('أحمد علي');
});

it('groups responses by a selected field', function () {
    $this->form->update(['is_public_report' => true]);

    Livewire::test(FormReport::class, ['slug' => $this->form->slug, 'token' => $this->form->public_report_token])
        ->set('groupBy', 'f_color')
        ->assertSee('اللون المفضل')
        ->assertSee('أحمر')
        ->assertSee('أزرق');
});

it('groups responses by nested primary and secondary fields', function () {
    $this->form->update(['is_public_report' => true]);

    Livewire::test(FormReport::class, ['slug' => $this->form->slug, 'token' => $this->form->public_report_token])
        ->set('groupBy', 'f_color')
        ->set('subGroupBy', 'f_name')
        ->assertSee('اللون المفضل')
        ->assertSee('الاسم')
        ->assertSee('أحمد علي');
});

it('exports the public report as CSV without the student linkage columns', function () {
    $this->form->update(['is_public_report' => true]);

    $rows = Livewire::test(FormReport::class, ['slug' => $this->form->slug, 'token' => $this->form->public_report_token])
        ->instance()
        ->exportRows();

    // Header carries the field labels but never the supervisor-only student columns.
    expect($rows[0])->toContain('الاسم', 'اللون المفضل');
    expect($rows[0])->not->toContain('الطالب المرتبط', 'الحالة');
    expect($rows)->toHaveCount(3); // header + 2 responses
});

it('respects active filters in the public CSV export', function () {
    $this->form->update(['is_public_report' => true]);

    $rows = Livewire::test(FormReport::class, ['slug' => $this->form->slug, 'token' => $this->form->public_report_token])
        ->set('filters.f_color', 'أزرق')
        ->instance()
        ->exportRows();

    expect($rows)->toHaveCount(2); // header + only the matching response
    expect(collect($rows[1]))->toContain('عمر فاروق');
});

it('returns a downloadable CSV from the public report', function () {
    $this->form->update(['is_public_report' => true]);

    Livewire::test(FormReport::class, ['slug' => $this->form->slug, 'token' => $this->form->public_report_token])
        ->call('exportCsv')
        ->assertFileDownloaded();
});
