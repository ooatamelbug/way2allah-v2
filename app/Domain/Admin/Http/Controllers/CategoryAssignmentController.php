<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Actions\Categories\AnasheedItemCategoryAction;
use App\Domain\Admin\Actions\Categories\CategoryList;
use App\Domain\Admin\Actions\Categories\KhotabContainerCategoryAction;
use App\Domain\Admin\Actions\Categories\KhotabItemCategoryAction;
use App\Domain\Admin\Http\Requests\Categories\CategoryAssignmentRequest;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Content\Models\AnasheedItem;
use App\Domain\Content\Models\Category;
use App\Domain\Content\Models\KhotabGroup;
use App\Domain\Content\Models\KhotabItem;
use App\Domain\Content\Models\Series;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * OLD Admin Migration Phase 3 — Category Assignment (OA-3).
 *
 * Replaces the assignment half of OLD `modules/Cat/admin/index.php`:
 * add / remove a category on a Khotab item or an Anasheed item, and
 * "add to all" / "remove from all" on a Khotab series or group. No category
 * CRUD, no `cat_fastcopy`, no `KhotabEditCat`. Thin: the actions own every
 * mutation, authorization re-check and audit row.
 */
class CategoryAssignmentController
{
    private const NOOP = [
        KhotabItemCategoryAction::ALREADY_PRESENT => 'التصنيف موجود بالفعل — لم يتغير شيء',
        KhotabItemCategoryAction::ABSENT => 'التصنيف غير موجود في المادة — لم يتغير شيء',
    ];

    /** Lookup by Khotab item, series or group id. */
    public function khotabIndex(Request $request): View|RedirectResponse
    {
        foreach (['item' => [KhotabItem::class, 'admin.categories.khotab.item'], 'series' => [Series::class, 'admin.categories.khotab.series'], 'group' => [KhotabGroup::class, 'admin.categories.khotab.group']] as $key => [$model, $route]) {
            if ($request->filled($key)) {
                $id = (int) $request->input($key);

                return $model::query()->whereKey($id)->exists()
                    ? redirect()->route($route, $id)
                    : back()->with('error', "لم يتم العثور على #{$id}");
            }
        }

        return view('admin.categories.khotab.index');
    }

    public function anasheedIndex(Request $request): View|RedirectResponse
    {
        if ($request->filled('item')) {
            $id = (int) $request->input('item');

            return AnasheedItem::query()->whereKey($id)->exists()
                ? redirect()->route('admin.categories.anasheed.item', $id)
                : back()->with('error', "لم يتم العثور على المادة #{$id}");
        }

        return view('admin.categories.anasheed.index');
    }

    public function khotabItem(KhotabItem $khotabItem): View
    {
        return view('admin.categories.item', [
            'heading' => 'المادة #'.$khotabItem->id.' — '.$khotabItem->title,
            'raw' => (string) $khotabItem->getAttribute('cat'),
            'assigned' => $this->assigned($khotabItem->getAttribute('cat')),
            'options' => $this->options(),
            'addRoute' => route('admin.categories.khotab.item.add', $khotabItem),
            'removeRoute' => route('admin.categories.khotab.item.remove', $khotabItem),
        ]);
    }

    public function anasheedItem(AnasheedItem $anasheedItem): View
    {
        return view('admin.categories.item', [
            'heading' => 'النشيد #'.$anasheedItem->id.' — '.$anasheedItem->title,
            'raw' => (string) $anasheedItem->getAttribute('cat_id'),
            'assigned' => $this->assigned($anasheedItem->getAttribute('cat_id')),
            'options' => $this->options(),
            'addRoute' => route('admin.categories.anasheed.item.add', $anasheedItem),
            'removeRoute' => route('admin.categories.anasheed.item.remove', $anasheedItem),
        ]);
    }

    public function series(Series $series): View
    {
        return $this->container('series', (int) $series->id, (string) $series->title, $series->getAttribute('cat'));
    }

    public function group(KhotabGroup $group): View
    {
        return $this->container('group', (int) $group->id, (string) $group->title, $group->getAttribute('cat'));
    }

    public function addToKhotabItem(CategoryAssignmentRequest $request, KhotabItem $khotabItem, KhotabItemCategoryAction $action): RedirectResponse
    {
        $result = $action->add($this->actor(), $khotabItem, $this->category($request), $request->ip());

        return $this->itemRedirect('admin.categories.khotab.item', $khotabItem->id, $result);
    }

    public function removeFromKhotabItem(CategoryAssignmentRequest $request, KhotabItem $khotabItem, KhotabItemCategoryAction $action): RedirectResponse
    {
        $result = $action->remove($this->actor(), $khotabItem, $this->category($request), $request->ip());

        return $this->itemRedirect('admin.categories.khotab.item', $khotabItem->id, $result);
    }

    public function addToAnasheedItem(CategoryAssignmentRequest $request, AnasheedItem $anasheedItem, AnasheedItemCategoryAction $action): RedirectResponse
    {
        $result = $action->add($this->actor(), $anasheedItem, $this->category($request), $request->ip());

        return $this->itemRedirect('admin.categories.anasheed.item', $anasheedItem->id, $result);
    }

