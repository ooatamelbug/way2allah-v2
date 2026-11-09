<?php

namespace App\Domain\Admin\Actions\Anasheed;

use App\Domain\Content\Models\AnasheedItem;
use Illuminate\Support\Facades\DB;

/**
 * The groups «تعديل المادة» may move an item to — OLD `EditAnasheed()`'s
 * «القسم» select (`modules/Anasheed/admin/index.php`, production working
 * copy), in OLD's order:
 *
 * - «***إصدارات الموقع» (group 158), always;
 * - the item's stored root (`parent_id`), when it differs from its group;
 * - the current group (selected, marked «*»);
 * - the current group's direct sub-groups, by id ASC.
 *
 * OLD accepted any posted id; Laravel accepts only these, and only groups
 * that exist.
 */
final class AnasheedItemGroups
{
    /** OLD's hard-coded first option. */
    public const RELEASES_GROUP = 158;

    /** @return list<array{id: int, title: string, selected: bool}> */
    public static function options(AnasheedItem $item): array
    {
        $main = DB::connection('main');
        $groupId = (int) $item->group_id;
        $rootId = (int) $item->parent_id;
        $options = [['id' => self::RELEASES_GROUP, 'title' => '***إصدارات الموقع', 'selected' => false]];

        if ($groupId !== $rootId && $rootId > 0) {
            $root = $main->table('nuke_anasheed_groups')->where('id', $rootId)->first(['id', 'title']);
            if ($root !== null) {
                $options[] = ['id' => (int) $root->id, 'title' => '-->'.$root->title, 'selected' => false];
            }
        }

        $current = $main->table('nuke_anasheed_groups')->where('id', $groupId)->first(['id', 'title']);
        if ($current !== null) {
            $options[] = ['id' => (int) $current->id, 'title' => '   -->'.$current->title.'*', 'selected' => true];
        }

        foreach ($main->table('nuke_anasheed_groups')->where('parent_id', $groupId)->orderBy('id')->get(['id', 'title']) as $child) {
            $options[] = ['id' => (int) $child->id, 'title' => '      -->'.$child->title, 'selected' => false];
        }

        return $options;
    }

    /** @return list<int> the allowed ids that are real groups */
    public static function allowedIds(AnasheedItem $item): array
    {
        $ids = array_values(array_unique(array_map(fn (array $option) => $option['id'], self::options($item))));

        return DB::connection('main')->table('nuke_anasheed_groups')->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
