<?php

/**
 * The sidebar is read in Arabic; an English label is a leftover, not a choice.
 */
it('leaves no untranslated label in any sidebar', function () {
    foreach (glob(resource_path('views/*/sidebar-nav.blade.php')) as $file) {
        $markup = file_get_contents($file);

        expect($markup)->not->toContain("__('Dashboard')", basename(dirname($file)));
    }
});

/**
 * The sidebar's «الإنجازات» and «المتصدرون» are fragments on the plain
 * dashboard. Most students see the gamification one instead, where the same
 * content sits inside a tab — so the fragment has to open the tab, or the two
 * items lead nowhere for three students in four.
 */
it('opens the matching tab when the gamification dashboard is reached by fragment', function () {
    $markup = file_get_contents(resource_path('views/components/student/⚡gamification-dashboard.blade.php'));

    expect($markup)->toContain("'#achievements': 'badges'")
        ->and($markup)->toContain("'#leaderboard-standings': 'leaderboard'");

    // And the plain dashboard still carries the anchors themselves.
    $plain = file_get_contents(resource_path('views/components/student/⚡dashboard.blade.php'));

    expect($plain)->toContain('id="achievements"')
        ->and($plain)->toContain('id="leaderboard-standings"');
});
