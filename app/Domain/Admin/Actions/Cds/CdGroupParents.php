<?php

namespace App\Domain\Admin\Actions\Cds;

use App\Domain\Content\Models\W2acdGroup;
use Illuminate\Support\Facades\DB;

/**
 * The parents «تعديل القسم» may move a CD group to — OLD `EditGroup()`'s
 * «القسم الرئيسي» select (`modules/w2acd/admin/index.php`), in OLD's order:
 *
 * - «بدون مجموعة» (0) when the group, or its parent, is a root;
 * - otherwise the grandparent (one level up);
 * - the current parent (selected), shown as «--title»;
 * - every sibling (`parent_id` = the group's own parent, itself excluded),
 *   by id ASC, shown as «-----title».
 *
 * So OLD only ever offered "stay", "up one level" or "into a sibling" — never
 * a descendant, so a move cannot create a cycle. ConfirmEditGroup accepted
 * any posted id; Laravel accepts only these, plus the current parent even
 * when its row is missing, so saving the form never silently moves a group.
 * Same rule as `AnasheedGroupParents`.
 */
final class CdGroupParents
{
    /** @return list<array{id: int, title: string, selected: bool}> */
    public static function options(W2acdGroup $group): array
    {
        $groups = DB::connection('main')->table('nuke_w2acd_groups');
        $parentId = (int) $group->parent_id;
        $options = [];

        if ($parentId === 0) {
            $options[] = ['id' => 0, 'title' => 'بدون مجموعة', 'selected' => true];
        } else {
            $parent = (clone $groups)->where('id', $parentId)->first(['id', 'title', 'parent_id']);
            if ($parent !== null) {
                $grandparentId = (int) $parent->parent_id;
                if ($grandparentId === 0) {
                    $options[] = ['id' => 0, 'title' => 'بدون مجموعة', 'selected' => false];
                } else {
                    $grandparent = (clone $groups)->where('id', $grandparentId)->first(['id', 'title']);
                    if ($grandparent !== null) {
                        $options[] = ['id' => (int) $grandparent->id, 'title' => (string) $grandparent->title, 'selected' => false];
                    }
                }
                $options[] = ['id' => (int) $parent->id, 'title' => '--'.$parent->title, 'selected' => true];
            }
        }

        $siblings = (clone $groups)->where('parent_id', $parentId)->where('id', '<>', $group->id)->orderBy('id')->get(['id', 'title']);
        foreach ($siblings as $sibling) {
            $options[] = ['id' => (int) $sibling->id, 'title' => '-----'.$sibling->title, 'selected' => false];
        }

        return $options;
    }

    /** @return list<int> */
    public static function allowedIds(W2acdGroup $group): array
    {
        $ids = array_map(fn (array $option) => $option['id'], self::options($group));
        $ids[] = (int) $group->parent_id;

        return array_values(array_unique($ids));
    }
}
