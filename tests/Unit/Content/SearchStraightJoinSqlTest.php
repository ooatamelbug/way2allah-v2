<?php

use App\Domain\Content\Services\ContentListingService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;
use Tests\TestCase;

// Same convention as tests/Unit/Content/MediaUrlTest.php — tests/Pest.php
// only extends TestCase in Feature, and these guards need the container
// (config + the DB manager). They still make no HTTP request and never
// open a database connection for the MySQL half.
uses(TestCase::class);

/**
 * C-1 SQL-shape guards for `ContentListingService`'s results-pass-only
 * `STRAIGHT_JOIN` hint. Every test here is deterministic and server-free —
 * none is skipped on a machine without MySQL.
 *
 * Two connection setups, each for a reason that the other cannot cover:
 *
 *  - The **MySQL** half never connects. `->toSql()` compiles through
 *    `MySqlGrammar` without touching PDO, so pointing `main` at an
 *    unroutable address is enough to assert production's real SQL text.
 *    This is what proves the hint is present in the results pass and
 *    absent from the count clone.
 *  - The **SQLite** half really executes, which is the only way to observe
 *    what Laravel's own `paginate()` emits for the COUNT pass. That is the
 *    framework assumption this whole design rests on: the count clone drops
 *    `columns`. Under SQLite the hint is correctly absent (driver gate), so
 *    this half proves the *mechanism*, not the hint.
 *
 * Together they are decisive: Laravel drops the select list from the COUNT,
 * and the hint lives only in the select list.
 */
function mysqlGrammarMainConnection(): void
{
    // 203.0.113.0/24 is RFC 5737 TEST-NET-3, guaranteed unroutable — and
    // never contacted anyway, because nothing in these tests executes.
    Config::set('database.connections.main', [
        'driver' => 'mysql',
        'host' => '203.0.113.1',
        'port' => '3306',
        'database' => 'unused',
        'username' => 'unused',
        'password' => 'unused',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
    ]);

    DB::purge('main');
}

/** Exposes the one protected builder method these guards assert against. */
function searchQueryProbe(): object
{
    return new class extends ContentListingService
    {
        public function probe(bool $straightJoinResults): Builder
        {
            return $this->khotabAdvancedSearchQuery(['title' => 'x'], 'tb1.weight', true, $straightJoinResults);
        }
    };
}

function sqliteMainConnectionForSqlGuards(): void
{
    InMemoryConnection::setup('main', [
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_sat_channels' => MainSchema::nukeSatChannels(),
    ]);
}

it('results pass: STRAIGHT_JOIN is a SELECT modifier, with all nine columns and the INNER JOIN unchanged', function () {
    mysqlGrammarMainConnection();

    $sql = searchQueryProbe()->probe(true)->toSql();

    expect($sql)->toStartWith(
        'select STRAIGHT_JOIN `tb1`.`id`, `tb1`.`title`, `tb1`.`author`, `tb1`.`hits`, '
        .'`tb1`.`time`, `tb1`.`weight`, `tb1`.`channel_id`, `tb2`.`name`, `tb2`.`prename` '
        .'from `nuke_islamic_khotab` as `tb1` '
        .'inner join `nuke_islamic_authors` as `tb2` on `tb1`.`author` = `tb2`.`id`'
    )->and($sql)->toContain('order by `tb1`.`weight` desc');
});

it('COUNT isolation: the pagination count clone drops the hint, because the hint lives in the select list', function () {
    mysqlGrammarMainConnection();

    // Exactly Builder::runPaginationCountQuery()'s own clone operations.
    $count = searchQueryProbe()->probe(true)
        ->cloneWithout(['columns', 'orders', 'limit', 'offset'])
        ->cloneWithoutBindings(['select', 'order']);

    expect(strtoupper($count->toSql()))->not->toContain('STRAIGHT_JOIN');

    // ...while everything the COUNT genuinely needs is still there.
    expect($count->toSql())
        ->toContain('inner join `nuke_islamic_authors` as `tb2` on `tb1`.`author` = `tb2`.`id`')
        ->toContain('where `tb1`.`vedio` = ?')
        ->toContain('`tb1`.`hidden` = ?')
        ->toContain('`tb1`.`title` like ?');
});

it('COUNT isolation: Laravel\'s native straightJoin() WOULD leak into the count clone — the reason it is not used', function () {
    mysqlGrammarMainConnection();

    $leaky = DB::connection('main')->table('nuke_islamic_khotab as tb1')
        ->straightJoin('nuke_islamic_authors as tb2', 'tb1.author', '=', 'tb2.id')
        ->where('tb1.vedio', '1')
        ->select(['tb1.id'])
        ->cloneWithout(['columns', 'orders', 'limit', 'offset'])
        ->cloneWithoutBindings(['select', 'order']);

    // `joins` is NOT in the count clone's $without list, so this survives.
    expect($leaky->toSql())->toContain('straight_join `nuke_islamic_authors` as `tb2`');
});

it('opt-in only: the hint is omitted when the caller does not request it (KhotabSearchController\'s GET default)', function () {
    mysqlGrammarMainConnection();

    $sql = searchQueryProbe()->probe(false)->toSql();

    expect(strtoupper($sql))->not->toContain('STRAIGHT_JOIN');
    expect($sql)->toStartWith('select `tb1`.`id`, `tb1`.`title`,');
});

it('driver gate: the hint is omitted on SQLite even when the caller requests it', function () {
    sqliteMainConnectionForSqlGuards();

    expect(strtoupper(searchQueryProbe()->probe(true)->toSql()))->not->toContain('STRAIGHT_JOIN');
});

it('framework assumption: paginate() emits a COUNT with no select list and no ORDER BY, then a separate LIMIT 20 results query', function () {
    sqliteMainConnectionForSqlGuards();

    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh', 'prename' => 'Dr.']);
    $db->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'title' => 'x marks it', 'author' => 1, 'vedio' => 1, 'hidden' => 0, 'weight' => 1,
    ]);

    $captured = [];
    $db->listen(function ($query) use (&$captured) {
        $captured[] = $query->sql;
    });

    app(ContentListingService::class)->khotabAdvancedSearch(['title' => 'x'], 'tb1.weight', true, true);

    expect($captured)->toHaveCount(2);

    // Count pass: aggregate only — the select list did not survive the clone.
    expect($captured[0])->toContain('count(*)')
        ->and($captured[0])->not->toContain('prename')
        ->and($captured[0])->not->toContain('order by')
        ->and($captured[0])->not->toContain('limit');

    // Results pass: the select list and the ordering are both intact.
    expect($captured[1])->toContain('prename')
        ->and($captured[1])->toContain('order by')
        ->and($captured[1])->toContain('limit 20');
});
