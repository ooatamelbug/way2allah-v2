<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Actions\Mobile\RateMobileMaterialAction;
use App\Domain\Content\Actions\Mobile\RequestMobileChangeAction;
use App\Domain\Content\Actions\Mobile\SubmitMobileMaterialAction;
use App\Domain\Content\Http\Requests\Mobile\MobileMaterialFieldsRequest;
use App\Domain\Content\Http\Requests\Mobile\MobileRateRequest;
use App\Domain\Content\Models\Mobile\MobileCategory;
use App\Domain\Content\Models\Mobile\MobileEditorial;
use App\Domain\Content\Models\Mobile\MobileMaterial;
use App\Domain\Content\Models\Mobile\MobileVote;
use App\Domain\Content\Services\Mobile\MobileCatalog;
use App\Domain\Content\Services\Mobile\MobileSite;
use App\Domain\Identity\Models\VbUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * OLD Admin Migration Phase 7 — the public Mobile Downloads section
 * (قسم الموبايل), replacing OLD `modules/Mobile/index.php`, which no longer
 * runs on PHP 7+ (plan §26.3). Legacy `mobile*.htm` URLs are served at their
 * exact paths, as the rest of the site's legacy pages are; form submissions
 * post to clean `/mobile/...` paths.
 *
 * "Members" are vBulletin-session users — exactly OLD's `is_user()`.
 */
class MobileController
{
    public function index(): View
    {
        return view('mobile.index', [
            'categories' => MobileCatalog::mainCategories(),
            'latestByCategory' => MobileMaterial::query()->selectRaw('cid, max(date) as latest')->groupBy('cid')->pluck('latest', 'cid'),
            'materialCount' => MobileMaterial::query()->count(),
            'categoryCount' => MobileCategory::query()->count(),
        ]);
    }

    /*
     * One method per legacy URL shape: Laravel passes route segments to a
     * controller positionally, in route order (IF-051), so each shape gets
     * parameters in its own order and delegates to one implementation.
     */

    /** OLD viewdownload — `mobiles-cat{cid}.htm`. */
    public function category(Request $request, string $cid): View|RedirectResponse
    {
        return $this->showCategory($request, $cid, null, null, null);
    }

    /** `mobile{cid}-orderby{orderby}.htm`. */
    public function categoryOrdered(Request $request, string $cid, string $orderby): View|RedirectResponse
    {
        return $this->showCategory($request, $cid, $orderby, null, null);
    }

    /** `mobile{cid}-{min}-{orderby}-{show}.htm`. */
    public function categoryPage(Request $request, string $cid, string $min, string $orderby, string $show): View|RedirectResponse
    {
        return $this->showCategory($request, $cid, $orderby, $min, $show);
    }

    private function showCategory(Request $request, string $cid, ?string $orderby, ?string $min, ?string $show): View|RedirectResponse
    {
        $orderby ??= $request->query('orderby');
        if (! MobileCatalog::validOrder($orderby)) {
            return redirect('/');   // OLD convertorderbyin(): Header("Location: index.php")
        }
        $cid = (int) $cid;
        $category = MobileCategory::query()->findOrFail($cid);
        [$perPage, $page] = $this->paging($request, $show, $min, (int) config('mobile_downloads.per_page'));
        $order = $orderby ?? 'titleA';

        return view('mobile.category', [
            'category' => $category,
            'path' => $category->path(),
            'children' => $category->children()->get()->each(fn (MobileCategory $c) => $c->setRelation('firstChildren', $c->children()->limit(3)->get())),
            'materials' => MobileCatalog::categoryMaterials($cid, $order, $page, $perPage),
            'order' => $order,
            'perPage' => $perPage,
        ]);
    }

    /** OLD NewDownloads — `mobiles-newmobiles.htm`, `mobile-shownew-{days}.htm`. */
    public function newMaterials(?string $days = null): View
    {
        $span = max(1, min(365, $days !== null ? (int) $days : (int) config('mobile_downloads.new_default_days')));

        return view('mobile.new', ['span' => $span] + MobileCatalog::newByDays($span));
    }

    /** OLD NewDownloadsDate — `mobile-seldate-{timestamp}.htm`. */
    public function addedOn(string $timestamp): View
    {
        $timestamp = (int) $timestamp;

        return view('mobile.list', [
            'heading' => 'المواد المضافة يوم '.date('Y-m-d', $timestamp),
            'materials' => MobileCatalog::addedOn($timestamp),
        ]);
    }

    public function mostPopular(): View
    {
        return view('mobile.list', ['heading' => 'الأكثر تحميلا', 'materials' => MobileCatalog::mostPopular()]);
    }

