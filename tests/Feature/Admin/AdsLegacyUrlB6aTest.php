<?php

/*
 * F01 / B6a security review — existing malformed advertisement URLs.
 *
 * - an UNCHANGED stored value may be kept (legacy data is never rewritten), but never becomes an executable link
 *   or image in AdminCP;
 * - a NEW or CHANGED value must be a plain http(s) URL without characters that break out of the quoted attribute /
 *   JavaScript-string contexts OLD's and Gen 2's public renderers use (`href='…'`, `src="…"`, popup JS string);
 * - the "unchanged" exemption only covers a value stored in the same role, so converting a code ad to an image ad
 *   cannot carry its raw HTML over as an "image URL";
 * - editing unrelated fields cannot slip a new URL through.
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AdAdminHarness as H;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();
});

function b6aEditInput(array $overrides = []): array
{
    return array_merge([
        'name' => 'إعلان قديم', 'type' => '1', 'path_type' => '2', 'image_url' => 'javascript:alert(1)',
        'link' => 'ttps://www.youtube.com/watch?v=rxg2d7ZUN', 'position' => '4', 'ads_show_type' => 'numview', 'required_num_view' => '10000000',
    ], $overrides);
}

it('an unchanged malformed legacy link and image URL are preserved when only an unrelated field changes', function () {
    $this->actingAs(H::admin(), 'admin');
    $id = H::legacyRow(['image_path' => 'javascript:alert(1)', 'path_type' => 2]);

    $this->put(route('admin.ads.update', $id), b6aEditInput(['name' => 'اسم جديد']))->assertSessionHasNoErrors()->assertSessionHas('success');

    $row = H::row($id);
    expect($row->name)->toBe('اسم جديد')
        ->and($row->link)->toBe('ttps://www.youtube.com/watch?v=rxg2d7ZUN')
        ->and($row->image_path)->toBe('javascript:alert(1)');
});

it('preserved unsafe legacy values never become an executable link or image in AdminCP', function () {
    $this->actingAs(H::admin(), 'admin');
    $id = H::legacyRow(['image_path' => 'javascript:alert(1)', 'path_type' => 2, 'link' => "javascript:alert('l')"]);

    foreach ([route('admin.ads.index'), route('admin.ads.edit', $id)] as $page) {
        $html = $this->get($page)->assertOk()->getContent();
        expect($html)->not->toMatch('/(src|href)\s*=\s*["\']?\s*javascript:alert/i');
    }
    // The edit page shows the stored values only as escaped form values.
    expect($this->get(route('admin.ads.edit', $id))->getContent())
        ->toContain('value="javascript:alert(1)"')->toContain('value="javascript:alert(&#039;l&#039;)"');
});

it('a changed or new URL must be safe: schemes, protocol-relative, whitespace and quote/JS-breakout characters are rejected', function (string $value) {
    $this->actingAs(H::admin(), 'admin');
    $id = H::legacyRow();
    $before = H::snapshot();

    // as a changed link on a legacy row …
    $this->put(route('admin.ads.update', $id), b6aEditInput(['link' => $value, 'path_type' => '1', 'image_url' => '']))->assertSessionHasErrors('link');
    // … and as a new image URL and link on create
    $this->post(route('admin.ads.store'), H::imageInput(['image_url' => $value]))->assertSessionHasErrors('image_url');
    $this->post(route('admin.ads.store'), H::imageInput(['link' => $value]))->assertSessionHasErrors('link');

    expect(H::snapshot())->toBe($before)->and(H::auditRows())->toBe([]);
})->with([
    'javascript:' => 'javascript:alert(1)',
    'JaVaScRiPt:' => 'JaVaScRiPt:alert(1)',
    'data:' => 'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
    'vbscript:' => 'vbscript:msgbox(1)',
    'protocol-relative' => '//evil.example/x.jpg',
    'leading-space scheme' => ' javascript:alert(1)',
    'tab in scheme' => "java\tscript:alert(1)",
    'malformed legacy-like' => 'ttps://changed.example',
    'single-quote breakout' => "https://example.org/x.jpg'onerror='alert(1)",
    'double-quote breakout' => 'https://example.org/x.jpg"onerror="alert(1)',
    'angle brackets' => 'https://example.org/<script>alert(1)</script>',
    'backtick' => 'https://example.org/`x`',
    'backslash' => 'https://example.org/x\\\'',
    'newline injection' => "https://example.org/\nalert(1)",
    'space' => 'https://example.org/a b.jpg',
]);

it('ordinary http(s) URLs with query strings are accepted', function (string $value) {
    $this->actingAs(H::admin(), 'admin');

    $this->post(route('admin.ads.store'), H::imageInput(['image_url' => $value, 'link' => $value]))->assertSessionHasNoErrors();
    expect(H::snapshot()[0]['image_path'])->toBe($value);
})->with(['https://example.org/banner.jpg', 'http://example.org/path/b-1_2.png?x=1&y=%D8%A7', 'https://www.youtube.com/watch?v=rxg2d7ZUN']);

it('converting a code ad to an image ad cannot carry the raw HTML over as an "unchanged" image URL', function () {
    $this->actingAs(H::superAdmin(), 'admin');
    $code = '<script>alert(1)</script>';
    $id = H::codeRow(['image_path' => $code]);

    $this->put(route('admin.ads.update', $id), [
        'name' => 'شفرة', 'type' => '1', 'path_type' => '2', 'image_url' => $code, 'link' => '',
        'position' => '4', 'ads_show_type' => 'period', 'startdate' => '2026-01-01', 'enddate' => '2026-12-31',
    ])->assertSessionHasErrors('image_url');

    expect((int) H::row($id)->type)->toBe(0)->and(H::row($id)->image_path)->toBe($code)->and(H::auditRows())->toBe([]);
});

it('an uploaded image\'s stored path is not an exemption for a typed URL either', function () {
    $this->actingAs(H::admin(), 'admin');
    $id = H::legacyRow(['image_path' => "https://way2allah.com/media/bann/x.jpg'onerror='1", 'path_type' => 1]);

    // switching to "URL" and resubmitting the stored upload path as if unchanged
    $this->put(route('admin.ads.update', $id), b6aEditInput(['image_url' => "https://way2allah.com/media/bann/x.jpg'onerror='1"]))
        ->assertSessionHasErrors('image_url');
});

it('editing unrelated fields cannot slip in a new URL: any changed URL is validated', function () {
    $this->actingAs(H::admin(), 'admin');
    $id = H::legacyRow(['image_path' => 'https://way2allah.com/media/bann/1742505317.jpg', 'path_type' => 2]);

    $this->put(route('admin.ads.update', $id), b6aEditInput([
        'name' => 'تعديل بريء', 'image_url' => 'https://way2allah.com/media/bann/1742505317.jpg', 'link' => 'javascript:alert(1)',
    ]))->assertSessionHasErrors('link');

    $this->put(route('admin.ads.update', $id), b6aEditInput([
        'name' => 'تعديل بريء', 'image_url' => 'https://way2allah.com/media/bann/1742505317.jpg"><script>1</script>',
    ]))->assertSessionHasErrors('image_url');

    expect(H::row($id)->name)->toBe('إعلان قديم')->and(H::auditRows())->toBe([]);
});

it('ads.manageads gates only the B6a routes (it must not authorize future B6b modules)', function () {
    $gated = collect(app('router')->getRoutes())->filter(fn ($r) => in_array('admin.permission:ads.manageads', $r->gatherMiddleware(), true))
        ->map(fn ($r) => (string) $r->getName())->values();

    expect($gated)->toHaveCount(8)->and($gated->every(fn ($n) => str_starts_with($n, 'admin.ads.')))->toBeTrue();
});
