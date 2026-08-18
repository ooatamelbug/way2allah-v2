<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Models\Concerns\TracksViews;
use App\Domain\Content\Models\Contracts\Viewable;
use App\Domain\Content\Support\MediaPathResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Blueprint v1.0 §6's Content aggregate root — one khotab item (a single
 * sermon/lecture, video/audio/PDF-transcribed) over `nuke_islamic_khotab`.
 * Owns `Mirror` (alternate download links). References `Author`, `Channel`,
 * `Series`, `KhotabGroup`, `Category` (many-to-many, Wave 4 task 4.2/4.3)
 * by id — never nested inside them (§6's aggregate table).
 *
 * Column list confirmed from 00-database-schema.md's `nuke_islamic_khotab`
 * entry (Fact, the single largest/most-referenced table in the audit — 300K
 * rows): id, in_storage, converted, backup, title, notes, author,
 * channel_id, description, meta_keywords, meta_description, meta_index,
 * meta_follow, link, linksize, mobile, pdf, banner, hits, downcount, vedio,
 * time, ser_id, weight, comments, cat, group_id, mirror, lastmirror,
 * advfile, down, trial, cd, newslist, broken, checktime, online, fixed,
 * archgif, thumbnail, hidden, lastvisit, frame, gif, build_thumb,
 * m_lastupdate, downloader, uploader, addeddate, leeched, location_id,
 * pdf_time, percent, docx, docx_time, booking.
 *
 * `vedio` is the real column (int 0/1) — see IF-014/IF-017/IF-018 in
 * implementation-findings.md for the `->video`/`->vedio` naming-mismatch
 * bug class this exact column name was the source of throughout khotab's
 * legacy pages, none of which are reproduced in this port.
 *
 * `cat` (pipe-delimited category-list string) is a confirmed data-quality
 * anti-pattern already superseded, for this table specifically, by the
 * `khotab_category_index` junction table (Fact, `db_migration.sql`, kept
 * in sync by DB triggers, not application code) — the `categories()`
 * relationship below uses that junction table, not this raw column.
 *
 * @property int $id
 * @property int|null $in_storage
 * @property int|null $converted
 * @property int|null $backup
 * @property string|null $title
 * @property string|null $notes
 * @property int $author
 * @property int|null $channel_id
 * @property string|null $description
 * @property string|null $meta_keywords
 * @property string|null $meta_description
 * @property int|null $meta_index
 * @property int|null $meta_follow
 * @property string|null $link
 * @property int|null $linksize
 * @property int|null $mobile
 * @property int $pdf
 * @property int $banner
 * @property int $hits
 * @property int $downcount
 * @property int $vedio
 * @property int|null $time
 * @property int $ser_id
 * @property int|null $weight
 * @property int|null $comments
 * @property string|null $cat
 * @property int $group_id
 * @property int|null $mirror
 * @property int|null $lastmirror
 * @property int $advfile
 * @property int|null $down
 * @property int|null $trial
 * @property int|null $cd
 * @property int $newslist
 * @property int|null $broken
 * @property int|null $checktime
 * @property int|null $online
 * @property int|null $fixed
 * @property int|null $archgif
 * @property int|null $thumbnail
 * @property int $hidden
 * @property int|null $lastvisit
 * @property int $frame
 * @property int $gif
 * @property int|null $build_thumb
 * @property int|null $m_lastupdate
 * @property string|null $downloader
 * @property string|null $uploader
 * @property int|null $addeddate
 * @property int|null $leeched
 * @property int|null $location_id
 * @property int|null $pdf_time
 * @property int|null $percent
 * @property int $docx
 * @property int|null $docx_time
 * @property int|null $booking
 * @property-read \App\Domain\Content\Models\Author|null $authorModel
 * @property-read \App\Domain\Content\Models\Channel|null $channel
 * @property-read \App\Domain\Content\Models\Series|null $series
 * @property-read \App\Domain\Content\Models\KhotabGroup|null $group
 * @property-read \App\Domain\Content\Models\KhotabAdvanced|null $advanced
 */
class KhotabItem extends Model implements Viewable
{
    use TracksViews;

    protected $connection = 'main';

    protected $table = 'nuke_islamic_khotab';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = ['id'];

    /** Named `authorModel` — `author` is already the legacy FK column name (an int), not a relationship. */
    public function authorModel(): BelongsTo
    {
        return $this->belongsTo(Author::class, 'author');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class, 'ser_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(KhotabGroup::class, 'group_id');
    }

    public function advanced(): HasOne
    {
        return $this->hasOne(KhotabAdvanced::class, 'id', 'id');
    }

    public function mirrors(): HasMany
    {
        return $this->hasMany(Mirror::class, 'khid');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'khid');
    }

    /**
     * `khotab_category_index` junction table (Fact) — not the raw `cat` column (superseded, see class docblock).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<Category, $this>
     */
    public function categories(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'khotab_category_index', 'khotab_id', 'category_id');
    }

    /**
     * `khotab/functions.php:957-991`'s `download_khotab()`, primary-link
     * branch — `update nuke_islamic_khotab set downcount=downcount+1`. A
     * distinct concept from `recordView()`/TracksViews (a download, not a
     * page view) — see `Mirror::incrementDownloadCount()`'s docblock for
     * why these are deliberately not unified into one shared mechanism yet.
     */
    public function incrementDownloadCount(): void
    {
        $this->increment('downcount');
    }

    /** `frame == 1` case of `topitems()`/`randomitems()` (functions.php:1046-1052, 1122-1125). */
    public function videoFramePath(): string
    {
        return MediaPathResolver::path('khotab_frames', $this->id, 'jpg');
    }

    /** `gif == 1` case of `randomitems()` (functions.php:1122-1123). */
    public function videoGifPath(): string
    {
        return MediaPathResolver::path('khotab_gifs', $this->id, 'gif');
    }

    /** `khotab/functions.php:1055` (`download_khotab_pdf()`'s redirect target). */
    public function pdfPath(): string
    {
        return MediaPathResolver::path('pdf', $this->id, 'pdf');
    }

    /** `khotab/functions.php:291` (`adminItemControls()`'s docx download link). */
    public function docxPath(): string
    {
        return MediaPathResolver::path('docx', $this->id, 'docx');
    }
}
