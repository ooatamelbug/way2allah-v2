<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Technical/encoding metadata for a khotab item — a 1:1 extension of
 * `KhotabItem`, not a separate business entity (00-database-schema.md's
 * `nuke_islamic_advanced` entry). `id` shares the same value space as
 * `nuke_islamic_khotab.id` (Inferred from the `ON kh.id=ad.id` join
 * pattern used throughout `khotab/functions.php`), not an independent
 * auto-increment sequence.
 *
 * Column list (Fact): id, perf, cright, frate, srate, vres, ares, vstr,
 * astr, vbit, abit, adur, width, height, vlist, alist. Only `adur`
 * (duration, formatted via legacy's `Duration()`) is confirmed actually
 * displayed anywhere in the audited khotab pages — the rest are exposed
 * for completeness but not yet used by any Wave 4 view.
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
class KhotabAdvanced extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_islamic_advanced';

    protected $primaryKey = 'id';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = ['id'];
}
