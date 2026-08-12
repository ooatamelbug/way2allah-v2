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
 * live link size and updates `checktime`/`online`/`percent`. Legacy ran
 * this via `get_headers()` synchronously inside the admin request; moved
 * to a queued job per Blueprint §14's standing instruction for this exact
 * pattern. Unlike `stats.php`/`stats_khotab.php`, `telawah/stats.php`
 * does **not** downgrade `https:` to `http:` before checking — reproduced
 * here as a literal `false`, not silently harmonized with the other two.
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

        $online = $checker->check($telawahItem->link, downgradeHttpsToHttp: false);

        $telawahItem->checktime = time();
        $telawahItem->online = $online;

        if ($online === (int) $telawahItem->linksize) {
            $telawahItem->percent = 100;
        }

        $telawahItem->save();
    }
}
