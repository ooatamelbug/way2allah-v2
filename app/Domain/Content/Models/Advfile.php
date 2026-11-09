<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OLD Admin Migration Phase 5. `nuke_islamic_advfile` — the promotional
 * ("featured / homepage") payload of a Khotab item, 1:1 by shared id: `id` IS
 * the `nuke_islamic_khotab.id` (unique key named `khid`, no FK). The workflow
 * state lives on the item (`KhotabItem::advfile`), not here.
 *
 * `code` is raw HTML authored by admins and rendered by OLD's public advfiles
 * module; Laravel stores it verbatim and never renders it unescaped.
 * `small_thumb` / `large_thumb` are written by no code path anywhere and are
 * carried through untouched.
 *
 * @property int $id
 * @property string|null $code
 * @property int|null $credit
 * @property string|null $audio_url
 * @property int $small_thumb
 * @property int $large_thumb
 */
class Advfile extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_islamic_advfile';

    protected $primaryKey = 'id';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    public function item(): BelongsTo
    {
        return $this->belongsTo(KhotabItem::class, 'id');
    }
}
