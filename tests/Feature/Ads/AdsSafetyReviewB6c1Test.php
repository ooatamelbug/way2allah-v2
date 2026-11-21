<?php

/*
 * F01 / B6c-1 — owner's final safety review, scenarios 1–10 ("B6c-1 Owner Review" §2). Every scenario asserts:
 * no stored advertising code in the output, no script / event-handler / javascript: markup, and no write.
 */

use App\Domain\Admin\Support\SafeExternalLink;
use App\Domain\Content\Ads\HomeBannerStripService;
use App\Support\Http\SafeExternalUrl;
use Illuminate\Support\Facades\DB;
use Tests\Support\AdsRenderingHarness as H;

const B6C1_CODE = '<script>window.B6C1_EXECUTED=1</script><ins class="adsbygoogle B6C1-CODE"></ins><img src=x onerror=alert(1)>';

beforeEach(function () {
    $this->media = H::setUp();
});

afterEach(fn () => H::removeMedia($this->media));

/** Output must never carry executable markup or stored code. */
function b6c1Inert(string $html): void
{
    expect($html)->not->toContain('<script')->not->toContain('B6C1-CODE')->not->toContain('B6C1_EXECUTED')
        ->not->toContain('javascript:')->not->toMatch('/\son[a-z]+\s*=/i')->not->toMatch('/<dialog[^>]*\bopen\b/');
}

/** Render every position and the strip; return the concatenated output. */
function b6c1RenderAll(): string
{
    $html = '';
    foreach (range(1, 12) as $p) {
        $html .= H::slot($p);
    }

    return $html.H::strip();
}

function b6c1Fixtures(string $media): void
{
    foreach (range(1, 12) as $p) {
        H::ad($p);
        H::ad($p, ['type' => 0, 'image_path' => B6C1_CODE, 'link' => '']);
    }
    $item = H::item('بنر');
    H::bannerFile($media, $item);
    DB::connection('main')->table('nuke_banners_ads')->insert(['media_id' => $item, 'creation_date' => '2019-12-01 06:43:29']);
    DB::connection('main')->table('nuke_banners')->insert(['cat' => 'montada', 'title1' => 'f', 'url1' => 'https://forums.example.org/1', 'image1' => 'https://example.org/f1.png']);
}

it('scenarios 1–3: each flag combination renders only what it allows, inertly, with no write', function (bool $main, bool $popup, bool $inline, bool $dialog) {
    b6c1Fixtures($this->media);
    config(['ads.rendering_enabled' => $main, 'ads.popup_enabled' => $popup]);
    $before = [H::rows('nuke_ads'), H::rows('nuke_banners_ads'), H::rows('nuke_banners'), H::rows('nuke_islamic_khotab')];
    H::$queries = [];

    $html = '';
    for ($i = 0; $i < 10; $i++) {   // repeated: code and image ads both get drawn
        $html .= b6c1RenderAll();
    }
    $queries = H::$queries;
    $writes = H::writes();

    b6c1Inert($html);
    expect(str_contains($html, 'b6c1-4.jpg'))->toBe($inline)
        ->and(str_contains($html, 'w2a_home_banners'))->toBe($inline)
        ->and(str_contains($html, '<dialog'))->toBe($dialog)
        ->and($writes)->toBe([])
        ->and([H::rows('nuke_ads'), H::rows('nuke_banners_ads'), H::rows('nuke_banners'), H::rows('nuke_islamic_khotab')])->toBe($before);
    if (! $main) {
        expect($queries)->toBe([]);
    }
})->with([
    '1. both flags OFF' => [false, false, false, false],
    '1b. popup flag alone ON (no effect)' => [false, true, false, false],
    '2. main ON, popup OFF' => [true, false, true, false],
    '3. both ON (popup still closed, no script)' => [true, true, true, true],
]);

it('scenario 4: a selected raw code ad outputs nothing and stays stored unchanged', function () {
    H::on(popup: true);
    foreach (range(1, 12) as $p) {
        H::ad($p, ['type' => 0, 'image_path' => B6C1_CODE, 'link' => 'https://example.org/']);
    }
    $before = H::rows('nuke_ads');
    H::$queries = [];

    foreach (range(1, 12) as $p) {
        expect(H::slot($p))->toBe('');
    }
    expect(H::rows('nuke_ads'))->toBe($before)->and(H::writes())->toBe([]);
});

it('scenario 5: a selected ad with an unsafe stored URL is withheld; the stored value is not rewritten', function () {
    H::on(popup: true);
    foreach (range(1, 12) as $p) {
        H::ad($p, ['link' => "javascript:alert('x')"]);
        H::ad($p, ['image_path' => "https://example.org/x.jpg' onerror='alert(1)", 'link' => 'https://example.org/']);
    }
    $before = H::rows('nuke_ads');
    H::$queries = [];

    $html = '';
    for ($i = 0; $i < 10; $i++) {
        $html .= b6c1RenderAll();
    }

    expect($html)->toBe('')->and(H::rows('nuke_ads'))->toBe($before)->and(H::writes())->toBe([]);
});

