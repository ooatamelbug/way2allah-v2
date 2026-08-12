<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Content\Models\BackupSession;
use App\Domain\Content\Support\BackupApiAuthenticator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Replaces the root `backup.php` (Roadmap task 6.8) — a machine-to-machine
 * content-backup/booking API, not a browser-facing page. Every response
 * below is raw text at HTTP 200, matching legacy exactly (it never calls
 * `header()` to change the status code, including on auth failure).
 *
 * **Request shape, CORRECTED this round against the confirmed real Desktop
 * App source (`Form4.frm`/`frmMain`, `Project1.vbp`'s `Startup="frmBackup"`
 * chain):** `op` travels in the POST body for every operation, never the
 * query string — read via `$request->input('op')`, not `$request->query()`.
 * The Task 6.8 "confirmed core" round read `op` via the query string,
 * following the repository `backup.php`'s own `$_GET['op']` line; the
 * Desktop-App-source investigation proved the real client never sends
 * `op` that way. `ID`/`APIKey`/`Session`/`myop`/all other fields are POST
 * body fields on both sides, unchanged.
 *
 * **Implemented:** `CreateSession`, `LiveUpdate`, `KhotabUpdateBackup`
 * with `myop=get` or `myop=put`. **Not implemented, by explicit
 * instruction:** `myop=getdown` (no confirmed real-client evidence of use,
 * `getdown` search across the entire inspected Desktop App source: zero
 * hits), `KhotabCheckLink`, `BackUpListDown`, `BackUpDownloadList`
 * (confirmed undefined functions in legacy — calling them would fatal).
 * Any unmatched `op`/`myop` combination falls through to an empty 200
 * response, matching `KhotabUpdateBackup`'s own confirmed current
 * behavior for parameters that don't populate a matched branch.
 */
class BackupApiController
{
    /** `$dbtables[1..5]` (`backup.php:31-35`) — the 5 content tables `get`/`put` operate against. */
    private const CATEGORY_TABLES = [
        1 => 'nuke_islamic_khotab',
        2 => 'nuke_islamic_mirror',
        3 => 'nuke_telawah_telawah',
        4 => 'nuke_anasheed_anasheed',
        5 => 'nuke_anasheed_mirror',
    ];

    /** `$paths[1..5]` (`backup.php:37-41`) — literal placeholder tokens the Desktop App itself substitutes with its own configured local folders (confirmed: `DownloadForm.frm`'s `Replace(..., "@KhotabPath@", ...)`). Echoed verbatim, never resolved server-side. */
    private const CATEGORY_PATH_PLACEHOLDERS = [
        1 => '@KhotabPath@',
        2 => '@MirrorPath@',
        3 => '@TelawahPath@',
        4 => '@AnasheedPath@',
        5 => '@AnasheedMirrorPath@',
    ];

    /**
     * `put`'s own, SEPARATE `switch($cat)` table map (`backup.php:364-388`)
     * — confirmed NOT the same table set as CATEGORY_TABLES above, despite
     * sharing the same 1-5 keys. Left exactly as legacy names them,
     * including the confirmed, unresolved discrepancy: `AnasheedAdvanced`
     * (the existing Eloquent model for `nuke_anasheed_advanced`) is
     * independently documented elsewhere as 1:1 with `nuke_anasheed_mirror`
     * (mode 5), yet `put`'s own cat=4 maps to this same table. Not
     * resolved here — implemented literally as legacy's switch defines it,
     * via Query Builder against the raw table name rather than that model,
     * so no semantic claim from the model's docblock is implied.
     */
    private const ADVANCED_TABLES = [
        1 => 'nuke_islamic_advanced',
        2 => 'nuke_islamic_advanced_m',
        3 => 'nuke_telawah_advanced',
        4 => 'nuke_anasheed_advanced',
        5 => 'nuke_anasheed_advanced_m',
    ];

    public function __construct(
        private readonly BackupApiAuthenticator $authenticator,
    ) {}

