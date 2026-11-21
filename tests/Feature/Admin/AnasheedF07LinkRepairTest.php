<?php

/*
 * B2 / F07-B2 (owner-approved 2026-11-16, decision-log #148) — `anasheed:repair-f07-links`, on the harness's isolated
 * in-memory database (never the shared MySQL). The fixture holds the 46 approved rows with their exact stored bytes
 * (decoded from the manifest's hex) and control rows that must never change: the excluded cosmetic trims, item 16759,
 * a MEDIUM and a LOW row. MySQL/MariaDB byte semantics are proven separately in AnasheedF07LinkRepairMysqlTest.
 */

use App\Domain\Admin\Actions\Anasheed\F07LinkRepair;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\ContextualAdminHarness as H;

uses(RefreshDatabase::class);

const F07B2_CMD = 'anasheed:repair-f07-links';

/** The approved scope, written out independently of the manifest (a manifest edit must fail this test). */
const F07B2_ITEMS = [4860, 4868, 4869, 4892, 4893, 8703, 8870, 8871, 8872, 9075, 9106, 9360, 9483, 9498, 9589, 9601, 9604, 9642, 9688, 9724,
    9826, 9869, 9870, 9871, 9872, 9873, 9874, 9875, 9876, 9877, 9955, 10467, 10547, 11172, 14823];
const F07B2_MIRRORS = [10659, 15966, 15969, 15970, 15971, 15978, 15979, 15981, 15986, 15987, 15997];

/** Control rows (id => exact link) that are out of scope. */
const F07B2_CONTROL_ITEMS = [
    1427 => 'http://www.archive.org/download/way2allah-104/resalt_alzyton.rm ',   // cosmetic trim, excluded
    1921 => ' http://www.archive.org/download/84_way2allah/asrar_alamzon_7A.rm', // cosmetic trim, excluded
    16759 => 'new-new-alaser-3arog_x264.mp4new-new-alaser-3arog_x264.mp4',      // excluded (duplicate decision)
    16755 => 'new-new-aby-alhanon_x264.mp4new-new-al3awda_x264.mp4',            // MEDIUM, excluded
    8818 => ' https://archive.org/download/media-w..._hane_hlme.WMV',           // LOW, excluded
    9851 => 'http://vars.way2allah.com/cartoon/amin3/tabi3ia-rehlat-bahria-rehla-ela-almohit-alhadi-2-8-2014.WMV', // 9106's evidence
];

beforeEach(function () {
    H::setUp();
    $main = DB::connection('main');
    foreach (F07LinkRepair::MANIFEST as [$type, $id, $parent, $oldHex]) {
        $type === 'item'
            ? $main->table('nuke_anasheed_anasheed')->insert(['id' => $id, 'title' => 'مادة '.$id, 'group_id' => $parent, 'hidden' => 0, 'vedio' => 1, 'link' => hex2bin($oldHex), 'downcount' => 3])
            : $main->table('nuke_anasheed_mirror')->insert(['id' => $id, 'khid' => $parent, 'title' => 'وصلة '.$id, 'hidden' => 0, 'vedio' => 0, 'link' => hex2bin($oldHex), 'hits' => 2]);
    }
    foreach (F07B2_CONTROL_ITEMS as $id => $link) {
        $main->table('nuke_anasheed_anasheed')->insert(['id' => $id, 'title' => 'ضابطة '.$id, 'group_id' => 1, 'hidden' => 0, 'vedio' => 1, 'link' => $link]);
    }
    $main->table('nuke_anasheed_mirror')->insert(['id' => 10848, 'khid' => 8772, 'title' => 'LOW', 'hidden' => 0, 'link' => 'https://archive.org/download/media-w..._hane_hlme.AVI']);

    $this->dir = sys_get_temp_dir().'/f07b2-'.bin2hex(random_bytes(5));
    H::superAdmin('ops-boss');
});

