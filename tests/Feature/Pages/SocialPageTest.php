<?php

/**
 * Roadmap task 2.5 (added post-Wave-4 — see
 * docs/reviews/gap-closure-action-plan.md item 3), revisited by the
 * Legacy-Source Reconstruction pass. Legacy source is pure static
 * content, no DB. `social.htm` currently 404s in production despite being
 * a real, standing header-nav link (LEGACY_PRETTY_URL_ORPHANED) — fixed
 * by registering the route at its exact legacy path, not a behavior
 * change.
 *
 * Image path corrected back to `media/social-images/` (SocialController's
 * own updated docblock has the full evidence trail): at the time this was
 * first ported, `legacy-project/media/` genuinely did not exist locally,
 * so `pages/social-images/` was a real, working substitute. `media/` has
 * since been downloaded in full — `media/social-images/` now exists,
 * confirmed byte-identical to `pages/social-images/`, and a live raw-path
 * fetch confirms production itself serves images from `media/social-images/`.
 */
it('serves the social page at its exact legacy pretty path', function () {
    $response = $this->get('/social.htm');

    $response->assertOk()
        ->assertSee('روابط منصات السوشيال ميديا لشبكة الطريق إلى الله')
        ->assertSee('الفيس بوك')
        ->assertSee('اليوتيوب')
        ->assertSee('إنستجرام')
        ->assertSee('تليجرام')
        ->assertSee('تابعونا على بودكاست')
        ->assertSee('https://www.facebook.com/Way2allahCom', false)
        ->assertSee('https://www.youtube.com/c/Way2allahPlus', false)
        ->assertSee('https://open.spotify.com/show/65amn21YcaheOCxFpmU7Kb', false);
});

it('references the real legacy image path, media/social-images/ (media/ has since been downloaded — no longer the broken path)', function () {
    $content = $this->get('/social.htm')->getContent();

    expect($content)
        ->toContain('/media/social-images/w2a.jpg')
        ->not->toContain('/pages/social-images/');
});

it('redirects the raw legacy pages/social.php path to /social.htm', function () {
    $this->get('/pages/social.php')->assertRedirect('/social.htm');
});

// ---- Legacy-Source Reconstruction: pages/social.php:38,42-43's real page chrome ----

it('renders the real page chrome — <h3 class="page-title"> (a meaningful heading, no bug here) and the single self-referencing (empty-href) breadcrumb item', function () {
    $content = $this->get('/social.htm')->getContent();

    expect($content)->toContain('<h3 class="page-title">روابط منصات السوشيال ميديا لشبكة الطريق إلى الله</h3>');
    expect($content)
        ->toContain('<li><i class="fa fa-home"></i><a href="/">الرئيسية</a><i class="fa fa-angle-right"></i></li>')
        ->toContain('<li><a href="">روابط منصات السوشيال ميديا لشبكة الطريق إلى الله</a><i class=""></i></li>');
    expect(strpos($content, 'page-title'))->toBeLessThan(strpos($content, 'page-bar'));
});

// ---- Legacy-Source Reconstruction: pages/social.php:49-54's real w2a_open_div() portlet wrapper ----

it('wraps every section in a real portlet (.portlet.box.blue, empty id="", caption+icon), not a bare .portlet-title div', function () {
    $content = $this->get('/social.htm')->getContent();

    expect(substr_count($content, 'portlet box blue'))->toBe(6);
    expect($content)
        ->toContain('<div id="" class="col-md-12 col-sm-12">')
        ->toContain('<div class="caption"><i class="fa fa-facebook-square"></i> الفيس بوك</div>')
        ->toContain('<div class="portlet-body ">');
});

it('reuses the fa-telegram icon for 3 different portlets (تليجرام/منصات تواصل متنوعة/تابعونا على بودكاست) — a real, confirmed source repetition, not corrected', function () {
    $content = $this->get('/social.htm')->getContent();

    expect(substr_count($content, 'fa-telegram'))->toBe(3);
});

// ---- Legacy-Source Reconstruction: pages/social.php's own separate "alt" field, and the restored background-image CSS ----

it('uses the restored, source-distinct alt attribute (a short identifier), not the full Arabic name, on each image', function () {
    $content = $this->get('/social.htm')->getContent();

    expect($content)->toContain('alt="Way2allahCom"')
        ->not->toContain('alt="شبكة الطريق إلى الله - Way2Allah"');
});

it('loads the background-image CSS rule that pages/social.php:1-32\'s inline <style> block defines (previously dropped entirely)', function () {
    $content = $this->get('/social.htm')->getContent();

    expect($content)->toContain('background-image: url(/assets/frontend/layout/css/images/block_bg.png);');
});

it('reproduces the literal leading space on the واتساب link (harmless — browsers trim href whitespace — but a real, confirmed source byte)', function () {
    $content = $this->get('/social.htm')->getContent();

    expect($content)->toContain('href=" https://whatsapp.com/channel/0029Va5lZWm90x2sDeAEoR3o"');
});

it('gives the two "free" sections their own distinct, source-confirmed column widths — منصات تواصل متنوعة is col-xs-4/col-sm-2, تابعونا على بودكاست is col-xs-6/col-sm-3', function () {
    $content = $this->get('/social.htm')->getContent();

    $miscSectionStart = strpos($content, 'منصات تواصل متنوعة');
    $podcastSectionStart = strpos($content, 'تابعونا على بودكاست');
    $miscSection = substr($content, $miscSectionStart, $podcastSectionStart - $miscSectionStart);

    expect($miscSection)->toContain('col-xs-4 col-sm-2 col-md-2');
    expect(substr($content, $podcastSectionStart))->toContain('col-xs-6 col-sm-3 col-md-2');
});
