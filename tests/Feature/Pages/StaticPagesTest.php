<?php

/**
 * Roadmap task 2.1. Legacy source has no DB/business logic (help.md §2,
 * pages.md §5, §8 both confirm "No database interaction anywhere in these
 * files") — these tests verify presence of the actual legacy content, not
 * just a 200 status, since a blank 200 page would pass a status-only check
 * while silently losing the content ADR-0010 classifies "Must preserve".
 */
it('serves the privacy policy page with its real legacy content', function () {
    $response = $this->get('/privacy');

    $response->assertOk()
        ->assertSee('سياسة الخصوصية') // title, appears in <h1> and @section('title')
        ->assertSee('مقدمة')
        ->assertSee('أمن البيانات') // section 10 heading — proves the full document rendered, not just the intro
        ->assertSee('GDPR');
});

it('serves the about-us page with its real legacy content', function () {
    $response = $this->get('/about');

    $response->assertOk()
        ->assertSee('من نحن') // title
        ->assertSee('الرؤية')
        ->assertSee('شبكة الطريق إلى الله')
        ->assertSee('2005'); // founding-year detail from the historical narrative section
});

it('renders both pages with the correct Arabic/RTL document direction', function () {
    expect($this->get('/privacy')->getContent())->toContain('dir="rtl"');
    expect($this->get('/about')->getContent())->toContain('dir="rtl"');
});

// ---- Four-Route Migration Gap Audit: landing_page.htm (ROUTE_MAPPING_GAP) — a second legacy pretty-URL reaching the SAME About content, not a new page ----

it('serves /landing_page.htm directly (no redirect) with the exact same real content as /about', function () {
    $response = $this->get('/landing_page.htm');

    $response->assertOk()
        ->assertSee('من نحن')
        ->assertSee('الرؤية')
        ->assertSee('شبكة الطريق إلى الله')
        ->assertSee('2005');
});

it('/landing_page.htm and /about return byte-identical content — confirming this is pass-through to the same existing controller, not a duplicated view', function () {
    $landingPage = $this->get('/landing_page.htm')->getContent();
    $about = $this->get('/about')->getContent();

    expect($landingPage)->toBe($about);
});

it('/landing_page.htm is not a redirect — the URL stays in the browser, matching the project\'s established pattern for a real nav-linked legacy path with no working .htaccess rule', function () {
    $this->get('/landing_page.htm')->assertOk();
});

it('/about remains unaffected by the new /landing_page.htm route', function () {
    $this->get('/about')->assertOk()->assertSee('من نحن');
});

// ---- Chrome/Portlet Gap Closure (2026-08-22): about.php's real
// title()/breadcrumb() chrome and w2a_open_div() portlet wrapper were
// entirely absent — restored via the existing <x-page-chrome> component,
// applying identically to both /about and /landing_page.htm (one shared
// view/controller). ----

it('/about renders the shared page chrome — exact document title, heading, and single-item breadcrumb with an empty-href self-link', function () {
    $content = $this->get('/about')->assertOk()->getContent();

    $titleTag = substr($content, (int) strpos($content, '<title>'), 100);
    expect($titleTag)->toContain('<title>من نحن - '.config('app.name').'</title>')
        ->and(substr_count($titleTag, (string) config('app.name')))->toBe(1);

    expect($content)
        ->toContain('<h3 class="page-title">من نحن</h3>')
        ->toContain('<a href="/">الرئيسية</a>')
        ->toContain('<li><a href="">من نحن</a><i class=""></i></li>');
});

it('/about wraps its content in the real portlet — fa-child icon, "من نحن" caption, portlet box blue', function () {
    $content = $this->get('/about')->assertOk()->getContent();

    expect($content)
        ->toContain('<div class="caption"><i class="fa fa-child"></i> من نحن</div>')
        ->toContain('<div class="portlet box blue">')
        ->toContain('<div class="portlet-body ">');
});

it('/about content itself is unchanged — the MS-Word-pasted body still renders inside the new portlet wrapper', function () {
    $content = $this->get('/about')->assertOk()->getContent();

    expect($content)
        ->toContain('<div dir="rtl">')
        ->toContain('class="MsoNormal"')
        ->toContain('رؤيتنا دلالة الخلق كل الخلق على الله');
});

it('/landing_page.htm inherits the exact same chrome/portlet fix as /about — still byte-identical to it', function () {
    $landingPage = $this->get('/landing_page.htm')->getContent();
    $about = $this->get('/about')->getContent();

    expect($landingPage)->toBe($about)
        ->toContain('<h3 class="page-title">من نحن</h3>')
        ->toContain('<div class="caption"><i class="fa fa-child"></i> من نحن</div>');
});