afterEach(function () {
    foreach (glob($this->dir.'/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($this->dir);
});

function f07b2Run(array $options = []): array
{
    $code = Artisan::call(F07B2_CMD, $options);

    return [$code, Artisan::output()];
}

function f07b2Apply(array $extra = []): array
{
    return f07b2Run(['--apply' => true, '--actor' => 'ops-boss', '--backup-dir' => test()->dir] + $extra);
}

/** id => exact link, items and mirrors. */
function f07b2Links(): array
{
    $main = DB::connection('main');

    return [
        'item' => $main->table('nuke_anasheed_anasheed')->orderBy('id')->pluck('link', 'id')->all(),
        'mirror' => $main->table('nuke_anasheed_mirror')->orderBy('id')->pluck('link', 'id')->all(),
    ];
}

function f07b2Snapshot(): array
{
    $main = DB::connection('main');

    return [
        $main->table('nuke_anasheed_anasheed')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
        $main->table('nuke_anasheed_mirror')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
    ];
}

function f07b2Old(string $type, int $id): string
{
    foreach (F07LinkRepair::MANIFEST as [$t, $i, , $hex]) {
        if ($t === $type && $i === $id) {
            return (string) hex2bin($hex);
        }
    }
    throw new RuntimeException("not in manifest: {$type} {$id}");
}

function f07b2New(string $type, int $id): string
{
    foreach (F07LinkRepair::MANIFEST as [$t, $i, , , $new]) {
        if ($t === $type && $i === $id) {
            return $new;
        }
    }
    throw new RuntimeException("not in manifest: {$type} {$id}");
}

function f07b2SetLink(string $type, int $id, string $link): void
{
    DB::connection('main')->table($type === 'item' ? 'nuke_anasheed_anasheed' : 'nuke_anasheed_mirror')->where('id', $id)->update(['link' => $link]);
}

function f07b2Audit(): array
{
    return DB::connection('main')->table('admin_audit_log')->orderBy('id')->get()->all();
}

// --- Scope ------------------------------------------------------------------------------------------------------------

it('the manifest is exactly the approved 46 rows (35 items, 11 mirrors), nothing else', function () {
    $items = $mirrors = [];
    foreach (F07LinkRepair::MANIFEST as [$type, $id]) {
        $type === 'item' ? $items[] = $id : $mirrors[] = $id;
    }
    sort($items);
    sort($mirrors);

    expect($items)->toBe(F07B2_ITEMS)->and($mirrors)->toBe(F07B2_MIRRORS)
        ->and(array_intersect(array_keys(F07B2_CONTROL_ITEMS), $items))->toBe([]);
});

it('every approved replacement is exactly the documented one', function (string $type, int $id, string $new) {
    expect(f07b2New($type, $id))->toBe($new);
})->with([
    'archive host (8703)' => ['item', 8703, 'https://archive.org/download/TheSummerHolidayHD/The%20summer%20holiday_HD.wmv'],
    'archive host (9877)' => ['item', 9877, 'https://archive.org/download/heekmaa702/taghrid-alroda-mohamed-abo-almofalfil.WMV'],
    'stray «ر» (14823)' => ['item', 14823, 'http://vars.way2allah.com/cartoon/amin8/cartoon-fariq-alse7a-fawz-ghir-motawaqa3-11-2021_x264.mp4'],
    'leading space (4860)' => ['item', 4860, 'http://vars.way2allah.com/cartoon/3/3azoz_2.rmvb'],
    'exact copy of item 9851 (9106)' => ['item', 9106, F07B2_CONTROL_ITEMS[9851]],
    'exact copy of mirror 13857 (9955)' => ['item', 9955, 'http://vars.way2allah.com/cartoon/amin3/tabi3ia-3alam-alhaiwan-alghamid-ghabit-alghizlan-3-9-2014.WMV'],
    'leading + trailing space (15979)' => ['mirror', 15979, 'https://soundcloud.com/way2allahcom/wfqm7e8fcriu'],
    'leading space (15966)' => ['mirror', 15966, 'https://soundcloud.com/way2allahcom/al7ashr'],
    'duplicated URL (10659)' => ['mirror', 10659, 'https://archive.org/download/heekmaa701/mahboba-raw3et-alhejab-sama-osama_PocketPcVga.AVI'],
]);

it('the expected old values keep their exact bytes (leading / trailing spaces, Arabic)', function () {
    expect(f07b2Old('item', 4860))->toStartWith(' http://')
        ->and(f07b2Old('mirror', 15979))->toStartWith(' https://')->toEndWith('u ')
        ->and(f07b2Old('item', 14823))->toStartWith("\u{0631}http://")
        ->and(f07b2Old('mirror', 10659))->toBe(f07b2New('mirror', 10659).f07b2New('mirror', 10659));
});

// --- Dry run --------------------------------------------------------------------------------------------------------

it('dry-runs by default: 46 rows pending, every value shown with visible spaces, nothing written', function () {
    $before = f07b2Snapshot();

    [$code, $out] = f07b2Run();

    expect($code)->toBe(0)->and($out)->toContain('DRY RUN (no database write)')
        ->and($out)->toContain('Rows: 46 (pending 46, applied 0). Verdict: ready')
        ->and($out)->toContain('" http://vars.way2allah.com/cartoon/3/3azoz_2.rmvb"')
        ->and(f07b2Snapshot())->toEqual($before)->and(f07b2Audit())->toBe([])->and(is_dir($this->dir))->toBeFalse();
});

// --- Apply ----------------------------------------------------------------------------------------------------------

it('applies exactly the 46 approved corrections and nothing else, audited with the exact original values', function () {
    $before = f07b2Snapshot();

    [$code, $out] = f07b2Apply();

    expect($code)->toBe(0, $out)->and($out)->toContain('Corrected 46 link(s). Backup: '.$this->dir);
    $links = f07b2Links();
    foreach (F07LinkRepair::MANIFEST as [$type, $id, , , $new]) {
        expect($links[$type][$id])->toBe($new);
    }
    foreach (F07B2_CONTROL_ITEMS as $id => $link) {
        expect($links['item'][$id])->toBe($link);
    }
    expect($links['mirror'][10848])->toBe('https://archive.org/download/media-w..._hane_hlme.AVI');

    // Only `link` of the manifest rows changed: every other column of every row is identical.
    $strip = fn (array $rows) => array_map(fn (array $r) => array_diff_key($r, ['link' => 0]), $rows);
    $after = f07b2Snapshot();
    expect($strip($after[0]))->toEqual($strip($before[0]))->and($strip($after[1]))->toEqual($strip($before[1]));

    $audit = f07b2Audit();
    expect($audit)->toHaveCount(46)->and(array_unique(array_column($audit, 'action')))->toBe(['repair_link'])
        ->and(array_unique(array_column($audit, 'actor_aid')))->toBe(['ops-boss']);
    $row4860 = collect($audit)->first(fn ($a) => $a->entity_type === 'item' && (int) $a->entity_id === 4860);
    $meta = json_decode($row4860->metadata, true);
    expect($meta['from'])->toBe(' http://vars.way2allah.com/cartoon/3/3azoz_2.rmvb')     // leading space preserved
        ->and($meta['from_hex'])->toStartWith('20687474')
        ->and($meta['to'])->toBe('http://vars.way2allah.com/cartoon/3/3azoz_2.rmvb');

    $backups = glob($this->dir.'/*.json');
    expect($backups)->toHaveCount(1);
    $backup = F07LinkRepair::readBackup($backups[0]);
    expect($backup['rows'])->toHaveCount(46)->and(collect($backup['rows'])->firstWhere('id', 15979)['old'])->toBe(f07b2Old('mirror', 15979))
        ->and(substr(sprintf('%o', fileperms($backups[0])), -4))->toBe('0640');
});

it('is idempotent: a second apply reports nothing to do and writes nothing', function () {
    f07b2Apply();
    $snapshot = f07b2Snapshot();

    [$code, $out] = f07b2Apply();

    expect($code)->toBe(0)->and($out)->toContain('No repair necessary')
        ->and(f07b2Snapshot())->toEqual($snapshot)->and(f07b2Audit())->toHaveCount(46)->and(glob($this->dir.'/*.json'))->toHaveCount(1);
});

it('applies only the still-pending rows of a partially repaired state (a manual fix to the approved value counts as applied)', function () {
    f07b2SetLink('item', 4860, f07b2New('item', 4860));         // someone already trimmed it by hand
    f07b2SetLink('mirror', 15966, f07b2New('mirror', 15966));

    [$code, $out] = f07b2Apply();

    expect($code)->toBe(0, $out)->and($out)->toContain('Corrected 44 link(s)')
        ->and(f07b2Audit())->toHaveCount(44)
        ->and(collect(f07b2Audit())->contains(fn ($a) => (int) $a->entity_id === 4860))->toBeFalse();
});

// --- Byte-exact conflicts --------------------------------------------------------------------------------------------

it('treats any byte difference from the expected old value as a conflict and writes nothing', function (string $type, int $id, Closure $mutate) {
    f07b2SetLink($type, $id, $mutate(f07b2Old($type, $id)));
    $snapshot = f07b2Snapshot();

    [$code, $out] = f07b2Apply();

    expect($code)->toBe(1)->and($out)->toContain("Discrepancy: {$type} {$id}: conflict")->and($out)->toContain('Nothing was written')
        ->and(f07b2Snapshot())->toEqual($snapshot)->and(f07b2Audit())->toBe([])->and(glob($this->dir.'/*.json') ?: [])->toBe([]);
})->with([
    'extra trailing space' => ['item', 8703, fn (string $v) => $v.' '],
    'leading space removed differently (tab)' => ['item', 4868, fn (string $v) => "\t".ltrim($v)],
    'letter case' => ['item', 9870, fn (string $v) => str_replace('heekmaa702', 'HEEKMAA702', $v)],
    'scheme case' => ['mirror', 15970, fn (string $v) => str_replace('https://', 'HTTPS://', $v)],
    'trailing space dropped' => ['mirror', 15979, fn (string $v) => rtrim($v)],
    'Arabic prefix replaced' => ['item', 14823, fn (string $v) => 'ز'.mb_substr($v, 1)],
    'a later legitimate edit' => ['item', 9106, fn () => 'https://archive.org/download/other/file.WMV'],
]);

it('blocks on a missing record or a changed parent', function (Closure $change, string $problem) {
    $change();

    [$code, $out] = f07b2Apply();

    expect($code)->toBe(1)->and($out)->toContain($problem)->and(f07b2Audit())->toBe([]);
})->with([
    'missing item' => [fn () => DB::connection('main')->table('nuke_anasheed_anasheed')->where('id', 9877)->delete(), 'item 9877: missing'],
    'missing mirror' => [fn () => DB::connection('main')->table('nuke_anasheed_mirror')->where('id', 10659)->delete(), 'mirror 10659: missing'],
    'item moved to another group' => [fn () => DB::connection('main')->table('nuke_anasheed_anasheed')->where('id', 11172)->update(['group_id' => 1]), 'item 11172: parent_changed'],
    'mirror moved to another item' => [fn () => DB::connection('main')->table('nuke_anasheed_mirror')->where('id', 15997)->update(['khid' => 1]), 'mirror 15997: parent_changed'],
]);

it('rolls the whole transaction back when a write fails part-way', function () {
    DB::connection('main')->statement("CREATE TRIGGER f07b2_fail BEFORE UPDATE ON nuke_anasheed_anasheed WHEN NEW.id = 9875 BEGIN SELECT RAISE(ABORT, 'simulated failure'); END");
    $before = f07b2Links();

    [$code, $out] = f07b2Apply();

    expect($code)->toBe(1)->and($out)->toContain('simulated failure')
        ->and(f07b2Links())->toBe($before)->and(f07b2Audit())->toBe([]);
});

it('aborts before any write when the backup cannot be created', function () {
    $blocker = sys_get_temp_dir().'/f07b2-file-'.bin2hex(random_bytes(4));
    file_put_contents($blocker, 'x');
    try {
        $before = f07b2Links();
        [$code, $out] = f07b2Run(['--apply' => true, '--actor' => 'ops-boss', '--backup-dir' => $blocker.'/sub']);

        expect($code)->toBe(1)->and($out)->toContain('cannot create the backup directory')
            ->and(f07b2Links())->toBe($before)->and(f07b2Audit())->toBe([]);
    } finally {
        @unlink($blocker);
    }
});

it('requires an existing actor holding both Variety edit permissions (or super-admin)', function (array $options, string $message) {
    H::admin(['anasheed.editanasheed'], 'items-only');
    $before = f07b2Snapshot();

    [$code, $out] = f07b2Run(['--apply' => true, '--backup-dir' => $this->dir] + $options);

    expect($code)->toBe(1)->and($out)->toContain($message)->and(f07b2Snapshot())->toEqual($before);
})->with([
    'no actor' => [[], '--actor=<AdminCP aid> is required'],
    'unknown actor' => [['--actor' => 'nobody'], '--actor=<AdminCP aid> is required'],
    'items permission only' => [['--actor' => 'items-only'], 'lacks anasheed.editanasheed / anasheed.editmirror'],
]);

it('accepts an admin holding both permissions', function () {
    H::admin(['anasheed.editanasheed', 'anasheed.editmirror'], 'editor');

    [$code] = f07b2Run(['--apply' => true, '--actor' => 'editor', '--backup-dir' => $this->dir]);

    expect($code)->toBe(0)->and(f07b2Links()['item'][8703])->toBe(f07b2New('item', 8703));
});

// --- Rollback -------------------------------------------------------------------------------------------------------

it('rolls back byte-exactly from the backup (dry run first), audited; a repeat is a no-op', function () {
    $original = f07b2Links();
    f07b2Apply();
    $backup = glob($this->dir.'/*.json')[0];

    [$code, $out] = f07b2Run(['--rollback' => $backup]);
    expect($code)->toBe(0)->and($out)->toContain('restorable 46, already original 0, conflicts 0');

    [$code, $out] = f07b2Run(['--rollback' => $backup, '--apply' => true, '--actor' => 'ops-boss']);
    expect($code)->toBe(0)->and($out)->toContain('Restored 46 link(s); 0 conflicting')
        ->and(f07b2Links())->toBe($original)                              // leading / trailing spaces and «ر» are back
        ->and(collect(f07b2Audit())->where('action', 'rollback_link'))->toHaveCount(46);

    [$code, $out] = f07b2Run(['--rollback' => $backup, '--apply' => true, '--actor' => 'ops-boss']);
    expect($code)->toBe(0)->and($out)->toContain('Restored 0 link(s)')->and(f07b2Links())->toBe($original);
});

it('never overwrites a later edit: a conflict blocks the rollback; --skip-conflicts restores only the rest', function () {
    $original = f07b2Links();
    f07b2Apply();
    $backup = glob($this->dir.'/*.json')[0];
    f07b2SetLink('item', 9870, 'https://archive.org/download/heekmaa702/edited-later.WMV');
    f07b2SetLink('mirror', 15969, f07b2New('mirror', 15969).' ');    // a byte difference only

    [$code, $out] = f07b2Run(['--rollback' => $backup, '--apply' => true, '--actor' => 'ops-boss']);
    expect($code)->toBe(1)->and($out)->toContain('Refused: 2 row(s) changed after the repair')
        ->and(collect(f07b2Audit())->where('action', 'rollback_link'))->toHaveCount(0);

    [$code, $out] = f07b2Run(['--rollback' => $backup, '--apply' => true, '--actor' => 'ops-boss', '--skip-conflicts' => true]);
    $links = f07b2Links();
    expect($code)->toBe(1)->and($out)->toContain('Restored 44 link(s); 2 conflicting')
        ->and($links['item'][9870])->toBe('https://archive.org/download/heekmaa702/edited-later.WMV')
        ->and($links['mirror'][15969])->toBe(f07b2New('mirror', 15969).' ')
        ->and($links['item'][14823])->toBe($original['item'][14823]);
});

it('refuses a tampered, foreign or unreadable backup', function (Closure $tamper, string $message) {
    f07b2Apply();
    $backup = glob($this->dir.'/*.json')[0];
    $tamper($backup);

    [$code, $out] = f07b2Run(['--rollback' => $backup, '--apply' => true, '--actor' => 'ops-boss']);

    expect($code)->toBe(1)->and($out)->toContain($message);
})->with([
    'edited value' => [fn (string $f) => file_put_contents($f, str_replace('3azoz_2.rmvb', '3azoz_3.rmvb', (string) file_get_contents($f))), 'failed its checksum'],
    'not a backup' => [fn (string $f) => file_put_contents($f, '{"x": 1}'), 'is not an F07-B2 link backup'],
    'missing file' => [fn (string $f) => unlink($f), 'cannot be read'],
]);

it('runs on the isolated in-memory connection (never the shared MySQL)', function () {
    expect(DB::connection('main')->getDriverName())->toBe('sqlite');
});
