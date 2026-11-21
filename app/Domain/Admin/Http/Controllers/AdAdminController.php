<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Actions\Ads\CreateAdAction;
use App\Domain\Admin\Actions\Ads\DeleteAdAction;
use App\Domain\Admin\Actions\Ads\ToggleAdAction;
use App\Domain\Admin\Actions\Ads\UpdateAdAction;
use App\Domain\Admin\Http\Requests\Ads\AdRequest;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdsReport;
use App\Domain\Admin\Support\LegacyAds;
use App\Domain\Content\Models\HomeAd;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * F01 / B6a — OLD advertising administration (`old-project/modules/ads/admin`, ops `w2a_ads_main`,
 * `w2a_ads_newads`, `w2a_ads_editads`, `w2a_ads_delads`, `update_show`, `w2a_ads_reports`) on the existing
 * `nuke_ads` table. OLD gated the whole module with one check (radminsuper, or the admin's name in
 * `nuke_modules.admins` for `ads`): here one permission, `ads.manageads`, plus super-admin for code ads.
 *
 * Administration only: no public rendering, click or view counting exists (B6c, not implemented).
 * Separate from Advfiles («ملفات الرئيسية», `khotab.addadv`).
 */
class AdAdminController
{
    /** OLD `w2a_ads_main()`: filters name / dates / show / type / position, `ORDER BY show DESC, id DESC`. */
    public function index(Request $request): View
    {
        $filters = [
            'name' => trim((string) $request->query('name', '')),
            'from' => self::date($request->query('from')),
            'to' => self::date($request->query('to')),
            'show' => self::choice($request->query('show'), [0, 1], 2),
            'type' => self::choice($request->query('type'), [0, 1], 2),
            'position' => self::choice($request->query('position'), array_keys(LegacyAds::POSITIONS), -1),
        ];

        $query = HomeAd::query();
        if ($filters['from'] !== null && $filters['to'] !== null) {
            $query->where(fn ($q) => $q->whereBetween('startdate', [$filters['from'], $filters['to']])
                ->orWhereBetween('enddate', [$filters['from'], $filters['to']]));
        }
        if ($filters['name'] !== '') {
            $query->where('name', 'like', '%'.addcslashes($filters['name'], '%_\\').'%');
        }
        if ($filters['show'] !== 2) {
            $query->where('show', $filters['show']);
        }
        if ($filters['type'] !== 2) {
            $query->where('type', $filters['type']);
        }
        if ($filters['position'] !== -1) {
            $query->where('position', $filters['position']);
        }

        return view('admin.ads.index', [
            'ads' => $query->orderByDesc('show')->orderByDesc('id')->paginate(50)->withQueryString(),
            'filters' => $filters,
            'isSuperAdmin' => $this->actor()->hasRole('super-admin'),
        ]);
    }

    public function create(): View
    {
        return view('admin.ads.create', ['ad' => null, 'isSuperAdmin' => $this->actor()->hasRole('super-admin')]);
    }

    public function store(AdRequest $request, CreateAdAction $action): RedirectResponse
    {
        $id = $action->execute($this->actor(), $request->validated(), $request->file('image_file'), $request->ip());

        return redirect()->route('admin.ads.index')->with('success', "تم إضافة الإعلان بنجاح (#{$id})");
    }

    public function edit(HomeAd $ad): View
    {
        $actor = $this->actor();
        if ((int) $ad->type === LegacyAds::TYPE_CODE && ! $actor->hasRole('super-admin')) {
            throw new AuthorizationException('Raw HTML/JavaScript advertisements are super-admin only.');
        }

        return view('admin.ads.edit', ['ad' => $ad, 'isSuperAdmin' => $actor->hasRole('super-admin')]);
    }

    public function update(AdRequest $request, HomeAd $ad, UpdateAdAction $action): RedirectResponse
    {
        $changed = $action->execute($this->actor(), $ad, $request->validated(), $request->file('image_file'), $request->ip());

        return redirect()->route('admin.ads.edit', $ad)
            ->with($changed ? 'success' : 'error', $changed ? 'تم تعديل بيانات الإعلان بنجاح' : 'لم يتغير شيء');
    }

    public function destroy(Request $request, HomeAd $ad, DeleteAdAction $action): RedirectResponse
    {
        $id = (int) $ad->getKey();
        $action->execute($this->actor(), $ad, $request->ip());

        return redirect()->route('admin.ads.index')->with('success', "تم حذف الإعلان #{$id} بنجاح");
    }

    public function toggle(Request $request, HomeAd $ad, ToggleAdAction $action): RedirectResponse
    {
        $validated = $request->validate(['show' => ['required', 'in:0,1']]);
        $changed = $action->execute($this->actor(), $ad, (int) $validated['show'] === 1, $request->ip());

        return redirect()->back(fallback: route('admin.ads.index'))
            ->with($changed ? 'success' : 'error', $changed ? 'تم تغيير حالة عرض الإعلان' : 'لم يتغير شيء');
    }

    /** OLD `view_report()`: type (all / image / code), from, to (required), one position. */
    public function reports(Request $request): View
    {
        $report = null;
        $input = [
            'type' => self::choice($request->query('type'), [0, 1], 2),
            'from' => self::date($request->query('from')),
            'to' => self::date($request->query('to')),
            'position' => self::choice($request->query('position'), array_keys(LegacyAds::POSITIONS), 1),
        ];

        if ($request->has('search')) {
            $request->validate([
                'from' => ['required', 'date_format:Y-m-d'],
                'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
                'position' => ['required', 'integer', 'between:1,12'],
                'type' => ['nullable', 'in:0,1,2'],
            ], [
                'from.required' => 'من فضلك حدد بداية المدة',
                'to.required' => 'من فضلك حدد نهاية المدة',
                'to.after_or_equal' => 'نهاية المدة يجب أن تكون بعد بدايتها',
            ]);

            $report = AdsReport::build((int) $input['position'], (string) $input['from'], (string) $input['to'],
                $input['type'] === 2 ? null : $input['type']);
        }

        return view('admin.ads.reports', ['input' => $input, 'report' => $report, 'isSuperAdmin' => $this->actor()->hasRole('super-admin')]);
    }

    private function actor(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }

    private static function date(mixed $value): ?string
    {
        $value = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }

    /** @param list<int> $allowed */
    private static function choice(mixed $value, array $allowed, int $default): int
    {
        return is_numeric($value) && in_array((int) $value, $allowed, true) ? (int) $value : $default;
    }
}
