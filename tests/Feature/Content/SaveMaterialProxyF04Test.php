<?php

use App\Support\Downloads\DownloadCapacityGuard;
use App\Support\Downloads\DownloadRejectionRecorder;
use App\Support\Downloads\ProxiedDownloadResponder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\ContextualAdminHarness as H;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

uses(RefreshDatabase::class);

/**
 * F04 «حفظ المادة» — Variety, Telawah, Fatwa and chat-room-lesson save links now use the existing protected proxy
 * (the Khotab pattern, owner-approved 2026-11-17) through ProxiedDownloadResponder. Every network source here is a
 * local `php -S` fixture server on 127.0.0.1 (the private-host block is switched off for it); no real host is
 * contacted.
 */
function f04pStartServer(): array
{
    $dir = sys_get_temp_dir().'/f04p-'.uniqid();
    mkdir($dir.'/files', 0777, true);
    file_put_contents($dir.'/files/clip.mp4', str_repeat('V', 4096));
    file_put_contents($dir.'/files/talk.mp3', str_repeat('A', 2048));
    file_put_contents($dir.'/router.php', <<<'PHP'
<?php
$p = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($p === '/download/item/clip.mp4') { header('Location: /files/clip.mp4', true, 302); exit; }   // archive.org-style hop
if ($p === '/error.mp4') { header('Content-Type: text/html; charset=UTF-8'); echo '<html><body>error page</body></html>'; exit; }
if ($p === '/gone.mp4') { http_response_code(404); echo 'not found'; exit; }
if ($p === '/slow.mp4') { sleep(4); header('Content-Type: video/mp4'); echo 'late'; exit; }
return false;
PHP);

    $port = random_int(20000, 60000);
    $process = proc_open(['php', '-S', "127.0.0.1:{$port}", '-t', $dir, $dir.'/router.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    for ($i = 0; $i < 50; $i++) {
        $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
        if ($socket !== false) {
            fclose($socket);
            break;
        }
        usleep(100000);
    }

    return [$process, "http://127.0.0.1:{$port}", $dir];
}

function f04pStopServer($process, string $dir): void
{
    proc_terminate($process);
    proc_close($process);
    foreach (['/files/clip.mp4', '/files/talk.mp3', '/router.php'] as $f) {
        @unlink($dir.$f);
    }
    @rmdir($dir.'/files');
    @rmdir($dir);
}

function f04pVariety(int $id, string $link, array $extra = []): void
{
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert(array_merge(['id' => $id, 'title' => 'مادة '.$id, 'group_id' => 1, 'parent_id' => 1,
        'hidden' => 0, 'weight' => 1, 'vedio' => 1, 'mirror' => 0, 'link' => $link, 'downcount' => 0], $extra));
}

function f04pListen(): void
{
    DB::connection('main')->listen(function ($query) {
        if (str_starts_with(strtolower($query->sql), 'update')) {
            $GLOBALS['f04p_updates'][] = $query->sql;
        }
    });
}

/** How many counter UPDATEs touched $table.$column during the request(s). */
function f04pIncrements(string $table, string $column = 'downcount'): int
{
    return count(array_filter($GLOBALS['f04p_updates'] ?? [], fn (string $sql) => str_contains($sql, '"'.$table.'"') && str_contains($sql, '"'.$column.'"')));
}

function f04pCount(string $table, int $id, string $column = 'downcount'): int
{
    return (int) DB::connection('main')->table($table)->where('id', $id)->value($column);
}

beforeEach(function () {
    H::setUp();
    Schema::connection('main')->create(DownloadRejectionRecorder::TABLE, MainSchema::downloadRejections());
    $this->slotDir = sys_get_temp_dir().'/f04p-slots-'.uniqid();
    config([
        'downloads.guard' => true,
        'downloads.max_concurrent' => 1,
        'downloads.retry_after' => 60,
        'downloads.rejection_log' => true,
        'downloads.slot_directory' => $this->slotDir,
        'downloads.remote_connect_timeout' => 1.0,
        'downloads.remote_idle_timeout' => 2.0,
        'download_save.attachment_query_hosts' => [],
        'download_save.proxy_block_private_hosts' => false,   // the fixture server is on 127.0.0.1
    ]);
    [$this->server, $this->base, $this->serverDir] = f04pStartServer();
    $GLOBALS['f04p_held'] = [];
    // Counter writes are captured as they happen: after a successful stream the streamer has released the `main`
    // connection (as required), which drops this test's in-memory SQLite database, so it cannot be re-read.
    $GLOBALS['f04p_updates'] = [];
    f04pListen();
});

afterEach(function () {
    foreach ($GLOBALS['f04p_held'] ?? [] as $slot) {
        $slot?->release();
    }
    f04pStopServer($this->server, $this->serverDir);
    foreach (glob($this->slotDir.'/*') ?: [] as $path) {
        @unlink($path);
    }
    @rmdir($this->slotDir);
});

/** Hold the only slot so the pool is full. */
function f04pFillPool(): void
{
    $slot = app(DownloadCapacityGuard::class)->tryAcquire();
    expect($slot)->not->toBeNull();
    $GLOBALS['f04p_held'][] = $slot;
}

// ---------------------------------------------------------------- real downloads

it('Variety keeps its existing https normalisation BEFORE the proxy fetch (an http-only source is then unreachable: 500, never a file)', function () {
    f04pVariety(1, $this->base.'/files/talk.mp3');   // stored as http://127.0.0.1:…; Variety always rewrites http: → https:

    $response = $this->get('/var-download-1.htm');

    expect($response->getStatusCode())->toBe(500)
        ->and((string) $response->headers->get('Content-Disposition'))->not->toContain('attachment')
        // counted like Khotab: the slot was granted and the source opened, then rejected
        ->and(f04pIncrements('nuke_anasheed_anasheed'))->toBe(1);
});

it('lesson MP3: streams the real bytes as an attachment with the audio type', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 31, 'author' => 0, 'title' => 'درس', 'hidden' => 0, 'downcount' => 0, 'link' => $this->base.'/files/talk.mp3']);

    $response = $this->get('/lesson-download-31.htm')->assertOk();

    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment')->toContain('talk.mp3')
        ->and($response->headers->get('Content-Type'))->toContain('audio/mpeg')
        ->and($response->streamedContent())->toBe(str_repeat('A', 2048));
});

