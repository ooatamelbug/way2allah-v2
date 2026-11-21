<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Actions\Ads\UpdateBannerAdsAction;
use App\Domain\Admin\Models\AdminUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * F01 / B6b — OLD «إعلانات بنرات المواد» (`old-project/modules/banners_ads/admin`, op `w2a_banners_ads_main`):
 * choose which Khotab items (those with an uploaded banner, `banner = 1`) the home banner strip may draw from
 * (`nuke_banners_ads.media_id`). Permission `banners_ads.managebanners` (OLD: radminsuper or the module's admins);
 * `ads.manageads` grants nothing here.
 *
 * OLD showed the current set and every banner item, with category / author filters that never worked (its AJAX
 * endpoints echoed debug text only, and compared the pipe-delimited `cat` column with `=`). Here the same
 * filters work server-side (category through `khotab_category_index`, author, title).
 *
 * Administration only: the strip itself is public rendering (B6c, not implemented).
 */
class BannerAdsAdminController
{
    public function index(Request $request): View
    {
        $main = DB::connection('main');
        $filters = [
            'category' => self::positiveInt($request->query('category')),
            'author' => self::positiveInt($request->query('author')),
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
        ];

        $current = $main->table('nuke_banners_ads as b')
            ->leftJoin('nuke_islamic_khotab as k', 'k.id', '=', 'b.media_id')
            ->leftJoin('nuke_islamic_authors as a', 'a.id', '=', 'k.author')
            ->orderBy('b.id')
            ->get(['b.id', 'b.media_id', 'b.creation_date', 'k.id as item_id', 'k.title', 'k.banner', 'k.hidden', 'a.name as author_name']);
        $currentIds = $current->pluck('media_id')->map(fn ($id) => (int) $id)->all();

        $candidates = $main->table('nuke_islamic_khotab as k')
            ->leftJoin('nuke_islamic_authors as a', 'a.id', '=', 'k.author')
            ->where('k.banner', 1)
            ->when($filters['author'] !== null, fn ($q) => $q->where('k.author', $filters['author']))
            ->when($filters['q'] !== '', fn ($q) => $q->where('k.title', 'like', '%'.addcslashes($filters['q'], '%_\\').'%'))
            ->when($filters['category'] !== null, fn ($q) => $q->whereExists(fn ($sub) => $sub->from('khotab_category_index as kci')
                ->whereColumn('kci.khotab_id', 'k.id')->where('kci.category_id', $filters['category'])))
            ->orderBy('k.title')->orderBy('k.id')
            ->select(['k.id', 'k.title', 'k.hidden', 'a.name as author_name'])
            ->paginate(100)->withQueryString();

        return view('admin.banners-ads.index', [
            'current' => $current,
            'currentIds' => $currentIds,
            'setVersion' => UpdateBannerAdsAction::fingerprint($currentIds),
            'candidates' => $candidates,
            'filters' => $filters,
            'categories' => self::categoryPaths(),
            'authors' => $main->table('nuke_islamic_authors')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, UpdateBannerAdsAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'keep' => ['nullable', 'array', 'max:5000'],
            'keep.*' => ['integer', 'min:1', 'distinct'],
            'add' => ['nullable', 'array', 'max:5000'],
            'add.*' => ['integer', 'min:1', 'distinct'],
            'set_version' => ['required', 'string', 'size:64'],
        ]);

        $result = $action->execute($this->actor(), array_values($validated['keep'] ?? []), array_values($validated['add'] ?? []),
            $validated['set_version'], $request->ip());

        if ($result['added'] === [] && $result['removed'] === []) {
            return redirect()->route('admin.banners_ads.index')->with('error', 'لم يتغير شيء');
        }

        return redirect()->route('admin.banners_ads.index')
            ->with('success', 'تم تحديث بيانات إعلانات البنر: أضيفت '.count($result['added']).' وحذفت '.count($result['removed']));
    }

    /**
     * OLD `get_all_cats()`: every category as its full path «أب=>ابن», sorted by that path.
     *
     * @return array<int, string>
     */
    private static function categoryPaths(): array
    {
        $rows = DB::connection('main')->table('nuke_w2a_cat')->get(['id', 'title', 'main_cat'])->keyBy('id');
        $paths = [];
        foreach ($rows as $id => $row) {
            $names = [];
            $seen = [];
            for ($cat = $row; $cat !== null && ! isset($seen[$cat->id]); $cat = $rows->get((int) $cat->main_cat)) {
                $seen[$cat->id] = true;   // guards against a cycle in legacy data
                array_unshift($names, (string) $cat->title);
                if ((int) $cat->main_cat <= 0) {
                    break;
                }
            }
            $paths[(int) $id] = implode('=>', $names);
        }
        asort($paths);

        return $paths;
    }

    private static function positiveInt(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function actor(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }
}
