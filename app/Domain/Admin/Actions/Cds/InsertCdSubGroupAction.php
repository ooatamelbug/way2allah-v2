<?php

namespace App\Domain\Admin\Actions\Cds;

use App\Domain\Admin\Actions\Khotab\AuditText;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\W2acdGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * G4 «إضافة قسم فرعي» — OLD `W2ACDAddGroup` → `ConfirmAddGroup`
 * (`modules/w2acd/admin/index.php`), from a real group.
 *
 * OLD refused a title already used by a group with the same parent
 * («عفواً .... لن تتمكن من إضافة قسم …»). Otherwise it inserted parent_id,
 * title, des and `module_type = 'cd'`, and added 1 to the parent's
 * maintained `child` counter.
 *
 * Corrections: authorized (`w2acd.managecds`; OLD's GreenZone was inert);
 * the parent is the bound group, never a posted id; `size` and `dur` (NOT
 * NULL, no default — OLD's INSERT relied on non-strict MySQL) are written as
 * 0; the parent row locked, the duplicate check, the insert, the counter and
 * the audit row in one transaction.
 *
 * M4 (Phase 3) — the same OLD control on `cds-main` (`Group(0)` renders
 * `group_cp_for_cd.htm` with id 0): `executeRoot()` creates a top-level group
 * (`parent_id = 0`) with the same duplicate check among the top-level groups.
 * OLD's `child + 1 WHERE id = 0` matched no row, so no counter is touched.
 */
final class InsertCdSubGroupAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /**
     * @param  array{Title: string, Des?: string|null}  $data  the validated form, OLD field names
     *
     * @throws ValidationException OLD's duplicate-title refusal
     */
    public function execute(AdminUser $actor, W2acdGroup $parent, array $data, ?string $ip = null): int
    {
        $this->authorizer->authorize($actor, 'w2acd.managecds', $parent);

        return DB::connection('main')->transaction(function () use ($actor, $parent, $data, $ip) {
            $parentId = (int) DB::connection('main')->table('nuke_w2acd_groups')->where('id', $parent->id)->lockForUpdate()->value('id');
            abort_if($parentId < 1, 404);

            $id = $this->insert($parentId, $data);
            DB::connection('main')->table('nuke_w2acd_groups')->where('id', $parentId)->increment('child');
            $this->recordAudit($actor, $id, $parentId, $data, $ip);

            return $id;
        });
    }

    /**
     * M4 — a top-level group from `cds-main` (OLD `ConfirmAddGroup` with id 0).
     *
     * @param  array{Title: string, Des?: string|null}  $data  the validated form, OLD field names
     *
     * @throws ValidationException OLD's duplicate-title refusal
     */
    public function executeRoot(AdminUser $actor, array $data, ?string $ip = null): int
    {
        $this->authorizer->authorize($actor, 'w2acd.managecds');

        return DB::connection('main')->transaction(function () use ($actor, $data, $ip) {
            $id = $this->insert(0, $data);
            $this->recordAudit($actor, $id, 0, $data, $ip);

            return $id;
        });
    }

    /** @param  array{Title: string, Des?: string|null}  $data */
    private function insert(int $parentId, array $data): int
    {
        $main = DB::connection('main');
        $title = (string) $data['Title'];
        if ($main->table('nuke_w2acd_groups')->where('title', $title)->where('parent_id', $parentId)->exists()) {
            throw ValidationException::withMessages(['Title' => "عفواً .... لن تتمكن من إضافة قسم '' {$title} '' إلى قاعدة بيانات الموقع - يوجد بالموقع نفس البيانات الجديدة التى حاولت إدخالها"]);
        }

        return (int) $main->table('nuke_w2acd_groups')->insertGetId([
            'parent_id' => $parentId,
            'title' => $title,
            'des' => (string) ($data['Des'] ?? ''),
            'module_type' => 'cd',
            'size' => 0,
            'dur' => 0,
        ]);
    }

    /** @param  array{Title: string, Des?: string|null}  $data */
    private function recordAudit(AdminUser $actor, int $id, int $parentId, array $data, ?string $ip): void
    {
        $this->audit->record($actor, 'add_group', 'w2acd', 'cd_group', $id, [
            'parent_id' => $parentId,
            'title' => AuditText::digest((string) $data['Title']),
        ], $ip);
    }
}
