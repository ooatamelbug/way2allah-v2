<?php

/*
 * B2 / F03-A (owner-approved 2026-11-16, decision-log #145) — `khotab:repair-f03-series-counts`.
 *
 * Every test runs on the harness's isolated in-memory `main` database (never the shared local MySQL). The fixture
 * reproduces the 22 approved series with their approved identity and current counts, their 316 own items (some
 * hidden), one foreign item in 15625, and control rows that must never change.
 */

use App\Domain\Admin\Actions\Khotab\F03SeriesCountRepair;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\ContextualAdminHarness as H;

uses(RefreshDatabase::class);

const F03_CMD = 'khotab:repair-f03-series-counts';

beforeEach(function () {
    H::setUp();
    Schema::connection('main')->table('nuke_islamic_series', fn ($t) => $t->integer('old_id')->nullable());

    $main = DB::connection('main');
    foreach ([[1138, 154, 1], [232, 154, 1], [1298, 195, 1], [500, 154, 1]] as [$id, $author, $vedio]) {
        $main->table('nuke_islamic_groups')->insert(['id' => $id, 'author_id' => $author, 'title' => 'مجموعة '.$id, 'vedio' => $vedio, 'hidden' => 0, 'count' => 77]);
    }

    $items = [];
    foreach (F03SeriesCountRepair::BASELINE as $id => [$author, $group, $vedio, $oldId, $before, $after]) {
        $main->table('nuke_islamic_series')->insert([
            'id' => $id, 'author_id' => $author, 'group_id' => $group, 'vedio' => $vedio, 'hidden' => 0, 'old_id' => $oldId,
            'count' => $before, 'title' => 'سلسلة '.$id, 'lastupdate' => $id === 15627 ? 1747820473 : 0, 'time' => 1742500000 + $id,
        ]);
        for ($n = 0; $n < $after; $n++) {
            // Own items, the last one hidden when the series has more than one (hidden own items are counted).
            $items[] = ['author' => $author, 'ser_id' => $id, 'group_id' => $group, 'vedio' => $vedio,
                'hidden' => ($after > 1 && $n === $after - 1) ? 1 : 0, 'title' => "مادة {$id}-{$n}", 'time' => 1710000000 + $n];
        }
    }
    // A foreign item inside 15625 (a renumbering collision): never counted.
    $items[] = ['author' => 999, 'ser_id' => 15625, 'group_id' => 0, 'vedio' => 1, 'hidden' => 0, 'title' => 'مادة غريبة', 'time' => 1700000000];
    // Control rows: a foreign-only series at 0, a count-without-items copy, a healthy series, and one that looks
    // eligible (count 0, own visible items) but is not in the approved list.
    $main->table('nuke_islamic_series')->insert([
        ['id' => 15314, 'author_id' => 589, 'group_id' => 0, 'vedio' => 1, 'hidden' => 1, 'old_id' => 14514, 'count' => 0, 'title' => 'x', 'lastupdate' => 0, 'time' => 1],
        ['id' => 16087, 'author_id' => 3, 'group_id' => 0, 'vedio' => 1, 'hidden' => 0, 'old_id' => null, 'count' => 34, 'title' => 'y', 'lastupdate' => 5, 'time' => 2],
        ['id' => 17000, 'author_id' => 154, 'group_id' => 500, 'vedio' => 1, 'hidden' => 0, 'old_id' => null, 'count' => 2, 'title' => 'z', 'lastupdate' => 9, 'time' => 3],
        ['id' => 17001, 'author_id' => 154, 'group_id' => 500, 'vedio' => 1, 'hidden' => 0, 'old_id' => null, 'count' => 0, 'title' => 'w', 'lastupdate' => 0, 'time' => 4],
    ]);
    foreach ([[748, 15314], [748, 15314], [154, 17000], [154, 17000], [154, 17001]] as [$author, $ser]) {
        $items[] = ['author' => $author, 'ser_id' => $ser, 'group_id' => $ser >= 17000 ? 500 : 0, 'vedio' => 1, 'hidden' => 0, 'title' => 'c', 'time' => 1];
    }
    foreach (array_chunk($items, 100) as $chunk) {
        $main->table('nuke_islamic_khotab')->insert($chunk);
    }

    $this->dir = sys_get_temp_dir().'/f03a-'.bin2hex(random_bytes(5));
    $this->boss = H::superAdmin('ops-boss');
});

