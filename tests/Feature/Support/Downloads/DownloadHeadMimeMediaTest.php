<?php

/*
 * Phase 3B.1 — (1) Content-Type of proxied Khotab downloads from an explicit allowlist (never the upstream's), with
 * nosniff; (2) HEAD on the redirect download routes: same redirect, no counter; (3) YouTube / SoundCloud mirror pages
 * answered by a 302 (exact host allowlist, rebuilt URL, no fetch, no slot; GET counts once, HEAD never);
 * (4) source-link query strings redacted from logs.
 */

use App\Support\Downloads\DownloadCapacityGuard;
use App\Support\Downloads\DownloadMime;
use App\Support\Downloads\DownloadRejectionRecorder;
use App\Support\Downloads\ExternalMediaLink;
use App\Support\Downloads\ProxiedDownloadStreamer;
use App\Support\Downloads\Telemetry\BatchInspector;
use App\Support\Downloads\Telemetry\DownloadTelemetry;
use App\Support\Downloads\Telemetry\DropLedger;
use App\Support\Downloads\Telemetry\TelemetryEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\Support\ContextualAdminHarness as H;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\LocalHttpSource;

uses(RefreshDatabase::class);

function hmmCount(string $table, int $id, string $column): int
{
    return (int) DB::connection('main')->table($table)->where('id', $id)->value($column);
}

function hmmEvents(object $test): array
{
    $events = [];
    foreach (glob($test->scratch.'/spool/active/*.ndjson') ?: [] as $file) {
        BatchInspector::inspect($file, function (TelemetryEvent $e) use (&$events) {
            $events[] = $e->toArray();
        });
    }

    return $events;
}

beforeEach(function () {
    H::setUp();
    Schema::connection('main')->create(DownloadRejectionRecorder::TABLE, MainSchema::downloadRejections());
    $this->scratch = sys_get_temp_dir().'/hmm-'.bin2hex(random_bytes(5));
    mkdir($this->scratch);
    config([
        'downloads.guard' => true, 'downloads.max_concurrent' => 1, 'downloads.slot_directory' => $this->scratch.'/slots',
        'downloads.remote_connect_timeout' => 1.0, 'downloads.remote_idle_timeout' => 1.0,
        'download_telemetry' => ['events' => 'on', 'spool' => 'on', 'root' => $this->scratch.'/spool', 'disk_reserve_bytes' => 0],
    ]);
    DropLedger::resetProcessStateForTesting();
    app()->forgetScopedInstances();
    app()->forgetInstance(DownloadTelemetry::class);
    H::insert('nuke_islamic_authors', ['id' => 7, 'name' => 'الشيخ', 'prename' => 'د.']);
    $this->item = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'خطبة', 'vedio' => 1, 'link' => 'x', 'linksize' => 1, 'downcount' => 3, 'pdf' => 2]);
    $this->source = null;
    $this->held = [];
});

afterEach(function () {
    foreach ($this->held as $slot) {
        $slot?->release();
    }
    $this->source?->stop();
    DropLedger::resetProcessStateForTesting();
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->scratch, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $file) {
        $file->isDir() || $file->isLink() ? (@rmdir($file->getPathname()) || @unlink($file->getPathname())) : @unlink($file->getPathname());
    }
    @rmdir($this->scratch);
});

// --- 1. Content-Type ------------------------------------------------------------------------------------------

it('local files: Content-Type from the allowlist by extension, nosniff, attachment, body unchanged — GET and HEAD', function (string $name, string $type) {
    $file = $this->scratch.'/'.$name;
    file_put_contents($file, 'bytes-of-'.$name);
    DB::connection('main')->table('nuke_islamic_khotab')->where('id', $this->item)->update(['link' => $file]);

    $get = $this->get("/khotab-download-{$this->item}.htm");
    $head = $this->call('HEAD', "/khotab-download-{$this->item}.htm");

    foreach ([$get, $head] as $response) {
        $response->assertOk()->assertHeader('Content-Type', $type)->assertHeader('X-Content-Type-Options', 'nosniff');
        expect($response->headers->get('Content-Disposition'))->toStartWith('attachment; filename=');
    }
    expect($get->streamedContent())->toBe('bytes-of-'.$name)
        ->and($head->streamedContent())->toBe('');
})->with([
    'mp3' => ['lecture.mp3', 'audio/mpeg'],
    'mp4' => ['lecture.mp4', 'video/mp4'],
    'pdf' => ['transcript.pdf', 'application/pdf'],
    'upper-case extension' => ['LECTURE.MP3', 'audio/mpeg'],
    'doc' => ['notes.doc', 'application/msword'],
    'unknown' => ['archive.rar', 'application/octet-stream'],
    'html is never served as html' => ['page.html', 'application/octet-stream'],
    'no extension' => ['lecture', 'application/octet-stream'],
]);