it('Telawah MP4: streams the real bytes as an attachment, counted once', function () {
    InMemoryConnection::setup('main', [
        'nuke_telawah_telawah' => MainSchema::nukeTelawahTelawah(),
        'nuke_telawah_groups' => MainSchema::nukeTelawahGroups(),
        'nuke_telawah_mirror' => MainSchema::nukeTelawahMirror(),
        DownloadRejectionRecorder::TABLE => MainSchema::downloadRejections(),
    ]);
    DB::connection('main')->table('nuke_telawah_telawah')->insert(['id' => 1, 'link' => $this->base.'/files/clip.mp4', 'downcount' => 0]);

    $response = $this->get('/recite-download-1.htm')->assertOk();

    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment')
        ->and($response->headers->get('Content-Type'))->toContain('video/mp4')
        ->and($response->streamedContent())->toBe(str_repeat('V', 4096))
        ->and(f04pIncrements('nuke_telawah_telawah'))->toBe(1);
});

it('an archive.org-style redirect (302 to the storage node) is followed and the file is downloaded (Fatwa)', function () {
    DB::connection('main')->table('nuke_fatwa_questions')->insert(['id' => 5, 'media_link' => $this->base.'/download/item/clip.mp4', 'num_download' => 0]);

    $response = $this->get('/fatawa-download-5.htm')->assertOk();

    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment')
        ->and($response->streamedContent())->toBe(str_repeat('V', 4096))
        ->and(f04pIncrements('nuke_fatwa_questions', 'num_download'))->toBe(1);
});

it('a chat-room lesson streams like Khotab', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 9, 'author' => 0, 'title' => 'درس', 'hidden' => 0, 'downcount' => 0, 'link' => $this->base.'/files/talk.mp3']);

    $response = $this->get('/lesson-download-9.htm')->assertOk();

    expect($response->streamedContent())->toBe(str_repeat('A', 2048))->and(f04pIncrements('nuke_islamic_khotab'))->toBe(1);
});

