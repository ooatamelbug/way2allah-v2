<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per fatwa sub-topic (`nuke_fatwa_topics`) — Roadmap task 6.1.
 * `parent_id` refers to a `Category` (`nuke_w2a_cat.id`), NOT another
 * `FatwaTopic` row — confirmed directly from `fatawa/functions.php`'s
 * `get_all_tasnifat($id)` (`WHERE parent_id=$id`, called from
 * `tobics.php:72` with `$id` being a `nuke_w2a_cat` category id, never
 * another topic id). No evidence anywhere in the legacy source of
 * topic-under-topic nesting; not modeled as self-referential.
 *
 * Column list confirmed from `fatawa.md` §4 (Fact, from `CREATE TABLE`):
 * id, topic_name, description, meta_keywords, meta_description,
 * meta_index, meta_follow, parent_id, db_insertion_date, author_id,
 * channel_id.
 *
 * Plain Eloquent model, referenced by id, never owned — same convention
 * as `Category`/`Channel`/`KhotabGroup` (Blueprint v1.0 §6).
 *
 * @property int $id
 * @property string|null $topic_name
 * @property string|null $description
 * @property string|null $meta_keywords
 * @property string|null $meta_description
 * @property int|null $meta_index
 * @property int|null $meta_follow
 * @property int $parent_id
 * @property int|null $db_insertion_date
 * @property int|null $author_id
 * @property int|null $channel_id
 */
class FatwaTopic extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_fatwa_topics';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = ['id'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }
}
