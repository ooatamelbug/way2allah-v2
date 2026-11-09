<?php

namespace App\Domain\Admin\Http\Controllers;

use App\Domain\Content\Models\TelawahItem;
use Illuminate\View\View;

/**
 * OLD Admin Migration, contextual surface batch C5 — the Telawah counterpart
 * of Phase 2's Khotab item link-health page, reached from the Telawah item
 * page as OLD's admin-only `AdvancedStats()` block was
 * (`modules/Telawah/index.php:317`).
 *
 * Read-only: it shows the stored link-quality columns and offers the EXISTING
 * `LinkQualityStatsController` recheck / fix-size routes for this item,
 * unchanged. No Telawah CRUD and no Telawah mirror handling (deferred).
 */
class TelawahLinkReportController
{
    public function item(TelawahItem $telawahItem): View
    {
        return view('admin.link-reports.telawah-item', ['item' => $telawahItem]);
    }
}
