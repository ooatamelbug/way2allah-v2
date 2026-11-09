<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Actions\Khotab\DeleteKhotabSeriesAction;
use App\Domain\Admin\Actions\Khotab\FastCopyKhotabSeriesCategoriesAction;
use App\Domain\Admin\Actions\Khotab\InsertKhotabSeriesItemAction;
use App\Domain\Admin\Actions\Khotab\KhotabSeriesContext;
use App\Domain\Admin\Actions\Khotab\ReArrangeKhotabSeriesItemsAction;
use App\Domain\Admin\Actions\Khotab\UpdateKhotabSeriesAction;
use App\Domain\Admin\Contextual\ContextualReturn;
use App\Domain\Admin\Http\Requests\Khotab\StoreKhotabSeriesItemRequest;
use App\Domain\Admin\Http\Requests\Khotab\UpdateKhotabSeriesRequest;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Content\Models\Series;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * OLD public admin parity — the Khotab series page's «لوحة تحكم السلسلة»
 * (`themes/way2allah/templates/Khotab/series_cp.htm`, rendered by OLD
 * `modules/Khotab/index.php` `Series()`). Production = the OLD working copy
 * (owner, 2026-10-21). The per-item detach icon of the same page reuses the
 * group page's `admin.khotab.items.detach` route, whose action already
 * follows OLD `UpKhotab`'s series branch.
 *
 * Every page here is the Laravel counterpart of an OLD `admin.php?op=…`
 * page; every mutation is an action class (authorized, transactional,
 * audited). After a mutation the admin lands where OLD sent them, with the
 * message shown in the admin-only public notice.
 */
class KhotabSeriesAdminController
{
    /** «إعادة ترتيب» — OLD ReArrange(Author, Group, Series). */
    public function rearrange(Series $series): View
    {
        return view('admin.khotab.series.rearrange', [
            'series' => $series,
            'items' => $this->directItems($series)->orderByDesc('weight')->get(['id', 'title', 'time', 'weight']),
        ]);
    }

    public function saveRearrange(Request $request, Series $series, ReArrangeKhotabSeriesItemsAction $action): RedirectResponse
    {
        $submitted = $request->input('Khotab', []);
        $action->execute($this->actor(), $series, is_array($submitted) ? $submitted : [], $request->ip());

        return $this->toSeries($series, 'تم إعادة ترتيب مواد السلسلة');
    }

    /** «التصنيف السريع» — OLD cat_fastcopy(Author, Group, Series). */
    public function fastCopy(Series $series, FastCopyKhotabSeriesCategoriesAction $action): View
    {
        return view('admin.khotab.series.fastcopy', [
            'series' => $series,
            'columns' => $action->columns($series),
            'items' => $this->directItems($series)->orderByDesc('weight')->get(['id', 'title', 'cat']),
        ]);
    }

    /** OLD cat_fastcopy_confirm() ends by showing the matrix again. */
    public function saveFastCopy(Request $request, Series $series, FastCopyKhotabSeriesCategoriesAction $action): RedirectResponse
    {
        $submitted = $request->input('Khotab', []);
        $changed = $action->execute($this->actor(), $series, is_array($submitted) ? $submitted : [], $request->ip());

        return redirect()->route('admin.khotab.series.fastcopy', $series)
            ->with('success', "تم حفظ التصنيف: {$changed} مادة تغيرت");
    }

    /**
     * «تعديل السلسلة» — OLD EditSeries(): the author's groups of the same
     * media type (`ORDER BY id ASC`); a channel select only for a series with
     * no group, otherwise the channel's title as plain text.
     */
    public function edit(Series $series): View
    {
        $main = DB::connection('main');
        $grouped = (int) $series->group_id !== 0;

        return view('admin.khotab.series.edit', [
            'series' => $series,
            'groups' => $main->table('nuke_islamic_groups')
                ->where('author_id', $series->author_id)->where('vedio', $series->vedio)
                ->orderBy('id')->get(['id', 'title']),
            'channels' => $grouped ? collect() : $main->table('nuke_sat_channels')->orderByRaw($this->byTitle())->get(['id', 'title']),
            'channelTitle' => $grouped ? (string) $main->table('nuke_sat_channels')->where('id', $series->channel_id)->value('title') : '',
        ]);
    }

    /** OLD: to the posted group's page when one was chosen, else back to the series. */
    public function update(UpdateKhotabSeriesRequest $request, Series $series, UpdateKhotabSeriesAction $action): RedirectResponse
    {
        $group = $action->execute($this->actor(), $series, $request->validated(), $request->ip());

        return redirect()->to($group > 0 ? '/khotab-group-'.$group.'.htm' : '/khotab-series-'.$series->id.'.htm')
            ->with(ContextualReturn::FLASH, 'تم تعديل السلسلة');
    }