it('scenario 6: no eligible advertisement renders an empty slot (no fallback image)', function () {
    H::on(popup: true);
    foreach ([1, 2, 3] as $p) {
        H::ad($p, ['show' => 0]);
        H::ad($p, ['ads_show_type' => 'period', 'startdate' => '2020-01-01', 'enddate' => '2020-02-01']);
    }

    foreach (range(1, 12) as $p) {
        expect(H::slot($p))->toBe('');
    }
    expect(H::slot(1).H::slot(2).H::slot(3))->not->toContain('ads.jpg')->not->toContain('ads3.jpg');
});

it('scenario 7: a view-limited ad AT its limit is shown (OLD\'s <=) and its counter never moves', function () {
    H::on();
    $id = H::ad(4, ['ads_show_type' => 'numview', 'num_view' => 10, 'required_num_view' => 10]);
    H::$queries = [];

    for ($i = 0; $i < 25; $i++) {
        expect(H::slot(4))->toContain('b6c1-4.jpg');
    }

    $row = DB::connection('main')->table('nuke_ads')->find($id);
    expect((int) $row->num_view)->toBe(10)->and((int) $row->show)->toBe(1)->and(H::writes())->toBe([]);
});

it('scenarios 8–9: deleted, hidden, bannerless or file-less selections are never drawn and never modified', function () {
    H::on();
    $hidden = H::item('مخفية', 1, 1);
    H::bannerFile($this->media, $hidden);
    $bannerless = H::item('بلا بنر', 0);
    H::bannerFile($this->media, $bannerless);
    $noFile = H::item('بلا ملف', 1);
    $ok = H::item('صالحة', 1);
    H::bannerFile($this->media, $ok);
    foreach ([$hidden, $bannerless, $noFile, 987654, $ok] as $id) {
        DB::connection('main')->table('nuke_banners_ads')->insert(['media_id' => $id, 'creation_date' => '2019-12-01 06:43:29']);
    }
    $before = H::rows('nuke_banners_ads');
    $strips = app(HomeBannerStripService::class);
    H::$queries = [];

    for ($i = 0; $i < 50; $i++) {
        expect($strips->pickItem()['id'])->toBe($ok);
    }
    unlink($this->media.'/banner/0/'.$ok.'.gif');   // scenario 9: the only valid file disappears
    expect($strips->pickItem())->toBeNull()->and(H::strip())->toBe('')
        ->and(H::rows('nuke_banners_ads'))->toBe($before)->and(H::writes())->toBe([]);
});

it('scenario 10: queries are bounded per render and do not amplify with repetition or pool size', function () {
    H::on(popup: true);
    foreach (range(1, 12) as $p) {
        for ($n = 0; $n < 20; $n++) {   // a large, mixed pool per position
            H::ad($p, $n % 3 === 0 ? ['type' => 0, 'image_path' => B6C1_CODE] : ($n % 3 === 1 ? ['show' => 0] : []));
        }
    }
    for ($n = 0; $n < 40; $n++) {
        DB::connection('main')->table('nuke_banners_ads')->insert(['media_id' => H::item('x'.$n, $n % 2), 'creation_date' => '2019-12-01 06:43:29']);
    }
    DB::connection('main')->table('nuke_banners')->insert(['cat' => 'montada']);

    H::$queries = [];
    H::slot(4);
    expect(H::$queries)->toHaveCount(1);                    // one SELECT per slot render

    H::$queries = [];
    H::strip();
    expect(H::$queries)->toHaveCount(2);                    // selection + forum row, whatever the pool size

    H::$queries = [];
    for ($i = 0; $i < 50; $i++) {
        b6c1RenderAll();
    }
    expect(H::$queries)->toHaveCount(50 * (12 + 2))           // linear in renders: no N+1, no retry loop
        ->and(H::writes())->toBe([]);

    config(['ads.popup_enabled' => false]);
    H::$queries = [];
    H::slot(12);
    expect(H::$queries)->toBe([]);                            // the disabled popup costs nothing
});

it('architecture: B6b\'s SafeExternalLink and B6a\'s AdRequest rule give the same verdicts as the shared SafeExternalUrl', function (string $url) {
    // B6a's AdRequest keeps its own (unchanged) copy of the rule; B6b's SafeExternalLink delegates.
    $b6a = preg_match('#^https?://#i', $url) === 1 && filter_var($url, FILTER_VALIDATE_URL) !== false
        && preg_match('/[\s\x00-\x1F\x7F<>"\'`\\\\]/u', $url) !== 1;

    expect(SafeExternalLink::href($url))->toBe(SafeExternalUrl::href($url))
        ->and(SafeExternalUrl::href($url) !== null)->toBe($b6a);
})->with([
    'https://example.org/a?b=1&c=2', 'http://example.org', 'HTTPS://EXAMPLE.ORG/', 'javascript:alert(1)', 'data:text/html,x',
    '//evil.example/x', '/media/x.jpg', 'ttps://x.org', "https://x.org/a'b", 'https://x.org/a"b', 'https://x.org/<b>',
    'https://x.org/a b', "https://x.org/\tb", 'https://x.org\\@evil', 'https://x.org/`', 'https://مثال.org/', '',
]);
