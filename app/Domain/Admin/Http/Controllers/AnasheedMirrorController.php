<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Actions\Mirrors\DeleteAnasheedMirrorAction;
use App\Domain\Admin\Actions\Mirrors\InsertAnasheedMirrorAction;
use App\Domain\Admin\Actions\Mirrors\UpdateAnasheedMirrorAction;
use App\Domain\Admin\Http\Requests\Mirrors\StoreAnasheedMirrorRequest;
use App\Domain\Admin\Http\Requests\Mirrors\UpdateAnasheedMirrorRequest;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\AnasheedItem;
use App\Domain\Content\Models\AnasheedMirror;
use App\Domain\Content\Support\VarietyMediaLink;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * OLD Admin Migration Phase 1 — Anasheed mirror administration.
 *
 * Replaces OLD's Anasheed `ConfirmInsertMirror`, `ConfirmEditMirror` and
 * `ConfirmDeleteMirror`. No re-parent: Anasheed never had one. Thin in the
 * same way as KhotabMirrorController — actions own every mutation.
 */
class AnasheedMirrorController
{
    public function __construct(private readonly AdminRecordAuthorizer $authorizer) {}

    /** Lookup by item id or mirror id. Not an orphan listing. */
    public function index(Request $request): View|RedirectResponse
    {
        if ($request->filled('item')) {
            $item = AnasheedItem::query()->find((int) $request->input('item'));

            return $item
                ? redirect()->route('admin.mirrors.anasheed.show', $item)
                : back()->with('error', 'لم يتم العثور على المادة #'.(int) $request->input('item'));
        }

        if ($request->filled('mirror')) {
            $mirror = AnasheedMirror::query()->find((int) $request->input('mirror'));

            return $mirror
                ? redirect()->route('admin.mirrors.anasheed.edit', $mirror)
                : back()->with('error', 'لم يتم العثور على الوصلة #'.(int) $request->input('mirror'));
        }

        return view('admin.mirrors.anasheed.index');
    }

    public function show(AnasheedItem $anasheedItem): View
    {
        $admin = $this->actor();

        return view('admin.mirrors.anasheed.show', [
            'item' => $anasheedItem,
            'mirrors' => $anasheedItem->mirrors()->orderBy('id')->get(),
            'canAdd' => $this->authorizer->allows($admin, 'anasheed.addmirror'),
            'canEdit' => $this->authorizer->allows($admin, 'anasheed.editmirror'),
            'canDelete' => $this->authorizer->allows($admin, 'anasheed.delmirror'),
        ]);
    }

    public function store(StoreAnasheedMirrorRequest $request, AnasheedItem $anasheedItem, InsertAnasheedMirrorAction $action): RedirectResponse
    {
        $mirror = $action->execute($this->actor(), $anasheedItem, $request->validated(), $request->ip());
        $this->flashLinkWarning((int) $mirror->id);

        return redirect()->route('admin.mirrors.anasheed.show', $anasheedItem)
            ->with('success', "تمت إضافة الوصلة البديلة #{$mirror->id}");
    }

    public function edit(AnasheedMirror $anasheedMirror): View
    {
        return view('admin.mirrors.anasheed.edit', [
            'mirror' => $anasheedMirror,
            'item' => AnasheedItem::query()->find((int) $anasheedMirror->khid),
            'sizeEditable' => UpdateAnasheedMirrorAction::acceptsSizeEdit($anasheedMirror),
            'canDelete' => $this->authorizer->allows($this->actor(), 'anasheed.delmirror'),
        ]);
    }

    public function update(UpdateAnasheedMirrorRequest $request, AnasheedMirror $anasheedMirror, UpdateAnasheedMirrorAction $action): RedirectResponse
    {
        $action->execute($this->actor(), $anasheedMirror, $request->validated(), $request->ip());
        $this->flashLinkWarning((int) $anasheedMirror->id);

        $parent = AnasheedItem::query()->find((int) $anasheedMirror->khid);

        $redirect = $parent
            ? redirect()->route('admin.mirrors.anasheed.show', $parent)
            : redirect()->route('admin.mirrors.anasheed.edit', $anasheedMirror);

        return $redirect->with('success', "تم تعديل الوصلة #{$anasheedMirror->id}");
    }

    public function destroy(Request $request, AnasheedMirror $anasheedMirror, DeleteAnasheedMirrorAction $action): RedirectResponse
    {
        $id = (int) $anasheedMirror->id;
        $parent = AnasheedItem::query()->find((int) $anasheedMirror->khid);

        $action->execute($this->actor(), $anasheedMirror, $request->ip());

        $redirect = $parent
            ? redirect()->route('admin.mirrors.anasheed.show', $parent)
            : redirect()->route('admin.mirrors.anasheed.index');

        return $redirect->with('success', "تم حذف الوصلة #{$id}");
    }

    private function actor(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }

    /** B2 / F07-A: warn (never block) when the SAVED mirror link cannot be played or downloaded as stored. */
    private function flashLinkWarning(int $mirrorId): void
    {
        $warning = VarietyMediaLink::adminWarning(AnasheedMirror::query()->whereKey($mirrorId)->value('link'), 'الوصلة #'.$mirrorId);
        if ($warning !== null) {
            session()->flash(VarietyMediaLink::ADMIN_WARNING_FLASH, $warning);
        }
    }
}
