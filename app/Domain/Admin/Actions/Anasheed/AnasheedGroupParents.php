<?php

namespace App\Domain\Admin\Actions\Anasheed;

use App\Domain\Content\Models\AnasheedGroup;
use Illuminate\Support\Facades\DB;

/**
 * The parents «تعديل القسم» may move a group to — OLD `EditGroup()`'s
 * «القسم الرئيسي» select (`modules/Anasheed/admin/index.php`, HEAD:755-801),
 * in OLD's order:
 *
 * - «بدون مجموعة» (0) when the group, or its parent, is a root;
 * - otherwise the grandparent (one level up);
 * - the current parent (selected);
 * - every sibling (`parent_id` = the group's own parent, itself excluded),
 *   by id ASC, shown as «-----title».
 *
 * So OLD only ever offered "stay", "up one level" or "into a sibling" — never
 * a descendant, so a move can never create a cycle. ConfirmEditGroup accepted
 * any posted id; Laravel accepts only these. One addition: the current parent
 * is always accepted even when its row is missing (an orphan), so saving the
 * form never silently moves a group.
 */
final class AnasheedGroupParents
{
    /** @return list<array{id: int, title: string, selected: bool}> */
    public static function options(AnasheedGroup $group): array
    {
        $main = DB::connection('main');
        $parentId = (int) $group->parent_id;
        $options = [];

        if ($parentId === 0) {
            $options[] = ['id' => 0, 'title' => 'بدون مجموعة', 'selected' => true];
        } else {
            $parent = $main->table('nuke_anasheed_groups')->where('id', $parentId)->first(['id', 'title', 'parent_id']);
            if ($parent !== null) {
                $grandparentId = (int) $parent->parent_id;
                if ($grandparentId === 0) {
                    $options[] = ['id' => 0, 'title' => 'بدون مجموعة', 'selected' => false];
                } else {
                    $grandparent = $main->table('nuke_anasheed_groups')->where('id', $grandparentId)->first(['id', 'title']);
                    if ($grandparent !== null) {
                        $options[] = ['id' => (int) $grandparent->id, 'title' => (string) $grandparent->title, 'selected' => false];
                    }
                }
                $options[] = ['id' => (int) $parent->id, 'title' => '--'.$parent->title, 'selected' => true];
            }
        }

        $siblings = $main->table('nuke_anasheed_groups')
            ->where('parent_id', $parentId)->where('id', '<>', $group->id)
            ->orderBy('id')->get(['id', 'title']);
        foreach ($siblings as $sibling) {
            $options[] = ['id' => (int) $sibling->id, 'title' => '-----'.$sibling->title, 'selected' => false];
        }

        return $options;
    }

    /** @return list<int> */
    public static function allowedIds(AnasheedGroup $group): array
    {
        $ids = array_map(fn (array $option) => $option['id'], self::options($group));
        $ids[] = (int) $group->parent_id;

        return array_values(array_unique($ids));
    }
}
