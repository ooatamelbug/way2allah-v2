<?php

use App\Domain\Content\Services\GeoIpLookup;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

beforeEach(function () {
    InMemoryConnection::setup('main', ['ips' => MainSchema::ips()]);
});

it('resolves a lowercased country code for an IP within a matching range', function () {
    DB::connection('main')->table('ips')->insert([
        'startip_num' => 100, 'endip_num' => 200, 'code' => 'EG', 'country' => 'Egypt',
    ]);

    // 0.0.0.150 -> long 150, inside (100, 200).
    expect((new GeoIpLookup)->codeForIp('0.0.0.150'))->toBe('eg');
});

it('returns an empty string when no range matches', function () {
    DB::connection('main')->table('ips')->insert([
        'startip_num' => 100, 'endip_num' => 200, 'code' => 'EG', 'country' => 'Egypt',
    ]);

    expect((new GeoIpLookup)->codeForIp('0.0.0.250'))->toBe('');
});

it('uses strict inequalities, matching the legacy SQL exactly (boundary values do not match)', function () {
    DB::connection('main')->table('ips')->insert([
        'startip_num' => 100, 'endip_num' => 200, 'code' => 'EG', 'country' => 'Egypt',
    ]);

    expect((new GeoIpLookup)->codeForIp('0.0.0.100'))->toBe('');
    expect((new GeoIpLookup)->codeForIp('0.0.0.200'))->toBe('');
});

it('returns an empty string for a non-IPv4 address instead of guessing', function () {
    expect((new GeoIpLookup)->codeForIp('::1'))->toBe('');
});