    public function topRated(): View
    {
        return view('mobile.list', ['heading' => 'الأعلى تقييما', 'materials' => MobileCatalog::topRated()]);
    }

    /** OLD search — the search form (`?query=`). */
    public function search(Request $request): View|RedirectResponse
    {
        return $this->showSearch($request, null, null, null, null);
    }

    /** `mobile-search-{query}.htm`. */
    public function searchLegacy(Request $request, string $query): View|RedirectResponse
    {
        return $this->showSearch($request, $query, null, null, null);
    }

    /** `mobile-search-{query}-{orderby}.htm`. */
    public function searchOrdered(Request $request, string $query, string $orderby): View|RedirectResponse
    {
        return $this->showSearch($request, $query, $orderby, null, null);
    }

    /** `mobile-search-{query}-{min}-{orderby}-{show}.htm`. */
    public function searchPage(Request $request, string $query, string $min, string $orderby, string $show): View|RedirectResponse
    {
        return $this->showSearch($request, $query, $orderby, $min, $show);
    }

    private function showSearch(Request $request, ?string $query, ?string $orderby, ?string $min, ?string $show): View|RedirectResponse
    {
        $query = trim((string) ($query ?? $request->query('query', '')));
        $orderby ??= $request->query('orderby');
        if (! MobileCatalog::validOrder($orderby)) {
            return redirect('/');
        }
        [$perPage, $page] = $this->paging($request, $show, $min, (int) config('mobile_downloads.search_per_page'));
        $order = $orderby ?? 'titleA';

        return view('mobile.search', [
            'query' => $query,
            'order' => $order,
            'perPage' => $perPage,
            'categories' => $query === '' ? collect() : MobileCatalog::searchCategories($query),
            'materials' => $query === '' ? null : MobileCatalog::search($query, $order, $page, $perPage),
        ]);
    }

    /** OLD viewdownloaddetails — `mobileview-details-{lid}[-{title}].htm`. */
    public function details(string $lid, ?string $ttitle = null): View
    {
        $material = MobileMaterial::query()->findOrFail($lid);
        $anonymous = MobileSite::anonymousName();
        $votes = MobileVote::query()->where('ratinglid', $lid)->get(['rating', 'ratinguser', 'ratingcomments']);
        $split = fn ($subset) => ['count' => $subset->count(), 'average' => $subset->count() > 0 ? round($subset->avg('rating'), 2) : null];

        return view('mobile.details', [
            'material' => $material,
            'path' => MobileCategory::query()->find($material->cid)?->path(),
            'available' => MobileSite::downloadAvailable($material->url),
            'registered' => $split($votes->filter(fn ($v) => $v->ratinguser !== $anonymous && $v->ratinguser !== 'outside')),
            'anonymous' => $split($votes->where('ratinguser', $anonymous)),
            'hasEditorial' => MobileEditorial::query()->whereKey($lid)->exists(),
        ]);
    }

    /** OLD viewdownloadcomments — comments newest first. */
    public function comments(string $lid, ?string $ttitle = null): View
    {
        $material = MobileMaterial::query()->findOrFail($lid);

        return view('mobile.comments', [
            'material' => $material,
            'comments' => MobileVote::query()->where('ratinglid', $lid)->where('ratingcomments', '!=', '')
                ->orderByDesc('ratingtimestamp')->orderByDesc('ratingdbid')->get(),
        ]);
    }

    /** OLD viewdownloadeditorial. */
    public function editorial(string $lid, ?string $ttitle = null): View
    {
        return view('mobile.editorial', [
            'material' => MobileMaterial::query()->findOrFail($lid),
            'editorial' => MobileEditorial::query()->find($lid),
        ]);
    }

    /**
     * OLD getit — `mobile-file-{lid}.htm`: hits +1, then redirect to the URL.
     * A URL that cannot be served (dead host, non-http) is reported as
     * unavailable (410) — no redirect, no hit.
     */
    public function download(string $lid): RedirectResponse|Response
    {
        $material = MobileMaterial::query()->findOrFail($lid);
        if (! MobileSite::downloadAvailable($material->url)) {
            return response()->view('mobile.unavailable', ['material' => $material], 410);
        }
        DB::connection('main')->table('nuke_downloads_downloads')->where('lid', $lid)->increment('hits');

        return redirect()->away(trim($material->url));
    }

    /** OLD ratedownload — `ratemobile-{lid}[-{title}].htm`. */
    public function rateForm(string $lid, ?string $ttitle = null): View
    {
        return view('mobile.rate', ['material' => MobileMaterial::query()->findOrFail($lid), 'member' => $this->member()]);
    }

