<?php

/*
 * F01 / B6c-1 — with the switch turned ON in-test only: OLD's twelve positions, OLD's eligibility and selection,
 * safe URL handling, escaping, code-ad withholding, the inert popup, and no counter / auto-hide writes.
 */

use App\Domain\Content\Ads\AdSlotService;
use App\Domain\Content\Ads\LegacyAdEligibility;
use Illuminate\Support\Facades\DB;
use Tests\Support\AdsRenderingHarness as H;

beforeEach(function () {
    $this->media = H::setUp();
    H::on();
});

afterEach(fn () => H::removeMedia($this->media));

// ─── all twelve positions ─────────────────────────────────────────────────────

it('renders an inline position with OLD\'s wrapper, link and image — no onclick, no script', function (int $position, ?string $wrapper) {
    H::ad($position);

    $html = H::slot($position);
    $anchor = '<a href="https://example.org/go-'.$position.'" target="_blank" rel="nofollow noopener"><img border="0" src="https://example.org/b6c1-'.$position.'.jpg" alt=""></a>';

    expect($html)->toContain($anchor)->not->toContain('onclick')->not->toContain('<script')->not->toContain('increaseclick');
    expect(substr_count($html, '<img'))->toBe(1);
    $wrapper === null
        ? expect($html)->toBe($anchor)
        : expect($html)->toStartWith('<div class="'.$wrapper.'">')->toEndWith('</div>');
})->with([
    'pos 1 home left' => [1, null], 'pos 2 home middle' => [2, null], 'pos 3 home right' => [3, null],
    'pos 4 item top' => [4, 'ads_details'], 'pos 5 item side' => [5, 'ads_details_bottom'],
    'pos 6 audio side' => [6, 'ads_details_bottom'], 'pos 7 author top' => [7, 'ads_details_2'],
    'pos 8 author side' => [8, 'ads_details_bottom'], 'pos 9 varieties top' => [9, 'ads_details_2'],
    'pos 10 channel top' => [10, 'ads_details_2'], 'pos 11 player dialog' => [11, 'ads_dialog'],
]);

it('position 12 (popup) renders nothing unless the popup switch is also on', function () {
    H::ad(12);

    expect(H::slot(12))->toBe('');
});

it('position 12 renders an inert CLOSED <dialog> — never opened, no script, no JS-string values', function () {
    H::on(popup: true);
    H::ad(12, ['image_path' => 'http://example.org/popup.jpg', 'link' => 'https://example.org/promo?a=1&b=2']);

    $html = H::slot(12);

    expect($html)->toStartWith('<dialog class="w2a-ad-popup" data-ad-position="12" aria-label="إعلان">')
        ->not->toMatch('/<dialog[^>]*\bopen\b/')
        ->not->toContain('<script')->not->toContain('showModal')->not->toContain('fancybox')->not->toContain('onclick')
        ->not->toContain('ads_link_url')->not->toContain('link_this_ads')
        ->toContain('<a href="https://example.org/promo?a=1&amp;b=2" rel="nofollow noopener"><img src="https://example.org/popup.jpg" width="700" height="500" alt=""></a>')
        ->toContain('<form method="dialog">');
});

it('unknown positions render nothing and query nothing', function (int $position) {
    H::ad(4);
    H::$queries = [];

    expect(H::slot($position))->toBe('')->and(H::$queries)->toBe([]);
})->with([0, 13, -1, 99]);

// ─── eligibility (OLD parity) ─────────────────────────────────────────────────

