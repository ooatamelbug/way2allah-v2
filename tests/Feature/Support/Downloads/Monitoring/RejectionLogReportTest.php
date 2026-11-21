<?php

/* Phase 3C — the read-only download_rejections report: totals, bounds, daily growth, storage; never a write. */

use App\Support\Downloads\DownloadRejectionRecorder;
use App\Support\Downloads\Monitoring\RejectionLogReport;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

function rlrInsert(int $at, int $n = 1): void
{
    $rows = [];
    for ($i = 0; $i < $n; $i++) {
        $rows[] = ['created_at' => $at, 'reason' => 'capacity', 'pool' => 'global', 'module' => 'khotab', 'resource_type' => 'khotab_item', 'resource_id' => $i,
            'variant' => 'primary', 'requested_url' => '/k', 'file_size_source' => 'unknown', 'client_ip' => '192.0.2.1', 'active_downloads' => 400,
            'download_limit' => 400, 'retry_after_seconds' => 60];
    }
    DB::connection('main')->table(DownloadRejectionRecorder::TABLE)->insert($rows);
}

beforeEach(fn () => InMemoryConnection::setup('main', [DownloadRejectionRecorder::TABLE => MainSchema::downloadRejections()]));

it('reports totals, oldest/newest, a zero-filled daily series and projections', function () {
    $now = intdiv(time(), 86400) * 86400 + 43200;   // midday UTC
    rlrInsert($now - 100 * 86400, 5);   // outside the window, counts in the total
    rlrInsert($now - 2 * 86400, 30);
    rlrInsert($now - 3600, 12);

    $r = (new RejectionLogReport(DB::connection('main')))->collect($now, 7);

    expect($r)->toMatchArray(['total_rows' => 47, 'total_is_exact' => true, 'oldest' => gmdate('c', $now - 100 * 86400), 'newest' => gmdate('c', $now - 3600),
        'window_days' => 7, 'rows_in_window' => 42, 'rows_last_7_days' => 42, 'busiest_day' => gmdate('Y-m-d', $now - 2 * 86400),
        'projected_rows_per_year' => (int) round(42 / 7 * 365), 'storage_bytes' => null])
        ->and($r['per_day'])->toHaveCount(7)
        ->and(array_sum($r['per_day']))->toBe(42)
        ->and($r['per_day'][gmdate('Y-m-d', $now - 86400)])->toBe(0);   // zero-filled
});

it('an empty table is reported as empty, not as an error', function () {
    $r = (new RejectionLogReport(DB::connection('main')))->collect(time(), 30);

    expect($r)->toMatchArray(['total_rows' => 0, 'oldest' => null, 'newest' => null, 'rows_in_window' => 0, 'busiest_day' => null]);
});

it('is read-only: no write statement, row count unchanged; the command prints JSON', function () {
    rlrInsert(time() - 60, 3);
    $writes = [];
    DB::listen(function ($q) use (&$writes) {
        if (preg_match('/^\s*(insert|update|delete|replace|create|drop|alter|truncate)/i', $q->sql) === 1) {
            $writes[] = $q->sql;
        }
    });

    expect(Artisan::call('downloads:rejections-report', ['--json' => true, '--days' => 3]))->toBe(0);
    $json = json_decode(Artisan::output(), true);
    Artisan::call('downloads:rejections-report');

    expect($writes)->toBe([])
        ->and(DB::connection('main')->table(DownloadRejectionRecorder::TABLE)->count())->toBe(3)
        ->and($json['rows_last_7_days'])->toBe(3)->and($json['window_days'])->toBe(3);
});