    public function __invoke(Request $request): Response
    {
        $op = $request->input('op');
        $result = $this->authenticator->authenticate($request);

        if (! $result->authenticated) {
            Log::info('backup_api.auth_failed', ['op' => $op]);

            return response($result->errorResponse, 200);
        }

        return match ($op) {
            'CreateSession' => $this->createSession($result->admin),
            'LiveUpdate' => $this->liveUpdate($result->admin, (int) $request->input('Session')),
            'KhotabUpdateBackup' => $this->khotabUpdateBackup($request, $result->admin),
            default => response('', 200),
        };
    }

    /**
     * `backup.php:118-160`. **Confirmed bug, reproduced exactly, not
     * "fixed" to a proper count:** the legacy "session limit" check uses
     * `$db->get_var("SELECT id FROM ... WHERE active='1' AND uid='$ID'")`
     * — ezSQL's `get_var()` returns the first column of the FIRST MATCHED
     * ROW ONLY (`ez_sql_core.php:129-149`), not a row count. The
     * comparison `$TotalList>9` therefore checks whether *an arbitrary
     * existing active session's own `id` value* exceeds 9 — not whether
     * 10+ sessions exist. `->value('id')` below is the precise Eloquent
     * equivalent of `get_var()`'s unordered-first-row semantic.
     */
    private function createSession(AdminUser $admin): Response
    {
        $this->removeOldSessions();

        $existingSessionId = BackupSession::query()
            ->where('active', 1)
            ->where('uid', $admin->uid)
            ->value('id');

        if ($existingSessionId !== null && $existingSessionId > 9) {
            Log::info('backup_api.create_session_blocked', ['uid' => $admin->uid]);

            return response('0', 200);
        }

        $session = BackupSession::query()->create([
            'uid' => $admin->uid,
            'createtime' => time(),
            'updatetime' => time(),
            'downloaded' => '0',
            'ip' => request()->ip(),
        ]);

        Log::info('backup_api.create_session', ['uid' => $admin->uid, 'session_id' => $session->id]);

        return response($session->id.'<w2abackupspacer>'.$admin->aid, 200);
    }

    /**
     * `backup.php:162-191`. `$size`/`$downloaded`/`$count`/`$speed`/
     * `$itemid`/`$catid` are the function's own parameters, confirmed
     * never populated from `$_POST`/`$_GET` anywhere in `backup.php` —
     * every real call writes empty strings for these 6 columns.
     * Reproduced exactly here: NOT read from the request, per explicit
     * instruction, even though the confirmed real Desktop App does send
     * fields with these exact names (`Form4.frm:1251-1252`) — legacy's own
     * `backup.php` source simply never reads them, so nothing this port
     * does can make legacy write real telemetry; wiring them up here
     * would be new behavior, not a port.
     */
    private function liveUpdate(AdminUser $admin, int $sessionId): Response
    {
        $this->removeOldSessions();

        $session = BackupSession::query()
            ->where('id', $sessionId)
            ->where('active', 1)
            ->where('uid', $admin->uid)
            ->first();

        if ($session === null) {
            Log::info('backup_api.live_update_restart', ['uid' => $admin->uid, 'session_id' => $sessionId]);

            return response('Restart', 200);
        }

        $session->update([
            'updatetime' => time(),
            'size' => '',
            'downloaded' => '',
            'count' => '',
            'speed' => '',
            'itemid' => '',
            'catid' => '',
        ]);

        Log::info('backup_api.live_update', ['uid' => $admin->uid, 'session_id' => $sessionId]);

        return response('OK', 200);
    }

    private function khotabUpdateBackup(Request $request, AdminUser $admin): Response
    {
        return match ($request->input('myop')) {
            'get' => $this->khotabUpdateBackupGet($request, $admin),
            'put' => $this->khotabUpdateBackupPut($request, $admin),
            default => response('', 200),
        };
    }

