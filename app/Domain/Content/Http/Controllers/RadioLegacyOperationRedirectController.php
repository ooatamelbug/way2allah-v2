<?php

namespace App\Domain\Content\Http\Controllers;

use Illuminate\Http\RedirectResponse;

/**
 * Visitor URL compatibility B (owner, 2026-11-06; decision-log #104) — Gen 2's three radio operation URLs
 * (`7amlat/.htaccess:343-345`: `remove-playlist-item-{id}-{section}.htm`, `playlist-item-{id}.htm`,
 * `save-last-listen.htm`) all reach `radio/index.php`, which ignores the operation and renders the radio page
 * (IF-032). The owner declined reimplementing the operations; a GET to these URLs is redirected to `radio.htm`.
 * Only GET / HEAD are handled — Gen 2's own callers are POST AJAX (`scripts/w2a_radio.js`, `add_radio.js`) and are
 * deliberately not redirected. Nothing is read or written.
 */
class RadioLegacyOperationRedirectController
{
    public function __invoke(): RedirectResponse
    {
        return redirect()->route('radio.index', [], 301);
    }
}