it('local files: the type follows the file actually opened (symlink resolved), not the link name', function () {
    file_put_contents($this->scratch.'/real.pdf', '%PDF');
    symlink($this->scratch.'/real.pdf', $this->scratch.'/looks-like.mp3');

    expect(DownloadMime::for($this->scratch.'/looks-like.mp3', false))->toBe('application/pdf');
});

it('remote sources: the type comes from the stored link\'s path — never from the upstream Content-Type or the query string', function () {
    // (A text/html upstream is still rejected, unchanged.) A wrong, non-HTML upstream type is ignored:
    $this->source = LocalHttpSource::start([LocalHttpSource::ok('audio-bytes', 'application/x-evil')]);
    DB::connection('main')->table('nuke_islamic_khotab')->where('id', $this->item)->update(['link' => $this->source->url('/media/talk.mp3?name=x.pdf')]);

    $response = $this->get("/khotab-download-{$this->item}.htm")->assertOk();

    $response->assertHeader('Content-Type', 'audio/mpeg')->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->streamedContent())->toBe('audio-bytes')
        ->and(DownloadMime::for('https://h.test/x/file.bin?type=.mp4', true))->toBe('application/octet-stream')
        ->and(DownloadMime::for('https://h.test/x/%D8%AE.pdf', true))->toBe('application/pdf');
});

// --- 2. Redirect routes: HEAD -----------------------------------------------------------------------------------

it('redirect routes: HEAD gives the same status and Location as GET, and never counts', function (string $table, string $column, Closure $make) {
    [$id, $uri] = $make($this);
    $before = hmmCount($table, $id, $column);

    $head = $this->call('HEAD', $uri);
    expect(hmmCount($table, $id, $column))->toBe($before);
    $get = $this->get($uri);

    expect($head->getStatusCode())->toBe($get->getStatusCode())->toBe(302)
        ->and($head->headers->get('Location'))->toBe($get->headers->get('Location'))
        ->and(hmmCount($table, $id, $column))->toBe($before + 1);   // GET still counts exactly once
})->with([
    'khotab-item-pdf' => ['nuke_islamic_khotab', 'pdf', fn ($t) => [$t->item, "/khotab-item-pdf-{$t->item}.htm"]],
]);

