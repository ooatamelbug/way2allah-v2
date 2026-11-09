<?php

namespace App\Domain\Admin\Actions\Mobile;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Content\Models\Mobile\MobileCategory;
use Illuminate\Support\Facades\DB;

/**
 * OLD Admin Migration Phase 7 — Mobile categories. Replaces OLD
 * `DownloadsAddCat`, `DownloadsAddSubCat`, `DownloadsModCat(S)`,
 * `DownloadsDelCat` and `DownloadsTransfer` (`modules/Mobile/admin/index.php`).
 *
 * - add: a top-level category whose title is unused anywhere (OLD's check);
 *   a subcategory whose title is unused among its siblings. (OLD compared
 *   the new title with the *parent's own* title — `cid` written for
 *   `parentid` — so its intended sibling check never ran.)
 * - edit: title and description only; the hierarchy is not editable.
 * - delete, as OLD: a subcategory takes its materials with it; a top-level
 *   category takes its materials, its subcategories and their materials.
 *   Votes, editorials and requests of deleted materials stay behind, as in
 *   OLD.
 * - transfer: every material of one category moves to another.
 */
final class MobileCategoryAdmin extends MobileAdminAction
{
    public function create(AdminUser $actor, string $title, string $description, int $parentId, ?string $ip = null): int
    {
        $this->authorize($actor);

        return DB::connection('main')->transaction(function () use ($actor, $title, $description, $parentId, $ip) {
            $main = DB::connection('main');
            if ($parentId > 0 && ! $main->table('nuke_downloads_categories')->where('cid', $parentId)->where('parentid', 0)->exists()) {
                throw new MobileRefused('القسم الرئيسي غير موجود');
            }
            $taken = $main->table('nuke_downloads_categories')->where('title', $title)
                ->when($parentId > 0, fn ($q) => $q->where('parentid', $parentId))->lockForUpdate()->exists();
            if ($taken) {
                throw new MobileRefused('يوجد تصنيف بنفس الاسم');
            }

            $cid = (int) $main->table('nuke_downloads_categories')->insertGetId(['title' => $title, 'cdescription' => $description, 'parentid' => $parentId], 'cid');
            $this->record($actor, 'add_mobile_cat', 'mobile_category', $cid, self::describe(['title' => $title, 'parentid' => $parentId, 'cdescription' => $description]), $ip);

            return $cid;
        });
    }

    public function update(AdminUser $actor, MobileCategory $category, string $title, string $description, ?string $ip = null): bool
    {
        $this->authorize($actor, $category);

        return DB::connection('main')->transaction(function () use ($actor, $category, $title, $description, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_downloads_categories')->where('cid', $category->getKey())->lockForUpdate()->first();
            abort_if($row === null, 404);

            $before = ['title' => (string) $row->title, 'cdescription' => (string) $row->cdescription];
            $after = ['title' => $title, 'cdescription' => $description];
            $changes = array_diff_assoc($after, $before);
            if ($changes === []) {
                return false;
            }

            $main->table('nuke_downloads_categories')->where('cid', $row->cid)->update($changes);
            $this->record($actor, 'edit_mobile_cat', 'mobile_category', (int) $row->cid, [
                'before' => self::describe(array_intersect_key($before, $changes)),
                'after' => self::describe($changes),
            ], $ip);

            return true;
        });
    }

    public function delete(AdminUser $actor, MobileCategory $category, ?string $ip = null): void
    {
        $this->authorize($actor, $category);

        DB::connection('main')->transaction(function () use ($actor, $category, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_downloads_categories')->where('cid', $category->getKey())->lockForUpdate()->first();
            abort_if($row === null, 404);

            $cids = [(int) $row->cid];
            if ((int) $row->parentid === 0) {
                $cids = array_merge($cids, $main->table('nuke_downloads_categories')->where('parentid', $row->cid)->pluck('cid')->map(fn ($c) => (int) $c)->all());
            }
            $materials = $main->table('nuke_downloads_downloads')->whereIn('cid', $cids)->orderBy('lid')->pluck('lid')->map(fn ($l) => (int) $l)->all();

            $main->table('nuke_downloads_downloads')->whereIn('cid', $cids)->delete();
            $main->table('nuke_downloads_categories')->whereIn('cid', $cids)->delete();

            $this->record($actor, 'del_mobile_cat', 'mobile_category', (int) $row->cid, [
                'title' => self::text($row->title),
                'parentid' => (int) $row->parentid,
                'deleted_categories' => $cids,
                'deleted_materials' => $materials,
            ], $ip);
        });
    }

    public function transfer(AdminUser $actor, MobileCategory $from, MobileCategory $to, ?string $ip = null): int
    {
        $this->authorize($actor);

        return DB::connection('main')->transaction(function () use ($actor, $from, $to, $ip) {
            $main = DB::connection('main');
            $moved = $main->table('nuke_downloads_downloads')->where('cid', $from->getKey())->orderBy('lid')->lockForUpdate()
                ->pluck('lid')->map(fn ($l) => (int) $l)->all();
            if ($moved === [] || (int) $from->getKey() === (int) $to->getKey()) {
                return 0;
            }

            $main->table('nuke_downloads_downloads')->whereIn('lid', $moved)->update(['cid' => (int) $to->getKey()]);
            $this->record($actor, 'transfer_mobile', 'mobile_category', (int) $from->getKey(), [
                'to_cid' => (int) $to->getKey(),
                'materials' => $moved,
            ], $ip);

            return count($moved);
        });
    }
}
