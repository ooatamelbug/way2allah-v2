<?php

namespace App\Domain\Content\Models\Concerns;

use App\Domain\Content\Events\ContentViewed;

/**
 * Opt-in trait for any Content-domain model with an atomic visit/hit
 * counter column — the dispatch side of ContentViewed/RecordsView
 * (Roadmap task 1.4).
 */
trait TracksViews
{
    public function recordView(): void
    {
        ContentViewed::dispatch($this);
    }

    /**
     * Whether this model's table also has a `lastvisit` column to update.
     * Confirmed present on content-item tables (nuke_islamic_khotab,
     * anasheed_anasheed, w2acd_w2acd — khotab/item.php, anasheed/item.php,
     * w2acd/item.php all set it) but absent on mirror tables
     * (nuke_islamic_mirror, anasheed_mirror only ever do `hits = hits + 1`,
     * no lastvisit — khotab/functions.php:988, anasheed/functions.php:469).
     * Defaults to true; Mirror-shaped models override this to false.
     */
    public function tracksLastVisit(): bool
    {
        return true;
    }

    /**
     * The counter column RecordsView increments. Defaults to `hits`
     * (P-014's original 9 call sites). Wave 3 found the first real
     * exception: `nuke_sat_channels.ch_visits`
     * (live-stream/live-channel.php:37) is the same atomic-increment
     * concept under a different column name — Channel overrides this
     * rather than the trait staying hardcoded to `hits`. A small,
     * evidence-driven refinement to an already-built Wave 1 component
     * (Implementation Refactoring), not a new shared-component decision.
     */
    public function viewCountColumn(): string
    {
        return 'hits';
    }
}
