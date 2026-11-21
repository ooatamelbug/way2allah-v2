<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Admin\Actions\Ads\DeleteBadAdReportAction;
use App\Domain\Admin\Models\AdminUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * F01 / B6b — OLD «الإبلاغ عن اعلانات غير لائقة» (`old-project/modules/report_ads/admin`, ops `w2a_bad_ads_main`,
 * `w2a_bad_ads_delads`): list `nuke_report_for_bad_ads ORDER BY id DESC` and delete a report. Read and delete only —
 * OLD supported nothing else. Permission `report_ads.managereports`; `ads.manageads` grants nothing here.
 *
 * No public code creates reports (OLD's `show_report_for_bad_ads_dialog()` JS has no dialog element or caller in
 * any template, and nothing INSERTs into the table), so the list is expected to be empty. No submission endpoint
 * is added.
 */
class BadAdReportsAdminController
{
    public function index(): View
    {
        return view('admin.report-ads.index', [
            'reports' => DB::connection('main')->table('nuke_report_for_bad_ads')->orderByDesc('id')
                ->paginate(50, ['id', 'user_email', 'ads_url', 'db_creation_date']),
        ]);
    }

    public function destroy(Request $request, int $report, DeleteBadAdReportAction $action): RedirectResponse
    {
        $action->execute($this->actor(), $report, $request->ip());

        return redirect()->route('admin.report_ads.index')->with('success', 'تم حذف الإبلاغ بنجاح');
    }

    private function actor(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }
}
