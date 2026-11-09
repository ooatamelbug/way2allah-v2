<?php

/*
 * IF-056 — social-sharing metadata parity with Gen 2 `header.php` (owner-approved, 2026-11-08).
 * Gen 2 emits og:image / og:title / og:url / og:description / og:type / twitter:image /
 * image_src / videothumbnail on every page; the image is `logo-light.png` unless the page sets
 * `$video_thumb` (only `khotab/item.php`: the item's gif when `gif = 1`, else its frame).
 */

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

const SMP_HOST = 'https://way2allah.com';

beforeEach(function () {
    InMemoryConnection::setup('main', [
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_islamic_series' => MainSchema::nukeIslamicSeries(),
        'nuke_islamic_groups' => MainSchema::nukeIslamicGroups(),
        'nuke_islamic_advanced' => MainSchema::nukeIslamicAdvanced(),
        'nuke_islamic_mirror' => MainSchema::nukeIslamicMirror(),
        'nuke_islamic_advanced_m' => MainSchema::nukeIslamicAdvancedM(),
        'nuke_islamic_comments' => MainSchema::nukeIslamicComments(),
        'ips' => MainSchema::ips(),
        'nuke_w2a_cat' => MainSchema::nukeW2aCat(),
        'khotab_category_index' => MainSchema::khotabCategoryIndex(),
    ]);

    config(['app.url' => SMP_HOST]);

    // Isolated media root — the real public/media tree is never written to.
    $this->smpMediaRoot = sys_get_temp_dir().'/w2a-social-meta-'.bin2hex(random_bytes(6));
    mkdir($this->smpMediaRoot, 0775, true);
    config(['admin_khotab.media_root' => $this->smpMediaRoot]);
});

afterEach(function () {
    if (is_dir($this->smpMediaRoot)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->smpMediaRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->smpMediaRoot);
    }
});

function smpSeedItem(int $id, string $title, int $gif = 0): void
{
    $db = DB::connection('main');
    if ($db->table('nuke_islamic_authors')->where('id', 1)->doesntExist()) {
        $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'محمد حسان', 'prename' => 'الشيخ']);
    }
    $db->table('nuke_islamic_khotab')->insert([
        'id' => $id, 'author' => 1, 'title' => $title, 'vedio' => 1, 'hidden' => 0, 'gif' => $gif,
    ]);
}

function smpTouchMedia(string $root, string $relativeUnderMedia): void
{
    $path = $root.'/'.$relativeUnderMedia;
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0775, true);
    }
    file_put_contents($path, 'img');
}

/**
 * Decoded social tag values, keyed by tag; each key lists every occurrence.
 *
 * @return array<string, list<string>>
 */
function smpTags(string $html): array
{
    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
    libxml_clear_errors();

    $tags = [];
    foreach ($dom->getElementsByTagName('meta') as $meta) {
        $key = $meta->getAttribute('property') ?: $meta->getAttribute('name');
        if (str_starts_with($key, 'og:') || str_starts_with($key, 'twitter:')) {
            $tags[$key][] = $meta->getAttribute('content');
        }
    }
    foreach ($dom->getElementsByTagName('link') as $link) {
        $rel = $link->getAttribute('rel');
        if (in_array($rel, ['image_src', 'videothumbnail'], true)) {
            $tags[$rel][] = $link->getAttribute('href');
        }
    }

    return $tags;
}

it('a normal page emits Gen 2\'s social tags with the default logo image', function () {
    $html = $this->get('/privacy')->assertOk()->getContent();
    $tags = smpTags($html);

    expect($tags['og:title'])->toBe(['سياسة الخصوصية'])
        ->and($tags['og:type'])->toBe(['website'])
        ->and($tags['og:url'])->toBe([SMP_HOST.'/privacy'])
        ->and($tags['og:description'])->toBe(['شبكة الطريق إلى الله: مكتبة إسلامية مرئية وصوتية شاملة.'])
        ->and($tags['og:image'])->toBe([SMP_HOST.'/logo-light.png'])
        ->and($tags['twitter:image'])->toBe([SMP_HOST.'/logo-light.png'])
        ->and($tags['image_src'])->toBe([SMP_HOST.'/logo-light.png'])
        ->and($tags['videothumbnail'])->toBe([SMP_HOST.'/logo-light.png']);
});

it('the default share image is a real public asset', function () {
    expect(is_file(public_path('logo-light.png')))->toBeTrue();
});

it('a Khotab item with a frame uses its item-specific frame image', function () {
    smpSeedItem(77, 'درس الفجر');
    smpTouchMedia($this->smpMediaRoot, 'khotab_frames/0/77.jpg');

    $tags = smpTags($this->get('/khotab-item-77.htm')->assertOk()->getContent());

    $expected = SMP_HOST.'/media/khotab_frames/0/77.jpg';
    expect($tags['og:image'])->toBe([$expected])
        ->and($tags['twitter:image'])->toBe([$expected])
        ->and($tags['image_src'])->toBe([$expected])
        ->and($tags['videothumbnail'])->toBe([$expected]);
});

