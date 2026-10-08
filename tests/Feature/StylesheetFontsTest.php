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

it('brings Cairo for the headings along with Tajawal', function () {
    expect(stylesheet())->toContain('family=Cairo:wght@400;700&family=Tajawal')
        ->and(stylesheet())->toContain("--font-display: 'DIN Next Arabic', 'Cairo', 'Tajawal'");
});