// F04 (2026-11-17): these save links now proxy the file. HEAD answers like the Khotab HEAD — attachment headers, no
// Location, no slot, no source contact — and never counts. (No GET here: it would fetch the source.)
it('proxied save links: HEAD answers with attachment headers, no redirect, and never counts', function (string $table, string $column, Closure $make) {
    [$id, $uri] = $make($this);
    $before = hmmCount($table, $id, $column);

    $head = $this->call('HEAD', $uri)->assertOk();

    expect($head->headers->get('Content-Disposition'))->toStartWith('attachment')
        ->and($head->headers->get('Location'))->toBeNull()
        ->and(hmmCount($table, $id, $column))->toBe($before);
})->with([
    'lesson-download' => ['nuke_islamic_khotab', 'downcount', function ($t) {
        DB::connection('main')->table('nuke_islamic_khotab')->where('id', $t->item)->update(['link' => 'https://archive.invalid/l.mp3']);

        return [$t->item, "/lesson-download-{$t->item}.htm"];
    }],
    'recite-download' => ['nuke_telawah_telawah', 'downcount', function () {
        $id = H::insert('nuke_telawah_telawah', ['title' => 'تلاوة', 'link' => 'https://archive.invalid/t.mp3', 'downcount' => 1]);

        return [$id, "/recite-download-{$id}.htm"];
    }],
    'var-download' => ['nuke_anasheed_anasheed', 'downcount', function () {
        $id = H::insert('nuke_anasheed_anasheed', ['title' => 'نشيد', 'link' => 'http://archive.invalid/n.mp3', 'downcount' => 1]);

        return [$id, "/var-download-{$id}.htm"];
    }],
    'var-mirror' => ['nuke_anasheed_mirror', 'hits', function () {
        $item = H::insert('nuke_anasheed_anasheed', ['title' => 'نشيد', 'link' => 'http://archive.invalid/n.mp3', 'downcount' => 1]);
        $id = H::insert('nuke_anasheed_mirror', ['khid' => $item, 'link' => 'http://archive.invalid/nm.mp3', 'hits' => 1]);

        return [$id, "/var-mirror-{$item}-{$id}.htm"];
    }],
    'fatawa-download' => ['nuke_fatwa_questions', 'num_download', function () {
        DB::connection('main')->table('nuke_fatwa_questions')->insert(['id' => 7, 'media_link' => 'https://way2allah.com/files/answer7.mp4', 'num_download' => 2]);

        return [7, '/fatawa-download-7.htm'];
    }],
]);

// --- 3. External media ------------------------------------------------------------------------------------------

it('classifies only exact allowlisted hosts over http(s), and rebuilds an https URL keeping path, query and fragment', function (string $link, ?string $expected) {
    expect(ExternalMediaLink::classify($link)['url'] ?? null)->toBe($expected);
})->with([
    'youtube watch' => ['https://www.youtube.com/watch?v=abc123&t=10s', 'https://www.youtube.com/watch?v=abc123&t=10s'],
    'http upgraded' => ['http://youtube.com/watch?v=abc', 'https://youtube.com/watch?v=abc'],
    'short link' => ['https://youtu.be/abc', 'https://youtu.be/abc'],
    'mixed-case host, trailing dot' => ['https://WWW.YouTube.COM./watch?v=x', 'https://www.youtube.com/watch?v=x'],
    'explicit default port' => ['https://soundcloud.com:443/artist/track', 'https://soundcloud.com/artist/track'],
    'fragment kept' => ['https://soundcloud.com/a/b#t=1:00', 'https://soundcloud.com/a/b#t=1:00'],
    'no path' => ['https://youtube.com', 'https://youtube.com/'],
    'look-alike suffix' => ['https://youtube.com.evil.test/watch?v=x', null],
    'look-alike prefix' => ['https://evilyoutube.com/watch', null],
    'subdomain not listed' => ['https://music.youtube.com/watch?v=x', null],
    'userinfo' => ['https://user:pass@youtube.com/watch', null],
    'userinfo host trick' => ['https://youtube.com@evil.test/watch', null],
    'unusual port' => ['https://youtube.com:8443/watch', null],
    'http on 443' => ['http://youtube.com:443/watch', null],
    'ftp scheme' => ['ftp://youtube.com/x', null],
    'javascript' => ['javascript://youtube.com/%0aalert(1)', null],
    'protocol-relative' => ['//youtube.com/watch', null],
    'backslash trick' => ['https://evil.test\\@youtube.com/', null],
    'CRLF' => ["https://youtube.com/watch\r\nX-Evil: 1", null],
    'space' => ['https://youtube.com/watch?v=a b', null],
    'archive.org file' => ['https://archive.org/download/x/y.mp3', null],
    'too long' => ['https://youtube.com/watch?v='.str_repeat('a', 2100), null],
    'empty' => ['', null],
]);