    /** «حذف السلسلة» — OLD DeleteSeries()'s confirmation page. */
    public function confirmDestroy(Series $series): View
    {
        return view('admin.khotab.series.delete', [
            'series' => $series,
            'author' => DB::connection('main')->table('nuke_islamic_authors')->where('id', $series->author_id)->first(['prename', 'name']),
        ]);
    }

    public function destroy(Request $request, Series $series, DeleteKhotabSeriesAction $action): View|RedirectResponse
    {
        $title = (string) $series->title;
        $result = $action->execute($this->actor(), $series, $request->ip());

        // OLD refused, on its own error page, while the series still had items.
        if (! $result['deleted']) {
            return view('admin.khotab.series.delete-refused', ['series' => $series]);
        }

        $target = $result['group_id'] > 0
            ? '/khotab-group-'.$result['group_id'].'.htm'
            : '/khotab-'.($result['vedio'] === 1 ? 'video' : 'audio').'-'.$result['author_id'].'.htm';

        return redirect()->to($target)->with(ContextualReturn::FLASH, "تم حذف السلسلة '' {$title} ''");
    }

    /**
     * «مواد لم تصنف» — OLD `KhotabLists&ser_id=X`. With no `mode` OLD adds no
     * filter: every item `WHERE ser_id = X`, hidden ones included,
     * `ORDER BY id ASC`, 50 a page.
     */
    public function items(Request $request, Series $series): View
    {
        $main = DB::connection('main');
        $total = $main->table('nuke_islamic_khotab')->where('ser_id', $series->id)->count();
        $page = max(1, (int) $request->query('page', 1));

        $rows = $main->table('nuke_islamic_khotab as k')
            ->leftJoin('nuke_islamic_authors as a', 'a.id', '=', 'k.author')
            ->leftJoin('nuke_islamic_series as s', 's.id', '=', 'k.ser_id')
            ->leftJoin('nuke_islamic_groups as g', 'g.id', '=', 'k.group_id')
            ->where('k.ser_id', $series->id)
            ->orderBy('k.id')
            ->forPage($page, 50)
            ->get(['k.id', 'k.title', 'k.hidden', 'k.ser_id', 'k.group_id', 'a.prename', 'a.name', 's.title as series_title', 'g.title as group_title']);

        return view('admin.khotab.series.items', [
            'series' => $series,
            'items' => new LengthAwarePaginator($rows, $total, 50, $page, ['path' => $request->url()]),
            'total' => $total,
        ]);
    }

    /** «إضافة مادة» — OLD InsertKhotab(author, series, 0, vedio). */
    public function createItem(Series $series): View
    {
        $main = DB::connection('main');
        $groupId = KhotabSeriesContext::groupId((int) $series->group_id);
        $selectable = InsertKhotabSeriesItemAction::channelSelectable($series);

        return view('admin.khotab.series.item-create', [
            'series' => $series,
            'author' => $main->table('nuke_islamic_authors')->where('id', $series->author_id)->first(['id', 'prename', 'name']),
            'groupTitle' => $groupId > 0 ? (string) $main->table('nuke_islamic_groups')->where('id', $groupId)->value('title') : 'بدون مجموعة',
            'selectable' => $selectable,
            'channels' => $selectable ? $main->table('nuke_sat_channels')->orderByRaw($this->byTitle())->get(['id', 'title']) : collect(),
            'fixedChannel' => (int) $series->channel_id > 0 ? $main->table('nuke_sat_channels')->where('id', $series->channel_id)->first(['id', 'title']) : null,
            'locations' => $main->table('nuke_islamic_locations')->orderByRaw($this->byTitle())->get(['id', 'title']),
        ]);
    }

    public function storeItem(StoreKhotabSeriesItemRequest $request, Series $series, InsertKhotabSeriesItemAction $action): RedirectResponse
    {
        $action->execute($this->actor(), $series, $request->validated(), $request->ip());

        return $this->toSeries($series, 'تمت إضافة المادة');
    }

    /** OLD's `author = A AND ser_id = X AND group_id = G` item set. */
    private function directItems(Series $series): Builder
    {
        return DB::connection('main')->table('nuke_islamic_khotab')
            ->where('author', $series->author_id)->where('ser_id', $series->id)
            ->where('group_id', KhotabSeriesContext::groupId((int) $series->group_id));
    }

    /** OLD's `ORDER BY BINARY title ASC`; SQLite (tests) has no BINARY, so it falls back to `title ASC`. */
    private function byTitle(): string
    {
        return DB::connection('main')->getDriverName() === 'mysql' ? 'BINARY title ASC' : 'title ASC';
    }

    private function toSeries(Series $series, string $message): RedirectResponse
    {
        return redirect()->to('/khotab-series-'.$series->id.'.htm')->with(ContextualReturn::FLASH, $message);
    }

    private function actor(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }
}
