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

/**
 * A label has to describe the whole page, not its first section. The manager's
 * settings page holds the discipline rules and the database backups; calling it
 * «إعدادات الانضباط» hid the backups from anyone looking for them.
 */
it('names a page by everything on it, not by its first section', function () {
    $page = file_get_contents(resource_path('views/livewire/manager/settings.blade.php'));
    $label = 'الانضباط والنسخ الاحتياطي';

    // Both sections are still there, and the label still names both.
    expect($page)->toContain('إعدادات الانضباط')
        ->and($page)->toContain('النسخ الاحتياطي')
        ->and(file_get_contents(resource_path('views/manager/sidebar-nav.blade.php')))->toContain($label);

    foreach (['الانضباط', 'النسخ الاحتياطي'] as $section) {
        expect($label)->toContain($section);
    }
});

/**
 * «مسار» means a ranking track in the competitions and a memorisation path in
 * the mutun and odes, so the student's plans page must not borrow the word: it
 * lists plans, and says so.
 */
it('calls the student plans page by what it lists', function () {
    $sidebar = file_get_contents(resource_path('views/student/sidebar-nav.blade.php'));

    expect($sidebar)->toContain('خططي القرآنية')
        ->and($sidebar)->not->toContain('مساري القرآني');
});
