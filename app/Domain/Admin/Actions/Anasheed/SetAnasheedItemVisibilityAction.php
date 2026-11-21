<?php

namespace App\Domain\Admin\Actions\Anasheed;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\AnasheedItem;
use Illuminate\Support\Facades\DB;

/**
 * F08 / B4 — «إخفاء المادة» / «إظهار المادة» for a Variety (Anasheed) item: writes ONLY the existing legacy
 * `hidden` column (0 = public, 1 = hidden). `is_published` and every other column are untouched, no row is deleted,
 * no media file is touched.
 *
 * OLD and Gen 2 had no such control (the column was set elsewhere and OLD's public module honoured it); this is the
 * plan's F08 feature. Authorized by `anasheed.editanasheed` (the key that already governs «تعديل المادة»), inside
 * one transaction with the row locked, audited as `hide_item` / `unhide_item` (module `anasheed`, entity `item`)
 * with the before and after values. Setting the state an item already has writes nothing and records nothing.
 *
 * Visibility takes effect immediately on every Laravel surface: no Laravel cache holds Variety rows (B4 report §4).
 */
final class SetAnasheedItemVisibilityAction
{
    public const PERMISSION = 'anasheed.editanasheed';

    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /** @return bool whether the stored value changed */
    public function execute(AdminUser $actor, AnasheedItem $item, bool $hidden, ?string $ip = null): bool
    {
        $this->authorizer->authorize($actor, self::PERMISSION, $item);

        return DB::connection('main')->transaction(function () use ($actor, $item, $hidden, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_anasheed_anasheed')->where('id', $item->id)->lockForUpdate()->first(['id', 'hidden']);
            abort_if($row === null, 404);

            $before = (int) $row->hidden;
            $after = $hidden ? 1 : 0;
            if (($before !== 0) === $hidden) {
                return false;
            }

            $main->table('nuke_anasheed_anasheed')->where('id', $row->id)->update(['hidden' => $after]);

            $this->audit->record($actor, $hidden ? 'hide_item' : 'unhide_item', 'anasheed', 'item', (int) $row->id, [
                'hidden' => ['before' => $before, 'after' => $after],
            ], $ip);

            return true;
        });
    }
}
