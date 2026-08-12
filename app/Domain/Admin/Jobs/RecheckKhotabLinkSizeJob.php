<?php

namespace App\Domain\Admin\Jobs;

use App\Domain\Admin\Support\LinkSizeChecker;
use App\Domain\Content\Models\KhotabItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * `stats_khotab.php`'s `getsizeid` branch — re-checks a khotab item's live
 * link size and updates `checktime`/`online`/`percent`. Legacy ran this
 * via `get_headers()` synchronously inside the admin request; moved to a
 * queued job per Blueprint §14's standing instruction for this exact
 * pattern. `stats_khotab.php` rewrites `https:` to `http:` before
 * checking, same as `stats.php` — reproduced here as a literal `true`.
 */
class RecheckKhotabLinkSizeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $id)
    {
    }

    public function handle(LinkSizeChecker $checker): void
    {
        $khotabItem = KhotabItem::on('main')->find($this->id);

        if ($khotabItem === null || empty($khotabItem->link)) {
            return;
        }

        $online = $checker->check($khotabItem->link, downgradeHttpsToHttp: true);

        $khotabItem->checktime = time();
        $khotabItem->online = $online;

        if ($online === (int) $khotabItem->linksize) {
            $khotabItem->percent = 100;
        }

        $khotabItem->save();
    }
}
