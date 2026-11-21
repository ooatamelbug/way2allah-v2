<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Actions\Mirrors\DeleteKhotabMirrorAction;
use App\Domain\Admin\Actions\Mirrors\InsertKhotabMirrorAction;
use App\Domain\Admin\Actions\Mirrors\MoveKhotabMirrorAction;
use App\Domain\Admin\Actions\Mirrors\UpdateKhotabMirrorAction;
use App\Domain\Admin\Http\Requests\Mirrors\MoveKhotabMirrorRequest;
use App\Domain\Admin\Http\Requests\Mirrors\StoreKhotabMirrorRequest;
use App\Domain\Admin\Http\Requests\Mirrors\UpdateKhotabMirrorRequest;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\KhotabItem;
use App\Domain\Content\Models\Mirror;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * OLD Admin Migration Phase 1 — Khotab mirror administration.
 *
 * Replaces the four OLD handlers `ConfirmInsertMirror`, `ConfirmEditMirror`,
 * `ConfirmDeleteMirror` and `MoveMirror`. Thin by design: every mutation
 * is delegated to its own action, which owns authorization
 * (`AdminRecordAuthorizer`), the `main` transaction, the parent-counter
 * compatibility update and the audit write. This class only translates
 * HTTP into those calls and back.
 *
 * Re-parenting keeps OLD's two-step *interaction* — pick a mirror here,
 * place it from the target item's page — but the state between the steps
 * is a mirror id in the Laravel session, not OLD's `.way2allah.com` cookie
 * holding a serialized database row (3E.1 I-11). The move endpoint itself
 * does not depend on that session: it takes the target explicitly.
 */
class KhotabMirrorController
{
    public const CLIPBOARD_KEY = 'admin.mirrors.khotab.clipboard';

    public function __construct(private readonly AdminRecordAuthorizer $authorizer) {}

    /** Lookup by item id or mirror id. Not an orphan listing. */
    public function index(Request $request): View|RedirectResponse
    {
        if ($request->filled('item')) {
            $item = KhotabItem::query()->find((int) $request->input('item'));

            return $item
                ? redirect()->route('admin.mirrors.khotab.show', $item)
                : back()->with('error', 'لم يتم العثور على المادة #'.(int) $request->input('item'));
        }

        if ($request->filled('mirror')) {
            $mirror = Mirror::query()->find((int) $request->input('mirror'));

            return $mirror
                ? redirect()->route('admin.mirrors.khotab.edit', $mirror)
                : back()->with('error', 'لم يتم العثور على الوصلة #'.(int) $request->input('mirror'));
        }

        return view('admin.mirrors.khotab.index');
    }

    public function show(KhotabItem $khotabItem): View
    {
        $admin = $this->actor();
        $clipboardId = session(self::CLIPBOARD_KEY);

        return view('admin.mirrors.khotab.show', [
            'item' => $khotabItem,
            'mirrors' => $khotabItem->mirrors()->orderBy('id')->get(),
            'clipboard' => $clipboardId ? Mirror::query()->find((int) $clipboardId) : null,
            'canAdd' => $this->authorizer->allows($admin, 'khotab.addmirror'),
            'canEdit' => $this->authorizer->allows($admin, 'khotab.editmirror'),
            'canDelete' => $this->authorizer->allows($admin, 'khotab.delmirror'),
        ]);
    }

    public function store(StoreKhotabMirrorRequest $request, KhotabItem $khotabItem, InsertKhotabMirrorAction $action): RedirectResponse
    {
        $mirror = $action->execute($this->actor(), $khotabItem, $request->validated(), $request->ip());

        return redirect()->route('admin.mirrors.khotab.show', $khotabItem)
            ->with('success', "تمت إضافة الوصلة البديلة #{$mirror->id}");
    }

    public function edit(Mirror $mirror): View
    {
        return view('admin.mirrors.khotab.edit', [
            'mirror' => $mirror,
            'item' => KhotabItem::query()->find((int) $mirror->khid),
            // An orphan has no item page, so the edit page is the only
            // place its delete can be offered from.
            'canDelete' => $this->authorizer->allows($this->actor(), 'khotab.delmirror'),
            // F09: OLD's post-backup link lock (enforced again in the action).
            'linkEditable' => UpdateKhotabMirrorAction::linkEditable($this->actor(), $mirror),
        ]);
    }

    public function update(UpdateKhotabMirrorRequest $request, Mirror $mirror, UpdateKhotabMirrorAction $action): RedirectResponse
    {
        $action->execute($this->actor(), $mirror, $request->validated(), $request->ip());

        return $this->backToParentOrEdit($mirror)->with('success', "تم تعديل الوصلة #{$mirror->id}");
    }

    public function destroy(Request $request, Mirror $mirror, DeleteKhotabMirrorAction $action): RedirectResponse
    {
        $id = (int) $mirror->id;
        $parent = KhotabItem::query()->find((int) $mirror->khid);

        $action->execute($this->actor(), $mirror, $request->ip());

        if ((int) session(self::CLIPBOARD_KEY) === $id) {
            session()->forget(self::CLIPBOARD_KEY);
        }

        $redirect = $parent
            ? redirect()->route('admin.mirrors.khotab.show', $parent)
            : redirect()->route('admin.mirrors.khotab.index');

        return $redirect->with('success', "تم حذف الوصلة #{$id}");
    }

    /** Step one of a re-parent: remember which mirror is being moved. */
    public function pick(Mirror $mirror): RedirectResponse
    {
        session([self::CLIPBOARD_KEY => (int) $mirror->id]);

        return back()->with('success', "تم اختيار الوصلة #{$mirror->id} للنقل — افتح المادة الهدف ثم اضغط «نقل الوصلة هنا».");
    }

    public function clearPick(): RedirectResponse
    {
        session()->forget(self::CLIPBOARD_KEY);

        return back()->with('success', 'تم إلغاء اختيار الوصلة للنقل.');
    }

    /** Step two: place the mirror on the target item. */
    public function move(MoveKhotabMirrorRequest $request, Mirror $mirror, MoveKhotabMirrorAction $action): RedirectResponse
    {
        $target = KhotabItem::query()->findOrFail((int) $request->validated('target_item_id'));

        $action->execute($this->actor(), $mirror, $target, $request->ip());

        session()->forget(self::CLIPBOARD_KEY);

        return redirect()->route('admin.mirrors.khotab.show', $target)
            ->with('success', "تم نقل الوصلة #{$mirror->id} إلى هذه المادة");
    }

    private function backToParentOrEdit(Mirror $mirror): RedirectResponse
    {
        $parent = KhotabItem::query()->find((int) $mirror->khid);

        return $parent
            ? redirect()->route('admin.mirrors.khotab.show', $parent)
            : redirect()->route('admin.mirrors.khotab.edit', $mirror);
    }

    private function actor(): AdminUser
    {
        $admin = auth('admin')->user();

        // Unreachable behind `admin.role`, but the actions need a typed
        // actor and an unauthenticated call must never reach one.
        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }
}