    /**
     * `UpdateBackup()`'s `$myop=="get"` branch (`backup.php:219-335`).
     * `getdown` (`$mydown='0'`) is intentionally not implemented — this
     * method always uses `$mydown='2'`, matching only `get`.
     *
     * **Newly discovered this round, not previously documented anywhere:
     * legacy's own `get`/`getdown` would fatal-error on any non-empty
     * result.** `fix_archive_links()` (`backup.php` line ~294) is defined
     * in `classes/archive.php`, which is included only by
     * `fatawa/download.php` and `vars/download.php` — confirmed via
     * repo-wide grep — NEVER by `backup.php` or anything in its include
     * chain. Calling it from inside `UpdateBackup()` would be a call to an
     * undefined function, exactly like the already-known
     * `CheckLink`/`ListDown`/`DownloadList` gaps, except this one sits
     * inside the one operation this task requires working end-to-end.
     * Reproducing the crash would make `get` non-functional whenever it
     * actually has data to return — the only case that matters. Since
     * `fix_archive_links()`'s own source is fully known, unambiguous, and
     * side-effect-free (a pure URL string transform, ported verbatim
     * below as `fixArchiveLinks()`), it is applied here rather than
     * reproducing legacy's fatal error. The actual legacy `backup.php`
     * file is NOT modified and still contains this latent bug.
     */
    private function khotabUpdateBackupGet(Request $request, AdminUser $admin): Response
    {
        // backup.php:196 — UpdateBackup() calls RemoveOldSessions()
        // unconditionally, before even checking $myop. Reconciliation
        // finding C1: this call was missing here. Added, not previously
        // present.
        $this->removeOldSessions();

        $permissions = $this->authenticator->backupCategoryPermissions($admin);

        // backup.php:98's $All ('allsite') is a confirmed dead flag — it
        // is computed by legacy but never consulted by any mode gate
        // below. NOT used here to bypass the 5 per-mode flags.
        $categoryEnabled = [
            1 => $permissions['backupkhotab'],
            2 => $permissions['backupkhotabmirror'],
            3 => $permissions['backuptelawah'],
            4 => $permissions['backupanasheed'],
            5 => $permissions['backupanasheedmirror'],
        ];

        $deadTime = time() - (24 * 60 * 60);
        $maxTrials = 10;
        $oldBooking = time() - (24 * 60 * 60);
        $sessionId = (int) $request->input('Session');

        $body = '<html><body>';
        $modesWithResults = [];

        foreach (self::CATEGORY_TABLES as $mode => $table) {
            // backup.php:232-234 — runs every mode, unconditionally, even
            // when this mode's permission flag is off. LIMIT 1 preserved
            // exactly — only one stale booking row is cleared per mode.
            DB::connection('main')->table($table)
                ->where('booking', '<', $oldBooking)
                ->limit(1)
                ->update(['booking' => 0]);

            $items = $categoryEnabled[$mode]
                ? $this->selectBackupCandidates($mode, $table, $deadTime, $maxTrials)
                : collect();

            if ($items->isEmpty()) {
                $body .= 'NONE';
            } else {
                $modesWithResults[] = $mode;

                foreach ($items as $item) {
                    DB::connection('main')->table($table)
                        ->where('id', $item->id)
                        ->limit(1)
                        ->update(['trial' => DB::raw('trial + 1'), 'booking' => time()]);

                    // backup.php:291 — floor(id/100), NOT MediaPathResolver's
                    // floor(id/1000) — a genuinely different, backup.php-
                    // specific bucketing convention, not reused here.
                    $basefolder = intdiv((int) $item->id, 100);
                    $extension = strtolower((string) pathinfo((string) ($item->link ?? ''), PATHINFO_EXTENSION));
                    $link = $this->fixArchiveLinks((string) ($item->link ?? ''));

                    // Mode 2's parent-title lookup (backup.php:296-302) is
                    // structurally reproduced but confirmed unreachable —
                    // mode 2 always selects 0 rows (see selectBackupCandidates,
                    // LIMIT 0). Kept for structural fidelity only.
                    $parentTitle = '';
                    if ($mode === 2) {
                        $parentTitle = (string) (DB::connection('main')->table('nuke_islamic_khotab')
                            ->where('id', $item->khid ?? 0)
                            ->value('title') ?? '');
                    }

                    // backup.php:306 — "$ParentTitle[title] $khotab[title]"
                    // has a literal separating space even when ParentTitle
                    // is empty (modes other than 2), producing a leading
                    // space before the title. Preserved, not trimmed away.
                    $name = $parentTitle.' '.($item->title ?? '');

                    $body .= "<ID>{$item->id}<NAME>{$name}<URL>{$link}<FILE>".self::CATEGORY_PATH_PLACEHOLDERS[$mode].$basefolder.'\\'.$item->id.'.'.$extension;

                    DB::connection('main')->table('nuke_backup_booking')->insert([
                        'uid' => $admin->uid,
                        'createtime' => time(),
                        'catid' => $mode,
                        'itemid' => $item->id,
                        'sessionid' => $sessionId,
                        'ip' => $request->ip(),
                    ]);
                }
            }

            $body .= '<ENDMODE>';
        }

        $body .= '</body></html>';

        Log::info('backup_api.khotab_update_backup_get', [
            'uid' => $admin->uid,
            'session_id' => $sessionId,
            'modes_with_results' => $modesWithResults,
        ]);

        return response($body, 200);
    }

