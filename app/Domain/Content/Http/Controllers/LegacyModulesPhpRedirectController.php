<?php

namespace App\Domain\Content\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Visitor URL compatibility A (owner, 2026-11-06; decision-log #104) — the two approved Gen 2 `modules.php`
 * redirects, with their exact Apache matching semantics (`7amlat/.htaccess:187-188, 193-194`):
 *
 *   RewriteCond %{QUERY_STRING} ^name=Khotab&op=Audio$
 *   RewriteRule ^modules\.php$ …/khotab-audio.htm? [R=301,L]
 *
 *   RewriteCond %{QUERY_STRING} ^name=Khotab&op=Day&video=0&d=([0-9]*)&m=([0-9]*)&y=([0-9]*)$
 *   RewriteRule ^modules\.php$ …/khotab-audiodate-%1-%2-%3.htm? [R=301,L]
 *
 * The whole raw query string must match — same order, case-sensitive, nothing extra — and the query is dropped from
 * the target (the trailing `?`). Any other `modules.php` request is a 404, as on Gen 2, where no `modules.php` file
 * exists. The third Gen 2 rule (`name=w2acd&op=DownSeries`, `.htaccess:265-266`) is not reproduced: `cds-series .grx`
 * stays an owner-approved LOW deferral (#91 / #92). No OLD `modules.php` behaviour is exposed.
 */
class LegacyModulesPhpRedirectController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $query = (string) $request->server('QUERY_STRING', '');

        if ($query === 'name=Khotab&op=Audio') {
            return redirect()->to('/khotab-audio.htm', 301);
        }

        if (preg_match('/^name=Khotab&op=Day&video=0&d=([0-9]*)&m=([0-9]*)&y=([0-9]*)$/D', $query, $m) === 1) {
            return redirect()->to("/khotab-audiodate-{$m[1]}-{$m[2]}-{$m[3]}.htm", 301);
        }

        abort(404);
    }
}
