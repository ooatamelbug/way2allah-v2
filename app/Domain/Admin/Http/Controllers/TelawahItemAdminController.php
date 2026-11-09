<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Actions\Telawah\DeleteTelawahItemAction;
use App\Domain\Admin\Actions\Telawah\MoveTelawahItemAction;
use App\Domain\Admin\Actions\Telawah\TelawahItemGroups;
use App\Domain\Admin\Actions\Telawah\UpdateTelawahItemAction;
use App\Domain\Admin\Contextual\ContextualReturn;
use App\Domain\Admin\Http\Requests\Telawah\UpdateTelawahItemRequest;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Content\Models\TelawahGroup;
use App\Domain\Content\Models\TelawahItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * OLD public admin parity — the Telawah item page's «لوحة تحكم المشرفين»
 * (OLD `AdvancedStats()`, `modules/Telawah/index.php`:560-627), owner-approved
 * 2026-10-29: «تعديل», «مسح» and «نقل إلى قسم شيخ أخر». Its «اضافة وصلة
 * بديلة» is disabled by OLD itself and «اضافة الى قائمة الشير» is
 * owner-retired; the status block stays the existing link-status page.
 *
 * Every mutation is an action class (authorized, transactional, audited),
 * followed by a direct redirect to OLD's destination with the message in the
 * admin-only public notice.
 */
class TelawahItemAdminController
{
    /** «تعديل» — OLD EditTelawah(). */
    public function edit(TelawahItem $telawahItem): View
    {
        return view('admin.telawah.items.edit', [
            'item' => $telawahItem,
            'suras' => DB::connection('main')->table('nuke_telawah_sorahlist')->whereBetween('id', [1, 114])->orderBy('id')->get(['id', 'name']),
            'groups' => TelawahItemGroups::editOptions($telawahItem),
            'sizeField' => UpdateTelawahItemAction::sizeField((int) $telawahItem->linksize),
        ]);
    }

    /** OLD ConfirmEditTelawah() refreshed to the item. */
    public function update(UpdateTelawahItemRequest $request, TelawahItem $telawahItem, UpdateTelawahItemAction $action): RedirectResponse
    {
        $action->execute($this->actor(), $telawahItem, $request->validated(), $request->ip());

        return $this->toItem((int) $telawahItem->id, 'تم تعديل التلاوة بنجاح');
    }

    /** «نقل إلى قسم شيخ أخر» — OLD TelawahmoveTelawah(): the reciter is the item's root, the sub-group its group. */
    public function move(TelawahItem $telawahItem): View
    {
        $groupId = (int) $telawahItem->group_id;
        $reciterId = TelawahItemGroups::rootOf($groupId);

        return view('admin.telawah.items.move', [
            'item' => $telawahItem,
            'reciters' => TelawahItemGroups::reciters(),
            'reciterId' => $reciterId,
            'subGroups' => TelawahItemGroups::subGroups($reciterId),
            'subGroupId' => $groupId !== $reciterId ? $groupId : 0,
        ]);
    }

    /**
     * OLD stayed on its own form with «تم نقل التلاوة إلى القسم الجديد»;
     * Laravel returns to the item page (the page the admin came from), with
     * that message.
     */
    public function saveMove(Request $request, TelawahItem $telawahItem, MoveTelawahItemAction $action): RedirectResponse
    {
        $data = $request->validate([
            'group_id' => ['required', 'integer', 'min:0'],
            'sub_group_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $result = $action->execute($this->actor(), $telawahItem, (int) $data['group_id'], (int) ($data['sub_group_id'] ?? 0), $request->ip());

        return $this->toItem((int) $telawahItem->id, $result['moved'] ? 'تم نقل التلاوة إلى القسم الجديد' : 'التلاوة موجودة بالفعل في هذا القسم');
    }

    /** OLD `load_sub_categories`: the chosen reciter's direct sub-groups, as the second select. */
    public function moveSubGroups(Request $request): View
    {
        return view('admin.telawah.items._move-sub-groups', [
            'subGroups' => TelawahItemGroups::subGroups((int) $request->query('reciter')),
        ]);
    }

    /** «مسح» — OLD DeleteTelawah()'s confirmation page, with what the delete removes. */
    public function confirmDestroy(TelawahItem $telawahItem): View
    {
        $main = DB::connection('main');

        return view('admin.telawah.items.delete', [
            'item' => $telawahItem,
            'mirrors' => $main->table('nuke_telawah_mirror')->where('telawa_id', $telawahItem->id)->count(),
            'comments' => $main->table('nuke_telawah_comments')->where('khid', $telawahItem->id)->count(),
        ]);
    }

    /** OLD ConfirmDeleteTelawah() refreshed to the item's group; without a group, Telawah Main. */
    public function destroy(Request $request, TelawahItem $telawahItem, DeleteTelawahItemAction $action): RedirectResponse
    {
        $result = $action->execute($this->actor(), $telawahItem, $request->ip());

        $groupId = $result['group_id'];
        $target = $groupId > 0 && TelawahGroup::whereKey($groupId)->exists() ? '/recite-group-'.$groupId.'.htm' : '/recite.htm';

        return redirect()->to($target)->with(ContextualReturn::FLASH, "تم حذف التلاوة '' {$result['title']} ''");
    }

    private function toItem(int $itemId, string $message): RedirectResponse
    {
        return redirect()->to('/recite-item-'.$itemId.'.htm')->with(ContextualReturn::FLASH, $message);
    }

    private function actor(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }
}
