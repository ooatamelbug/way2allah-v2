<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Actions\Anasheed\AnasheedGroupParents;
use App\Domain\Admin\Actions\Anasheed\DeleteAnasheedGroupAction;
use App\Domain\Admin\Actions\Anasheed\InsertAnasheedItemAction;
use App\Domain\Admin\Actions\Anasheed\InsertAnasheedSubGroupAction;
use App\Domain\Admin\Actions\Anasheed\UpdateAnasheedGroupAction;
use App\Domain\Admin\Actions\Anasheed\UploadAnasheedGroupIconAction;
use App\Domain\Admin\Contextual\ContextualReturn;
use App\Domain\Admin\Http\Requests\Anasheed\StoreAnasheedItemRequest;
use App\Domain\Admin\Http\Requests\Anasheed\StoreAnasheedSubGroupRequest;
use App\Domain\Admin\Http\Requests\Anasheed\UpdateAnasheedGroupRequest;
use App\Domain\Admin\Http\Requests\Anasheed\UploadAnasheedGroupIconRequest;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Content\Models\AnasheedGroup;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * OLD public admin parity — the Anasheed group page's «لوحة تحكم قسم
 * المنوعات» (`themes/way2allah/templates/Anasheed/group_cp.htm`, rendered by
 * OLD `modules/Anasheed/index.php` `Group()`), owner-approved 2026-10-23.
 * Five controls; the sixth («ترتيب القسم») is dead in production.
 *
 * Every page here is the Laravel counterpart of an OLD `admin.php?op=…`
 * page; every mutation is an action class (authorized, transactional,
 * audited). After a success the admin lands where OLD sent them, with the
 * message in the admin-only public notice — except that Laravel has no
 * Anasheed Main (OLD `Group(0)`), so where OLD went to Main the admin lands
 * on the nearest real page instead.
 */
class AnasheedGroupAdminController
{
    /** «اضافة صورة للقسم» — OLD GroupIcon(). */
    public function icon(AnasheedGroup $anasheedGroup): View
    {
        $hasIcon = (int) $anasheedGroup->icon === 1 && is_file(UploadAnasheedGroupIconAction::absolutePath((int) $anasheedGroup->id));

        return view('admin.anasheed.groups.icon', [
            'group' => $anasheedGroup,
            'currentIcon' => $hasIcon ? $anasheedGroup->thumbUrl() : null,
            'maxKb' => intdiv((int) config('admin_anasheed.icon_max_bytes'), 1000),
        ]);
    }

    /** OLD UploadIcon() then sent the admin to the PARENT group's page. */
    public function uploadIcon(UploadAnasheedGroupIconRequest $request, AnasheedGroup $anasheedGroup, UploadAnasheedGroupIconAction $action): RedirectResponse
    {
        $file = $request->file('icon');
        abort_unless($file instanceof UploadedFile, 422);
        $action->execute($this->actor(), $anasheedGroup, $file, $request->ip());

        $parentId = (int) $anasheedGroup->parent_id;
        $target = $parentId > 0 && AnasheedGroup::whereKey($parentId)->exists() ? $parentId : (int) $anasheedGroup->id;

        return redirect()->to('/var-group-'.$target.'.htm')
            ->with(ContextualReturn::FLASH, 'جزاك الله خيرا على إضافة الأيقونة إلى الموقع.');
    }

    /** «تعديل القسم» — OLD EditGroup(). */
    public function edit(AnasheedGroup $anasheedGroup): View
    {
        return view('admin.anasheed.groups.edit', [
            'group' => $anasheedGroup,
            'parents' => AnasheedGroupParents::options($anasheedGroup),
            'channels' => $this->channels(),
        ]);
    }

    public function update(UpdateAnasheedGroupRequest $request, AnasheedGroup $anasheedGroup, UpdateAnasheedGroupAction $action): RedirectResponse
    {
        $action->execute($this->actor(), $anasheedGroup, $request->validated(), $request->ip());

        return $this->toGroup((int) $anasheedGroup->id, 'تم تعديل القسم');
    }

    /** «إضافة مادة» — OLD InsertAnasheed(group_id). */
    public function createItem(AnasheedGroup $anasheedGroup): View
    {
        $root = InsertAnasheedItemAction::rootOf($anasheedGroup);

        return view('admin.anasheed.groups.item-create', [
            'group' => $anasheedGroup,
            'channels' => $this->channels(),
            'authors' => in_array($root, InsertAnasheedItemAction::ROOTS_WITHOUT_AUTHOR, true)
                ? null
                : DB::connection('main')->table('nuke_islamic_authors')->orderByRaw($this->binaryOrder('name'))->get(['id', 'name']),
        ]);
    }

    public function storeItem(StoreAnasheedItemRequest $request, AnasheedGroup $anasheedGroup, InsertAnasheedItemAction $action): RedirectResponse
    {
        $action->execute($this->actor(), $anasheedGroup, $request->validated(), $request->ip());

        return $this->toGroup((int) $anasheedGroup->id, 'تمت إضافة المادة');
    }

    /** «إضافة قسم فرعي» — OLD AddGroup(id). */
    public function createSubGroup(AnasheedGroup $anasheedGroup): View
    {
        return view('admin.anasheed.groups.subgroup-create', [
            'group' => $anasheedGroup,
            'channels' => $this->channels(),
        ]);
    }

    /** OLD ConfirmAddGroup() sent the admin to the parent (this) group's page. */
    public function storeSubGroup(StoreAnasheedSubGroupRequest $request, AnasheedGroup $anasheedGroup, InsertAnasheedSubGroupAction $action): RedirectResponse
    {
        $action->execute($this->actor(), $anasheedGroup, $request->validated(), $request->ip());

        return $this->toGroup((int) $anasheedGroup->id, "تمت إضافة القسم الفرعي '' {$request->validated('Title')} ''");
    }

    /** «حذف القسم» — OLD DeleteGroup()'s confirmation page. */
    public function confirmDestroy(AnasheedGroup $anasheedGroup): View
    {
        return view('admin.anasheed.groups.delete', ['group' => $anasheedGroup]);
    }

    /** OLD went to Anasheed Main, which Laravel does not have: the parent group, or the home page. */
    public function destroy(Request $request, AnasheedGroup $anasheedGroup, DeleteAnasheedGroupAction $action): RedirectResponse
    {
        $title = (string) $anasheedGroup->title;
        $result = $action->execute($this->actor(), $anasheedGroup, $request->ip());

        $parentId = $result['parent_id'];
        $target = $parentId > 0 && AnasheedGroup::whereKey($parentId)->exists() ? '/var-group-'.$parentId.'.htm' : '/';

        return redirect()->to($target)->with(ContextualReturn::FLASH, "تم حذف القسم '' {$title} ''");
    }

    private function toGroup(int $groupId, string $message): RedirectResponse
    {
        return redirect()->to('/var-group-'.$groupId.'.htm')->with(ContextualReturn::FLASH, $message);
    }

    /** @return Collection<int, \stdClass> */
    private function channels(): Collection
    {
        return DB::connection('main')->table('nuke_sat_channels')->orderByRaw($this->binaryOrder('title'))->get(['id', 'title']);
    }

    /** OLD's `ORDER BY BINARY {column} ASC` on MySQL; plain on the test suite's SQLite. */
    private function binaryOrder(string $column): string
    {
        return DB::connection('main')->getDriverName() === 'mysql' ? "BINARY {$column} ASC" : "{$column} ASC";
    }

    private function actor(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }
}
