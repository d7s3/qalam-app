<?php

use App\Models\Circle;
use App\Models\Stage;
use App\Models\Supervisor;
use App\Models\Teacher;
use App\Models\UserRole;
use App\Support\Scope;

/**
 * Every page names itself the way the home page does.
 *
 * The homes took a large, bold title in October; the hundred-odd pages behind
 * them kept Flux's smaller, lighter one, so leaving home felt like entering
 * another application. One rule now sets them all — and leaves alone the
 * titles inside a dialog, which are set smaller on purpose.
 */
beforeEach(function () {
    $programme = Stage::factory()->create();
    $group = Circle::factory()->create(['stage_id' => $programme->id]);

    $sheikh = Supervisor::factory()->create();
    $sheikh->stages()->attach($programme->id);
    $sheikh->roles()->where('role', 'supervisor')->update(['scope_type' => UserRole::SCOPE_CIRCLES, 'scope_ids' => [$group->id]]);

    Scope::forget();
    $this->actingAs($sheikh, 'supervisor');
});

it('titles a page as large and bold as the home page', function () {
    $home = visit('/supervisor/dashboard')->script(<<<'JS'
        (() => { const h = getComputedStyle(document.querySelector('[data-flux-main] h1')); return [h.fontSize, h.fontWeight]; })()
        JS);

    visit('/supervisor/students')
        ->assertNoJavaScriptErrors()
        ->assertScript(<<<'JS'
            (() => { const h = getComputedStyle(document.querySelector('[data-flux-main] [data-flux-heading]')); return [h.fontSize, h.fontWeight]; })()
            JS, $home);
});

it('leaves a dialog\'s title its own size', function () {
    visit('/supervisor/students')
        ->assertScript(<<<'JS'
            (() => {
                const dialog = document.createElement('dialog');
                dialog.setAttribute('data-flux-modal', '');
                dialog.innerHTML = '<div data-flux-heading class="text-2xl">x</div>';
                document.querySelector('[data-flux-main]').append(dialog);

                return getComputedStyle(dialog.firstChild).fontSize;
            })()
            JS, '24px');
});

it('keeps the sections of a home page below its title', function () {
    $this->actingAs(Teacher::factory()->create(['is_approved' => true]), 'teacher');

    visit('/teacher/dashboard')
        ->assertNoJavaScriptErrors()
        ->assertScript(<<<'JS'
            (() => {
                const title = parseFloat(getComputedStyle(document.querySelector('[data-flux-main] h1')).fontSize);
                const sections = [...document.querySelectorAll('[data-flux-main] [data-flux-heading]')]
                    .filter(h => h.offsetParent !== null)
                    .map(h => parseFloat(getComputedStyle(h).fontSize));

                return sections.length > 0 && sections.every(size => size < title);
            })()
            JS, true);
});
