<?php

/**
 * Regression guard for the mobile sidebar navigation bug.
 *
 * On phone-width screens the Flux sidebar renders a full-screen
 * `ui-sidebar-toggle[data-flux-sidebar-backdrop]` (`fixed inset-0`). If that
 * backdrop stacks ABOVE the `ui-sidebar`, it swallows every tap on a nav item:
 * the drawer closes but the link never fires, so the user is never navigated
 * to the page they tapped.
 *
 * Flux gives the sidebar `z-20` through a *layered* `!important` utility, which
 * an un-layered `!important` override cannot beat. So the backdrop must be kept
 * strictly below z-20 in our custom CSS. This test asserts exactly that.
 */
it('keeps the mobile sidebar backdrop below the sidebar z-index', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain('data-flux-sidebar-backdrop');

    // Extract the z-index applied to the backdrop in our custom CSS.
    preg_match(
        '/ui-sidebar-toggle\[data-flux-sidebar-backdrop\][^{}]*\{[^{}]*z-index:\s*(\d+)/',
        $css,
        $matches
    );

    expect($matches)->not->toBeEmpty('No z-index override found for the mobile sidebar backdrop.');

    // Flux's sidebar sits at z-20; the backdrop must stay strictly underneath it
    // so taps land on the nav links, not the backdrop.
    expect((int) $matches[1])->toBeLessThan(20);
});
