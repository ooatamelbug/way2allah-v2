<?php

namespace App\Domain\Admin\Actions\Anasheed;

use App\Domain\Admin\Actions\Khotab\AuditText;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\AnasheedGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «إضافة قسم فرعي» — OLD `AnasheedAddGroup` → `ConfirmAddGroup`
 * (`modules/Anasheed/admin/index.php`, HEAD:690-753), from a real group.
 *
 * OLD refused a title already used by a group with the same parent
 * («عفواً .... لن تتمكن من إضافة قسم …»). Otherwise it inserted parent_id,
 * title, description, meta_keywords, meta_description, meta_index,
 * meta_follow, channel_id and des — every other column keeps its default —
 * and added 1 to the parent's maintained `child` counter.
 *
 * Corrections: authorized (`anasheed.addgroup`; OLD's GreenZone was inert);
 * the parent row locked, the duplicate check, the insert, the counter and the
 * audit row in one transaction (OLD: separate statements, then a log write
 * that fails on PHP 8). OLD Main's empty-id variant (a root group) is not
 * reproduced — the route only binds an existing group.
 */
final class InsertAnasheedSubGroupAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data  the validated form, OLD field names
     *
     * @throws ValidationException OLD's duplicate-title refusal
     */
    public function execute(AdminUser $actor, AnasheedGroup $parent, array $data, ?string $ip = null): int
    {
        $this->authorizer->authorize($actor, 'anasheed.addgroup', $parent);

        return DB::connection('main')->transaction(function () use ($actor, $parent, $data, $ip) {
            $main = DB::connection('main');
            $parentId = (int) $main->table('nuke_anasheed_groups')->where('id', $parent->id)->lockForUpdate()->value('id');
            abort_if($parentId < 1, 404);

            $title = (string) ($data['Title'] ?? '');
            if ($main->table('nuke_anasheed_groups')->where('title', $title)->where('parent_id', $parentId)->exists()) {
                throw ValidationException::withMessages(['Title' => "عفواً .... لن تتمكن من إضافة قسم '' {$title} '' إلى قاعدة بيانات الموقع - يوجد بالموقع نفس البيانات الجديدة التى حاولت إدخالها"]);
            }

            $channel = (int) ($data['Channel'] ?? 0);
            $id = (int) $main->table('nuke_anasheed_groups')->insertGetId([
                'parent_id' => $parentId,
                'title' => $title,
                'description' => (string) ($data['description'] ?? ''),
                'meta_keywords' => (string) ($data['meta_keywords'] ?? ''),
                'meta_description' => (string) ($data['meta_description'] ?? ''),
                'meta_index' => empty($data['meta_index']) ? 0 : 1,
                'meta_follow' => empty($data['meta_follow']) ? 0 : 1,
                'channel_id' => $channel,
                'des' => (string) ($data['Des'] ?? ''),
            ]);

            $main->table('nuke_anasheed_groups')->where('id', $parentId)->increment('child');

            $this->audit->record($actor, 'add_group', 'anasheed', 'group', $id, [
                'parent_id' => $parentId,
                'channel_id' => $channel,
                'title' => AuditText::digest($title),
            ], $ip);

            return $id;
        });
    }
}
