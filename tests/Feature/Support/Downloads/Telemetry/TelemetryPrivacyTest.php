<?php

/*
 * Phase 3C privacy review — telemetry-owned leaks fixed: rollup errors never carry an exception message (a
 * QueryException embeds the SQL WITH its bound values, e.g. IP addresses), and stream exception messages logged by the
 * streamer have every URL redacted.
 */

use App\Support\Downloads\ProxiedDownloadStreamer;
use App\Support\Downloads\Telemetry\Aggregation\RollupRunner;
use Illuminate\Database\QueryException;

it('describes a database error by class, code and SQLSTATE only — never the SQL or its bound values', function () {
    $pdo = new PDOException('SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'203.0.113.7-abc\' for key \'PRIMARY\'', 23000);
    $pdo->errorInfo = ['23000', 1062, 'Duplicate entry'];
    $e = new QueryException('main', 'insert into download_ip_ua_hourly (ip) values (?)', ['203.0.113.7'], $pdo);

    $text = RollupRunner::describe($e);

    expect($e->getMessage())->toContain('203.0.113.7')   // what used to be reported
        ->and($text)->toBe(QueryException::class.' (code 23000) SQLSTATE 23000')
        ->and(RollupRunner::describe(new RuntimeException('secret detail 198.51.100.1', 7)))->toBe('RuntimeException (code 7)');
});

it('redacts every URL inside a stream exception message', function () {
    $message = 'fread(): SSL: Connection reset by peer while reading https://ia800.us.archive.org/2/items/x/y.mp3?X-Amz-Signature=SECRET&token=SECRET2 (after http://user:pw@mirror.test/z.mp3?k=SECRET3)';

    $redacted = ProxiedDownloadStreamer::redactMessage($message);

    expect($redacted)->not->toContain('SECRET')->not->toContain('user:pw')
        ->toContain('https://ia800.us.archive.org/2/items/x/y.mp3?[redacted]')
        ->toContain('http://mirror.test/z.mp3?[redacted]');
});
