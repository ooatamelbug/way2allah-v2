<?php

namespace App\Domain\Content\Listeners;

use App\Domain\Content\Events\ContentViewed;

/**
 * Reproduces the legacy `UPDATE ... SET hits = hits + 1[, lastvisit = <ts>]
 * WHERE id = <id>` pattern (P-014) as one dispatch point instead of 9+
 * independent copies (7 original + live-stream's `ch_visits`, found Wave
 * 3 — see TracksViews::viewCountColumn()). Uses Eloquent's own atomic
 * increment() (not read-then-write, and not a hand-rolled raw expression
 * — simplified from Wave 1's original version, per its own self-critique)
 * so concurrent views don't lose increments, the same race-safety
 * property the legacy SQL already had.
 *
 * `lastvisit` is stored as a raw Unix timestamp (`time()`), matching every
 * legacy call site — not a Carbon/DATETIME column.
 */
class RecordsView
{
    public function handle(ContentViewed $event): void
    {
        $model = $event->viewable;

        $extra = $model->tracksLastVisit() ? ['lastvisit' => time()] : [];

        $model->newQueryWithoutScopes()
            ->whereKey($model->getKey())
            ->increment($model->viewCountColumn(), 1, $extra);
    }
}
