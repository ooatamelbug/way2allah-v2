<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Services\ContentListingService;
use Illuminate\Contracts\View\View;

/**
 * Replaces `fatawa/fatawa-by-authers.php` — Roadmap task 6.1, G-07-03
 * (Phase 1 audit found this file had real, complete surviving source but
 * had never been built; not part of any prior increment).
 *
 * `fatawa-by-authers.php` itself branches on its own `$_GET['op']`
 * (`video`/`audio`/`pdf`/default-`fatwa`), but the `.htaccess:279` rule for
 * `fatawa-by-authers.htm` only ever dispatches through the missing
 * `modules.php?name=Fatwa&op=fatawa_by_authers` — `op` never becomes
 * `video`/`audio`/`pdf` via this route, so only the default (`fatwa`)
 * branch is reachable here. The other 3 branches have real, readable
 * source but no surviving route that reaches them — deliberately not
 * built, per this project's standing "don't invent reachability" rule.
 */
class FatwaByAuthorsController
{
    public function index(ContentListingService $listing): View
    {
        $authors = $listing->fatwaAuthorsWithQuestions();

        return view('fatawa.by-authors', compact('authors'));
    }
}
