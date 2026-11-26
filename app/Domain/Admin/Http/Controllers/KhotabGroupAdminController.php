<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Actions\Khotab\ArchiveOrgMetadata;
use App\Domain\Admin\Actions\Khotab\DeleteKhotabGroupAction;
use App\Domain\Admin\Actions\Khotab\DetachKhotabItemAction;
use App\Domain\Admin\Actions\Khotab\DetachKhotabSeriesAction;
use App\Domain\Admin\Actions\Khotab\FastCopyKhotabCategoriesAction;
use App\Domain\Admin\Actions\Khotab\InsertKhotabItemAction;
use App\Domain\Admin\Actions\Khotab\InsertKhotabSeriesAction;
use App\Domain\Admin\Actions\Khotab\KhotabAdditionalAuthorCopies;
use App\Domain\Admin\Actions\Khotab\ReArrangeKhotabItemsAction;
use App\Domain\Admin\Actions\Khotab\UpdateKhotabGroupAction;
use App\Domain\Admin\Contextual\ContextualReturn;
use App\Domain\Admin\Http\Requests\Khotab\StoreKhotabItemRequest;
use App\Domain\Admin\Http\Requests\Khotab\StoreKhotabSeriesRequest;
use App\Domain\Admin\Http\Requests\Khotab\UpdateKhotabGroupRequest;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Content\Models\KhotabGroup;
use App\Domain\Content\Models\KhotabItem;
use App\Domain\Content\Models\Series;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * OLD public admin parity — the Khotab group page's «لوحة تحكم المجموعة»
 * (`themes/way2allah/templates/Khotab/group_cp.htm`, rendered by OLD
 * `modules/Khotab/index.php` `Group()`), plus the per-series and per-item
 * detach icons of the same page. Production = the OLD working copy (owner,
 * 2026-10-21).
 *
 * Every page here is the Laravel counterpart of an OLD `admin.php?op=…`
 * page; every mutation is an action class (authorized, transactional,
 * audited). After a successful mutation the admin lands where OLD sent them —
 * the group page, or the author page after a delete — with the message shown
 * in the admin-only public notice.
 */
class KhotabGroupAdminController
{
    /** «إعادة ترتيب» — OLD ReArrange(Author, Group, 0). */
    public function rearrange(KhotabGroup $group): View
    {
        return view('admin.khotab.groups.rearrange', [
            'group' => $group,
            'items' => DB::connection('main')->table('nuke_islamic_khotab')
                ->where('author', $group->author_id)->where('ser_id', 0)->where('group_id', $group->id)
                ->orderByDesc('weight')->get(['id', 'title', 'time', 'weight']),
        ]);
    }

    public function saveRearrange(Request $request, KhotabGroup $group, ReArrangeKhotabItemsAction $action): RedirectResponse
    {
        $submitted = $request->input('Khotab', []);
        $action->execute($this->actor(), $group, is_array($submitted) ? $submitted : [], $request->ip());

        return $this->toGroup($group, 'تم إعادة ترتيب مواد المجموعة');
    }

    /** «التصنيف السريع» — OLD cat_fastcopy(Author, Group, 0). */
    public function fastCopy(KhotabGroup $group, FastCopyKhotabCategoriesAction $action): View
    {
        return view('admin.khotab.groups.fastcopy', [
            'group' => $group,
            'columns' => $action->columns($group),
            'items' => DB::connection('main')->table('nuke_islamic_khotab')
                ->where('author', $group->author_id)->where('ser_id', 0)->where('group_id', $group->id)
                ->orderByDesc('weight')->get(['id', 'title', 'cat']),
        ]);
    }

    /** OLD cat_fastcopy_confirm() ends by showing the matrix again. */
    public function saveFastCopy(Request $request, KhotabGroup $group, FastCopyKhotabCategoriesAction $action): RedirectResponse
    {
        $submitted = $request->input('Khotab', []);
        $changed = $action->execute($this->actor(), $group, is_array($submitted) ? $submitted : [], $request->ip());

        return redirect()->route('admin.khotab.groups.fastcopy', $group)
            ->with('success', "تم حفظ التصنيف: {$changed} مادة تغيرت");
    }

    /** «تعديل المجموعة» — OLD EditGroup(). */
    public function edit(KhotabGroup $group): View
    {
        return view('admin.khotab.groups.edit', [
            'group' => $group,
            'channels' => DB::connection('main')->table('nuke_sat_channels')->orderByRaw('BINARY title ASC')->get(['id', 'title']),
        ]);
    }

    public function update(UpdateKhotabGroupRequest $request, KhotabGroup $group, UpdateKhotabGroupAction $action): RedirectResponse
    {
        $action->execute($this->actor(), $group, $request->validated(), $request->ip());

        return $this->toGroup($group, 'تم تعديل المجموعة');
    }

    /** «حذف المجموعة» — OLD DeleteGroup()'s confirmation page. */
    public function confirmDestroy(KhotabGroup $group): View
    {
        return view('admin.khotab.groups.delete', ['group' => $group]);
    }

    public function destroy(Request $request, KhotabGroup $group, DeleteKhotabGroupAction $action): RedirectResponse
    {
        $title = (string) $group->title;
        $result = $action->execute($this->actor(), $group, $request->ip());

        // OLD: khotab-video-{author}.htm for a video group, else khotab-audio-{author}.htm.
        $target = '/khotab-'.($result['vedio'] === 1 ? 'video' : 'audio').'-'.$result['author_id'].'.htm';

        return redirect()->to($target)->with(ContextualReturn::FLASH, "تم حذف المجموعة '' {$title} ''");
    }

