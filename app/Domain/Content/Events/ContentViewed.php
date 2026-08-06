<?php

namespace App\Domain\Content\Events;

use App\Domain\Content\Models\Contracts\Viewable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Replaces P-014's 7+ independently hand-duplicated hit-counter
 * implementations (khotab/item.php, anasheed/item.php, w2acd/item.php,
 * w2acd/cds.php, khotab/functions.php's mirror update, anasheed/group.php,
 * anasheed/functions.php's mirror update — each its own
 * `UPDATE ... SET hits = hits + 1` statement) with one dispatch point
 * (Blueprint v1.0 §5). One of exactly two events retained in this
 * architecture (the other being CommentPosted) — every other cross-domain
 * concern uses a direct call, not an event (Wave 0 completion review).
 *
 * `Viewable&Model`: an Eloquent model AND a TracksViews-implementer — see
 * Viewable's own docblock for why the plain Model type wasn't precise
 * enough (added while wiring up PHPStan, pre-Wave-4 decision #3).
 */
class ContentViewed
{
    use Dispatchable;

    public function __construct(public readonly Viewable&Model $viewable) {}
}
