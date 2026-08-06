<?php

namespace App\Domain\Content\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * One of exactly two events retained in this architecture (Blueprint v1.0
 * §5, the other being ContentViewed) — reserved for comment moderation
 * and notification, "genuinely independent consumers" per the Blueprint's
 * own wording.
 *
 * Dispatched by `KhotabItemController::storeComment()` and
 * `AnasheedItemController::storeComment()` (post-Wave-4 cross-wave review,
 * decision-log #7) but **no listener is registered yet**. Neither legacy
 * `add_khotab_comment()` nor `add_anasheed_comment()` performs any
 * moderation or notification action beyond the insert itself (confirmed
 * by direct reading, both Wave 4) — there is no real behavior to port
 * into a listener today, only the Blueprint's stated intent for one to
 * exist later (an `admincp` moderation queue, Wave 6 territory, and/or an
 * admin-notification channel). Dispatching now — without inventing a
 * listener body evidence doesn't support yet — closes the "Blueprint
 * specifies this event, implementation never dispatches it" gap without
 * building ahead of need, matching this project's standing discipline
 * (same reasoning already applied to `EnsureAdminHasRole` sitting unused
 * until Wave 5's first real consumer).
 *
 * Typed against the plain `Model` (not a dedicated contract, unlike
 * `ContentViewed`'s `Viewable&Model`): `Comment` and `AnasheedComment` are
 * two separate, non-polymorphic models with no shared interface today,
 * and no listener exists yet to demand one — a contract gets added only
 * once a real listener needs to call a specific method across both.
 */
class CommentPosted
{
    use Dispatchable;

    public function __construct(public readonly Model $comment) {}
}
