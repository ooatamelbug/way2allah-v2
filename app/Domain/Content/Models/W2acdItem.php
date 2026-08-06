<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Models\Concerns\TracksViews;
use App\Domain\Content\Models\Contracts\Viewable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per CD release (`nuke_w2acd_w2acd`, `w2acd` module —
 * 00-database-schema.md, Wave 3) — the core content table for this
 * module. Blueprint v1.0 §6: plain Eloquent model.
 *
 * Column list confirmed (Fact): id, title, notes, link, linksize, banner,
 * hits, downcount, mytime, weight, comments, group_id, parent_id,
 * author_id, channel_id, down, trial, cd, broken, vedio, mirror,
 * lastmirror, checktime, online, fixed, archgif, thumbnail, hidden,
 * lastvisit, lastupdate, order_in_group, downloader.
 *
 * `link`/`cd`/`thumbnail` are comma-delimited multi-value columns (P-015,
 * a confirmed data-quality anti-pattern), positionally paired with no
 * length-parity validation — `mirrorLinks()`/`thumbnailUrls()` below
 * reproduce `w2acd/functions.php`'s `list_w2acd_mirrors()`/`w2acd_details()`
 * explode-based parsing exactly, including their lack of validation. The
 * properly-normalized `nuke_w2acd_mirror` table already exists but is
 * confirmed UNUSED by any of the 3 `w2acd/*.php` files (00-database-
 * schema.md) — not modeled here; a data-cleaning migration to it (P-015)
 * is a separate, explicitly deferred task (Roadmap 4.4), not done as
 * part of porting these read-only pages.
 *
 * Implements `Viewable`/`TracksViews` (post-Wave-4 fix, cross-wave review
 * finding — `W2acdController::show()` originally hand-rolled the same
 * `hits`/`lastvisit` increment `RecordsView` already does for `Channel`/
 * `KhotabItem`/`AnasheedItem`/`Album`, a regression against an already-
 * validated shared pattern). `viewCountColumn()`/`tracksLastVisit()`
 * defaults (`hits`, `true`) both match this table's confirmed columns —
 * no override needed.
 *
 * `hidden` (Fact, confirmed by direct reading of `w2acd/cds.php`'s and
 * `w2acd/item.php`'s queries, neither of which filters on it at all) is
 * only partially enforced: hidden items remain listed in group pages AND
 * directly viewable by URL — `w2acd_details()` only suppresses the image
 * gallery for a hidden item, nothing else. Reproduced exactly, not
 * "fixed" — this is confirmed-existing behavior across both call sites,
 * not a copy-paste artifact like IF-025.
 *
 * @property int $id
 * @property string|null $title
 * @property string|null $notes
 * @property string $link
 * @property int|null $linksize
 * @property string|null $banner
 * @property int $hits
 * @property int $downcount
 * @property int|null $mytime
 * @property int|null $weight
 * @property int|null $comments
 * @property int $group_id
 * @property int|null $parent_id
 * @property int|null $author_id
 * @property int|null $channel_id
 * @property int|null $down
 * @property int|null $trial
 * @property string|null $cd
 * @property int|null $broken
 * @property int|null $vedio
 * @property int|null $mirror
 * @property int|null $lastmirror
 * @property int|null $checktime
 * @property int|null $online
 * @property int|null $fixed
 * @property int|null $archgif
 * @property string $thumbnail
 * @property int $hidden
 * @property int|null $lastvisit
 * @property int|null $lastupdate
 * @property int|null $order_in_group
 * @property string|null $downloader
 */
class W2acdItem extends Model implements Viewable
{
    use TracksViews;

    protected $connection = 'main';

    protected $table = 'nuke_w2acd_w2acd';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = ['id'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(W2acdGroup::class, 'group_id');
    }

    /** `w2acd/functions.php:90-92`'s `explode(",", $thumbnail)` + `array_filter()`. */
    public function thumbnailFilenames(): array
    {
        return array_filter(explode(',', (string) $this->thumbnail));
    }

    /** Group-listing thumbnail (`ListW2ACD()`'s `$thum_array[0]`) — first parsed thumbnail filename, or null if none. */
    public function firstThumbnailFilename(): ?string
    {
        return $this->thumbnailFilenames()[0] ?? null;
    }

    /**
     * `w2acd/functions.php:132-154`'s `list_w2acd_mirrors()` — positionally
     * pairs `link`/`cd` (comma-split), computing a file-extension per
     * link exactly as legacy does (`getExtension()`: substring after the
     * last `.`, empty string if none).
     *
     * @return list<array{link: string, title: string, extension: string}>
     */
    public function mirrorLinks(): array
    {
        $links = explode(',', (string) $this->link);
        $titles = explode(',', (string) $this->cd);

        $mirrors = [];

        foreach ($links as $index => $link) {
            $mirrors[] = [
                'link' => $link,
                'title' => $titles[$index] ?? '',
                'extension' => $this->extensionOf($link),
            ];
        }

        return $mirrors;
    }

    private function extensionOf(string $link): string
    {
        $position = strrpos($link, '.');

        if ($position === false) {
            return '';
        }

        return strtolower(substr($link, $position + 1));
    }
}
