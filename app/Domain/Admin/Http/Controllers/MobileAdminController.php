<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Actions\Mobile\MobileCategoryAdmin;
use App\Domain\Admin\Actions\Mobile\MobileEditorialAdmin;
use App\Domain\Admin\Actions\Mobile\MobileMaterialAdmin;
use App\Domain\Admin\Actions\Mobile\MobileRefused;
use App\Domain\Admin\Actions\Mobile\MobileRequestModeration;
use App\Domain\Admin\Actions\Mobile\MobileVoteModeration;
use App\Domain\Admin\Http\Requests\Mobile\MobileCategoryRequest;
use App\Domain\Admin\Http\Requests\Mobile\MobileEditorialRequest;
use App\Domain\Admin\Http\Requests\Mobile\MobileMaterialRequest;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Content\Models\Mobile\MobileCategory;
use App\Domain\Content\Models\Mobile\MobileChangeRequest;
use App\Domain\Content\Models\Mobile\MobileMaterial;
use App\Domain\Content\Models\Mobile\MobileSubmission;
use App\Domain\Content\Models\Mobile\MobileVote;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * OLD Admin Migration Phase 7 — Mobile Downloads staff administration and
 * moderation (قسم الموبايل), replacing OLD `modules/Mobile/admin`, whose
 * entry page no longer runs on PHP 7+. Thin: every mutation is an action
 * that re-checks `mobile.managedownloads` and audits.
 */
class MobileAdminController
{
    public function dashboard(): View
    {
        return view('admin.mobile.dashboard', [
            'materials' => MobileMaterial::query()->count(),
            'categories' => MobileCategory::query()->count(),
            'submissions' => MobileSubmission::query()->count(),
            'broken' => MobileChangeRequest::query()->where('brokendownload', MobileChangeRequest::BROKEN)->distinct()->count('lid'),
            'corrections' => MobileChangeRequest::query()->where('brokendownload', MobileChangeRequest::CORRECTION)->count(),
        ]);
    }

    // ---- categories ----

    public function categories(): View
    {
        return view('admin.mobile.categories', [
            'tree' => MobileCategory::query()->where('parentid', 0)->orderBy('title')->get()
                ->each(fn ($c) => $c->setRelation('children', $c->children()->withCount('materials')->get()))
                ->loadCount('materials'),
            'all' => $this->categoryOptions(),
        ]);
    }

    public function storeCategory(MobileCategoryRequest $request, MobileCategoryAdmin $action): RedirectResponse
    {
        return $this->run(route('admin.mobile.categories'), fn () => [
            'success', 'تمت إضافة التصنيف #'.$action->create($this->actor(), (string) $request->validated('title'), (string) $request->validated('cdescription', ''), (int) $request->validated('parentid', 0), $request->ip()),
        ]);
    }

    public function editCategory(MobileCategory $category): View
    {
        return view('admin.mobile.category-edit', ['category' => $category, 'path' => $category->path()]);
    }

    public function updateCategory(MobileCategoryRequest $request, MobileCategory $category, MobileCategoryAdmin $action): RedirectResponse
    {
        $changed = $action->update($this->actor(), $category, (string) $request->validated('title'), (string) $request->validated('cdescription', ''), $request->ip());

        return redirect()->route('admin.mobile.categories')->with($changed ? 'success' : 'error', $changed ? 'تم حفظ التصنيف' : 'لم يتغير شيء');
    }

    public function destroyCategory(Request $request, MobileCategory $category, MobileCategoryAdmin $action): RedirectResponse
    {
        $action->delete($this->actor(), $category, $request->ip());

        return redirect()->route('admin.mobile.categories')->with('success', 'تم حذف التصنيف وما فيه من مواد');
    }

    public function transfer(Request $request, MobileCategoryAdmin $action): RedirectResponse
    {
        $data = $request->validate([
            'from' => ['required', 'integer', 'exists:main.nuke_downloads_categories,cid'],
            'to' => ['required', 'integer', 'exists:main.nuke_downloads_categories,cid', 'different:from'],
        ]);
        $moved = $action->transfer($this->actor(), MobileCategory::query()->findOrFail($data['from']), MobileCategory::query()->findOrFail($data['to']), $request->ip());

        return redirect()->route('admin.mobile.categories')->with($moved > 0 ? 'success' : 'error', $moved > 0 ? "تم نقل {$moved} مادة" : 'لا توجد مواد للنقل');
    }

