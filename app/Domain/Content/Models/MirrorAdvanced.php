<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Technical/encoding metadata for a `Mirror` — the same 1:1-extension shape
 * as `KhotabAdvanced`, but for a mirror row instead of the primary khotab
 * item (00-database-schema.md's `nuke_islamic_advanced_m` entry, Wave 2).
 * `id` shares the same value space as `nuke_islamic_mirror.id`
 * (`khotab/item.php:253-255`'s `LEFT JOIN nuke_islamic_advanced_m ON
 * nuke_islamic_mirror.id = nuke_islamic_advanced_m.id`).
 *
 * Identical column set to `KhotabAdvanced` (Fact): id, perf, cright,
 * frate, srate, vres, ares, vstr, astr, vbit, abit, adur, width, height,
 * vlist, alist.
 *
 * @property int $id
 * @property string|null $perf
 * @property string|null $cright
 * @property string|null $frate
 * @property string|null $srate
 * @property string|null $vres
 * @property string|null $ares
 * @property string|null $vstr
 * @property string|null $astr
 * @property string|null $vbit
 * @property string|null $abit
 * @property string|null $adur
 * @property int|null $width
 * @property int|null $height
 * @property string|null $vlist
 * @property string|null $alist
 */
class MirrorAdvanced extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_islamic_advanced_m';

    protected $primaryKey = 'id';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = ['id'];
}
