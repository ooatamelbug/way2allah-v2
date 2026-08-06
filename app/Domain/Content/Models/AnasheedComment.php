<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user comment on an anasheed item (`nuke_anasheed_comments` —
 * 00-database-schema.md, Wave 3) — same shape and same `view`-column
 * moderation gate as `Comment` (khotab), see that model's docblock for
 * the business-rule reasoning (never set to `1` by the one confirmed
 * write path, `add_anasheed_comment()`, matching `add_khotab_comment()`'s
 * identical omission).
 *
 * Column list confirmed (Fact): id, khid, uid, uname, mytime, comment,
 * ip, view, code.
 *
 * @property int $id
 * @property int $khid
 * @property int $uid
 * @property string|null $uname
 * @property int $mytime
 * @property string|null $comment
 * @property string $ip
 * @property int $view
 * @property string|null $code
 */
class AnasheedComment extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_anasheed_comments';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = ['id'];

    public function anasheedItem(): BelongsTo
    {
        return $this->belongsTo(AnasheedItem::class, 'khid');
    }
}
