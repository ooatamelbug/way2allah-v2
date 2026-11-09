<?php

namespace App\Domain\Admin\Actions\Telawah;

use App\Domain\Admin\Actions\Khotab\AuditText;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\TelawahGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «إضافة قارىء» (Main, `recite.htm`) and «إضافة قسم فرعى» (a group page) —
 * OLD `TelawahAddGroup` → `ConfirmAddGroup` (`modules/Telawah/admin/index.php`
 * :439-495, production-hash-verified working copy). One OLD handler: `id` = 0
 * adds a reciter (a root group), `id` = N a sub-group of N.
 *
 * OLD refused a title already used by a group with the same parent («عفواً
 * .... لن تتمكن من إضافة قسم …»); otherwise it inserted parent_id, title and
 * des — every other column keeps its default — and ran `child + 1` on the
 * parent (a no-op for 0: no group has id 0).
 *
 * Corrections: authorized (`telawah.addgroup`; OLD's GreenZone was inert);
 * the title and comment are bound, not interpolated unescaped into the SQL as
 * OLD did; the parent row locked, the duplicate check, the insert, the
 * counter and the audit row in one transaction.
 */
final class InsertTelawahGroupAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /**
     * @param  TelawahGroup|null  $parent  null on Main (a new reciter)
     * @param  array<string, mixed>  $data  the validated form, OLD field names
     *
     * @throws ValidationException OLD's duplicate-title refusal
     */
    public function execute(AdminUser $actor, ?TelawahGroup $parent, array $data, ?string $ip = null): int
    {
        $this->authorizer->authorize($actor, 'telawah.addgroup', $parent);

        return DB::connection('main')->transaction(function () use ($actor, $parent, $data, $ip) {
            $main = DB::connection('main');
            $parentId = 0;
            if ($parent !== null) {
                $parentId = (int) $main->table('nuke_telawah_groups')->where('id', $parent->id)->lockForUpdate()->value('id');
                abort_if($parentId < 1, 404);
            }

            $title = (string) ($data['Title'] ?? '');
            if ($main->table('nuke_telawah_groups')->where('title', $title)->where('parent_id', $parentId)->exists()) {
                throw ValidationException::withMessages(['Title' => "عفواً .... لن تتمكن من إضافة قسم '' {$title} '' إلى قاعدة بيانات الموقع - يوجد بالموقع نفس البيانات الجديدة التى حاولت إدخالها"]);
            }

            $id = (int) $main->table('nuke_telawah_groups')->insertGetId([
                'parent_id' => $parentId,
                'title' => $title,
                'des' => (string) ($data['Des'] ?? ''),
            ]);

            if ($parentId > 0) {
                $main->table('nuke_telawah_groups')->where('id', $parentId)->increment('child');
            }

            $this->audit->record($actor, 'add_group', 'telawah', 'group', $id, [
                'parent_id' => $parentId,
                'title' => AuditText::digest($title),
            ], $ip);

            return $id;
        });
    }
}
