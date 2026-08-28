<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Models\Author;
use App\Domain\Content\Services\ContentListingService;
use App\Domain\Content\Services\ContentSidebarWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

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
     * (`functions.php:647`), a *different* URL than this module's own
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
     * by this port. Classified `LEGACY_BROKEN_LINK` (decision-log #50):
     * a real generator, a real matching `.htaccess` rule, but genuinely no
     * implementation anywhere — reported, not silently repaired without
     * explicit approval (unlike `khotab-fatwa-*`'s own, separately-approved
     * repair, entry #49 — this one hasn't gone through that same process).
     */
    public function show(int $author, int $page, ContentListingService $listing, ContentSidebarWidget $sidebar): View
    {
        $authorModel = Author::findOrFail($author);

        $generalQuestions = $listing->fatwaGeneralQuestionsByAuthor($author, $page);

        // fatawa/functions.php:682,695 — the auther_id filter is commented
        // out in legacy (both mostdownload() and recentlyadd()); this
        // sidebar is confirmed sitewide-unscoped, not author-scoped, on
        // every author's page. See
        // ContentSidebarWidget::fatwaMostDownloadedSitewide()'s docblock.
        $mostDownloaded = $sidebar->fatwaMostDownloadedSitewide();
        $mostRecent = $sidebar->fatwaMostRecentSitewide();

        // Author Questions Visual Parity Pass (decision-log #50) — same
        // real pretty-URL pagination contract as FatwaDayController
        // (fatawa.author.show/.show.paged mirror fatawa.day.today/.paged
        // exactly), reused via fatawa.partials.pagination, not duplicated.
        $pageUrl = fn (int $requestedPage): string => $requestedPage === 1
            ? route('fatawa.author.show', ['author' => $author])
            : route('fatawa.author.show.paged', ['author' => $author, 'page' => $requestedPage]);

        return view('fatawa.author-show', compact('authorModel', 'generalQuestions', 'mostDownloaded', 'mostRecent', 'pageUrl'));
    }

    /**
     * `khotab-fatwa-{author}.htm` compatibility redirect — `BUSINESS_REPAIR_
     * LOW_RISK` (decision-log #48/#49), NOT legacy parity. `{author}` is
     * `nuke_islamic_authors.id` (proven in #48 via `khotab/authors.php:80`'s
     * own real link-generation loop). Legacy itself never implemented a
     * "fatwa" branch of `khotab/author.php` — no `.htaccess` rule, no
     * fatwa-op handling in the target file — so this is not a reconstructed
     * legacy route; it repairs a real, live, currently-broken link
     * `fatawa-authors.htm` still generates today, by pointing it at the
     * already-correct, already-proven canonical page for the exact same
     * "this author's fatwas" content (`fatawa.author.show` /
     * `/auther-questions-{author}.htm`, `ContentListingService::
     * fatwaGeneralQuestionsByAuthor()`) — not a second rendering path.
     * Resolved via the named route, not a hardcoded URL string, so this
     * stays correct if that route's own URI ever changes.
     */
    public function redirectFromKhotabFatwaUrl(int $author): RedirectResponse
    {
        return redirect()->route('fatawa.author.show', ['author' => $author]);
    }
}