it('applies OLD\'s eligibility exactly', function (array $ad, bool $eligible) {
    expect(LegacyAdEligibility::isEligible((object) $ad, H::TODAY))->toBe($eligible);
})->with([
    'period: the start day is EXCLUDED (OLD: today > startdate)' => [['ads_show_type' => 'period', 'startdate' => '2026-11-14', 'enddate' => '2026-12-01'], false],
    'period: the day after the start' => [['ads_show_type' => 'period', 'startdate' => '2026-11-13', 'enddate' => '2026-12-01'], true],
    'period: the end day is INCLUDED (today <= enddate)' => [['ads_show_type' => 'period', 'startdate' => '2026-11-01', 'enddate' => '2026-11-14'], true],
    'period: ended' => [['ads_show_type' => 'period', 'startdate' => '2026-11-01', 'enddate' => '2026-11-13'], false],
    'period: not started' => [['ads_show_type' => 'period', 'startdate' => '2026-11-20', 'enddate' => '2026-12-01'], false],
    'period: no end date' => [['ads_show_type' => 'period', 'startdate' => '2026-11-01', 'enddate' => null], false],
    'period: no start date' => [['ads_show_type' => 'period', 'startdate' => null, 'enddate' => '2026-12-01'], true],
    'period: no dates' => [['ads_show_type' => 'period', 'startdate' => null, 'enddate' => null], false],
    'numview: below the limit' => [['ads_show_type' => 'numview', 'num_view' => 5, 'required_num_view' => 10], true],
    'numview: AT the limit (OLD hides only above it)' => [['ads_show_type' => 'numview', 'num_view' => 10, 'required_num_view' => 10], true],
    'numview: above the limit' => [['ads_show_type' => 'numview', 'num_view' => 11, 'required_num_view' => 10], false],
    'numview: -1 («لم يبدأ بعد» in the admin) is shown publicly, as OLD' => [['ads_show_type' => 'numview', 'num_view' => -1, 'required_num_view' => 0], true],
    'any other show type behaves as numview (OLD\'s else branch)' => [['ads_show_type' => 'Period', 'startdate' => '2030-01-01', 'enddate' => '2030-02-01', 'num_view' => 0, 'required_num_view' => 0], true],
]);

it('draws only from enabled, eligible ads of the requested position', function () {
    H::ad(4, ['show' => 0, 'image_path' => 'https://example.org/off.jpg']);
    H::ad(4, ['ads_show_type' => 'period', 'startdate' => '2026-11-14', 'enddate' => '2026-12-01', 'image_path' => 'https://example.org/startday.jpg']);
    H::ad(4, ['num_view' => 1001, 'image_path' => 'https://example.org/exhausted.jpg']);
    H::ad(5, ['image_path' => 'https://example.org/other-position.jpg']);
    $slots = app(AdSlotService::class);

    expect($slots->select(4, H::TODAY))->toBeNull()->and(H::slot(4))->toBe('');

    $ok = H::ad(4, ['ads_show_type' => 'period', 'startdate' => '2026-11-13', 'enddate' => '2026-11-14', 'image_path' => 'https://example.org/ok.jpg']);
    for ($i = 0; $i < 20; $i++) {
        expect((int) $slots->select(4, H::TODAY)->id)->toBe($ok);
    }
});

it('picks one ad per render, uniformly among the eligible ones; the share (percentage) is ignored, as OLD', function () {
    $ids = [H::ad(7, ['percentage' => 100]), H::ad(7, ['percentage' => 25]), H::ad(7, ['percentage' => 50])];
    H::ad(7, ['show' => 0]);
    $slots = app(AdSlotService::class);

    $counts = array_fill_keys($ids, 0);
    for ($i = 0; $i < 600; $i++) {
        $counts[(int) $slots->select(7, H::TODAY)->id]++;
    }

    expect(array_keys($counts))->toBe($ids)->and(array_sum($counts))->toBe(600);
    foreach ($counts as $n) {
        expect($n)->toBeGreaterThan(150)->toBeLessThan(250);   // ~200 each: no weighting by share
    }
});

// ─── URLs, escaping, XSS ─────────────────────────────────────────────────────

it('applies OLD\'s image URL rewrites before validation', function (array $ad, int $position, string $src) {
    H::on(popup: true);
    H::ad($position, $ad);

    expect(H::slot($position))->toContain('src="'.$src.'"');
})->with([
    'linked: /images/ → /media/ and http → https' => [['image_path' => 'http://way2allah.com/images/a.jpg'], 4, 'https://way2allah.com/media/a.jpg'],
    'unlinked: no rewrite (OLD\'s else branch)' => [['image_path' => 'http://way2allah.com/images/a.jpg', 'link' => ''], 4, 'http://way2allah.com/images/a.jpg'],
    'popup: http → https only' => [['image_path' => 'http://way2allah.com/images/a.jpg'], 12, 'https://way2allah.com/images/a.jpg'],
]);

it('an ad without a link renders the bare image, as OLD', function () {
    H::ad(4, ['link' => '   ']);

    expect(H::slot(4))->toBe('<div class="ads_details"><img border="0" src="https://example.org/b6c1-4.jpg" alt=""></div>');
});

