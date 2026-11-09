<?php

namespace App\Domain\Admin\Actions\Telawah;

use App\Domain\Admin\Actions\Khotab\AuditText;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\TelawahGroup;
use Illuminate\Support\Facades\DB;

/**
 * «تعديل القسم» — OLD `TelawahEditGroup` → `ConfirmEditGroup`
 * (`modules/Telawah/admin/index.php`:497-553, production-hash-verified).
 *
 * OLD's form (`group_edit.htm`) edits the title and the comment (`des`).
 *
 * **Owner-approved correction (2026-10-29):** OLD's UPDATE also wrote
 * `parent_id = (int) $telawah_group`, a field the form never renders — so
 * every OLD save silently moved the group to the root. Laravel writes only
 * title and des; the group's `parent_id` is never touched here.
 *
 * OLD's `RepairCount($Author_id)` is not reproduced: the hidden `Author_id`
 * is `$Group[author_id]`, a column `nuke_telawah_groups` does not have, and
 * every statement RepairCount runs targets a column or table production lacks
 * (`nuke_telawah_groups.count`, `nuke_telawah_authors`, `nuke_telawah_series`),
 * so it changed nothing.
 *
 * Other corrections: authorized (`telawah.editgroup`; GreenZone was inert);
 * the row locked, the update and the audit row in one transaction.
 */
final class UpdateTelawahGroupAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /** @param array<string, mixed> $data the validated form, OLD field names */
    public function execute(AdminUser $actor, TelawahGroup $group, array $data, ?string $ip = null): void
    {
        $this->authorizer->authorize($actor, 'telawah.editgroup', $group);

        DB::connection('main')->transaction(function () use ($actor, $group, $data, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_telawah_groups')->where('id', $group->id)->lockForUpdate()->first(['id', 'title', 'des']);
            abort_if($row === null, 404);

            $after = [
                'title' => (string) ($data['title'] ?? ''),
                'des' => (string) ($data['Des'] ?? ''),
            ];
            $before = ['title' => $row->title, 'des' => $row->des];

            $main->table('nuke_telawah_groups')->where('id', $row->id)->update($after);

            $this->audit->record($actor, 'edit_group', 'telawah', 'group', (int) $row->id, [
                'changes' => AuditText::changes($before, $after, ['title', 'des']),
            ], $ip);
        });
    }
}
