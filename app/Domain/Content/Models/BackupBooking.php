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
 * NOT YET WRITTEN OR READ by any implemented operation — `get`/`getdown`/
 * `put` are explicitly excluded from this round (Task 6.8 plan §13,
 * UNRESOLVED production-usage status, decision-log #16). Modeled now,
 * per explicit instruction, so it exists when that scope is eventually
 * authorized — not wired into any controller action yet.
 */
class BackupBooking extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_backup_booking';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = ['id'];
}
