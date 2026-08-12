<?php

namespace App\Domain\Admin\Jobs;

use App\Domain\Admin\Support\LinkSizeChecker;
use App\Domain\Content\Models\Mirror;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * `stats.php`'s `getsizeid` branch — re-checks a mirror's live link size
 * and updates `checktime`/`online`/`percent`. Legacy ran this via
 * `get_headers()` synchronously inside the admin request; moved to a
 * queued job per Blueprint §14's standing instruction for this exact
 * pattern. `stats.php` rewrites `https:` to `http:` before checking
 * (`str_replace('https:', 'http:', $row->link)`) — reproduced here as a
 * literal `true`, not a parameter, since this job only ever means one
 * module's behavior.
 */
class RecheckMirrorLinkSizeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $id)
    {
    }

    public function handle(LinkSizeChecker $checker): void
    {
        $mirror = Mirror::on('main')->find($this->id);

        if ($mirror === null || empty($mirror->link)) {
            return;
        }

        $online = $checker->check($mirror->link, downgradeHttpsToHttp: true);

        $mirror->checktime = time();
        $mirror->online = $online;

        if ($online === (int) $mirror->linksize) {
            $mirror->percent = 100;
        }

        $mirror->save();
    }
}
