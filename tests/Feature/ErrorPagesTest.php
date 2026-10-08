<?php

use App\Models\Guardian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/**
 * When something is refused or missing, the page says so in Arabic and
 * offers the way back — a parent who follows an old link should not be left
 * on a page that says «Not Found» and nothing else.
 */
it('says a page is missing in Arabic, with a way home', function () {
    $this->actingAs(Guardian::factory()->create(['is_approved' => true]), 'guardian')
        ->get('/parent/student/999999')
        ->assertNotFound()
        ->assertSee('الصفحة غير موجودة')
        ->assertSee('العودة إلى الرئيسية')
        ->assertSee('dir="rtl"', false)
        ->assertDontSee('Not Found');
});

it('says a page is not open to the reader, and why when it was told', function () {
    Route::get('/__forbidden-test', fn () => abort(403, 'هذه الصفحة غير متاحة لك حالياً.'))->middleware('web');

    $this->get('/__forbidden-test')
        ->assertForbidden()
        ->assertSee('لا تملك صلاحية فتح هذه الصفحة')
        ->assertSee('هذه الصفحة غير متاحة لك حالياً.')
        ->assertDontSee('Forbidden');
});

it('says an expired page should be reloaded', function () {
    Route::get('/__expired-test', fn () => abort(419))->middleware('web');

    $this->get('/__expired-test')
        ->assertStatus(419)
        ->assertSee('انتهت صلاحية الصفحة')
        ->assertDontSee('Page Expired');
});

it('apologises for a server error without showing what broke', function () {
    config(['app.debug' => false]);
    Route::get('/__error-test', fn () => throw new RuntimeException('secret detail'))->middleware('web');

    $this->get('/__error-test')
        ->assertServerError()
        ->assertSee('حدث خطأ غير متوقع')
        ->assertDontSee('secret detail');
});
