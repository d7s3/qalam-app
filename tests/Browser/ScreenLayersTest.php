<?php

use App\Models\Teacher;

/**
 * Something pinned to the bottom of a page, the register's save bar say, has
 * to sit clear of the bottom bar a phone shows the teacher, or the bar hides
 * it — buttons and all. Measured against the bar itself, in a real browser.
 */
it('pins a bottom-pinned bar clear of a teacher\'s bottom bar on a phone', function () {
    $this->actingAs(Teacher::factory()->create(['is_approved' => true]), 'teacher');

    visit('/teacher/students')
        ->on()->mobile()
        ->assertNoJavaScriptErrors()
        ->assertScript(<<<'JS'
            (() => {
                const pinned = document.createElement('div');
                pinned.className = 'fixed inset-x-0 h-14 max-lg:bottom-above-bar';
                document.body.append(pinned);

                const bar = document.querySelector('.z-bar.bottom-0').getBoundingClientRect();

                return bar.height > 0 && pinned.getBoundingClientRect().bottom <= bar.top;
            })()
            JS, true);
});
