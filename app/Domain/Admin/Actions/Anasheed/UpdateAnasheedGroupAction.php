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
 * «تعديل القسم» — OLD `AnasheedEditGroup` → `ConfirmEditGroup`
 * (`modules/Anasheed/admin/index.php`, HEAD:755-889).
 *
 * Writes exactly OLD's columns on `nuke_anasheed_groups` — title,
 * description, meta_keywords, meta_description, meta_index, meta_follow,
 * parent_id, channel_id, des — and, when the parent changed, OLD's
 * maintained counters: `child - 1` on the old parent, `child + 1` on the new
 * one. Like OLD, nothing else moves: the items' stored root (`parent_id`) is
 * not recomputed.
 *
 * Corrections: authorized (`anasheed.editgroup`; OLD's GreenZone was inert);
 * the parent must be one OLD's own select offered (AnasheedGroupParents —
 * OLD accepted any posted id); the update, both counters and the audit row
 * commit in one transaction (OLD: four separate statements, then a log write
 * that fails on PHP 8); a counter is never taken below 0.
 */
final class UpdateAnasheedGroupAction
{
    private const TEXT = ['title', 'description', 'meta_keywords', 'meta_description', 'des'];

    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data  the validated form, OLD field names
     *
     * @throws ValidationException a parent OLD's select did not offer
     */
    public function execute(AdminUser $actor, AnasheedGroup $group, array $data, ?string $ip = null): void
    {
        $this->authorizer->authorize($actor, 'anasheed.editgroup', $group);

        DB::connection('main')->transaction(function () use ($actor, $group, $data, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_anasheed_groups')->where('id', $group->id)->lockForUpdate()->first();
            abort_if($row === null, 404);

            $current = AnasheedGroup::on('main')->findOrFail($group->id);
            $parentId = (int) ($data['parent_id'] ?? 0);
            if (! in_array($parentId, AnasheedGroupParents::allowedIds($current), true)) {
                throw ValidationException::withMessages(['parent_id' => 'القسم الرئيسي المختار غير متاح لهذا القسم']);
            }

            $after = [
                'title' => (string) ($data['title'] ?? ''),
                'description' => (string) ($data['description'] ?? ''),
                'meta_keywords' => (string) ($data['meta_keywords'] ?? ''),
                'meta_description' => (string) ($data['meta_description'] ?? ''),
                'meta_index' => empty($data['meta_index']) ? 0 : 1,
                'meta_follow' => empty($data['meta_follow']) ? 0 : 1,
                'parent_id' => $parentId,
                'channel_id' => (int) ($data['Channel'] ?? 0),
                'des' => (string) ($data['Des'] ?? ''),
            ];

            $before = [];
            foreach (array_keys($after) as $column) {
                $before[$column] = $row->{$column};
            }

            $main->table('nuke_anasheed_groups')->where('id', $group->id)->update($after);

            $oldParentId = (int) $row->parent_id;
            $moved = $parentId !== $oldParentId;
            if ($moved) {
                if ($oldParentId > 0) {
                    $main->table('nuke_anasheed_groups')->where('id', $oldParentId)->where('child', '>', 0)->decrement('child');
                }
                if ($parentId > 0) {
                    $main->table('nuke_anasheed_groups')->where('id', $parentId)->increment('child');
                }
            }

            $this->audit->record($actor, 'edit_group', 'anasheed', 'group', (int) $group->id, [
                'changes' => AuditText::changes($before, $after, self::TEXT),
                'moved' => $moved ? ['from_parent' => $oldParentId, 'to_parent' => $parentId] : null,
            ], $ip);
        });
    }
}
