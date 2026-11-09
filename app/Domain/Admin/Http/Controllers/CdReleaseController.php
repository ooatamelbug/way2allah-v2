<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Actions\Cds\CdImageList;
use App\Domain\Admin\Actions\Cds\CdLinkList;
use App\Domain\Admin\Actions\Cds\CreateCdReleaseAction;
use App\Domain\Admin\Actions\Cds\DeleteCdReleaseAction;
use App\Domain\Admin\Actions\Cds\ReorderCdReleasesAction;
use App\Domain\Admin\Actions\Cds\UnsupportedCdImageException;
use App\Domain\Admin\Actions\Cds\UpdateCdReleaseAction;
use App\Domain\Admin\Http\Requests\Cds\CdOrderRequest;
use App\Domain\Admin\Http\Requests\Cds\CdReleaseRequest;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Content\Models\W2acdGroup;
use App\Domain\Content\Models\W2acdItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * OLD Admin Migration Phase 6 — CD / Album Downloads administration
 * (internally "W2ACD", `nuke_w2acd_w2acd`). Replaces the working part of
 * OLD `modules/w2acd/admin`: add, edit (links, images, group), delete and
 * order releases. The public pages stay with `W2acdController`, unchanged.
 */
class CdReleaseController
{
    /** The releases of one group, in public order (`order_in_group DESC, mytime DESC`). */
    public function index(Request $request): View
    {
        $groupId = max(0, (int) $request->query('group', 0));

        return view('admin.cds.index', [
            'groupId' => $groupId,
            'groups' => W2acdGroup::query()->orderBy('id')->get(['id', 'title']),
            'releases' => W2acdItem::query()->where('group_id', $groupId)
                ->orderByDesc('order_in_group')->orderByDesc('mytime')
                ->get(['id', 'title', 'mytime', 'link', 'cd', 'thumbnail', 'order_in_group', 'hits']),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.cds.form', [
            'release' => null,
            'groupId' => max(0, (int) $request->query('group', 0)),
            'groups' => W2acdGroup::query()->orderBy('id')->get(['id', 'title']),
            'links' => [],
            'images' => [],
        ]);
    }

    public function store(CdReleaseRequest $request, CreateCdReleaseAction $action): RedirectResponse
    {
        try {
            $id = $action->execute($this->actor(), (string) $request->validated('title'), (int) $request->validated('group_id', 0), $request->links(), $request->addedImages(), $request->ip());
        } catch (UnsupportedCdImageException) {
            return back()->withInput()->with('error', 'نوع الصورة غير مدعوم، الصيغ المسموح بها: jpg و png و gif');
        }

        return redirect()->route('admin.cds.edit', $id)->with('success', "تمت إضافة الإسطوانة #{$id}");
    }

    public function edit(W2acdItem $release): View
    {
        return view('admin.cds.form', [
            'release' => $release,
            'groupId' => (int) $release->group_id,
            'groups' => W2acdGroup::query()->orderBy('id')->get(['id', 'title']),
            'links' => CdLinkList::parse($release->link, $release->cd),
            'images' => CdImageList::parse($release->thumbnail),
        ]);
    }

    public function update(CdReleaseRequest $request, W2acdItem $release, UpdateCdReleaseAction $action): RedirectResponse
    {
        try {
            $changed = $action->execute($this->actor(), $release, (string) $request->validated('title'), (int) $request->validated('group_id', 0), $request->links(), $request->existingImages(), $request->addedImages(), $request->ip());
        } catch (UnsupportedCdImageException) {
            return back()->with('error', 'نوع الصورة غير مدعوم، الصيغ المسموح بها: jpg و png و gif');
        } catch (InvalidArgumentException $e) {
            return back()->with('error', 'الحد الأقصى خمس صور للإسطوانة');
        }

        return redirect()->route('admin.cds.edit', $release)->with($changed ? 'success' : 'error', $changed ? 'تم حفظ التعديلات' : 'لم يتغير شيء');
    }

    public function destroy(Request $request, W2acdItem $release, DeleteCdReleaseAction $action): RedirectResponse
    {
        $groupId = (int) $release->group_id;
        $id = (int) $release->id;
        $action->execute($this->actor(), $release, $request->ip());

        return redirect()->route('admin.cds.index', ['group' => $groupId])->with('success', "تم حذف الإسطوانة #{$id}");
    }

    public function saveOrder(CdOrderRequest $request, ReorderCdReleasesAction $action): RedirectResponse
    {
        $groupId = max(0, (int) $request->query('group', 0));
        try {
            $changed = $action->execute($this->actor(), $groupId, $request->orderedIds(), $request->ip());
        } catch (InvalidArgumentException $e) {
            return redirect()->route('admin.cds.index', ['group' => $groupId])->with('error', 'يجب ترتيب كل إسطوانات القسم مرة واحدة');
        }

        return redirect()->route('admin.cds.index', ['group' => $groupId])
            ->with($changed > 0 ? 'success' : 'error', $changed > 0 ? 'تم تعديل الترتيب' : 'لم يتغير شيء');
    }

    private function actor(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }
}