    public function removeFromAnasheedItem(CategoryAssignmentRequest $request, AnasheedItem $anasheedItem, AnasheedItemCategoryAction $action): RedirectResponse
    {
        $result = $action->remove($this->actor(), $anasheedItem, $this->category($request), $request->ip());

        return $this->itemRedirect('admin.categories.anasheed.item', $anasheedItem->id, $result);
    }

    public function addToAllInSeries(CategoryAssignmentRequest $request, Series $series, KhotabContainerCategoryAction $action): RedirectResponse
    {
        return $this->bulk($request, $action, 'series', (int) $series->id, true);
    }

    public function removeFromAllInSeries(CategoryAssignmentRequest $request, Series $series, KhotabContainerCategoryAction $action): RedirectResponse
    {
        return $this->bulk($request, $action, 'series', (int) $series->id, false);
    }

    public function addToAllInGroup(CategoryAssignmentRequest $request, KhotabGroup $group, KhotabContainerCategoryAction $action): RedirectResponse
    {
        return $this->bulk($request, $action, 'group', (int) $group->id, true);
    }

    public function removeFromAllInGroup(CategoryAssignmentRequest $request, KhotabGroup $group, KhotabContainerCategoryAction $action): RedirectResponse
    {
        return $this->bulk($request, $action, 'group', (int) $group->id, false);
    }

    private function bulk(CategoryAssignmentRequest $request, KhotabContainerCategoryAction $action, string $type, int $id, bool $adding): RedirectResponse
    {
        $actor = $this->actor();
        $category = $this->category($request);

        $result = $adding
            ? $action->addToAll($actor, $type, $id, $category, $request->ip())
            : $action->removeFromAll($actor, $type, $id, $category, $request->ip());

        return redirect()->route("admin.categories.khotab.{$type}", $id)->with('success',
            ($adding ? 'تمت إضافة' : 'تم حذف')." التصنيف #{$category->id}: {$result['items_changed']} مادة تغيرت");
    }

    private function container(string $type, int $id, string $title, ?string $raw): View
    {
        return view('admin.categories.container', [
            'type' => $type,
            'id' => $id,
            'heading' => ($type === 'series' ? 'السلسلة' : 'المجموعة')." #{$id} — {$title}",
            'scopeNote' => $type === 'series'
                ? 'يطبق على جميع مواد السلسلة.'
                : 'يطبق على مواد المجموعة التي ليست ضمن سلسلة فقط.',
            'raw' => (string) $raw,
            'assigned' => $this->assigned($raw),
        ]);
    }

    private function itemRedirect(string $route, int $id, string $result): RedirectResponse
    {
        $redirect = redirect()->route($route, $id);

        return isset(self::NOOP[$result])
            ? $redirect->with('error', self::NOOP[$result])
            : $redirect->with('success', $result === KhotabItemCategoryAction::ADDED ? 'تمت إضافة التصنيف' : 'تم حذف التصنيف');
    }

    /**
     * The stored list resolved to titles, in stored order. An id with no
     * `nuke_w2a_cat` row is shown as unknown rather than hidden.
     *
     * @return list<array{id: int, title: string|null}>
     */
    private function assigned(?string $raw): array
    {
        $ids = array_map('intval', CategoryList::parse($raw));
        $titles = Category::query()->whereKey($ids)->pluck('title', 'id');

        return array_map(fn (int $id) => ['id' => $id, 'title' => $titles->has($id) ? (string) $titles[$id] : null], $ids);
    }

    /**
     * Every category, flattened depth-first from `main_cat = 0`, for the
     * select. Orphans (a parent id with no row) are appended at the end, with
     * their own subtrees, so nothing is unreachable.
     *
     * @return list<array{id: int, label: string}>
     */
    private function options(): array
    {
        $all = Category::query()->orderBy('id')->get(['id', 'title', 'main_cat']);
        $byParent = $all->groupBy(fn (Category $c) => (int) $c->main_cat);
        $out = [];
        $seen = [];

        $walk = function (int $parent, int $depth) use (&$walk, &$out, &$seen, $byParent): void {
            foreach ($byParent->get($parent, collect()) as $category) {
                if (isset($seen[$category->id])) {
                    continue;
                }
                $seen[$category->id] = true;
                $out[] = ['id' => (int) $category->id, 'label' => str_repeat('— ', $depth).$category->title];
                $walk((int) $category->id, $depth + 1);
            }
        };
        $walk(0, 0);

        foreach ($all as $category) {
            if (! isset($seen[$category->id])) {
                $seen[$category->id] = true;
                $out[] = ['id' => (int) $category->id, 'label' => (string) $category->title];
                $walk((int) $category->id, 1);
            }
        }

        return $out;
    }

    private function category(CategoryAssignmentRequest $request): Category
    {
        return Category::query()->findOrFail($request->categoryId());
    }

    private function actor(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }
}
