<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Models\Author;
use App\Domain\Content\Services\ContentListingService;
use App\Domain\Content\Services\ContentSidebarWidget;
use Illuminate\Contracts\View\View;

/**
 * Replaces `fatawa/auther_profile.php` — one scholar's own fatwa question
 * list (distinct from `fatawa-authors.htm`'s directory, which reuses
 * `KhotabAuthorController::index()` — see that controller's own
 * docblock). Roadmap task 6.1, increment 2.
 */
class FatwaAuthorController
{
    /**
     * `auther_profile.php` — `ContentListingService::fatwaGeneralQuestionsByAuthor()`.
     *
     * **Legacy's own generated links point to `auther-all-fatawa-{auther_id}-{g_q_id}.htm`**
     * (`functions.php:645`), a *different* URL than this module's own
     * `fatawa-all-{id}.htm` (which this port's `FatwaQuestionController::showAll()`
     * serves). `.htaccess:298` confirms a real rule exists for that URL
     * (`op=all_fatawa_for_auther`), but no file among the 16 read for this
     * module implements that specific op — its actual (possibly
     * author-highlighted) rendering is unknown. **Reproduced as legacy's
     * own link target, not silently redirected to this port's own
     * `fatawa-all-*` route** — inventing that redirect would assume the
     * two ops render identically, which isn't evidenced. The link
     * therefore points at a URL this Laravel app doesn't yet serve either
     * (same "confirmed real route, no implementing file" category as
     * `fatawa-play-*`/`fatawa-brokenlink-*`) — not a regression introduced
     * by this port.
     */
    public function show(int $author, int $page, ContentListingService $listing, ContentSidebarWidget $sidebar): View
    {
        $authorModel = Author::findOrFail($author);

        $generalQuestions = $listing->fatwaGeneralQuestionsByAuthor($author, $page);

        // fatawa/functions.php:682 — the auther_id filter is commented
        // out in legacy; this sidebar is confirmed sitewide-unscoped, not
        // author-scoped, on every author's page. See
        // ContentSidebarWidget::fatwaMostDownloadedSitewide()'s docblock.
        $mostDownloaded = $sidebar->fatwaMostDownloadedSitewide();
        $mostRecent = $sidebar->fatwaMostRecentSitewide();

        return view('fatawa.author-show', compact('authorModel', 'generalQuestions', 'mostDownloaded', 'mostRecent'));
    }
}