it('Variety mirror: a file mirror goes through the pool (variant mirror, its own id), not to the parent item', function () {
    f04pVariety(2, 'https://archive.org/download/x/a.mp4');
    DB::connection('main')->table('nuke_anasheed_mirror')->insert(['id' => 20, 'khid' => 2, 'link' => 'http://archive.org/download/x/m.mp4', 'linksize' => 77, 'hidden' => 0, 'hits' => 3]);
    f04pFillPool();

    $this->get('/var-mirror-2-20.htm')->assertStatus(503);

    $row = DB::connection('main')->table(DownloadRejectionRecorder::TABLE)->first();
    expect($row->module)->toBe('anasheed')->and($row->variant)->toBe('mirror')->and((int) $row->variant_id)->toBe(20)
        ->and($row->resolved_download_url)->toBe('https://archive.org/download/x/m.mp4')
        ->and($row->resource_title)->toBe('مادة 2')
        ->and(f04pCount('nuke_anasheed_mirror', 20, 'hits'))->toBe(3);
});

it('releases the slot after a transfer and releases the DB connection before streaming', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 3, 'author' => 0, 'title' => 'درس', 'hidden' => 0, 'downcount' => 0, 'link' => $this->base.'/files/talk.mp3']);

    $before = f04pCount('nuke_islamic_khotab', 3);
    expect($this->get('/lesson-download-3.htm')->assertOk()->streamedContent())->toBe(str_repeat('A', 2048));

    // The transfer is over: the ONE slot is free again. (A second request is not issued here: the streamer releases
    // the `main` connection before streaming — as required — which drops this test's in-memory SQLite database.)
    $probe = app(DownloadCapacityGuard::class)->tryAcquire();
    expect($probe)->not->toBeNull()->and($probe->isHeld())->toBeTrue();
    $probe->release();
    expect($before)->toBe(0);
});

// ---------------------------------------------------------------- bad sources never become a "download"

it('never streams an HTML error page, a source 404 or an unreachable source as a file: 500, slot released', function (string $path) {
    DB::connection('main')->table('nuke_fatwa_questions')->insert(['id' => 4, 'media_link' => $path === 'refused' ? 'http://127.0.0.1:1/x.mp4' : $this->base.$path, 'num_download' => 0]);

    $response = $this->get('/fatawa-download-4.htm');

    expect($response->getStatusCode())->toBe(500)
        ->and((string) $response->headers->get('Content-Disposition'))->not->toContain('attachment');
    // the slot was released: the single slot is free again
    $probe = app(DownloadCapacityGuard::class)->tryAcquire();
    expect($probe)->not->toBeNull();
    $probe->release();
})->with(['html error page' => ['/error.mp4'], 'source 404' => ['/gone.mp4'], 'connection refused' => ['refused']]);

it('a source that stalls before its headers is cut by the connect timeout (500), and the slot is released', function () {
    DB::connection('main')->table('nuke_fatwa_questions')->insert(['id' => 6, 'media_link' => $this->base.'/slow.mp4', 'num_download' => 0]);

    $started = microtime(true);
    $response = $this->get('/fatawa-download-6.htm');

    expect($response->getStatusCode())->toBe(500)
        ->and(microtime(true) - $started)->toBeLessThan(3.5);
    $probe = app(DownloadCapacityGuard::class)->tryAcquire();
    expect($probe)->not->toBeNull();
    $probe->release();
});

// ---------------------------------------------------------------- capacity

it('full pool: 503 + Retry-After, nothing counted, nothing opened, the refusal is recorded (Variety, Telawah, Fatwa, lesson)', function () {
    f04pVariety(7, 'http://archive.org/download/x/a.mp4', ['linksize' => 1234]);
    DB::connection('main')->table('nuke_fatwa_questions')->insert(['id' => 8, 'media_link' => 'https://archive.org/download/f/b.mp4', 'num_download' => 0, 'question_text' => 'سؤال']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 10, 'author' => 0, 'title' => 'درس', 'hidden' => 0, 'downcount' => 0, 'link' => 'https://archive.org/download/l/c.mp3']);
    f04pFillPool();

    foreach (['/var-download-7.htm', '/fatawa-download-8.htm', '/lesson-download-10.htm'] as $url) {
        $this->get($url)->assertStatus(503)->assertHeader('Retry-After', '60');
    }

    expect(f04pCount('nuke_anasheed_anasheed', 7))->toBe(0)
        ->and(f04pCount('nuke_fatwa_questions', 8, 'num_download'))->toBe(0)
        ->and(f04pCount('nuke_islamic_khotab', 10))->toBe(0);

    $rows = DB::connection('main')->table(DownloadRejectionRecorder::TABLE)->orderBy('id')->get();
    expect($rows->pluck('module')->all())->toBe(['anasheed', 'fatawa', 'lesson'])
        // existing URL normalisation preserved: the stored http archive.org link is resolved to https
        ->and($rows[0]->resolved_download_url)->toBe('https://archive.org/download/x/a.mp4')
        ->and((int) $rows[0]->file_size)->toBe(1234);
});

