<?php

/**
 * Pins the confirmed-dead `fatawa`/`fatwa` routes as 404 (Phase 1 G-07
 * audit, §6 test-coverage gap) — none of these have any surviving
 * implementing code anywhere in the 16 `fatawa/` files, re-confirmed by
 * exhaustive grep during the audit. This is a regression guard, not new
 * behavior: if any of these ever start resolving, that's a route
 * registered somewhere it shouldn't be.
 */
it('fatawa-category-{id}.htm stays 404 — fatawa/category.php is confirmed source-unrecoverable (IF-038)', function () {
    $this->get('/fatawa-category-1.htm')->assertNotFound();
});

it('fatawa-play-{id}.htm stays 404 — no file implements op=play', function () {
    $this->get('/fatawa-play-1.htm')->assertNotFound();
});

it('fatawa-brokenlink-{id}.htm stays 404 — no file implements op=brokenlink', function () {
    $this->get('/fatawa-brokenlink-1.htm')->assertNotFound();
});

it('fatawa-friend-{id}.htm stays 404 — no file implements the op=friend display step (only op=sendemail is built)', function () {
    $this->get('/fatawa-friend-1.htm')->assertNotFound();
});

it('auther-all-fatawa-{id}-{id2}.htm stays 404 — no file implements op=all_fatawa_for_auther', function () {
    $this->get('/auther-all-fatawa-1-1.htm')->assertNotFound();
});

it('fatwa-date-{d}-{m}-{y}-{page}.htm stays 404 — fatwa-today.php never reads separate d/m/y parameters', function () {
    $this->get('/fatwa-date-1-1-2020-1.htm')->assertNotFound();
});
