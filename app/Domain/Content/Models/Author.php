<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per khotab speaker (Blueprint v1.0 §6 — plain Eloquent model,
 * referenced by `Series`/`KhotabItem`/`KhotabGroup`, never owned).
 *
 * Column list confirmed from 00-database-schema.md's `nuke_islamic_authors`
 * entry (Fact): id, name, prename, description, meta_keywords,
 * meta_description, meta_index, meta_follow, audio, vedio, fatwa, pdf, des,
 * author_image, block, banner, advfile, hidden, topdownload, topnews, time,
 * updatetime, docx, stats. No secondary indexes (deliberately — low
 * selectivity on a small table, per prior-session performance work).
 *
 * `audio`/`vedio`/`fatwa`/`pdf` are denormalized per-content-type counters
 * used by `khotab/authors.php` (Fact, lines 8/14/19/24) to both filter
 * ("show only authors with vedio > 0") and display a count next to each
 * author's name — not booleans despite the naming, and not independently
 * verified to always match a live COUNT() of the author's actual items.
 *
 * @property int $id
 * @property string|null $name
 * @property string|null $prename
 * @property string|null $description
 * @property string|null $meta_keywords
 * @property string|null $meta_description
 * @property int|null $meta_index
 * @property int|null $meta_follow
 * @property int $audio
 * @property int $vedio
 * @property int $fatwa
 * @property int $pdf
 * @property string|null $des
 * @property string|null $author_image
 * @property int|null $block
 * @property int|null $banner
 * @property int|null $advfile
 * @property int $hidden
 * @property int|null $topdownload
 * @property int|null $topnews
 * @property int|null $time
 * @property int|null $updatetime
 * @property int|null $docx
 * @property int|null $stats
 */
class Author extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_islamic_authors';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = ['id'];

    public function series(): HasMany
    {
        return $this->hasMany(Series::class, 'author_id');
    }

    public function khotabItems(): HasMany
    {
        return $this->hasMany(KhotabItem::class, 'author');
    }

    public function khotabGroups(): HasMany
    {
        return $this->hasMany(KhotabGroup::class, 'author_id');
    }

    /**
     * `functions.php:1143`'s `get_author_img()` — a third, non-bucketed
     * media convention (IF-013): `media/authors/sq/{id}.png`, with an
     * existence check falling back to `media/authors/no_author_image.png`.
     * Deliberately NOT MediaPathResolver's `floor(id/1000)` bucketing — this
     * is a distinct, smaller convention confirmed only for this one
     * square-thumbnail use case (`author_image` column, when set, takes
     * priority over this fallback — `khotab/author.php:104`).
     */
    public function fallbackImageUrl(): string
    {
        $path = public_path("media/authors/sq/{$this->id}.png");

        return file_exists($path)
            ? "/media/authors/sq/{$this->id}.png"
            : '/media/authors/no_author_image.png';
    }

    public function displayImageUrl(): string
    {
        return $this->author_image !== null && $this->author_image !== ''
            ? $this->author_image
            : $this->fallbackImageUrl();
    }
}