it('a YouTube mirror: GET is a 302 to the rebuilt URL, counts hits once, takes no slot, fetches nothing, records no transfer', function () {
    $mirror = H::insert('nuke_islamic_mirror', ['khid' => $this->item, 'link' => 'http://www.youtube.com/watch?v=abc&list=PL1', 'hits' => 4]);
    $this->held[] = app(DownloadCapacityGuard::class)->tryAcquire();   // pool full: a redirect needs no slot

    $response = $this->get("/khotab-mirror-{$this->item}-{$mirror}.htm");

    $response->assertStatus(302)->assertHeader('Location', 'https://www.youtube.com/watch?v=abc&list=PL1');
    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and(hmmCount('nuke_islamic_mirror', $mirror, 'hits'))->toBe(5)
        ->and(DB::connection('main')->table(DownloadRejectionRecorder::TABLE)->count())->toBe(0);
    $events = hmmEvents($this);
    expect(array_column($events, 'ev'))->toBe(['redirect_issued'])
        ->and($events[0])->toMatchArray(['st' => 'redirect', 'sh' => 'www.youtube.com', 'k' => 'youtube', 'var' => 'mirror', 'vid' => $mirror]);
});

it('a SoundCloud mirror: HEAD is the same 302 and counts nothing', function () {
    $mirror = H::insert('nuke_islamic_mirror', ['khid' => $this->item, 'link' => 'https://soundcloud.com/way2allah/lecture-1', 'hits' => 4]);

    $this->call('HEAD', "/khotab-mirror-{$this->item}-{$mirror}.htm")
        ->assertStatus(302)->assertHeader('Location', 'https://soundcloud.com/way2allah/lecture-1');

    expect(hmmCount('nuke_islamic_mirror', $mirror, 'hits'))->toBe(4)
        ->and(array_column(hmmEvents($this), 'ev'))->toBe(['head_answered']);
});

it('a look-alike host is NOT redirected: it takes the normal proxied path (an HTML page stays a 500)', function () {
    $this->source = LocalHttpSource::start([LocalHttpSource::ok('<html>fake</html>', 'text/html')]);
    $mirror = H::insert('nuke_islamic_mirror', ['khid' => $this->item, 'link' => $this->source->url('/youtube.com/watch?v=x'), 'hits' => 4]);

    $this->get("/khotab-mirror-{$this->item}-{$mirror}.htm")->assertStatus(500);

    expect($this->source->requests())->toHaveCount(1)   // proxied (and refused), not redirected
        ->and(array_column(hmmEvents($this), 'ev'))->toBe(['download_accepted', 'source_rejected']);
});

it('primary Khotab downloads are unaffected by the media policy (mirrors only)', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->where('id', $this->item)->update(['link' => 'https://www.youtube.com/watch?v=abc']);

    // HEAD contacts nothing: the primary route still answers as a download (200, attachment), not with a redirect.
    $this->call('HEAD', "/khotab-download-{$this->item}.htm")->assertOk()->assertHeader('Content-Type', 'application/octet-stream');
    expect(hmmCount('nuke_islamic_khotab', $this->item, 'downcount'))->toBe(3);
});

// --- 4. Log redaction -------------------------------------------------------------------------------------------

it('source problems are logged without the query string or credentials', function () {
    Log::spy();
    $probe = stream_socket_server('tcp://127.0.0.1:0');
    $address = stream_socket_get_name($probe, false);
    fclose($probe);
    DB::connection('main')->table('nuke_islamic_khotab')->where('id', $this->item)
        ->update(['link' => "http://user:secret@{$address}/files/talk.mp3?X-Amz-Signature=SECRET-SIG&token=SECRET-TOKEN"]);

    $this->get("/khotab-download-{$this->item}.htm")->assertStatus(500);

    Log::shouldHaveReceived('warning')->withArgs(function (string $m, array $c) use ($address) {
        $encoded = json_encode($c);

        return $c['event'] === 'DOWNLOAD_SOURCE_OPEN_FAILED' && $c['link'] === "http://{$address}/files/talk.mp3?[redacted]"
            && ! str_contains($encoded, 'SECRET') && ! str_contains($encoded, 'secret');
    })->once();
    expect(ProxiedDownloadStreamer::redactLink('/var/media/local file.mp3'))->toBe('/var/media/local file.mp3')
        ->and(ProxiedDownloadStreamer::redactLink('https://a.test/x.mp3'))->toBe('https://a.test/x.mp3');
});
