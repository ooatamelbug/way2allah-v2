<?php

/**
 * Roadmap task 6.3. help/share.php has no DB/logic (Task 6.3 investigation
 * §6) — same static-content-check style as StaticPagesTest.php.
 */
it('serves the share page with its real legacy content and banner groups', function () {
    $response = $this->get('/share.htm');

    $response->assertOk()
        ->assertSee('إنشر الموقع')
        ->assertSee('مقاس 300*600')
        ->assertSee('مقاس 468*60');
});

it('keeps the confirmed-broken w2a/*.gif banner URLs exactly as legacy references them, per Business Confirmation #2\'s placeholder approval', function () {
    $response = $this->get('/share.htm');

    $response->assertOk()
        ->assertSee('https://way2allah.com/w2a/300-600-1.gif', false)
        ->assertSee('https://way2allah.com/w2a/468-60-9.gif', false);
});

it('renders all 25 banner images', function () {
    $response = $this->get('/share.htm');

    // Scoped to the banner base URL, not a raw `<img` count across the
    // whole page — the shared layout (logo, nav icons) contributes its
    // own `<img` tags too, which a page-wide count would wrongly include.
    expect(substr_count($response->getContent(), 'https://way2allah.com/w2a/'))->toBe(25);
});

// ---- Chrome/Portlet Gap Closure (2026-08-22): share.php's real
// title()/breadcrumb() chrome and w2a_open_div() portlet wrapper were
// entirely absent — restored via the existing <x-page-chrome> component.
// Business Confirmation #2's banner-URL approval is not reopened here. ----

it('renders the shared page chrome — exact document title, heading, and single-item breadcrumb with an empty-href self-link', function () {
    $content = $this->get('/share.htm')->assertOk()->getContent();

    $titleTag = substr($content, (int) strpos($content, '<title>'), 100);
    expect($titleTag)->toContain('<title>إنشر الموقع - '.config('app.name').'</title>')
        ->and(substr_count($titleTag, (string) config('app.name')))->toBe(1);

    expect($content)
        ->toContain('<h3 class="page-title">إنشر الموقع</h3>')
        ->toContain('<a href="/">الرئيسية</a>')
        ->toContain('<li><a href="">إنشر الموقع</a><i class=""></i></li>');
});

it('wraps its content in the real portlet — fa-child icon, "إنشر الموقع" caption, portlet box blue, and the real "share text-center" inner wrapper', function () {
    $content = $this->get('/share.htm')->assertOk()->getContent();

    expect($content)
        ->toContain('<div class="caption"><i class="fa fa-child"></i> إنشر الموقع</div>')
        ->toContain('<div class="portlet box blue">')
        ->toContain('<div class="portlet-body ">')
        ->toContain('<div class="share text-center">');
});

it('approved banner behavior is unchanged by the chrome/portlet fix — still all 25 confirmed-broken URLs, unaltered', function () {
    $content = $this->get('/share.htm')->assertOk()->getContent();

    expect(substr_count($content, 'https://way2allah.com/w2a/'))->toBe(25);
    expect($content)
        ->toContain('https://way2allah.com/w2a/300-600-1.gif')
        ->toContain('https://way2allah.com/w2a/468-60-9.gif');
});
