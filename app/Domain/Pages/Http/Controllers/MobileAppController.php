<?php

namespace App\Domain\Pages\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * Replaces pages/mobile-app.php (Roadmap task 2.4). pages.md §5: "genuine,
 * complete, static marketing page ... no database interaction, no bugs
 * found, no dead code." Confirmed no .htaccess rule (pages.md §2 row 26) —
 * reachable only at its raw legacy path, same profile as privacy/about.
 */
class MobileAppController
{
    public function __invoke(): View
    {
        return view('pages.mobile-app');
    }
}
