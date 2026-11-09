<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Models\Location;
use App\Domain\Content\Services\ContentListingService;
use App\Domain\Content\Services\ContentSidebarWidget;
use App\Support\Http\AdminViewer;
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
 * `locationAuthor()` (decision-log #114) serves `location-{id}-author-{id2}.htm`
 * for the REQUESTED location, as OLD `modules/Locations` `Author($id,
 * $location)` does, via `ChatRoomLessonController::authorAtGivenLocation()`:
 * a nonexistent location is a 404, the author must be registered at that
 * location (else 404), and groups, series, items and both sidebars are
 * scoped to it. `chat_author_{id2}.htm` stays fixed to location 10. History:
 * IF-048 originally made this a pass-through to the location-10 chat-room
 * author page (Gen 2 `chat_room/author.php` hardcodes `$location_id = 10`);
 * #114 superseded that.
 *
 * `locationItem()` is unchanged (IF-049): `location-{id}-item-{id2}.htm`
 * reuses `khotab/item.php` exactly, which ignores the `location` segment, so
 * it delegates to the unmodified `KhotabItemController::show()`.
 *
 * Both methods declare `{location}` and the second id by name because, when
 * a route declares more segments than the target method's parameters,
 * Laravel binds the extra segment(s) positionally rather than by name
 * (caught during Wave C verification via `Route::parameters()` and a
 * position-swap test).
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
        // decision-log #114: scope to the REQUESTED location (OLD Locations `Author($id, $location)`), not location 10.
        return $chatRoomLessonController->authorAtGivenLocation($location, $author, $listing, $sidebar);
    }

    public function locationItem(
        int $location,
        int $khotab,
        KhotabItemController $khotabItemController,
        ContentSidebarWidget $sidebar,
        AdminViewer $contextual,
    ): View {
        return $khotabItemController->show($khotab, $sidebar, $contextual);
    }
}
