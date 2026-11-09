<?php

namespace App\Domain\Admin\Actions\Telawah;

use App\Domain\Content\Models\TelawahItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The Telawah group hierarchy as OLD's item forms used it
 * (`modules/Telawah/admin/index.php`, production-hash-verified):
 *
 * - `rootOf()` — the item's stored `parent_id`: OLD ConfirmInsertTelawah's
 *   `while ($Group['parent_id'] > 0)` walk up to the reciter (bounded
 *   against a cycle);
 * - `editOptions()` — EditTelawah()'s «القسم» select, in OLD's order: the
 *   stored root when it differs from the group, the current group (selected,
 *   marked «*»), then the current group's direct sub-groups by id ASC;
 * - `reciters()` / `subGroups()` — TelawahmoveTelawah()'s two selects
 *   (`List_telawat_groups`): every root group («إختر الشيخ»), then the
 *   chosen reciter's direct sub-groups (OLD `load_sub_categories`).
 *
 * OLD accepted any posted id; Laravel accepts only what these offered, and
 * only groups that exist.
 */
final class TelawahItemGroups
{
    public static function rootOf(int $groupId): int
    {
        $main = DB::connection('main');
        $id = $groupId;
        $parentId = (int) $main->table('nuke_telawah_groups')->where('id', $groupId)->value('parent_id');

        for ($steps = 0; $parentId > 0 && $steps < 50; $steps++) {
            $row = $main->table('nuke_telawah_groups')->where('id', $parentId)->first(['id', 'parent_id']);
            if ($row === null) {
                break;
            }
            $id = (int) $row->id;
            $parentId = (int) $row->parent_id;
        }

        return $id;
    }

    /** @return list<array{id: int, title: string, selected: bool}> */
    public static function editOptions(TelawahItem $item): array
    {
        $main = DB::connection('main');
        $groupId = (int) $item->group_id;
        $rootId = (int) $item->parent_id;
        $options = [];

        if ($groupId !== $rootId && $rootId > 0) {
            $root = $main->table('nuke_telawah_groups')->where('id', $rootId)->first(['id', 'title']);
            if ($root !== null) {
                $options[] = ['id' => (int) $root->id, 'title' => '-->'.$root->title, 'selected' => false];
            }
        }

        $current = $main->table('nuke_telawah_groups')->where('id', $groupId)->first(['id', 'title']);
        if ($current !== null) {
            $options[] = ['id' => (int) $current->id, 'title' => '   -->'.$current->title.'*', 'selected' => true];
        }

        foreach ($main->table('nuke_telawah_groups')->where('parent_id', $groupId)->orderBy('id')->get(['id', 'title']) as $child) {
            $options[] = ['id' => (int) $child->id, 'title' => '      -->'.$child->title, 'selected' => false];
        }

        return $options;
    }

    /** @return list<int> */
    public static function editAllowedIds(TelawahItem $item): array
    {
        return array_values(array_unique(array_map(fn (array $option) => $option['id'], self::editOptions($item))));
    }

    /** @return Collection<int, \stdClass> */
    public static function reciters(): Collection
    {
        return DB::connection('main')->table('nuke_telawah_groups')->where('parent_id', 0)->orderBy('id')->get(['id', 'title']);
    }

    /** @return Collection<int, \stdClass> */
    public static function subGroups(int $reciterId): Collection
    {
        if ($reciterId < 1) {
            return collect();
        }

        return DB::connection('main')->table('nuke_telawah_groups')->where('parent_id', $reciterId)->orderBy('id')->get(['id', 'title']);
    }
}
