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

// ---- Premium social directory refresh ----

it('wraps all six platform groups in the reusable premium section component', function () {
    $content = $this->get('/social.htm')->getContent();

    expect(substr_count($content, 'w2a-social-section'))->toBeGreaterThanOrEqual(6);
    expect($content)
        ->toContain('class="w2a-refresh-page w2a-social-page"')
        ->toContain('<h2>الفيس بوك</h2>')
        ->toContain('<h2>تابعونا على بودكاست</h2>');
});

it('uses meaningful, Font Awesome 4-compatible icons for distinct platform groups', function () {
    $content = $this->get('/social.htm')->getContent();

    expect($content)
        ->toContain('fa-paper-plane')
        ->toContain('fa-globe')
        ->toContain('fa-microphone');
});

// ---- Images, external-link safety, and responsive variants ----

it('uses the restored, source-distinct alt attribute (a short identifier), not the full Arabic name, on each image', function () {
    $content = $this->get('/social.htm')->getContent();

    expect($content)->toContain('alt="Way2allahCom"')
        ->not->toContain('alt="شبكة الطريق إلى الله - Way2Allah"');
});

it('loads the scoped premium refresh stylesheet after the global premium stylesheet', function () {
    $content = $this->get('/social.htm')->getContent();

    expect($content)->toContain('/assets/frontend/layout/css/content-refresh.css');
    expect(strpos($content, 'premium-ui.css'))->toBeLessThan(strpos($content, 'content-refresh.css'));
});

it('normalizes external links and protects new tabs from opener access', function () {
    $content = $this->get('/social.htm')->getContent();

    expect($content)
        ->toContain('href="https://whatsapp.com/channel/0029Va5lZWm90x2sDeAEoR3o"')
        ->not->toContain('href=" https://whatsapp.com')
        ->toContain('rel="noopener noreferrer"');
});

it('uses the compact responsive grid variant for miscellaneous and podcast platforms', function () {
    $content = $this->get('/social.htm')->getContent();

    expect(substr_count($content, 'w2a-social-section--compact'))->toBe(2);
});