it('withholds an ad whose image URL or link is unsafe or malformed — never rendered as src/href', function (string $field, string $value) {
    H::on(popup: true);
    foreach ([4, 12] as $position) {
        H::ad($position, [$field => $value]);
    }
    $before = H::rows('nuke_ads');

    expect(H::slot(4))->toBe('')->and(H::slot(12))->toBe('')
        ->and(H::rows('nuke_ads'))->toBe($before);
})->with([
    'image javascript:' => ['image_path', 'javascript:alert(1)'],
    'image data:' => ['image_path', 'data:image/svg+xml,<svg onload=alert(1)>'],
    'image quote breakout' => ['image_path', "https://example.org/x.jpg' onerror='alert(1)"],
    'image tag injection' => ['image_path', 'https://example.org/x.jpg"><script>alert(1)</script>'],
    'image relative path' => ['image_path', 'images/ads.jpg'],
    'image protocol-relative' => ['image_path', '//evil.example/x.jpg'],
    'link javascript:' => ['link', 'javascript:alert(1)'],
    'link quote breakout' => ['link', "https://example.org/x'onmouseover='alert(1)"],
    'link backslash' => ['link', 'https://example.org\\@evil.example'],
    'link malformed (the local copy\'s real row: ttps://…)' => ['link', 'ttps://www.youtube.com/watch?v=x'],
    'link with whitespace' => ['link', 'https://example.org/a b'],
]);

it('escapes stored values (no raw markup reaches the page)', function () {
    H::ad(4, ['link' => 'https://example.org/?q=<b>&x="1"', 'image_path' => 'https://example.org/a.jpg?x=1&y=2']);

    $html = H::slot(4);
    // `<`, `>` and `"` make the link unsafe, so the whole ad is withheld; `&` is escaped when safe.
    expect($html)->toBe('');

    DB::connection('main')->table('nuke_ads')->delete();
    H::ad(4, ['link' => 'https://example.org/?a=1&b=2', 'image_path' => 'https://example.org/a.jpg?x=1&y=2']);
    expect(H::slot(4))->toContain('href="https://example.org/?a=1&amp;b=2"')->toContain('src="https://example.org/a.jpg?x=1&amp;y=2"');
});

// ─── code ads ────────────────────────────────────────────────────────────────

it('never outputs a raw HTML/JS (type 0) ad, and never modifies or deletes it', function (int $position) {
    H::on(popup: true);
    $code = '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script><ins class="adsbygoogle B6C1-CODE"></ins><img src=x onerror=alert(1)>';
    H::ad($position, ['type' => 0, 'image_path' => $code, 'link' => '']);
    $before = H::rows('nuke_ads');
    H::$queries = [];

    $html = H::slot($position);

    expect($html)->toBe('')->not->toContain('B6C1-CODE')->not->toContain('adsbygoogle')
        ->and(H::rows('nuke_ads'))->toBe($before)
        ->and(H::writes())->toBe([]);
})->with([1, 4, 11, 12]);

it('a code ad is withheld even when its stored value is a valid URL (type decides, not content)', function () {
    H::on(popup: true);
    foreach ([4, 12] as $position) {
        H::ad($position, ['type' => 0, 'image_path' => 'https://example.org/B6C1-CODE-URL.jpg', 'link' => 'https://example.org/']);
    }

    expect(H::slot(4))->toBe('')->and(H::slot(12))->toBe('');
});

it('a selected code ad blanks the slot for that render (pool and odds unchanged); an image ad still shows otherwise', function () {
    H::ad(3, ['type' => 0, 'image_path' => '<ins class="B6C1-CODE"></ins>']);
    $image = H::ad(3);

    $shown = 0;
    for ($i = 0; $i < 200; $i++) {
        $html = H::slot(3);
        expect($html)->not->toContain('B6C1-CODE');
        $shown += $html === '' ? 0 : 1;
    }
    expect($shown)->toBeGreaterThan(60)->toBeLessThan(140);   // ~half: the code ad keeps its OLD share of picks
    expect($image)->toBeInt();
});

// ─── no counters, no auto-hide ───────────────────────────────────────────────

it('renders without writing: no num_view +1, no num_click, no show = 0 auto-hide', function () {
    H::on(popup: true);
    for ($p = 1; $p <= 12; $p++) {
        H::ad($p, ['num_view' => 7]);
    }
    H::ad(4, ['ads_show_type' => 'period', 'startdate' => '2020-01-01', 'enddate' => '2020-02-01']);   // ended: OLD hid it
    H::ad(4, ['num_view' => 5000, 'required_num_view' => 10]);                                        // exhausted: OLD hid it
    $before = H::rows('nuke_ads');
    H::$queries = [];

    for ($i = 0; $i < 5; $i++) {
        foreach (range(1, 12) as $p) {
            H::slot($p);
        }
    }

    expect(H::writes())->toBe([])
        ->and(H::rows('nuke_ads'))->toBe($before)
        ->and(collect(H::$queries)->every(fn ($sql) => str_starts_with(strtolower(ltrim($sql)), 'select')))->toBeTrue();
});
