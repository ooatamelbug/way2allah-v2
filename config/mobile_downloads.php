<?php

/*
|--------------------------------------------------------------------------
| OLD Admin Migration Phase 7 — Mobile Downloads (قسم الموبايل)
|--------------------------------------------------------------------------
|
| Values recovered from OLD `modules/Mobile/d_config.php`, except
| `unavailable_hosts`, which records the production verification of
| 2026-10-14 (plan §26.6): `way2allah.knoz4arab.com` does not resolve and
| its media payload is not on any production host, so a download pointing
| there is reported as unavailable instead of redirecting to a dead host.
|
*/

return [
    'per_page' => 10,               // $perpage
    'search_per_page' => 10,        // $downloadsresults
    'max_page_size' => 100,         // upper bound for OLD's ?show= override
    'top_rated' => 25,              // $topdownloads
    'top_rated_min_votes' => 5,     // $downloadvotemin
    'most_popular' => 25,           // $mostpopdownloads
    'new_default_days' => 7,        // NewDownloads default
    'anonymous_wait_days' => 1,     // $anonwaitdays
    'anonymous_weight' => 10,       // $anonweight
    'outside_weight' => 20,         // $outsideweight
    'unavailable_hosts' => ['way2allah.knoz4arab.com'],
];
