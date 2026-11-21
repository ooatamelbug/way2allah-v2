<?php

/*
 * Phase 1 — the versioned telemetry event schema (v1): validation, encoding, decoding and classification.
 */

use App\Support\Downloads\Telemetry\InvalidTelemetryEvent;
use App\Support\Downloads\Telemetry\TelemetryEvent;

function tevBase(array $extra = []): array
{
    return $extra + ['t' => 1762790400.1234, 'ev' => 'capacity_rejected', 'mod' => 'khotab'];
}

it('encodes a valid event as one line that starts with the version and round-trips', function () {
    $line = TelemetryEvent::fromArray(tevBase(['rid' => 7, 'var' => 'mirror', 'ip' => '203.0.113.9', 'head' => false]))->encode();

    expect($line)->toStartWith('{"v":1,"t":1762790400.123,"ev":"capacity_rejected"')
        ->and($line)->not->toContain("\n");

    $decoded = TelemetryEvent::decode($line);
    expect($decoded['status'])->toBe('ok')
        ->and($decoded['event']->toArray())->toBe(['v' => 1, 't' => 1762790400.123, 'ev' => 'capacity_rejected', 'mod' => 'khotab', 'rid' => 7, 'var' => 'mirror', 'head' => false, 'ip' => '203.0.113.9']);
});

it('rejects unknown fields, missing required fields, unknown types and wrong value types', function (array $data) {
    expect(fn () => TelemetryEvent::fromArray($data))->toThrow(InvalidTelemetryEvent::class);
})->with([
    'unknown field' => [tevBase(['cookie' => 'x'])],
    'integer key' => [tevBase([0 => 'x'])],
    'missing t' => [['ev' => 'capacity_rejected', 'mod' => 'khotab']],
    'missing mod' => [['t' => 1.0, 'ev' => 'capacity_rejected']],
    'unknown event type' => [tevBase(['ev' => 'download_completed_maybe'])],
    'negative id' => [tevBase(['rid' => -1])],
    'string id' => [tevBase(['rid' => '7'])],
    'bool as int' => [tevBase(['head' => 1])],
    'negative time' => [tevBase(['t' => -5])],
    'non-string ip' => [tevBase(['ip' => 3232235777])],
]);

it('cuts free text to its byte limit at a UTF-8 boundary and replaces invalid UTF-8', function () {
    $event = TelemetryEvent::fromArray(tevBase(['ua' => str_repeat('ب', 300), 'sh' => "bad\xC3host"]));
    $array = $event->toArray();

    expect(strlen($array['ua']))->toBeLessThanOrEqual(256)
        ->and(mb_check_encoding($array['ua'], 'UTF-8'))->toBeTrue();

    $decoded = json_decode($event->encode(), true);
    expect(mb_check_encoding($decoded['sh'], 'UTF-8'))->toBeTrue();
});

it('never produces a line longer than MAX_LINE_BYTES', function () {
    // Every field at its maximum still fits; the limit is enforced regardless.
    $max = tevBase(['id' => str_repeat('a', 64), 'rq' => str_repeat('b', 64), 'rt' => str_repeat('c', 64), 'ua' => str_repeat('\\', 256),
        'sh' => str_repeat('"', 128), 'uah' => str_repeat('d', 32), 'uaf' => str_repeat('e', 32), 'st' => str_repeat('f', 32), 'k' => str_repeat('g', 32)]);

    expect(strlen(TelemetryEvent::fromArray($max)->encode()) + 1)->toBeLessThanOrEqual(TelemetryEvent::MAX_LINE_BYTES);
});

it('classifies malformed and unsupported lines without throwing', function (string $line, string $status) {
    expect(TelemetryEvent::decode($line)['status'])->toBe($status);
})->with([
    'torn fragment' => ['{"v":1,"t":17627904', 'malformed'],
    'not an object' => ['[1,2,3]', 'malformed'],
    'no version' => ['{"t":1,"ev":"capacity_rejected","mod":"k"}', 'malformed'],
    'string version' => ['{"v":"1","t":1,"ev":"capacity_rejected","mod":"k"}', 'malformed'],
    'v1 with unknown field' => ['{"v":1,"t":1,"ev":"capacity_rejected","mod":"k","x":1}', 'malformed'],
    'newer schema' => ['{"v":2,"t":1,"ev":"anything","mod":"k","new":true}', 'unsupported'],
    'valid' => ['{"v":1,"t":1,"ev":"capacity_rejected","mod":"k"}', 'ok'],
]);
