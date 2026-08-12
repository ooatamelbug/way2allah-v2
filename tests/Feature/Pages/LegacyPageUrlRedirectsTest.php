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

/**
 * Roadmap task 6.3. Unlike privacy.php/about.php above, ramadan.php and
 * share.php DO have real .htaccess rules (to the missing new_modules.php
 * dispatcher) — /ramadan.htm and /share.htm are registered directly in
 * routes/pages.php, not via this redirect map. These entries only protect
 * the raw ".php" paths, same defensive shape as pages/social.php's entry.
 */
it('redirects the raw legacy pages/ramadan.php path to the new /ramadan.htm route', function () {
    $this->get('/pages/ramadan.php')->assertRedirect('/ramadan.htm');
});

it('redirects the raw legacy pages/ramadan1442.php path to the consolidated /ramadan.htm route', function () {
    $this->get('/pages/ramadan1442.php')->assertRedirect('/ramadan.htm');
});

it('redirects the raw legacy pages/ramadan-archive.php path to the consolidated /ramadan.htm route', function () {
    $this->get('/pages/ramadan-archive.php')->assertRedirect('/ramadan.htm');
});

it('redirects the raw legacy help/share.php path to the new /share.htm route', function () {
    $this->get('/help/share.php')->assertRedirect('/share.htm');
});
