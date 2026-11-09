<?php

namespace App\Domain\Admin\Actions\Telawah;

use App\Domain\Admin\Actions\Khotab\AuditText;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\TelawahGroup;
use Illuminate\Support\Facades\DB;

/**
 * «إضافة تلاوة» on a group page — OLD `TelawahInsertTelawah` →
 * `ConfirmInsertTelawah` (`modules/Telawah/admin/index.php`:143-255,
 * production-hash-verified working copy).
 *
 * OLD's insert: title, link, linksize (the MB field × 1024 × 1024), mytime
 * (now), vedio, sorah («لا يوجد» = 0), group_id, parent_id (the group's ROOT,
 * TelawahItemGroups::rootOf) and notes — every other column keeps its
 * default — then `telawah + 1` on the group.
 *
 * Not reproduced: OLD's `count = count + 1` on the group and its
 * `weight = id` on the new row — neither column exists in production, so
 * both statements failed (swallowed by OLD's DB layer) and changed nothing.
 *
 * Corrections: authorized (`telawah.addtelawah`; GreenZone was inert); the
 * group row locked, the insert, the counter and the audit row in one
 * transaction.
 */
final class InsertTelawahItemAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /** @param array<string, mixed> $data the validated form, OLD field names */
    public function execute(AdminUser $actor, TelawahGroup $group, array $data, ?string $ip = null): int
    {
        $this->authorizer->authorize($actor, 'telawah.addtelawah', $group);

        return DB::connection('main')->transaction(function () use ($actor, $group, $data, $ip) {
            $main = DB::connection('main');
            $groupId = (int) $main->table('nuke_telawah_groups')->where('id', $group->id)->lockForUpdate()->value('id');
            abort_if($groupId < 1, 404);

            $root = TelawahItemGroups::rootOf($groupId);
            $title = (string) ($data['title'] ?? '');

            $id = (int) $main->table('nuke_telawah_telawah')->insertGetId([
                'title' => $title,
                'link' => (string) ($data['Telawahfile'] ?? ''),
                'linksize' => self::bytes($data['Telawahsize'] ?? null),
                'mytime' => time(),
                'vedio' => (int) ($data['vedio'] ?? 0) === 1 ? 1 : 0,
                'sorah' => (int) ($data['sorah'] ?? 0),
                'group_id' => $groupId,
                'parent_id' => $root,
                'notes' => (string) ($data['MoreInfo'] ?? ''),
            ]);

            $main->table('nuke_telawah_groups')->where('id', $groupId)->increment('telawah');

            $this->audit->record($actor, 'add_item', 'telawah', 'item', $id, [
                'group_id' => $groupId,
                'parent_id' => $root,
                'title' => AuditText::digest($title),
            ], $ip);

            return $id;
        });
    }

    /** OLD's `(int) (1024 * 1024 * (float) $Telawahsize)`. */
    public static function bytes(mixed $megabytes): int
    {
        $value = trim((string) ($megabytes ?? ''));

        return (int) (1024 * 1024 * (float) ($value === '' ? 0 : $value));
    }
}