it('Telawah with a full pool: 503 and a telawah rejection row', function () {
    InMemoryConnection::setup('main', [
        'nuke_telawah_telawah' => MainSchema::nukeTelawahTelawah(),
        DownloadRejectionRecorder::TABLE => MainSchema::downloadRejections(),
    ]);
    DB::connection('main')->table('nuke_telawah_telawah')->insert(['id' => 2, 'link' => 'https://archive.org/download/t/1.mp3', 'downcount' => 0, 'title' => 'تلاوة']);
    f04pFillPool();

    $this->get('/recite-download-2.htm')->assertStatus(503)->assertHeader('Retry-After', '60');

    expect(DB::connection('main')->table(DownloadRejectionRecorder::TABLE)->value('module'))->toBe('telawah')
        ->and(f04pCount('nuke_telawah_telawah', 2))->toBe(0);
});

// ---------------------------------------------------------------- HEAD, external media, operated hosts

it('HEAD: attachment headers without a slot, without contacting the source, without counting', function () {
    f04pVariety(11, 'http://127.0.0.1:1/never-contacted.mp4');
    f04pFillPool();   // a HEAD must not need a slot

    $response = $this->call('HEAD', '/var-download-11.htm')->assertOk();

    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment')
        ->and(f04pCount('nuke_anasheed_anasheed', 11))->toBe(0);
});

it('YouTube and SoundCloud links keep their redirect: no slot, counted on GET only', function (string $stored, string $expected) {
    f04pVariety(12, 'https://archive.org/download/x/a.mp4');
    DB::connection('main')->table('nuke_anasheed_mirror')->insert(['id' => 30, 'khid' => 12, 'link' => $stored, 'hidden' => 0, 'hits' => 0]);
    f04pFillPool();   // proves no slot is needed

    $this->call('HEAD', '/var-mirror-12-30.htm')->assertRedirect($expected);
    expect(f04pCount('nuke_anasheed_mirror', 30, 'hits'))->toBe(0);

    $this->get('/var-mirror-12-30.htm')->assertRedirect($expected)->assertHeader('Cache-Control', 'no-store, private');
    expect(f04pCount('nuke_anasheed_mirror', 30, 'hits'))->toBe(1);
})->with([
    'youtube' => ['http://youtu.be/abc123', 'https://youtu.be/abc123'],
    'soundcloud' => ['https://soundcloud.com/way2allah/track', 'https://soundcloud.com/way2allah/track'],
]);

it('an operated host with a verified attachment rule keeps the direct header mechanism: marker redirect, no slot', function () {
    config(['download_save.attachment_query_hosts' => ['vars.way2allah.com']]);
    f04pVariety(13, 'http://vars.way2allah.com/cartoon/a.mp4');
    f04pFillPool();

    $this->get('/var-download-13.htm')->assertRedirect('https://vars.way2allah.com/cartoon/a.mp4?w2a_save=1');
    expect(f04pCount('nuke_anasheed_anasheed', 13))->toBe(1);
});

it('vars.way2allah.com NOT configured: it goes through the protected proxy like any other host', function () {
    f04pVariety(14, 'https://vars.way2allah.com/cartoon/a.mp4');
    f04pFillPool();

    $this->get('/var-download-14.htm')->assertStatus(503);
});

// ---------------------------------------------------------------- visibility and missing items

