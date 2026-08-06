<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Technical/encoding metadata for an `AnasheedMirror` — one-to-one with
 * `nuke_anasheed_mirror` (`nuke_anasheed_advanced` — 00-database-schema.md,
 * Wave 3), NOT with `AnasheedItem` directly — a real, confirmed
 * difference from khotab, which has both an item-scoped (`KhotabAdvanced`)
 * AND a mirror-scoped (`MirrorAdvanced`) table. `anasheed/functions.php:
 * 683-685`'s query only ever joins this table to `nuke_anasheed_mirror.id`,
 * never to `nuke_anasheed_anasheed.id` — confirmed by direct reading, not
 * assumed from naming symmetry with khotab.
 *
 * Identical column set to `KhotabAdvanced`/`MirrorAdvanced` (Fact): id,
 * perf, cright, frate, srate, vres, ares, vstr, astr, vbit, abit, adur,
 * width, height, vlist, alist.
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
class AnasheedAdvanced extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_anasheed_advanced';

    protected $primaryKey = 'id';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = ['id'];
}
