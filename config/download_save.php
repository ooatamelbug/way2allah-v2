<?php

return [

    /*
    |--------------------------------------------------------------------------
    | F04 «حفظ المادة» — operated hosts that answer save links as attachments
    |--------------------------------------------------------------------------
    |
    | Comma-separated exact host names (e.g. "vars.way2allah.com") whose web
    | server answers `?w2a_save=1` with `Content-Disposition: attachment`
    | (rule and verification: docs/reports/f04-save-material-download-results.md
    | §3). EMPTY by default: list a host only AFTER its rule is deployed and
    | verified. Redirect-delivered downloads to any other host are unchanged
    | and are NOT guaranteed downloads (archive.org sends no attachment header).
    |
    | Kept separate from config/downloads.php on purpose: that file is the
    | capacity / worker-protection configuration and is left untouched.
    |
    */

    'attachment_query_hosts' => array_values(array_filter(array_map(
        static fn (string $host): string => rtrim(strtolower(trim($host)), '.'),
        explode(',', (string) env('DOWNLOAD_ATTACHMENT_QUERY_HOSTS', '')),
    ), static fn (string $host): bool => $host !== '')),

    /*
    | F04 proxy expansion (Variety, Telawah, Fatwa, lessons): never proxy a link
    | whose host is localhost or a loopback / private / reserved IP literal.
    | Security default ON; only the test suite turns it off (its fixture server
    | listens on 127.0.0.1). Not an operational setting.
    */
    'proxy_block_private_hosts' => (bool) env('DOWNLOAD_PROXY_BLOCK_PRIVATE_HOSTS', true),

];