afterEach(function () {
    foreach (glob($this->dir.'/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($this->dir);
});

function f03Run(array $options = []): array
{
    $code = Artisan::call(F03_CMD, $options);

    return [$code, Artisan::output()];
}

function f03Apply(array $extra = []): array
{
    return f03Run(['--apply' => true, '--actor' => 'ops-boss', '--backup-dir' => test()->dir] + $extra);
}

function f03Counts(): array
{
    return DB::connection('main')->table('nuke_islamic_series')->orderBy('id')->pluck('count', 'id')->map(fn ($c) => (int) $c)->all();
}

function f03Snapshot(): array
{
    $main = DB::connection('main');

    return [
        'series' => $main->table('nuke_islamic_series')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
        'items' => $main->table('nuke_islamic_khotab')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
        'groups' => $main->table('nuke_islamic_groups')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
    ];
}

function f03Audit(): array
{
    return DB::connection('main')->table('admin_audit_log')->orderBy('id')->get()->all();
}

function f03Backups(): array
{
    return glob(test()->dir.'/*.json') ?: [];
}

function f03Before(): array
{
    return array_map(fn (array $b) => $b[4], F03SeriesCountRepair::BASELINE);
}

function f03After(): array
{
    return array_map(fn (array $b) => $b[5], F03SeriesCountRepair::BASELINE);
}

// --- Dry run --------------------------------------------------------------------------------------------------------

it('dry-runs by default: lists all 22 series with stored, approved and recomputed counts, and writes nothing', function () {
    $before = f03Snapshot();

    [$code, $out] = f03Run();

    expect($code)->toBe(0)
        ->and($out)->toContain('DRY RUN (no database write)')
        ->and($out)->toContain('Own items: 316 (expected 316). Verdict: ready');
    foreach (F03SeriesCountRepair::BASELINE as $id => $b) {
        expect($out)->toMatch('~\|\s*'.$id.'\s*\|\s*'.$b[0].'\s*\|\s*'.$b[1].'\s*\|\s*'.$b[4].'\s*\|\s*'.$b[4].'\s*\|\s*'.$b[5].'\s*\|~');
    }
    expect(f03Snapshot())->toEqual($before)->and(f03Audit())->toBe([])->and(is_dir($this->dir))->toBeFalse();
});

it('dry-run JSON: exactly the 22 approved ids, all eligible', function () {
    [, $out] = f03Run(['--json' => true]);
    $report = json_decode($out, true);

    expect(array_column($report['rows'], 'id'))->toBe(array_keys(F03SeriesCountRepair::BASELINE))
        ->and(array_unique(array_column($report['rows'], 'eligible')))->toBe([true])
        ->and($report['verdict'])->toBe('ready')
        ->and($report['total_own_items'])->toBe(316);
});

// --- Criterion -----------------------------------------------------------------------------------------------------

it('counts own items only (hidden included) and never other authors\' items', function () {
    $rows = collect(app(F03SeriesCountRepair::class)->plan()['rows'])->keyBy('id');

    expect($rows[15625]['recomputed'])->toBe(2)            // + 1 foreign item in the series, not counted
        ->and($rows[15346]['recomputed'])->toBe(25)        // 24 visible + 1 hidden own item
        ->and($rows[15346]['visible_own_items'])->toBe(24);
});

it('blocks a series whose own items are all hidden (no visible own item)', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->where('ser_id', 15884)->update(['hidden' => 1]);

    [$code, $out] = f03Apply();

    expect($code)->toBe(1)->and($out)->toContain('series 15884: no_visible_items')
        ->and(f03Counts())->toMatchArray(f03Before())->and(f03Backups())->toBe([]);
});

// --- Apply ---------------------------------------------------------------------------------------------------------

it('applies the controlled repair: only the 22 counts change, audited, with a verified backup', function () {
    $before = f03Snapshot();

    [$code, $out] = f03Apply();

    expect($code)->toBe(0, $out)->and($out)->toContain('Repaired 22 series. Backup: '.$this->dir);
    expect(f03Counts())->toMatchArray(f03After())
        ->and(f03Counts()[15314])->toBe(0)->and(f03Counts()[16087])->toBe(34)->and(f03Counts()[17000])->toBe(2)->and(f03Counts()[17001])->toBe(0);

    // Every other column of every series, every item and every group is unchanged (lastupdate, group_id, author_id,
    // hidden, old_id, items' ser_id / group_id / hidden …).
    $after = f03Snapshot();
    $strip = fn (array $rows) => array_map(fn (array $r) => array_diff_key($r, ['count' => 0]), $rows);
    expect($strip($after['series']))->toEqual($strip($before['series']))
        ->and($after['items'])->toEqual($before['items'])
        ->and($after['groups'])->toEqual($before['groups']);

    $audit = f03Audit();
    expect($audit)->toHaveCount(22)
        ->and(array_unique(array_column($audit, 'action')))->toBe(['repair_series_count'])
        ->and(array_map('intval', array_column($audit, 'entity_id')))->toBe(array_keys(F03SeriesCountRepair::BASELINE))
        ->and(array_unique(array_column($audit, 'actor_aid')))->toBe(['ops-boss']);

    $backups = f03Backups();
    expect($backups)->toHaveCount(1);
    $backup = F03SeriesCountRepair::readBackup($backups[0]);
    expect(array_column($backup['series'], 'count_before'))->toBe(array_values(f03Before()))
        ->and(array_column($backup['series'], 'count_after'))->toBe(array_values(f03After()))
        ->and(json_decode($audit[0]->metadata, true)['backup_sha256'])->toBe($backup['sha256'])
        ->and(substr(sprintf('%o', fileperms($backups[0])), -4))->toBe('0640');
});

it('is idempotent: a second apply reports that no repair is necessary and writes nothing', function () {
    f03Apply();
    $snapshot = f03Snapshot();
    $audit = count(f03Audit());

    [$code, $out] = f03Apply();

    expect($code)->toBe(0)->and($out)->toContain('No repair necessary')
        ->and(f03Snapshot())->toEqual($snapshot)->and(f03Audit())->toHaveCount($audit)->and(f03Backups())->toHaveCount(1);
});

it('rolls the whole transaction back when a write fails part-way (no count, no audit row)', function () {
    DB::connection('main')->statement("CREATE TRIGGER f03_fail BEFORE UPDATE ON nuke_islamic_series WHEN NEW.id = 15627 BEGIN SELECT RAISE(ABORT, 'simulated failure'); END");

    [$code, $out] = f03Apply();

    expect($code)->toBe(1)->and($out)->toContain('simulated failure')
        ->and(f03Counts())->toMatchArray(f03Before())       // the 11 rows updated before 15627 were rolled back
        ->and(f03Audit())->toBe([]);
});

// --- Refusals: nothing written --------------------------------------------------------------------------------------

it('refuses when data differs from the approved baseline', function (Closure $change, string $problem) {
    $change();
    $snapshot = f03Snapshot();

    [$code, $out] = f03Apply();

    expect($code)->toBe(1)->and($out)->toContain($problem)->and($out)->toContain('Nothing was written')
        ->and(f03Snapshot())->toEqual($snapshot)->and(f03Audit())->toBe([])->and(f03Backups())->toBe([]);
})->with([
    'changed baseline count' => [fn () => DB::connection('main')->table('nuke_islamic_series')->where('id', 15756)->update(['count' => 3]), 'series 15756: count_changed'],
    'missing series' => [fn () => DB::connection('main')->table('nuke_islamic_series')->where('id', 15884)->delete(), 'series 15884: missing'],
    'an own item added' => [fn () => DB::connection('main')->table('nuke_islamic_khotab')->insert(['author' => 154, 'ser_id' => 15495, 'group_id' => 1138, 'vedio' => 1, 'hidden' => 0, 'title' => 'new', 'time' => 2]), 'series 15495: items_changed'],
    'an own item moved away' => [fn () => DB::connection('main')->table('nuke_islamic_khotab')->where('ser_id', 15766)->limit(1)->update(['ser_id' => 0]), 'series 15766: items_changed'],
    'identity changed (group)' => [fn () => DB::connection('main')->table('nuke_islamic_series')->where('id', 15404)->update(['group_id' => 500]), 'series 15404: identity_changed'],
    'identity changed (hidden)' => [fn () => DB::connection('main')->table('nuke_islamic_series')->where('id', 15346)->update(['hidden' => 1]), 'series 15346: identity_changed'],
    'identity changed (old_id)' => [fn () => DB::connection('main')->table('nuke_islamic_series')->where('id', 15561)->update(['old_id' => 1]), 'series 15561: identity_changed'],
    'group no longer the author\'s' => [fn () => DB::connection('main')->table('nuke_islamic_groups')->where('id', 1298)->update(['author_id' => 1]), 'series 15766: group_mismatch'],
    'partially repaired' => [fn () => DB::connection('main')->table('nuke_islamic_series')->where('id', 15627)->update(['count' => 24]), 'partially repaired'],
]);

it('the dry run reports the same discrepancies and exits non-zero', function () {
    DB::connection('main')->table('nuke_islamic_series')->where('id', 15756)->update(['count' => 3]);

    [$code, $out] = f03Run();

    expect($code)->toBe(1)->and($out)->toContain('Discrepancy: series 15756: count_changed')->and($out)->toContain('Verdict: blocked');
});

it('aborts before any write when the backup cannot be created', function () {
    $blocker = sys_get_temp_dir().'/f03a-file-'.bin2hex(random_bytes(4));
    file_put_contents($blocker, 'not a directory');
    try {
        [$code, $out] = f03Run(['--apply' => true, '--actor' => 'ops-boss', '--backup-dir' => $blocker.'/sub']);

        expect($code)->toBe(1)->and($out)->toContain('cannot create the backup directory')->and($out)->toContain('nothing was written')
            ->and(f03Counts())->toMatchArray(f03Before())->and(f03Audit())->toBe([]);
    } finally {
        @unlink($blocker);
    }
});

it('requires an existing, authorized actor for a write', function (array $options, string $message) {
    H::admin(['khotab.additem'], 'weak-admin');   // a real admin without khotab.editauthor
    $snapshot = f03Snapshot();

    [$code, $out] = f03Run(['--apply' => true, '--backup-dir' => $this->dir] + $options);

    expect($code)->toBe(1)->and($out)->toContain($message)->and(f03Snapshot())->toEqual($snapshot)->and(f03Backups())->toBe([]);
})->with([
    'no actor' => [[], '--actor=<AdminCP aid> is required'],
    'unknown actor' => [['--actor' => 'nobody'], '--actor=<AdminCP aid> is required'],
    'actor without permission' => [['--actor' => 'weak-admin'], 'lacks khotab.editauthor'],
]);

it('accepts an admin holding khotab.editauthor', function () {
    H::admin(['khotab.editauthor'], 'counter-admin');

    [$code] = f03Run(['--apply' => true, '--actor' => 'counter-admin', '--backup-dir' => $this->dir]);

    expect($code)->toBe(0)->and(f03Counts())->toMatchArray(f03After());
});

// --- Rollback ------------------------------------------------------------------------------------------------------

it('rolls back from the backup: dry run first, then restores the original values, audited', function () {
    f03Apply();
    $backup = f03Backups()[0];

    [$code, $out] = f03Run(['--rollback' => $backup]);
    expect($code)->toBe(0)->and($out)->toContain('restorable 22, already original 0, conflicts 0')->and(f03Counts())->toMatchArray(f03After());

    [$code, $out] = f03Run(['--rollback' => $backup, '--apply' => true, '--actor' => 'ops-boss']);
    expect($code)->toBe(0)->and($out)->toContain('Restored 22 series; 0 conflicting')
        ->and(f03Counts())->toMatchArray(f03Before())
        ->and(collect(f03Audit())->where('action', 'rollback_series_count'))->toHaveCount(22);

    // Repeating the rollback changes nothing.
    [$code, $out] = f03Run(['--rollback' => $backup, '--apply' => true, '--actor' => 'ops-boss']);
    expect($code)->toBe(0)->and($out)->toContain('Restored 0 series')->and(collect(f03Audit())->where('action', 'rollback_series_count'))->toHaveCount(22);
});

it('never overwrites a later legitimate change: a conflict blocks the rollback, --skip-conflicts restores only the rest', function () {
    f03Apply();
    $backup = f03Backups()[0];
    DB::connection('main')->table('nuke_islamic_series')->where('id', 15627)->update(['count' => 25]);   // an item added since

    [$code, $out] = f03Run(['--rollback' => $backup, '--apply' => true, '--actor' => 'ops-boss']);
    expect($code)->toBe(1)->and($out)->toContain('Refused: 1 series changed after the repair')
        ->and(f03Counts())->toMatchArray([15627 => 25] + f03After())
        ->and(collect(f03Audit())->where('action', 'rollback_series_count'))->toHaveCount(0);

    [$code, $out] = f03Run(['--rollback' => $backup, '--apply' => true, '--actor' => 'ops-boss', '--skip-conflicts' => true]);
    expect($code)->toBe(1)->and($out)->toContain('Restored 21 series; 1 conflicting series left untouched')
        ->and(f03Counts())->toMatchArray([15627 => 25] + f03Before());
});

it('refuses a tampered, foreign or unreadable backup file', function (Closure $tamper, string $message) {
    f03Apply();
    $backup = f03Backups()[0];
    $tamper($backup);

    [$code, $out] = f03Run(['--rollback' => $backup, '--apply' => true, '--actor' => 'ops-boss']);

    expect($code)->toBe(1)->and($out)->toContain($message)->and(f03Counts())->toMatchArray(f03After());
})->with([
    'edited value' => [fn (string $f) => file_put_contents($f, str_replace('"count_before": -39', '"count_before": -38', (string) file_get_contents($f))), 'failed its checksum'],
    'not a backup' => [fn (string $f) => file_put_contents($f, '{"hello": 1}'), 'is not an F03-A series-count backup'],
    'missing file' => [fn (string $f) => unlink($f), 'cannot be read'],
]);

it('keeps the shared local database out of reach: the tests run on the in-memory connection', function () {
    expect(DB::connection('main')->getDriverName())->toBe('sqlite');
});
