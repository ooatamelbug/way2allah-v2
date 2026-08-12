<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Admin\Models\Location;
use App\Domain\Content\Services\ContentListingService;
use App\Domain\Content\Services\ContentSidebarWidget;
use Illuminate\Contracts\View\View;

/**
 * Wave C ("Public Locations & Da'wah Registration Surfaces"). Replaces the
 * generic half of the missing `new_modules.php`'s `Locations` module
 * (`location-{id}.htm`) — `.htaccess:154`. No file implementing this
 * generic (any-location) page survives anywhere in the codebase; the only
 * surviving source is `chat_room/alhedaya_room.php`, a hardcoded
 * `location=10` instance of the same shape (location details, branched on
 * `type`, then an alphabetical author list scoped to that location). This
 * controller generalizes that structure to any location id — a moderate,
 * not "very high," confidence generalization (unlike
 * `ChatRoomLessonController`/`KhotabItemController` below, which are
 * exact, proven source matches), explicitly flagged as such rather than
 * presented as an equal-confidence port.
 *
 * `alhedaya-room.htm` (`.htaccess:162`, `new_modules.php?name=Locations&
 * location=10` — a hardcoded literal in the rule itself, not a dynamic
 * segment) reuses this exact same action with `location` defaulted to 10
 * in the route — not a separate controller/query, since real production
 * data confirms `location-10.htm` and `alhedaya-room.htm` are the same
 * location row.
 *
 * The legacy photo line (`alhedaya_room.php:34-39`) computes a real
 * bucketed path then unconditionally overwrites it with one hardcoded
 * Hidaya-specific `archive.org` image — confirmed dead/overridden code,
 * not a real per-location convention. Per explicit decision, NOT
 * reproduced: `Location::photoUrl()` uses the computed path and its own
 * `no_location_image.png` fallback instead (see that model's own
 * docblock).
 *
 * `locationAuthor()`/`locationItem()` below are NOT new query logic —
 * `location-{id}-author-{id2}.htm` and `location-{id}-item-{id2}.htm` were
 * re-verified (IF-048/IF-049) to reuse `chat_room/author.php`/
 * `khotab/item.php` exactly, both of which ignore the `location` URL
 * segment entirely. These two methods exist only because of a Laravel
 * routing mechanic, not a legacy-behavior difference: when a route
 * declares more segments than the target method's parameters, Laravel
 * binds the extra segment(s) positionally rather than by name, which
 * silently fed `{location}`'s value into `ChatRoomLessonController::
 * author()`'s/`KhotabItemController::show()`'s single int parameter
 * instead of the real `{author}`/`{khotab}` value (caught during this
 * round's own verification, confirmed via `Route::parameters()` and a
 * position-swap test). Declaring both route parameters explicitly by name
 * here, then delegating to the real, unmodified, already-tested
 * controllers, fixes the binding without touching either of them or
 * duplicating their queries.
 */
class LocationController
{
    /**
     * `chat_room/alhedaya_room.php:23`'s own location fetch has no
     * `hidden` filter at all — confirmed by direct re-read, not assumed
     * from this codebase's usual per-listing convention. Real `olddb` data
     * shows location 10 itself is `hidden=1`, and `alhedaya-room.htm` is a
     * real, proven-live legacy page for it regardless — applying a hidden
     * filter here (as an initial draft of this controller did) would 404
     * a page legacy actually served. Not filtered, matching the source
     * exactly. The author list's own `hidden=0` filter (line 80) is a
     * separate, real condition, preserved in `authorsByLocation()`.
     */
    public function show(int $location, ContentListingService $listing): View
    {
        $locationModel = Location::findOrFail($location);

        $authors = $listing->authorsByLocation($location);

        return view('locations.show', compact('locationModel', 'authors'));
    }

    public function locationAuthor(
        int $location,
        int $author,
        ChatRoomLessonController $chatRoomLessonController,
        ContentListingService $listing,
        ContentSidebarWidget $sidebar,
    ): View {
        return $chatRoomLessonController->author($author, $listing, $sidebar);
    }

    public function locationItem(
        int $location,
        int $khotab,
        KhotabItemController $khotabItemController,
        ContentSidebarWidget $sidebar,
    ): View {
        return $khotabItemController->show($khotab, $sidebar);
    }
}
