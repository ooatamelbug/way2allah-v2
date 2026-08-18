<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * `nuke_backup_booking` (Roadmap task 6.8, `00-database-schema.md`'s
 * dedicated entry) — a content-backup "booking"/coordination queue, one
 * row per item handed out by `UpdateBackup()`'s `get`/`getdown` branch
 * and removed by its `put` branch (`backup.php:309-325,354-362`).
 *
 * Column list (Fact, from the confirmed INSERT statement): id, uid,
 * createtime, catid, itemid, sessionid, ip.
 *
 * **Corrected (G-08-02, Phase 1 audit):** this table IS actively read and
 * written by the implemented `KhotabUpdateBackup` operation, via direct
 * query-builder calls, not through this model class:
 * `BackupApiController::khotabUpdateBackupGet()` inserts one row per item
 * handed out (`get`'s `$mydown='2'` branch only — `getdown` remains
 * deliberately unimplemented, no confirmed real-client evidence of use);
 * `khotabUpdateBackupPut()` deletes the matching booking row when an item
 * completes. The production-usage status of `get`/`getdown`/`put` as a
 * whole remains UNRESOLVED (decision-log #16) — this correction is about
 * what the *code* does, not a claim about real-world call volume.
 */
class BackupBooking extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_backup_booking';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = ['id'];
}
