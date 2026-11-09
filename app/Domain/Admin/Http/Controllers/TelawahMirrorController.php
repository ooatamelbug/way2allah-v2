<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Actions\Mirrors\DeleteTelawahMirrorAction;
use App\Domain\Admin\Actions\Mirrors\UpdateTelawahMirrorAction;
use App\Domain\Admin\Http\Requests\Mirrors\UpdateTelawahMirrorRequest;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\TelawahItem;
use App\Domain\Content\Models\TelawahMirror;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Telawah mirror administration (OLD Admin Migration, closure correction,
 * 2026-10-15) — the historically working half of OLD's Telawah mirror CRUD:
 * edit (`TelawahEditMirror` / `TelawahConfirmEditMirror`) and delete
 * (`TelawahDeleteMirror` / `TelawahConfirmDeleteMirror`).
 *
 * **No insert.** OLD switched it off itself: `AddMirror()` prints «هذه
 * الخاصية غير متاحة في الوقت الحالي» instead of its form (the form include
 * is commented out "as it is not completed yet"), and
 * `ConfirmInsertMirror()` `die()`s with the same message before any write
 * (`modules/Telawah/admin/index.php:1433-1460`). A disabled feature is not
 * recreated. No re-parent either — Telawah never had one.
 *
 * Same shape as AnasheedMirrorController; the actions own every mutation.
 */
class TelawahMirrorController
{
    public function __construct(private readonly AdminRecordAuthorizer $authorizer) {}

    /** Lookup by item id or mirror id. */
    public function index(Request $request): View|RedirectResponse
    {
        if ($request->filled('item')) {
            $item = TelawahItem::query()->find((int) $request->input('item'));

            return $item
                ? redirect()->route('admin.mirrors.telawah.show', $item)
                : back()->with('error', 'لم يتم العثور على التلاوة #'.(int) $request->input('item'));
        }

        if ($request->filled('mirror')) {
            $mirror = TelawahMirror::query()->find((int) $request->input('mirror'));

            return $mirror
                ? redirect()->route('admin.mirrors.telawah.edit', $mirror)
                : back()->with('error', 'لم يتم العثور على الوصلة #'.(int) $request->input('mirror'));
        }

        return view('admin.mirrors.telawah.index');
    }

    public function show(TelawahItem $telawahItem): View
    {
        $admin = $this->actor();

        return view('admin.mirrors.telawah.show', [
            'item' => $telawahItem,
            'mirrors' => $telawahItem->mirrors()->get(),
            'canEdit' => $this->authorizer->allows($admin, 'telawah.editmirror'),
            'canDelete' => $this->authorizer->allows($admin, 'telawah.delmirror'),
        ]);
    }

    public function edit(TelawahMirror $telawahMirror): View
    {
        return view('admin.mirrors.telawah.edit', [
            'mirror' => $telawahMirror,
            'item' => TelawahItem::query()->find((int) $telawahMirror->telawa_id),
            'canDelete' => $this->authorizer->allows($this->actor(), 'telawah.delmirror'),
        ]);
    }

    public function update(UpdateTelawahMirrorRequest $request, TelawahMirror $telawahMirror, UpdateTelawahMirrorAction $action): RedirectResponse
    {
        $action->execute($this->actor(), $telawahMirror, $request->validated(), $request->ip());

        $parent = TelawahItem::query()->find((int) $telawahMirror->telawa_id);

        $redirect = $parent
            ? redirect()->route('admin.mirrors.telawah.show', $parent)
            : redirect()->route('admin.mirrors.telawah.edit', $telawahMirror);

        return $redirect->with('success', "تم تعديل الوصلة #{$telawahMirror->id}");
    }

    public function destroy(Request $request, TelawahMirror $telawahMirror, DeleteTelawahMirrorAction $action): RedirectResponse
    {
        $id = (int) $telawahMirror->id;
        $parent = TelawahItem::query()->find((int) $telawahMirror->telawa_id);

        $action->execute($this->actor(), $telawahMirror, $request->ip());

        $redirect = $parent
            ? redirect()->route('admin.mirrors.telawah.show', $parent)
            : redirect()->route('admin.mirrors.telawah.index');

        return $redirect->with('success', "تم حذف الوصلة #{$id}");
    }

    private function actor(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }
}
