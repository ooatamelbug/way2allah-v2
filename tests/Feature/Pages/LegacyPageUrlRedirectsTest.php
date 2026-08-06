<?php

/**
 * Roadmap task 2.2. Both legacy paths were confirmed to have no
 * .htaccess-rewritten pretty URL (pages.md §2, help.md §2) — the raw
 * ".php" path is the only "legacy URL" that ever existed for either page.
 */
it('redirects the raw legacy pages/privacy.php path to the new /privacy route', function () {
    $this->get('/pages/privacy.php')->assertRedirect('/privacy');
});

it('redirects the raw legacy help/about.php path to the new /about route', function () {
    $this->get('/help/about.php')->assertRedirect('/about');
});
