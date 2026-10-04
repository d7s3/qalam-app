<?php

use Illuminate\Support\Facades\File;

/**
 * What stays put on a screen keeps to two layers: the bars that ride along a
 * page on `z-bar`, and above them, on `z-overlay`, whatever is laid over the
 * whole page. An overlay left at z-50 slid under a phone's bottom bar, which
 * then hid its last lines and the buttons that close it.
 *
 * @return list<array{file: string, classes: string}>
 */
function fixedElementsIn(string $pattern): array
{
    return collect(File::allFiles(resource_path('views')))
        // The public pages carry no bars of their own to slide under.
        ->reject(fn ($file) => str_starts_with($file->getRelativePathname(), 'livewire/public/'))
        ->flatMap(function ($file) use ($pattern) {
            preg_match_all('/class="([^"]*\bfixed\b[^"]*)"/', $file->getContents(), $matches);

            return collect($matches[1])
                ->filter(fn (string $classes) => preg_match($pattern, $classes))
                ->map(fn (string $classes) => ['file' => $file->getRelativePathname(), 'classes' => $classes]);
        })
        ->values()
        ->all();
}

it('lays everything that covers the whole page above the bars', function () {
    $overlays = fixedElementsIn('/\binset-0\b/');

    expect($overlays)->not->toBeEmpty()
        ->and(collect($overlays)->reject(fn (array $overlay) => str_contains($overlay['classes'], 'z-overlay'))->all())
        ->toBe([]);
});

it('keeps the bars pinned to an edge of the page on the bars\' layer', function () {
    $bars = collect(fixedElementsIn('/\b(top|bottom)-0\b/'))
        ->reject(fn (array $bar) => str_contains($bar['classes'], 'inset-0'));

    expect($bars->pluck('file')->all())->toContain('components/teacher-bottom-nav.blade.php', 'components/student-gamification-nav.blade.php')
        ->and($bars->reject(fn (array $bar) => str_contains($bar['classes'], 'z-bar'))->all())
        ->toBe([]);
});
