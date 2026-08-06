<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user comment on a khotab item (`nuke_islamic_comments`,
 * 00-database-schema.md). Blueprint v1.0 §6 describes `Comment` as
 * referencing its parent content item "by reference, not ownership" —
 * in practice this is realized as a plain `khid` foreign column, NOT a
 * shared polymorphic table: `nuke_islamic_comments` is khotab-specific
 * (confirmed distinct from `nuke_anasheed_comments`, its own separate
 * table per 00-database-schema.md's Wave 3 section). This model wraps only
 * the khotab table; a future content type gets its own Comment-shaped
 * model over its own table when it's actually built, not a shared one
 * invented ahead of that evidence.
 *
 * Column list confirmed (Fact): id, khid, uid, uname, uemail, mytime,
 * comment, view, ip, code. `add_khotab_comment()` (khotab/functions.php:
 * 1094-1144) only ever writes khid/uid/uname/ip/code/mytime/comment —
 * `uemail` and `view` are never set by the one confirmed write path,
 * despite existing as real columns.
 *
 * `view` (Inferred business rule, not stated in code, but strongly implied
 * by two independently-confirmed facts): `view` defaults to `0`
 * (00-database-schema.md) and `add_khotab_comment()` never sets it, yet
 * `khotab/item.php:367,370` only ever SELECTs comments `WHERE view='1'`.
 * A freshly-posted comment is therefore invisible on the item page until
 * something else (presumed: an `admincp/` moderation action, not audited
 * in this Wave) flips it to `1` — a moderation gate, not a bug. The
 * Laravel port preserves this: newly-submitted comments do not appear
 * immediately, matching legacy exactly (which also gives the submitter no
 * on-page indication that moderation is pending).
 *
 * `code` (Fact, `functions.php`'s `listcomments()`) is a 2-letter country
 * code used directly as a flag-image filename: `images/flags/{code}.png`
 * — a display-layer/asset coupling, not validated against a real country
 * list at write time (populated from the `ips` GeoIP lookup table by
 * `add_khotab_comment()`, itself unvalidated user-IP-derived data).
 *
 * @property int $id
 * @property int $khid
 * @property int $uid
 * @property string|null $uname
 * @property string $uemail
 * @property int $mytime
 * @property string|null $comment
 * @property int $view
 * @property string|null $ip
 * @property string|null $code
 */
class Comment extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_islamic_comments';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = ['id'];

    public function khotabItem(): BelongsTo
    {
        return $this->belongsTo(KhotabItem::class, 'khid');
    }
}
