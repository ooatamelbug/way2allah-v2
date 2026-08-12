<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * `nuke_backup_sessions` (Roadmap task 6.8, `00-database-schema.md`'s
 * dedicated entry) — session-lifecycle tracking for `backup.php`'s
 * external-client authentication flow. One row per `op=CreateSession`
 * call, heartbeated via `op=LiveUpdate`, expired after 10 minutes of
 * inactivity by `RemoveOldSessions()`.
 *
 * Column list (Fact, `backup.php:110-191`): id, uid, createtime,
 * updatetime, downloaded, ip, size, count, speed, itemid, catid, active.
 *
 * `active` is confirmed NEVER set explicitly by `CreateSession()`'s own
 * INSERT (`backup.php:134-148`) — the schema's own DEFAULT must supply a
 * truthy value for a session to ever be found again by `LiveUpdate()`,
 * but that DEFAULT's real value is UNKNOWN (PHP source alone cannot
 * confirm it). This model deliberately never sets `active` on create,
 * matching legacy exactly — the real (shared, side-by-side) database's
 * own column default governs, not a value assumed here.
 *
 * `size`/`downloaded`/`count`/`speed`/`itemid`/`catid` are confirmed
 * written as empty strings by every `LiveUpdate()` call (Task 6.8
 * investigation §7.3) — their own function parameters are never
 * populated from the request anywhere in `backup.php`. Reproduced
 * exactly by `BackupApiController::liveUpdate()`, not fixed here or
 * there.
 */
class BackupSession extends Model
{
    protected $connection = 'main';

    protected $table = 'nuke_backup_sessions';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = ['id'];
}
