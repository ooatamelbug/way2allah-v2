<?php

/**
 * Pins confirmed-never-existed `khotab-*` URLs as 404 — a regression guard,
 * not new behavior. See implementation-findings.md IF-055.
 */

it('khotab-fatwa-{id}.htm stays 404 — no .htaccess rule, no generated link, and no dispatcher branch anywhere in legacy source combines the khotab and fatwa concepts under this URL shape (SOURCE_UNRECOVERABLE)', function () {
    $this->get('/khotab-fatwa-17.htm')->assertNotFound();
});
