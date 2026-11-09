<?php

/*
 * D2 (owner-approved 2026-11-09): visitor comment IPs must fit the legacy `varchar(15)` columns
 * (`nuke_islamic_comments.ip`, `nuke_anasheed_comments.ip` — legacy-project/docs/migration/00-database-schema.md
 * :50, :353) because the `main` connection runs in strict mode, where a longer value fails the insert.
 *
 * SQLite does not enforce VARCHAR lengths, so each test table gets BEFORE INSERT / UPDATE triggers that raise
 * exactly when `length(ip) > 15` — modelling MySQL strict mode's "Data too long" instead of the fixtures'
 * effectively unlimited string column.
 */

use App\Domain\Content\Services\GeoIpLookup;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

const CIW_IPV6 = '2001:db8:85a3:0:0:8a2e:370:7334';      // 31 characters
const CIW_IPV6_MAPPED = '::ffff:203.0.113.42';           // 19 characters

const CIW_MODULES = [
    'khotab' => ['url' => '/khotab-item-1/comments', 'table' => 'nuke_islamic_comments'],
    'anasheed' => ['url' => '/var-item-1/comments', 'table' => 'nuke_anasheed_comments'],
];

/** Enforce the real legacy width (varchar(15), strict mode) on a comment table. */
function ciwEnforceLegacyWidth(string $table): void
{
    foreach (['INSERT', 'UPDATE'] as $event) {
        DB::connection('main')->statement(
            "CREATE TRIGGER {$table}_ip_width_".strtolower($event)." BEFORE {$event} ON {$table} ".
            "WHEN length(NEW.ip) > 15 BEGIN SELECT RAISE(ABORT, 'Data too long for column ip (varchar(15))'); END"
        );
    }
}

function ciwPost(object $test, string $module, string $ip, array $payload = ['user_nickname' => 'زائر', 'user_comment' => 'تعليق']): object
{
    return $test->withServerVariables(['REMOTE_ADDR' => $ip])->post(CIW_MODULES[$module]['url'], $payload);
}

function ciwStored(string $module): ?object
{
    return DB::connection('main')->table(CIW_MODULES[$module]['table'])->where('khid', 1)->first();
}

beforeEach(function () {
    InMemoryConnection::setup('main', [
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_islamic_comments' => MainSchema::nukeIslamicComments(),
        'nuke_anasheed_anasheed' => MainSchema::nukeAnasheedAnasheed(),
        'nuke_anasheed_comments' => MainSchema::nukeAnasheedComments(),
        'ips' => MainSchema::ips(),
    ]);
    foreach (CIW_MODULES as ['table' => $table]) {
        ciwEnforceLegacyWidth($table);
    }
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'title' => 'مادة', 'vedio' => 1]);
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert(['id' => 1, 'title' => 'نشيد']);
    DB::connection('main')->table('ips')->insert(['startip_num' => 0, 'endip_num' => 4294967295, 'code' => 'EG', 'country' => 'Egypt']);
});

it('the test tables really enforce the legacy varchar(15) width (the pre-fix failure mode)', function (string $module) {
    $table = CIW_MODULES[$module]['table'];

    expect(fn () => DB::connection('main')->table($table)->insert(['khid' => 1, 'uid' => 0, 'uname' => 'x', 'ip' => CIW_IPV6, 'mytime' => 1, 'comment' => 'c', 'view' => 0, 'code' => '']))
        ->toThrow(QueryException::class, 'Data too long for column ip');

    DB::connection('main')->table($table)->insert(['khid' => 1, 'uid' => 0, 'uname' => 'x', 'ip' => '255.255.255.255', 'mytime' => 1, 'comment' => 'c', 'view' => 0, 'code' => '']);
    expect(DB::connection('main')->table($table)->count())->toBe(1);
})->with(array_keys(CIW_MODULES));

it('IPv4: stores the full address and its country code, unchanged', function (string $module) {
    ciwPost($this, $module, '203.0.113.42')->assertOk()->assertSeeText('1');

    $row = ciwStored($module);
    expect($row->ip)->toBe('203.0.113.42')->and($row->code)->toBe('eg')->and((int) $row->view)->toBe(0)
        ->and($row->uname)->toBe('زائر')->and($row->comment)->toBe('تعليق');
})->with(array_keys(CIW_MODULES));

it('IPv4 at the full 15-character width is stored intact', function (string $module) {
    ciwPost($this, $module, '255.255.255.255')->assertOk()->assertSeeText('1');

    expect(ciwStored($module)->ip)->toBe('255.255.255.255');
})->with(array_keys(CIW_MODULES));

it('IPv6: the comment is saved (no insert failure); the IP is fitted to the legacy 15 characters', function (string $module, string $ip) {
    ciwPost($this, $module, $ip)->assertOk()->assertSeeText('1');

    $row = ciwStored($module);
    expect($row)->not->toBeNull()
        ->and($row->ip)->toBe(substr($ip, 0, 15))
        ->and(strlen($row->ip))->toBeLessThanOrEqual(15)
        // The country code is still GeoIpLookup's result for the FULL address (unchanged behaviour: '' for IPv6).
        ->and($row->code)->toBe(app(GeoIpLookup::class)->codeForIp($ip))
        ->and((int) $row->view)->toBe(0)
        ->and($row->comment)->toBe('تعليق');
})->with(array_keys(CIW_MODULES))->with([
    'IPv6' => [CIW_IPV6],
    'IPv4-mapped IPv6' => [CIW_IPV6_MAPPED],
]);

it('existing validation responses are unchanged for IPv6 visitors', function (string $module) {
    ciwPost($this, $module, CIW_IPV6, ['user_nickname' => '', 'user_comment' => 'تعليق'])->assertOk()->assertSeeText('2');
    ciwPost($this, $module, CIW_IPV6, ['user_nickname' => 'زائر', 'user_comment' => ''])->assertOk()->assertSeeText('3');

    expect(ciwStored($module))->toBeNull();
})->with(array_keys(CIW_MODULES));
