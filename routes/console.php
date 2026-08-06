<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduler — Blueprint v1.0 §12, Roadmap task 0.6
|--------------------------------------------------------------------------
|
| Intentionally empty for Wave 0. Populated starting Wave 4/5 with:
|  - the P-021 bulk-recompute pattern (several admincp/ stats pages) moved
|    to a scheduled job, never synchronous inside a request;
|  - the get_headers() live-link-checking pattern moved to a queued job,
|    triggered on demand rather than scheduled;
|  - the two legacy cron jobs (cleanup_query_logs.php, cleanup_query_cache.php)
|    are largely obsolete once Cache::remember() replaces SimpleCache/the
|    DB-backed fragment cache — confirm nothing still needs porting before
|    adding a replacement entry here.
|
| Example of the convention to follow once real jobs exist:
|   Schedule::job(new RecomputeStatsPercentagesJob)->daily();
|
*/