    /** @return Collection<int, \stdClass> */
    private function selectBackupCandidates(int $mode, string $table, int $deadTime, int $maxTrials): Collection
    {
        return match ($mode) {
            1 => DB::connection('main')->table($table)
                ->where('down', 2)
                ->where('booking', 0)
                ->where('trial', '<', $maxTrials)
                ->where('time', '<', $deadTime)
                ->orderBy('id')
                ->limit(20)
                ->get(['id', 'title', 'author', 'link', 'ser_id', 'vedio']),
            // backup.php:271-273 — Mirror_Limit=0: this mode ALWAYS
            // selects zero rows. Not "optimized away" — the query and its
            // id>104839 floor are preserved exactly, only the LIMIT 0
            // makes it a confirmed permanent no-op.
            2 => DB::connection('main')->table($table)
                ->where('id', '>', 104839)
                ->where('down', 2)
                ->where('trial', '<', $maxTrials)
                ->where('backupme', 1)
                ->where('booking', 0)
                ->orderBy('id')
                ->limit(0)
                ->get(['id', 'khid', 'link']),
            3 => DB::connection('main')->table($table)
                ->where('down', 2)
                ->where('trial', '<', $maxTrials)
                ->where('booking', 0)
                ->orderBy('id')
                ->limit(20)
                ->get(['id', 'title', 'link']),
            4 => DB::connection('main')->table($table)
                ->where('down', 2)
                ->where('link', '!=', '')
                ->where('trial', '<', $maxTrials)
                ->where('booking', 0)
                ->orderBy('id')
                ->limit(20)
                ->get(['id', 'title', 'link']),
            5 => DB::connection('main')->table($table)
                ->where('down', 2)
                ->where('trial', '<', $maxTrials)
                ->where('backupme', 1)
                ->where('booking', 0)
                ->orderBy('id')
                ->limit(20)
                ->get(['id', 'khid', 'link', 'title']),
            default => collect(),
        };
    }

