<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Models\Author;
use App\Domain\Content\Models\KhotabItem;
use App\Domain\Content\Services\ContentListingService;
use App\Domain\Content\Services\ContentSidebarWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Replaces the location-scoped ("Hedaya," `location_id=10`) recorded-lesson
 * browsing half of `chat_room/` — `chat_room/author.php`, `chat_room/lesson.php`
 * (browse/detail/download branches only). Roadmap task 4.11 (added
 * post-Wave-4, see docs/reviews/legacy-vs-laravel-coverage.md §4 and
 * docs/reviews/gap-closure-action-plan.md item 4).
 *
 * This directory's OTHER capability — the live voice-chat room
 * (`chat_room/room.php`, `chat_room/rules.php`, `chat_room/alhedaya_room.php`,
 * `chat_room/table.php`) — is NOT covered here. It stays Roadmap task 6.5,
 * gated on Business Confirmation #4. Do not add live-room routes/controllers
 * to this class.
 *
 * `chat_room/table.php`'s weekly-lesson-schedule feature
 * (`get_lessons_table()`/`list_today_lessons()`, `nuke_hedaya_lessons`) was
 * initially assumed in-scope for this task (see the action plan's original
 * item 4 text) — direct reading during implementation showed it schedules
 * attendance at the LIVE voice rooms (joins `$chatdb`'s `room` table, links
 * to `chat_{room_id}.htm`), not recorded content. Re-scoped to task 6.5;
 * NOT built here. See IF-033 for the full correction.
 *
 * OWNER_DECISION (`chat_room.htm` Owner-Approved Partial Reconstruction):
 * following the decomposition audit's corrected business-confirmation
 * record — "FlashChat / Live Voice Chat Rooms = NO. Zoom = ALSO NO...
 * the entire live-room/chat-room feature family is retired, with no
 * replacement of any kind" (decision-log #14) — `index()` below now
 * implements `chat_room/chat_rooms.php`'s two still-active sections
 * (`list_most_chat_room_authors()`, `most_viewed_chat_lessons()`/
 * `most_recent_chat_lessons()`, this class's own domain already). Its
 * two retired sections (`list_chat_rooms()`, `list_today_lessons()`) are
 * intentionally omitted — not rendered empty, not replaced with a notice.
 *
 * Reuses `KhotabItem`/`Mirror`/`Author` directly (Wave 4) — these are the
 * exact same rows khotab's own pages serve, just filtered to
 * `location_id=10`. `ChatRoomLessonController::download()` reuses
 * `KhotabItem::incrementDownloadCount()` (the same model method
 * `KhotabItemController::download()` uses) but returns a redirect, not a
 * streamed file — matching `chat_room/lesson.php`'s own `op=getit` branch
 * exactly (`Header("Location: ...")`, not a stream), a genuine, confirmed
 * behavioral difference from khotab's own download route for the same
 * underlying content.
 */
class ChatRoomLessonController
{
    private const LOCATION_ID = 10;

    /**
     * `chat_room/chat_rooms.php` — the `chat_room.htm` listing page.
     * OWNER_DECISION: live FlashChat rooms and the weekly live-lesson
     * schedule are retired and intentionally omitted here; recorded-lesson
     * discovery (most active authors, most viewed/recent recorded lessons)
     * remains active — see this class's own docblock. Author image uses
     * `Author::fallbackImageUrl()`, not `displayImageUrl()` — legacy's
     * `list_most_chat_room_authors()` calls `get_author_img_src()`
     * directly (`chat_room/functions.php:575-586`), which never checks the
     * `author_image` column override, unlike `get_author_img()` (the
     * function `displayImageUrl()` is based on, used by this class's own
     * `chat-room.author` view for a different call site).
     */
    public function index(ContentListingService $listing, ContentSidebarWidget $sidebar): View
    {
        $mostActiveAuthors = $listing->mostActiveAuthorsAtLocation(self::LOCATION_ID);
        $authorImages = Author::whereIn('id', $mostActiveAuthors->pluck('id'))->get()->keyBy('id');
        $mostViewed = $sidebar->chatRoomMostViewedLessons();
        $mostRecent = $sidebar->chatRoomMostRecentLessons();

        return view('chat-room.index', compact('mostActiveAuthors', 'authorImages', 'mostViewed', 'mostRecent'));
    }

    /**
     * `chat_room/author.php`. `list_author_chat_lessons()` is dead code in
     * the legacy file (call commented out, confirmed) — the real content is
     * `ListGroup()`/`ListSeries()`/`ListKhotab()`, all filtered by this
     * author + location. 404s if the author isn't registered for this
     * location (`get_author_obj()`'s join returning no row) — legacy
     * instead renders a degraded page with `$author_obj` empty; this
     * diverges deliberately, matching this codebase's established
     * not-found-entity pattern elsewhere (`CategoryController`,
     * `KhotabItemController`'s series/group checks) rather than reproducing
     * the null-property-access edge case.
     */
    public function author(int $author, ContentListingService $listing, ContentSidebarWidget $sidebar): View
    {
        $authorModel = $this->authorAtLocation($author);
        abort_if($authorModel === null, 404);

        $groups = $listing->groupsByAuthorAndLocation($author, self::LOCATION_ID);
        $series = $listing->seriesByAuthorAndLocation($author, 0, self::LOCATION_ID);
        $items = $listing->khotabItemsByAuthorAndLocation($author, 0, 0, self::LOCATION_ID);
        $mostViewed = $sidebar->chatRoomMostViewedLessons($author);
        $mostRecent = $sidebar->chatRoomMostRecentLessons($author);

        return view('chat-room.author', compact('authorModel', 'groups', 'series', 'items', 'mostViewed', 'mostRecent'));
    }

    /**
     * `chat_room/lesson.php` (browse branch — `op=getit`/`op=getmirror`
     * live at their own routes, see `download()` below).
     *
     * `hits+1` only, no `lastvisit` update (`chat_room/functions.php:999`,
     * `show_lesson_details()`) — NOT `KhotabItem::recordView()`/TracksViews.
     * A genuine, confirmed difference from `khotab/item.php`'s own view
     * counting for the exact same underlying row shape: this legacy page
     * simply never adopted the `lastvisit` half of the pattern. Reproduced
     * as found, not silently unified with khotab's own pipeline.
     */
    public function show(int $lesson, ContentSidebarWidget $sidebar): View
    {
        $khotabItem = KhotabItem::where('location_id', self::LOCATION_ID)->where('hidden', 0)->findOrFail($lesson);

        $authorModel = $this->authorAtLocation($khotabItem->author);
        abort_if($authorModel === null, 404);

        $previousLesson = KhotabItem::where('author', $khotabItem->author)
            ->where('weight', '<', $khotabItem->weight)
            ->where('location_id', self::LOCATION_ID)
            ->where('hidden', 0)
            ->orderByDesc('weight')
            ->first(['id', 'title']);

        $nextLesson = KhotabItem::where('author', $khotabItem->author)
            ->where('weight', '>', $khotabItem->weight)
            ->where('location_id', self::LOCATION_ID)
            ->where('hidden', 0)
            ->orderBy('weight')
            ->first(['id', 'title']);

        $mirrors = $khotabItem->mirrors()->with('advanced')->orderBy('id')->get();
        $relatedLessons = $this->relatedLessons($khotabItem);
        $mostViewed = $sidebar->chatRoomMostViewedLessons($khotabItem->author);
        $mostRecent = $sidebar->chatRoomMostRecentLessons($khotabItem->author);

        $khotabItem->increment('hits');

        return view('chat-room.lesson', compact(
            'khotabItem', 'authorModel', 'previousLesson', 'nextLesson', 'mirrors', 'relatedLessons', 'mostViewed', 'mostRecent',
        ));
    }

    /** `chat_room/functions.php`'s `download_lesson($id)` — no location/hidden filter at all (confirmed, matching `KhotabItemController::download()`'s own no-filter precedent for the same reason). */
    public function download(int $lesson): RedirectResponse
    {
        $khotabItem = KhotabItem::select(['id', 'link'])->findOrFail($lesson);

        $khotabItem->incrementDownloadCount();

        return redirect($khotabItem->link ?? '/');
    }

    /** `chat_room/functions.php`'s `get_author_obj($id)` — join through `nuke_islamic_authors_location`, not a plain `Author::find()`. */
    private function authorAtLocation(int $authorId): ?Author
    {
        $exists = DB::connection('main')->table('nuke_islamic_authors_location')
            ->where('author_id', $authorId)
            ->where('location_id', self::LOCATION_ID)
            ->exists();

        if (! $exists) {
            return null;
        }

        return Author::where('hidden', 0)->find($authorId);
    }

    /**
     * `chat_room/functions.php`'s `get_related_lessons()` — splits the
     * lesson's title into words of 4+ Arabic characters (legacy's
     * `strlen($tag)/2 >= 4` — Arabic UTF-8 bytes, 2 bytes/char), matches any
     * of them via OR'd `LIKE`, excludes the lesson itself, scoped to this
     * location, `ORDER BY RAND() LIMIT 5` (reproduced as found — legacy's
     * own comment at this call site already acknowledges this as a known,
     * not-yet-proven-hot inefficiency, not a fresh finding here).
     */
    private function relatedLessons(KhotabItem $khotabItem): Collection
    {
        // `strlen($tag)/2 >= 4` — legacy's byte-length heuristic (Arabic
        // UTF-8 characters are typically 2 bytes each), reproduced exactly
        // via PHP's own byte-counting strlen(), not mb_strlen().
        $words = array_filter(
            explode(' ', (string) $khotabItem->title),
            fn (string $word) => (strlen(trim($word)) / 2) >= 4,
        );

        if ($words === []) {
            return collect();
        }

        $query = DB::connection('main')->table('nuke_islamic_khotab as k')
            ->join('nuke_islamic_authors as t', 'k.author', '=', 't.id')
            ->where('k.id', '!=', $khotabItem->id)
            ->where('k.location_id', self::LOCATION_ID)
            ->where(function ($q) use ($words) {
                foreach ($words as $word) {
                    $q->orWhere('k.title', 'like', "%{$word}%");
                }
            })
            ->select(['k.id', 'k.title', 'k.hits', 'k.downcount', 't.id as author_id', 't.prename', 't.name']);

        return $query->inRandomOrder()->limit(5)->get();
    }
}
