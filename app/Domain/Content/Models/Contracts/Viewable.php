<?php

namespace App\Domain\Content\Models\Contracts;

/**
 * Contract satisfied by the TracksViews trait (Models/Concerns/TracksViews.php).
 * Added while wiring up PHPStan/Larastan (pre-Wave-4 decision #3) — RecordsView
 * and ContentViewed previously typed the viewable model as the base Eloquent
 * `Model`, which doesn't declare tracksLastVisit()/viewCountColumn(); PHPStan
 * correctly flagged calling trait-only methods against that wider type. Models
 * using TracksViews should implement this contract explicitly (as Channel now
 * does) so the relationship is declared, not just structurally true.
 */
interface Viewable
{
    public function tracksLastVisit(): bool;

    public function viewCountColumn(): string;

    public function recordView(): void;
}