    /**
     * «مواد لم تصنف» — OLD `KhotabLists&group_id=X`. With no `mode` (the group
     * page passes none) OLD adds no filter: every item `WHERE group_id = X`,
     * hidden ones and series members included, `ORDER BY id ASC`, 50 a page.
     */
    public function items(Request $request, KhotabGroup $group): View
    {
        $main = DB::connection('main');
        $total = $main->table('nuke_islamic_khotab')->where('group_id', $group->id)->count();
        $page = max(1, (int) $request->query('page', 1));

        $rows = $main->table('nuke_islamic_khotab as k')
            ->leftJoin('nuke_islamic_authors as a', 'a.id', '=', 'k.author')
            ->leftJoin('nuke_islamic_series as s', 's.id', '=', 'k.ser_id')
            ->leftJoin('nuke_islamic_groups as g', 'g.id', '=', 'k.group_id')
            ->where('k.group_id', $group->id)
            ->orderBy('k.id')
            ->forPage($page, 50)
            ->get(['k.id', 'k.title', 'k.hidden', 'k.ser_id', 'k.group_id', 'a.prename', 'a.name', 's.title as series_title', 'g.title as group_title']);

        return view('admin.khotab.groups.items', [
            'group' => $group,
            'items' => new LengthAwarePaginator($rows, $total, 50, $page, ['path' => $request->url()]),
            'total' => $total,
        ]);
    }

    /** «إضافة سلسلة» — OLD InsertSeries(author, group, vedio). */
    public function createSeries(KhotabGroup $group): View
    {
        $main = DB::connection('main');

        return view('admin.khotab.groups.series-create', [
            'group' => $group,
            'author' => $main->table('nuke_islamic_authors')->where('id', $group->author_id)->first(['id', 'prename', 'name']),
            'channel' => $main->table('nuke_sat_channels')->where('id', $group->channel_id)->first(['id', 'title']),
        ]);
    }

    public function storeSeries(StoreKhotabSeriesRequest $request, KhotabGroup $group, InsertKhotabSeriesAction $action): RedirectResponse
    {
        $action->execute($this->actor(), $group, $request->validated(), $request->ip());

        return $this->toGroup($group, 'تمت إضافة السلسلة');
    }

    /** «إضافة مادة» — OLD InsertKhotab(author, 0, group, vedio). */
    public function createItem(KhotabGroup $group): View
    {
        $main = DB::connection('main');

        return view('admin.khotab.groups.item-create', [
            'group' => $group,
            'author' => $main->table('nuke_islamic_authors')->where('id', $group->author_id)->first(['id', 'prename', 'name']),
            'locations' => $main->table('nuke_islamic_locations')->orderByRaw('BINARY title ASC')->get(['id', 'title']),
            'additionalAuthors' => KhotabAdditionalAuthorCopies::selectedForForm(old('additional_authors'), (int) $group->author_id),
        ]);
    }

    public function storeItem(StoreKhotabItemRequest $request, KhotabGroup $group, InsertKhotabItemAction $action): RedirectResponse
    {
        $result = $action->execute($this->actor(), $group, $request->validated(), $request->ip());

        return KhotabAdditionalAuthorCopies::withImageWarning(
            $this->toGroup($group, KhotabAdditionalAuthorCopies::successMessage($result)), $result);
    }

    /** OLD `KhotabLeechImages` — the «التالي» AJAX step of the add-item form. */
    public function archive(Request $request, ArchiveOrgMetadata $archive): View
    {
        return view('admin.khotab.groups._archive', ['data' => $archive->read((string) $request->query('url', ''))]);
    }

    /** Icon beside a series — OLD KhotabUpSeries. */
    public function detachSeries(Request $request, Series $series, DetachKhotabSeriesAction $action): RedirectResponse
    {
        $groupId = $action->execute($this->actor(), $series, $request->ip());

        return ($groupId > 0 ? redirect()->to('/khotab-group-'.$groupId.'.htm') : back())
            ->with(ContextualReturn::FLASH, "تم نقل السلسلة '' {$series->title} '' خارج المجموعة الحالية");
    }

    /** Icon beside an item — OLD KhotabUpKhotab. */
    public function detachItem(Request $request, KhotabItem $item, DetachKhotabItemAction $action): RedirectResponse
    {
        $result = $action->execute($this->actor(), $item, $request->ip());

        if (! $result['detached']) {
            return back()->with(ContextualReturn::FLASH, $result['message']);
        }

        $target = ContextualReturn::safeTarget($request->input(ContextualReturn::PARAM), $request)
            ?? ($result['ser_id'] > 0 ? '/khotab-series-'.$result['ser_id'].'.htm' : '/khotab-group-'.$result['group_id'].'.htm');

        return redirect()->to($target)->with(ContextualReturn::FLASH, $result['message']);
    }

    private function toGroup(KhotabGroup $group, string $message): RedirectResponse
    {
        return redirect()->to('/khotab-group-'.$group->id.'.htm')->with(ContextualReturn::FLASH, $message);
    }

    private function actor(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }
}