    // ---- materials ----

    public function materials(Request $request): View
    {
        $cid = (int) $request->query('cid', 0);
        $q = trim((string) $request->query('q', ''));

        return view('admin.mobile.materials', [
            'materials' => MobileMaterial::query()
                ->when($cid > 0, fn ($query) => $query->where('cid', $cid))
                ->when($q !== '', fn ($query) => ctype_digit($q) ? $query->whereKey((int) $q) : $query->where('title', 'like', '%'.addcslashes($q, '%_\\').'%'))
                ->orderByDesc('lid')->paginate(50)->withQueryString(),
            'categories' => $this->categoryOptions(),
            'cid' => $cid,
            'q' => $q,
        ]);
    }

    public function createMaterial(): View
    {
        return view('admin.mobile.material-form', ['material' => null, 'categories' => $this->categoryOptions()]);
    }

    public function storeMaterial(MobileMaterialRequest $request, MobileMaterialAdmin $action): RedirectResponse
    {
        try {
            $lid = $action->create($this->actor(), $request->fields(), $request->ip());
        } catch (MobileRefused $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.mobile.materials.edit', $lid)->with('success', "تمت إضافة المادة #{$lid}");
    }

    public function editMaterial(MobileMaterial $material): View
    {
        return view('admin.mobile.material-form', [
            'material' => $material,
            'categories' => $this->categoryOptions(),
            'editorial' => $material->editorial,
            'votes' => MobileVote::query()->where('ratinglid', $material->lid)->orderByDesc('ratingtimestamp')->orderByDesc('ratingdbid')->get(),
        ]);
    }

    public function updateMaterial(MobileMaterialRequest $request, MobileMaterial $material, MobileMaterialAdmin $action): RedirectResponse
    {
        $changed = $action->update($this->actor(), $material, $request->fields() + ['hits' => $request->hits() ?? (int) $material->hits], $request->ip());

        return redirect()->route('admin.mobile.materials.edit', $material)->with($changed ? 'success' : 'error', $changed ? 'تم حفظ المادة' : 'لم يتغير شيء');
    }

    public function destroyMaterial(Request $request, MobileMaterial $material, MobileMaterialAdmin $action): RedirectResponse
    {
        $id = (int) $material->lid;
        $action->delete($this->actor(), $material, $request->ip());

        return redirect()->route('admin.mobile.materials')->with('success', "تم حذف المادة #{$id}");
    }

    public function saveEditorial(MobileEditorialRequest $request, MobileMaterial $material, MobileEditorialAdmin $action): RedirectResponse
    {
        $changed = $action->save($this->actor(), $material, (string) $request->validated('editorialtitle'), (string) $request->validated('editorialtext'), $request->ip());

        return redirect()->route('admin.mobile.materials.edit', $material)->with($changed ? 'success' : 'error', $changed ? 'تم حفظ رأي المحررين' : 'لم يتغير شيء');
    }

    public function destroyEditorial(Request $request, MobileMaterial $material, MobileEditorialAdmin $action): RedirectResponse
    {
        $deleted = $action->delete($this->actor(), $material, $request->ip());

        return redirect()->route('admin.mobile.materials.edit', $material)->with($deleted ? 'success' : 'error', $deleted ? 'تم حذف رأي المحررين' : 'لا يوجد رأي للمحررين');
    }

    // ---- votes / comments ----

    public function destroyVote(Request $request, MobileVote $vote, MobileVoteModeration $action): RedirectResponse
    {
        $lid = (int) $vote->ratinglid;
        $action->deleteVote($this->actor(), $vote, $request->ip());

        return $this->backToMaterial($lid, 'تم حذف التقييم وإعادة حساب المادة');
    }

    public function destroyComment(Request $request, MobileVote $vote, MobileVoteModeration $action): RedirectResponse
    {
        $lid = (int) $vote->ratinglid;
        $changed = $action->deleteComment($this->actor(), $vote, $request->ip());

        return $this->backToMaterial($lid, $changed ? 'تم حذف التعليق' : 'لا يوجد تعليق');
    }

    public function recalculate(Request $request, MobileVoteModeration $action): RedirectResponse
    {
        $changed = $action->recalculate($this->actor(), $request->ip());

        return redirect()->route('admin.mobile.dashboard')->with('success', "تمت إعادة حساب التقييمات ({$changed} مادة تغيرت)");
    }

    // ---- submissions ----

    public function submissions(): View
    {
        return view('admin.mobile.submissions', [
            'submissions' => MobileSubmission::query()->orderBy('lid')->get(),
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function approveSubmission(MobileMaterialRequest $request, MobileSubmission $submission, MobileMaterialAdmin $action): RedirectResponse
    {
        return $this->run(route('admin.mobile.submissions'), fn () => [
            'success', 'تمت الموافقة ونشر المادة #'.$action->approve($this->actor(), $submission, $request->fields(), $request->ip()),
        ]);
    }

    public function rejectSubmission(Request $request, MobileSubmission $submission, MobileMaterialAdmin $action): RedirectResponse
    {
        $action->reject($this->actor(), $submission, $request->ip());

        return redirect()->route('admin.mobile.submissions')->with('success', 'تم رفض المادة المقترحة');
    }

    // ---- requests ----

    public function brokenReports(): View
    {
        $reports = MobileChangeRequest::query()->where('brokendownload', MobileChangeRequest::BROKEN)->orderBy('requestid')->get()->groupBy('lid');

        return view('admin.mobile.broken', [
            'reports' => $reports,
            'materials' => MobileMaterial::query()->whereKey($reports->keys())->get()->keyBy('lid'),
        ]);
    }

    public function destroyReportedMaterial(Request $request, int $lid, MobileRequestModeration $action): RedirectResponse
    {
        $action->deleteReportedMaterial($this->actor(), $lid, $request->ip());

        return redirect()->route('admin.mobile.broken')->with('success', "تم حذف المادة #{$lid} وطلباتها");
    }

    public function ignoreBroken(Request $request, int $lid, MobileRequestModeration $action): RedirectResponse
    {
        $count = $action->ignoreBrokenReports($this->actor(), $lid, $request->ip());

        return redirect()->route('admin.mobile.broken')->with($count > 0 ? 'success' : 'error', $count > 0 ? "تم تجاهل {$count} بلاغ" : 'لا توجد بلاغات');
    }

    public function corrections(): View
    {
        $requests = MobileChangeRequest::query()->where('brokendownload', MobileChangeRequest::CORRECTION)->orderBy('requestid')->get();

        return view('admin.mobile.corrections', [
            'requests' => $requests,
            'materials' => MobileMaterial::query()->whereKey($requests->pluck('lid'))->get()->keyBy('lid'),
            'categories' => MobileCategory::query()->get()->keyBy('cid'),
        ]);
    }

    public function acceptCorrection(Request $request, MobileChangeRequest $changeRequest, MobileRequestModeration $action): RedirectResponse
    {
        $action->acceptCorrection($this->actor(), $changeRequest, $request->ip());

        return redirect()->route('admin.mobile.corrections')->with('success', 'تم تطبيق التعديل');
    }

    public function ignoreCorrection(Request $request, MobileChangeRequest $changeRequest, MobileRequestModeration $action): RedirectResponse
    {
        $action->ignoreCorrection($this->actor(), $changeRequest, $request->ip());

        return redirect()->route('admin.mobile.corrections')->with('success', 'تم تجاهل الطلب');
    }

    /** @param Closure(): array{0: string, 1: string} $run */
    private function run(string $to, Closure $run): RedirectResponse
    {
        try {
            [$level, $message] = $run();
        } catch (MobileRefused $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect($to)->with($level, $message);
    }

    private function backToMaterial(int $lid, string $message): RedirectResponse
    {
        return MobileMaterial::query()->whereKey($lid)->exists()
            ? redirect()->route('admin.mobile.materials.edit', $lid)->with('success', $message)
            : redirect()->route('admin.mobile.materials')->with('success', $message);
    }

    /** @return list<array{cid: int, label: string}> */
    private function categoryOptions(): array
    {
        return MobileCategory::query()->orderBy('parentid')->orderBy('title')->get()
            ->map(fn (MobileCategory $c) => ['cid' => (int) $c->cid, 'label' => $c->path()])->all();
    }

    private function actor(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }
}