it('hidden or missing items and mirrors are 404 before any slot or count', function () {
    f04pVariety(15, $this->base.'/files/talk.mp3', ['hidden' => 1]);
    f04pVariety(16, $this->base.'/files/talk.mp3');
    DB::connection('main')->table('nuke_anasheed_mirror')->insert(['id' => 40, 'khid' => 15, 'link' => $this->base.'/files/talk.mp3', 'hidden' => 0, 'hits' => 0]);
    DB::connection('main')->table('nuke_anasheed_mirror')->insert(['id' => 41, 'khid' => 16, 'link' => $this->base.'/files/talk.mp3', 'hidden' => 1, 'hits' => 0]);
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 17, 'author' => 0, 'title' => 'مخفي', 'hidden' => 1, 'downcount' => 0, 'link' => $this->base.'/files/talk.mp3']);

    $this->get('/var-download-15.htm')->assertNotFound();      // hidden item
    $this->get('/var-mirror-15-40.htm')->assertNotFound();     // mirror of a hidden item
    $this->get('/var-mirror-16-41.htm')->assertNotFound();     // hidden mirror
    $this->get('/var-download-999.htm')->assertNotFound();
    $this->get('/recite-download-999.htm')->assertNotFound();
    $this->get('/fatawa-download-999.htm')->assertNotFound();
    $this->get('/lesson-download-17.htm')->assertNotFound();   // hidden lesson (Khotab's download rule)
    $this->get('/lesson-download-999.htm')->assertNotFound();

    expect(f04pCount('nuke_anasheed_anasheed', 15))->toBe(0)
        ->and(f04pCount('nuke_anasheed_mirror', 40, 'hits'))->toBe(0)
        ->and(f04pCount('nuke_islamic_khotab', 17))->toBe(0);
    $probe = app(DownloadCapacityGuard::class)->tryAcquire();
    expect($probe)->not->toBeNull();
    $probe->release();
});

// ---------------------------------------------------------------- security of the new surface

it('never reads a local file for these modules: a path-like link is redirected as before, not streamed', function (string $link) {
    f04pVariety(18, $link);

    $response = $this->get('/var-download-18.htm');

    expect($response->getStatusCode())->toBe(302)
        ->and((string) $response->headers->get('Content-Disposition'))->toBe('');
})->with(['dot-env' => ['../.env'], 'absolute' => ['/etc/hostname'], 'site path' => ['/cartoon/amin2/a.mp4'], 'file scheme' => ['file:///etc/hostname']]);

it('with the security default ON, loopback / private / disguised IP hosts are not fetched (redirect as before)', function (string $link) {
    config(['download_save.proxy_block_private_hosts' => true]);
    f04pVariety(19, $link);
    f04pFillPool();   // if it reached the proxy this would be a 503

    expect($this->get('/var-download-19.htm')->getStatusCode())->toBe(302);
})->with([
    'loopback' => ['http://127.0.0.1/a.mp4'], 'localhost' => ['http://localhost/a.mp4'], 'private 10/8' => ['http://10.0.0.5/a.mp4'],
    'private 192.168' => ['http://192.168.1.10/a.mp4'], 'link-local' => ['http://169.254.169.254/latest/meta-data'],
    'ipv6 loopback' => ['http://[::1]/a.mp4'], 'decimal IP' => ['http://2130706433/a.mp4'], 'userinfo' => ['http://user@archive.org/a.mp4'],
    'loopback on another port' => ['http://127.0.0.1:8080/a.mp4'],
]);

it('the proxy rule accepts ordinary public hosts and the public IP already stored in Variety links', function () {
    config(['download_save.proxy_block_private_hosts' => true]);

    expect(ProxiedDownloadResponder::proxyable('https://archive.org/download/x/a.mp4'))->toBeTrue()
        ->and(ProxiedDownloadResponder::proxyable('https://ia800204.us.archive.org/5/items/x/a.mp4'))->toBeTrue()
        ->and(ProxiedDownloadResponder::proxyable('http://94.130.66.27/a.mp4'))->toBeTrue()
        ->and(ProxiedDownloadResponder::proxyable('https://quranrabbi.ghyth.com/1.mp3'))->toBeTrue();
});

it('an empty Fatwa link still goes home (counted), unchanged', function () {
    DB::connection('main')->table('nuke_fatwa_questions')->insert(['id' => 20, 'media_link' => '', 'num_download' => 0]);

    expect($this->get('/fatawa-download-20.htm')->headers->get('Location'))->toBe('/')
        ->and(f04pCount('nuke_fatwa_questions', 20, 'num_download'))->toBe(1);
});

it('playback is unchanged: the Variety page still links its save button to the same route and the player is not proxied', function () {
    f04pVariety(21, 'https://archive.org/download/x/clip.mp4');

    $html = $this->get('/var-item-21.htm')->assertOk()->getContent();

    expect($html)->toContain('/var-download-21.htm')->not->toContain('w2a_save');
});
