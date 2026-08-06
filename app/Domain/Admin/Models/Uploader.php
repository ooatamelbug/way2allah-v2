<?php

namespace App\Domain\Admin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * `nuke_uploaders` (khotab upload-team roster) — Roadmap task 5.9, Upload-
 * Team Tracking. `counter`/`last_upload` are recomputed on demand
 * (`?op=update`) via `RecomputeUploaderStatsAction`, not kept live —
 * matches the legacy button-triggered recompute exactly, not a new
 * always-fresh design.
 *
 * @property int $id
 * @property int $uid
 * @property string|null $username
 * @property string|null $email
 * @property int $counter
 * @property int|null $last_upload
 */
class Uploader extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_uploaders';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = ['id'];
}