    /**
     * `UpdateBackup()`'s `$myop=="put"` branch (`backup.php:338-435`).
     * `cd` is deliberately never read — the confirmed real Desktop App
     * does not send it (`Form4.frm:2251,2262`), and legacy's own
     * `UpdateBackup($id,$size,$cd,...)` parameter for it is likewise
     * never populated by any real call.
     */
    private function khotabUpdateBackupPut(Request $request, AdminUser $admin): Response
    {
        // backup.php:196 — same unconditional RemoveOldSessions() call as
        // khotabUpdateBackupGet() above. Reconciliation finding C1.
        $this->removeOldSessions();

        $itemId = (int) $request->input('id');
        $size = (int) $request->input('size');
        $cat = (int) $request->input('cat');
        $sessionId = (int) $request->input('Session');

        $table = self::CATEGORY_TABLES[$cat] ?? null;

        // backup.php:340-352 — this UPDATE runs unconditionally, before
        // the $cat validity switch below, via the same table map. For an
        // invalid $cat, legacy would build a malformed query against a
        // blank table name (undefined array index) — not reproduced
        // literally; skipped here instead of attempting a broken query.
        if ($table !== null) {
            DB::connection('main')->table($table)
                ->where('id', $itemId)
                ->update([
                    'down' => 1,
                    'trial' => 0,
                    'linksize' => $size,
                    'online' => $size,
                    'downloader' => $admin->uid,
                    'checktime' => time(),
                    'booking' => 0,
                ]);
        }

        DB::connection('main')->table('nuke_backup_booking')
            ->where('uid', $admin->uid)
            ->where('catid', $cat)
            ->where('itemid', $itemId)
            ->where('sessionid', $sessionId)
            ->delete();

        $advancedTable = self::ADVANCED_TABLES[$cat] ?? null;

        if ($advancedTable === null) {
            Log::info('backup_api.khotab_update_backup_put_invalid_cat', [
                'uid' => $admin->uid, 'session_id' => $sessionId, 'cat' => $cat, 'item_id' => $itemId,
            ]);

            return response('ERROR', 200);
        }

        // backup.php:390 — "$Adv==1", loose comparison against a raw
        // request value; the confirmed real Desktop App always sends
        // literal "Adv=1" (Form4.frm:2251,2262).
        if ((int) $request->input('Adv') === 1) {
            DB::connection('main')->table($advancedTable)->where('id', $itemId)->delete();

            DB::connection('main')->table($advancedTable)->insert([
                'id' => $itemId,
                'perf' => $request->input('perf'),
                'cright' => $request->input('cright'),
                'frate' => $request->input('frate'),
                'srate' => $request->input('srate'),
                'vres' => $request->input('vres'),
                'ares' => $request->input('ares'),
                'astr' => $request->input('astr'),
                'vstr' => $request->input('vstr'),
                'abit' => $request->input('abit'),
                'vbit' => $request->input('vbit'),
                'adur' => $request->input('adur'),
                'width' => $request->input('width'),
                'height' => $request->input('height'),
                'alist' => $request->input('alist'),
                'vlist' => $request->input('vlist'),
            ]);
        }

        Log::info('backup_api.khotab_update_backup_put', [
            'uid' => $admin->uid, 'session_id' => $sessionId, 'cat' => $cat, 'item_id' => $itemId,
        ]);

        return response('OK', 200);
    }

    /**
     * Verbatim port of `classes/archive.php`'s `fix_archive_links()` — see
     * `khotabUpdateBackupGet()`'s docblock for why this is applied here
     * despite legacy's own `backup.php` never successfully reaching it.
     * Rewrites an archive.org URL to a direct-download form; passes
     * through unchanged for any other domain.
     */
    private function fixArchiveLinks(string $urlOld): string
    {
        $urlParts = explode('/', $urlOld);
        $domainParts = explode('.', $urlParts[2] ?? '');
        $last = strtolower((string) ($domainParts[count($domainParts) - 1] ?? ''));
        $secondLast = strtolower((string) ($domainParts[count($domainParts) - 2] ?? ''));

        if ($last === 'org' && $secondLast === 'archive') {
            $count = count($urlParts);

            return 'http://www.archive.org/download/'.($urlParts[$count - 2] ?? '').'/'.($urlParts[$count - 1] ?? '');
        }

        return $urlOld;
    }

    /** `backup.php:110-116` — a session inactive for 600+ seconds is deactivated before every session-touching op. */
    private function removeOldSessions(): void
    {
        BackupSession::query()
            ->where('updatetime', '<', time() - 600)
            ->where('active', 1)
            ->update(['active' => 0]);
    }
}
