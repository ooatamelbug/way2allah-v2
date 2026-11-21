<?php

namespace App\Domain\Admin\Support\Constraints;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminRecordConstraint;
use App\Domain\Content\Models\KhotabGroup;
use App\Domain\Content\Models\KhotabItem;
use App\Domain\Content\Models\Mirror;
use App\Domain\Content\Models\Series;
use Illuminate\Support\Facades\DB;

/**
 * OLD Khotab audio / video moderator scope (`AvAdmin()`, `modules/Khotab/admin/index.php`), as a Laravel permission.
 *
 * OLD restricted these operations to the section(s) a moderator was assigned (`nuke_islamic_mod.audio` / `vedio`):
 * item insert / edit / move / clone / delete, series insert / edit / delete, group add / edit / delete, mirror edit /
 * delete. The section is the record's `vedio` (1 = video, else audio); for a mirror, its item's `vedio` (OLD read
 * `$Khotab['vedio']`). Here the scope is two Spatie permissions — `khotab.media_audio`, `khotab.media_video` — and each
 * scoped key needs the one matching the record. Super-admins never reach this (AdminRecordAuthorizer). The legacy
 * `nuke_*_mod` tables are not read at runtime: the one-time migration copies the flags into these two permissions.
 *
 * Owner instruction 2026-11-17 ("strict functional parity with OLD") supersedes decision D1b' (2026-10-12), which had
 * left this division unreproduced.
 */
final class KhotabMediaScopeConstraint implements AdminRecordConstraint
{
    public const AUDIO = 'khotab.media_audio';

    public const VIDEO = 'khotab.media_video';

    /** The Khotab keys OLD's `AvAdmin()` scoped. */
    public const SCOPED = [
        'khotab.additem', 'khotab.edititem', 'khotab.moveitem', 'khotab.delitem',
        'khotab.addseries', 'khotab.editseries', 'khotab.delseries',
        'khotab.addgroup', 'khotab.editgroup', 'khotab.delgroup',
        'khotab.editmirror', 'khotab.delmirror',
    ];

    public function appliesTo(string $permission, object $record): bool
    {
        return in_array($permission, self::SCOPED, true) && $this->vedio($record) !== null;
    }

    public function permits(AdminUser $admin, string $permission, object $record): bool
    {
        return $admin->hasPermissionTo($this->vedio($record) === 1 ? self::VIDEO : self::AUDIO, 'admin');
    }

    private function vedio(object $record): ?int
    {
        return match (true) {
            $record instanceof KhotabMediaTarget => $record->vedio === 1 ? 1 : 0,
            $record instanceof KhotabItem, $record instanceof KhotabGroup, $record instanceof Series => (int) $record->getAttribute('vedio') === 1 ? 1 : 0,
            $record instanceof Mirror => (int) DB::connection('main')->table('nuke_islamic_khotab')->where('id', (int) $record->getAttribute('khid'))->value('vedio') === 1 ? 1 : 0,
            default => null,
        };
    }
}
