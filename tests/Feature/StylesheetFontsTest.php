<?php

/**
 * The faces the stylesheet names have to be faces.
 *
 * Cairo, chosen for headings, sat in `public/fonts` as two GitHub pages saved
 * under the font's name, so the browser threw them away and no heading ever
 * changed. And a font named by an absolute path is looked for on Vite's dev
 * server, where it is not, so in development every one of them failed.
 */
function stylesheet(): string
{
    return file_get_contents(resource_path('css/app.css'));
}

/** @return list<string> */
function localFontsNamed(): array
{
    preg_match_all("/url\\('([^']+\\.(?:ttf|otf|woff2?))'\\)/", stylesheet(), $matches);

    return $matches[1];
}

it('names its own fonts by a path Vite can follow, in development as in a build', function () {
    expect(localFontsNamed())->not->toBeEmpty()
        ->each->not->toStartWith('/');
});

it('names only files that are fonts', function () {
    foreach (localFontsNamed() as $path) {
        $file = realpath(resource_path('css/'.$path));

        expect($file)->not->toBeFalse("{$path} is not there");

        $signature = file_get_contents($file, length: 4);

        expect(in_array($signature, ["\x00\x01\x00\x00", 'OTTO', 'true', 'wOFF', 'wOF2'], true))
            ->toBeTrue("{$path} is not a font");
    }
});

it('sets text and headings in IBM Plex Sans Arabic, as the October design does', function () {
    expect(stylesheet())->toContain('family=IBM+Plex+Sans+Arabic:wght@400;500;600;700')
        ->and(stylesheet())->toContain("--font-sans: 'IBM Plex Sans Arabic'")
        ->and(stylesheet())->toContain("--font-display: 'IBM Plex Sans Arabic'");
});

it('warms the light greys to the paper the pages stand on, and leaves the dark ones', function () {
    expect(stylesheet())->toContain('--color-zinc-100: #f6f0e4;')
        ->toContain('--color-zinc-200: #ece4d4;')
        ->toContain('--color-zinc-900: #171717;')
        ->toContain('[data-flux-card]');
});

it('brings a face made for the Quran\'s text, marks and all', function () {
    // The verse of the day was set in Zain, a display face without the
    // Mushaf's small marks, which some browsers then drew as empty boxes.
    expect(stylesheet())->toContain('family=Amiri+Quran')
        ->toContain("--font-quran: 'Amiri Quran'");
});
