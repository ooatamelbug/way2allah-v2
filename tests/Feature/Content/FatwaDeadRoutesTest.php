<?php

/**
 * Pins the confirmed-dead `fatawa`/`fatwa` routes as 404 (Phase 1 G-07
 * audit, §6 test-coverage gap) — none of these have any surviving
 * implementing code anywhere in the 16 `fatawa/` files, re-confirmed by
 * exhaustive grep during the audit. This is a regression guard, not new
 * behavior: if any of these ever start resolving, that's a route
 * registered somewhere it shouldn't be.
 */
// fatawa-category-{id}.htm moved OUT of this file (2026-08-22): its
// literal handler (fatawa/category.php) is still IF-038
// source-unrecoverable, but the URL itself was given an owner-approved
// alternate route onto FatwaTopicController::show() — see
// routes/content.php's own docblock and FatwaTopicControllerTest's
// "category:" tests. No longer a dead route.

// fatwa-date-{d}-{m}-{y}-{page}.htm moved OUT of this file (2026-08-28,
// decision-log #44, owner-clarified): unlike the 4 routes below, this one
// does NOT belong under "confirmed-dead" — a real .htaccess rule exists
// (`.htaccess:285`) and `fatwa-today.php` is a real, live, fully-migrated
// file; only the specific d/m/y-parameter shape (and the calendar widget
// that would generate links to it) is unbuilt. Owner instruction: keep it
// explicitly OUT OF SCOPE, not classified as permanently dead — moved to
// FatwaDayControllerTest.php, where it's tested alongside the route it's
// actually a variant of.

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
