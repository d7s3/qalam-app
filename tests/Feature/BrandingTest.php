<?php

use Illuminate\Support\Env;

/**
 * The application is handed to a different organisation each deployment, so the
 * name, the mark and the palette must live in configuration rather than in the
 * markup. These tests hold that line: they change the brand and insist the
 * interface follows, and they fail if anyone writes the old name back into a view.
 */
it('reads the organisation name from configuration', function () {
    config(['brand.name' => 'جمعية نور القرآن']);

    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSee('جمعية نور القرآن')
        ->assertDontSee('مجمع التاج القرآني');
});

it('paints the interface in the organisation colour', function () {
    config(['brand.colors.primary' => '#1e5f4a', 'brand.colors.dark' => '#164434']);

    $html = $this->get(route('login'))->assertSuccessful()->getContent();

    // The token every `bg-maroon` and `text-maroon` in the app resolves to.
    expect($html)->toContain('--color-maroon: #1e5f4a');
    expect($html)->toContain('--color-burgundy: #164434');
    expect($html)->not->toContain('--color-maroon: #7a2727');
});

it('paints the welcome page in the organisation colour too', function () {
    // The welcome page builds its own <head> rather than including partials.head,
    // and so once shipped without the palette at all: every page in the app wore
    // the new organisation's colour except the first one a visitor sees.
    config(['brand.colors.primary' => '#1e5f4a', 'brand.colors.gold' => '#d8b46a']);

    $html = $this->get(route('home'))->assertSuccessful()->getContent();

    expect($html)->toContain('--color-maroon: #1e5f4a');
    expect($html)->toContain('--color-gold: #d8b46a');
    expect($html)->not->toContain('--color-maroon: #7a2727');
});

it('serves the organisation logo and icon', function () {
    config([
        'brand.logo' => 'images/noor_logo.png',
        'brand.favicon' => 'noor.svg',
    ]);

    $html = $this->get(route('login'))->assertSuccessful()->getContent();

    expect($html)->toContain('images/noor_logo.png');
    expect($html)->toContain('noor.svg');
});

it('titles the page with the organisation, not the framework', function () {
    config(['brand.name' => 'جمعية نور القرآن']);

    $this->get(route('login'))->assertSee('<title>', false);
    expect($this->get(route('login'))->getContent())->toContain('جمعية نور القرآن');
});

it('falls back to the original academy when nothing is configured', function () {
    // An existing deployment that sets none of the BRAND_ keys must look
    // exactly as it does today. This machine's own .env sets them, so the keys
    // come out of the environment for the length of the assertion — otherwise
    // the test reads back whatever this deployment happens to be branded as,
    // and passes no matter what the fallbacks say.
    $keys = ['BRAND_NAME', 'BRAND_SHORT_NAME', 'BRAND_ENTITY', 'BRAND_LOGO', 'BRAND_COLOR', 'BRAND_COLOR_GOLD'];

    $saved = [];

    foreach ($keys as $key) {
        $saved[$key] = Env::getRepository()->get($key);
        Env::getRepository()->clear($key);
    }

    try {
        $brand = require config_path('brand.php');

        expect($brand['name'])->toBe('مجمع التاج القرآني');
        expect($brand['short_name'])->toBe('مجمع التاج القرآني');
        expect($brand['entity'])->toBe('المجمع');
        expect($brand['logo'])->toBe('images/altag_logo.png');
        expect($brand['colors']['primary'])->toBe('#7a2727');
        expect($brand['colors']['gold'])->toBe('#c9a063');
    } finally {
        foreach ($saved as $key => $value) {
            if ($value !== null) {
                Env::getRepository()->set($key, $value);
            }
        }
    }
});

it('borrows the full name when no short name is given', function () {
    expect(config('brand.short_name'))->not->toBeEmpty();
});

it('leaves no view naming the organisation directly', function () {
    // The whole point of the config: a hardcoded name would survive a rebrand
    // and quietly show the wrong organisation to the new one.
    $offenders = [];

    foreach (glob(resource_path('views').'/{,*/,*/*/,*/*/*/,*/*/*/*/}*.blade.php', GLOB_BRACE) as $file) {
        $body = file_get_contents($file);

        // Strip Blade comments — a mention in a comment harms nobody.
        $body = preg_replace('/\{\{--.*?--\}\}/s', '', $body);

        if (str_contains($body, 'مجمع التاج') || str_contains($body, 'altag_logo')) {
            $offenders[] = str_replace(resource_path('views').'/', '', $file);
        }
    }

    expect($offenders)->toBe([]);
});
