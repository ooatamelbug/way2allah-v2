<?php

namespace Tests\Support;

use App\Support\Downloads\DownloadCapacityGuard;
use App\Support\Downloads\DownloadRejectionRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Fixtures\MainSchema;

/**
 * F04 (2026-11-17): the save routes now hand public links to the protected proxy. To assert WHICH link a route would
 * fetch — without contacting any real host — fill the global pool (one slot, held here), request the route, expect
 * the 503 refusal, and read the link the refusal recorded (`resolved_download_url`). Nothing is fetched and nothing
 * is counted. Successful transfers and counting are covered by SaveMaterialProxyF04Test against a local server.
 */
final class ProxiedDownloadProbe
{
    public static function resolvedLink(string $url): ?string
    {
        if (! Schema::connection('main')->hasTable(DownloadRejectionRecorder::TABLE)) {
            Schema::connection('main')->create(DownloadRejectionRecorder::TABLE, MainSchema::downloadRejections());
        }
        $dir = sys_get_temp_dir().'/download-probe-'.uniqid();
        config([
            'downloads.guard' => true,
            'downloads.max_concurrent' => 1,
            'downloads.retry_after' => 60,
            'downloads.rejection_log' => true,
            'downloads.slot_directory' => $dir,
        ]);
        $slot = app(DownloadCapacityGuard::class)->tryAcquire();

        try {
            test()->get($url)->assertStatus(503);

            return DB::connection('main')->table(DownloadRejectionRecorder::TABLE)->orderByDesc('id')->value('resolved_download_url');
        } finally {
            $slot?->release();
            foreach (glob($dir.'/*') ?: [] as $path) {
                @unlink($path);
            }
            @rmdir($dir);
        }
    }
}
