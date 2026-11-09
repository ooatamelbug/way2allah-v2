<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Actions\Telawah\DeleteTelawahGroupAction;
use App\Domain\Admin\Actions\Telawah\InsertTelawahGroupAction;
use App\Domain\Admin\Actions\Telawah\InsertTelawahItemAction;
use App\Domain\Admin\Actions\Telawah\UpdateTelawahGroupAction;
use App\Domain\Admin\Contextual\ContextualReturn;
use App\Domain\Admin\Http\Requests\Telawah\StoreTelawahGroupRequest;
use App\Domain\Admin\Http\Requests\Telawah\StoreTelawahItemRequest;
use App\Domain\Admin\Http\Requests\Telawah\UpdateTelawahGroupRequest;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Content\Models\TelawahGroup;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * OLD public admin parity — Telawah Main (`recite.htm`, OLD `Group(0)`'s
 * «لوحة التحكم», `group_main_cp.htm`) and the group page
 * (`recite-group-{id}.htm`, OLD `Group(N)`'s «لوحة تحكم الاقسام الفرعية»,
 * `author_cp.htm`), owner-approved 2026-10-29. Main's «إضافة تلاوة»
 * (`group_id` = 0, «القسم غير موجود») and the group page's «نقل القسم» (no
 * handler) are dead in production and not offered.
 *
 * Every page here is the Laravel counterpart of an OLD `admin.php?op=…`
 * page; every mutation is an action class (authorized, transactional,
 * audited). OLD's 3-second meta-refresh pages are replaced by a direct
 * redirect to the same destination, with the message in the admin-only
 * public notice (owner decision, 2026-10-29).
 */
class TelawahGroupAdminController
{
    /** «إضافة قارىء» — OLD AddGroup(0): «اضافة قارىء بقسم التلاوات». */
    public function createReciter(): View
    {
        return view('admin.telawah.groups.create', ['parent' => null]);
    }

    /** OLD ConfirmAddGroup() refreshed to `Group&id=0` — Main. */
    public function storeReciter(StoreTelawahGroupRequest $request, InsertTelawahGroupAction $action): RedirectResponse
    {
        $action->execute($this->actor(), null, $request->validated(), $request->ip());

        return redirect()->to('/recite.htm')->with(ContextualReturn::FLASH, "تمت إضافة القارىء '' {$request->validated('Title')} ''");
    }

    /** «تعديل القسم» — OLD EditGroup(). */
    public function edit(TelawahGroup $telawahGroup): View
    {
        return view('admin.telawah.groups.edit', ['group' => $telawahGroup]);
    }

    public function update(UpdateTelawahGroupRequest $request, TelawahGroup $telawahGroup, UpdateTelawahGroupAction $action): RedirectResponse
    {
        $action->execute($this->actor(), $telawahGroup, $request->validated(), $request->ip());

        return $this->toGroup((int) $telawahGroup->id, 'تم تعديل القسم');
    }

    /** «إضافة تلاوة» — OLD InsertTelawah(group_id). */
    public function createItem(TelawahGroup $telawahGroup): View
    {
        return view('admin.telawah.groups.item-create', [
            'group' => $telawahGroup,
            'suras' => $this->suras(),
        ]);
    }

    /** OLD ConfirmInsertTelawah() refreshed to the group. */
    public function storeItem(StoreTelawahItemRequest $request, TelawahGroup $telawahGroup, InsertTelawahItemAction $action): RedirectResponse
    {
        $action->execute($this->actor(), $telawahGroup, $request->validated(), $request->ip());

        return $this->toGroup((int) $telawahGroup->id, 'تم اضافة التلاوة بنجاح');
    }

    /** «إضافة قسم فرعى» — OLD AddGroup(id). */
    public function createSubGroup(TelawahGroup $telawahGroup): View
    {
        return view('admin.telawah.groups.create', ['parent' => $telawahGroup]);
    }

    /** OLD ConfirmAddGroup() refreshed to the parent (this) group. */
    public function storeSubGroup(StoreTelawahGroupRequest $request, TelawahGroup $telawahGroup, InsertTelawahGroupAction $action): RedirectResponse
    {
        $action->execute($this->actor(), $telawahGroup, $request->validated(), $request->ip());

        return $this->toGroup((int) $telawahGroup->id, "تمت إضافة القسم الفرعي '' {$request->validated('Title')} ''");
    }

    /** «حذف القسم» — OLD DeleteGroup()'s confirmation page. */
    public function confirmDestroy(TelawahGroup $telawahGroup): View
    {
        return view('admin.telawah.groups.delete', ['group' => $telawahGroup]);
    }

    /** OLD ConfirmDeleteGroup() refreshed to Telawah Main. */
    public function destroy(Request $request, TelawahGroup $telawahGroup, DeleteTelawahGroupAction $action): RedirectResponse
    {
        $result = $action->execute($this->actor(), $telawahGroup, $request->ip());

        return redirect()->to('/recite.htm')->with(ContextualReturn::FLASH, "تم حذف القسم '' {$result['title']} ''");
    }

    private function toGroup(int $groupId, string $message): RedirectResponse
    {
        return redirect()->to('/recite-group-'.$groupId.'.htm')->with(ContextualReturn::FLASH, $message);
    }

    /** @return Collection<int, \stdClass> OLD's «السورة» options: ids 1–114 of `nuke_telawah_sorahlist`. */
    private function suras(): Collection
    {
        return DB::connection('main')->table('nuke_telawah_sorahlist')->whereBetween('id', [1, 114])->orderBy('id')->get(['id', 'name']);
    }

    private function actor(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }
}