    public function rate(MobileRateRequest $request, string $lid, RateMobileMaterialAction $action): RedirectResponse
    {
        $material = MobileMaterial::query()->findOrFail($lid);
        $result = $action->execute($this->member(), $material, (int) $request->validated('rating'), trim((string) $request->validated('comment', '')), (string) $request->ip());

        $messages = [
            RateMobileMaterialAction::RATED => ['success', 'نشكرك لتقييمك لهذه المادة'],
            RateMobileMaterialAction::OWN_MATERIAL => ['error', 'لا يمكنك تقييم مادة قمت بإرسالها'],
            RateMobileMaterialAction::ALREADY_RATED => ['error', 'لقد قمت بتقييم هذه المادة من قبل'],
            RateMobileMaterialAction::ANONYMOUS_WAIT => ['error', 'يمكن للزائر تقييم المادة مرة واحدة في اليوم'],
        ];
        [$level, $message] = $messages[$result];

        return redirect("/mobileview-details-{$lid}.htm")->with($level, $message);
    }

    /** OLD AddDownload — `mobiles-admobile.htm`; members only. */
    public function submitForm(): View
    {
        return $this->member() === null ? $this->membersOnly() : view('mobile.submit', ['categories' => $this->categoryOptions()]);
    }

    public function submit(MobileMaterialFieldsRequest $request, SubmitMobileMaterialAction $action): View|RedirectResponse
    {
        $member = $this->member();
        if ($member === null) {
            return $this->membersOnly();
        }
        $result = $action->execute($member, $request->fields());

        return $result === SubmitMobileMaterialAction::URL_EXISTS
            ? back()->withInput()->withErrors(['url' => 'خطأ: تم ادخال هذا الرابط في قاعدة البيانات!'])
            : redirect('/mobiles.htm')->with('success', 'شكرا! تم استلام المادة التي أرسلتها، وستتم مراجعتها');
    }

    /** OLD modifydownloadrequest — `mobile-mod-{lid}.htm`; members only. */
    public function correctionForm(string $lid): View
    {
        $material = MobileMaterial::query()->findOrFail($lid);

        return $this->member() === null ? $this->membersOnly() : view('mobile.correction', ['material' => $material, 'categories' => $this->categoryOptions()]);
    }

    public function correction(MobileMaterialFieldsRequest $request, string $lid, RequestMobileChangeAction $action): View|RedirectResponse
    {
        $material = MobileMaterial::query()->findOrFail($lid);
        $member = $this->member();
        if ($member === null) {
            return $this->membersOnly();
        }
        $action->correction($member, $material, $request->fields());

        return redirect("/mobileview-details-{$lid}.htm")->with('success', 'شكرا على هذه المعلومات. سنراجع طلبك قريباً');
    }

    /** OLD brokendownload — `mobile-broken-{lid}.htm`; members only. */
    public function brokenForm(string $lid): View
    {
        $material = MobileMaterial::query()->findOrFail($lid);

        return $this->member() === null ? $this->membersOnly() : view('mobile.broken', ['material' => $material]);
    }

    public function broken(string $lid, RequestMobileChangeAction $action): View|RedirectResponse
    {
        $material = MobileMaterial::query()->findOrFail($lid);
        $member = $this->member();
        if ($member === null) {
            return $this->membersOnly();
        }
        $action->brokenLink($member, $material);

        return redirect("/mobileview-details-{$lid}.htm")->with('success', 'شكرا على هذه المعلومات. سنراجع الرابط قريباً');
    }

    private function member(): ?VbUser
    {
        $user = Auth::guard('vbulletin')->user();

        return $user instanceof VbUser ? $user : null;
    }

    private function membersOnly(): View
    {
        return view('mobile.members-only');
    }

    /** @return list<array{cid: int, label: string}> categories with their parent path, as OLD's select */
    private function categoryOptions(): array
    {
        return MobileCategory::query()->orderBy('parentid')->orderBy('title')->get()
            ->map(fn (MobileCategory $c) => ['cid' => (int) $c->cid, 'label' => $c->path()])->all();
    }

    /**
     * OLD paged with an offset (`min`) and a page size (`show`, default the
     * section's page size); converted to a page number. `show` is bounded.
     *
     * @return array{0: int, 1: int}
     */
    private function paging(Request $request, ?string $show, ?string $min, int $default): array
    {
        $perPage = max(1, min((int) config('mobile_downloads.max_page_size'), (int) ($show ?? $request->query('show', $default))));
        $page = $min !== null ? intdiv(max(0, (int) $min), $perPage) + 1 : max(1, (int) $request->query('page', 1));

        return [$perPage, $page];
    }
}