it('a Khotab item with gif = 1 uses its gif, as Gen 2 did (bucketed by id / 1000)', function () {
    smpSeedItem(298784, 'Item', gif: 1);
    smpTouchMedia($this->smpMediaRoot, 'khotab_gifs/298/298784.gif');
    smpTouchMedia($this->smpMediaRoot, 'khotab_frames/298/298784.jpg');

    $tags = smpTags($this->get('/khotab-item-298784.htm')->assertOk()->getContent());

    expect($tags['og:image'])->toBe([SMP_HOST.'/media/khotab_gifs/298/298784.gif']);
});

it('a Khotab item whose image file is missing falls back to the default logo, never a broken URL', function (int $gif, string $presentOtherFile) {
    smpSeedItem(78, 'Item', gif: $gif);
    smpTouchMedia($this->smpMediaRoot, $presentOtherFile);

    $tags = smpTags($this->get('/khotab-item-78.htm')->assertOk()->getContent());

    expect($tags['og:image'])->toBe([SMP_HOST.'/logo-light.png'])
        ->and($tags['twitter:image'])->toBe([SMP_HOST.'/logo-light.png']);
})->with([
    'no frame file' => [0, 'khotab_frames/0/other.jpg'],
    'gif = 1 but no gif file (frame not substituted)' => [1, 'khotab_frames/0/78.jpg'],
]);

it('the item share image honours a configured media host (CDN) unchanged', function () {
    config(['media.base_url' => 'https://media.example.org/media']);
    smpSeedItem(77, 'Item');
    smpTouchMedia($this->smpMediaRoot, 'khotab_frames/0/77.jpg');

    $tags = smpTags($this->get('/khotab-item-77.htm')->assertOk()->getContent());

    expect($tags['og:image'])->toBe(['https://media.example.org/media/khotab_frames/0/77.jpg']);
});

it('escapes quotes, ampersands and angle brackets once, keeping Arabic text intact', function () {
    smpSeedItem(79, 'قال "الشيخ" & <b>درس</b> \'خاص\'');

    $response = $this->get('/khotab-item-79.htm')->assertOk();
    $html = $response->getContent();
    $tags = smpTags($html);

    $expectedTitle = 'قال "الشيخ" & <b>درس</b> \'خاص\' - الشيخ محمد حسان';
    expect($tags['og:title'])->toBe([$expectedTitle])
        ->and($html)->toContain('<meta property="og:title" content="'.e($expectedTitle).'">')
        ->and($html)->not->toContain('&amp;amp;')
        ->and($html)->not->toContain('&amp;quot;')
        ->and(mb_check_encoding($html, 'UTF-8'))->toBeTrue();
});

it('og:url uses the configured public host, never the request host, and keeps the query string', function () {
    smpSeedItem(77, 'Item');

    $html = $this->get('http://127.0.0.1:8123/khotab-item-77.htm?page=2')->assertOk()->getContent();
    $tags = smpTags($html);

    expect($tags['og:url'])->toBe([SMP_HOST.'/khotab-item-77.htm?page=2'])
        ->and(implode(' ', array_merge(...array_values($tags))))->not->toContain('127.0.0.1');

    foreach (['og:url', 'og:image', 'twitter:image', 'image_src', 'videothumbnail'] as $key) {
        expect($tags[$key][0])->toStartWith(SMP_HOST.'/');
    }
});

it('emits each social tag exactly once', function (string $url) {
    smpSeedItem(77, 'Item');

    $tags = smpTags($this->get($url)->assertOk()->getContent());

    expect(array_keys($tags))->toEqualCanonicalizing(
        ['og:image', 'og:title', 'og:url', 'og:description', 'og:type', 'twitter:image', 'image_src', 'videothumbnail'],
    );
    foreach ($tags as $values) {
        expect($values)->toHaveCount(1);
    }
})->with(['/privacy', '/khotab-item-77.htm']);

it('leaves the existing head and item page unchanged: title, description and content', function () {
    smpSeedItem(77, 'درس الفجر');

    $html = $this->get('/khotab-item-77.htm')->assertOk()->getContent();

    expect($html)->toContain('<title>درس الفجر - الشيخ محمد حسان - '.e(config('app.name')).'</title>')
        ->and($html)->toContain('<meta name="description" content="شبكة الطريق إلى الله: مكتبة إسلامية مرئية وصوتية شاملة.">')
        ->and(substr_count($html, '<meta name="description"'))->toBe(1)
        ->and($html)->toContain('درس الفجر');
});
