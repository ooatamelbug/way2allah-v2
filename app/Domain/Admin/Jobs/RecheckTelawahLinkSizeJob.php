<?php

namespace App\Domain\Admin\Jobs;

use App\Domain\Admin\Support\LinkSizeChecker;
use App\Domain\Content\Models\TelawahItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * `telawah/stats.php`'s `getsizeid` branch — re-checks a telawah item's
 * live link size and updates `checktime`/`online`. Legacy ran this via
 * `get_headers()` synchronously inside the admin request; moved to a
 * queued job per Blueprint §14's standing instruction for this exact
 * pattern. Unlike `stats.php`/`stats_khotab.php`, `telawah/stats.php`
 * does **not** downgrade `https:` to `http:` before checking — reproduced
 * here as a literal `false`, not silently harmonized with the other two.
 *
 * **G-10-02 fix (Phase 1 audit):** `telawah/stats.php:70-83`'s `getsizeid`
 * branch has no `percent` reference at all — unlike `stats.php`'s/
 * `stats_khotab.php`'s equivalent branches, it never conditionally sets
 * `percent=100` on an exact `online`/`linksize` match. This job previously
 * carried that conditional anyway (evidently copied from the mirror/khotab
 * jobs' shared shape without checking this one real difference) — removed.
 * `percent` is only ever touched here by the page-load-time full-table
 * recompute (`RecomputeLinkQualityPercentAction`) or by `fixSizeTelawah()`,
 * matching legacy exactly.
 */
class RecheckTelawahLinkSizeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $id)
    {
    }

    public function handle(LinkSizeChecker $checker): void
    {
        $telawahItem = TelawahItem::on('main')->find($this->id);

        if ($telawahItem === null || empty($telawahItem->link)) {
            return;
        }

        $telawahItem->checktime = time();
        $telawahItem->online = $checker->check($telawahItem->link, downgradeHttpsToHttp: false);
        $telawahItem->save();
    }
}
