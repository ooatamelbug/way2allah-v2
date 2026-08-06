<?php

/**
 * Roadmap task 2.5 (added post-Wave-4 — see
 * docs/reviews/gap-closure-action-plan.md item 3). Legacy source is pure
 * static content, no DB. Two confirmed production bugs are fixed while
 * porting (not a behavior change — see SocialController's docblock):
 * `social.htm` currently 404s despite being a real, standing header-nav
 * link, and every image path was broken (`media/social-images/` doesn't
 * exist).
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

it('references the fixed image path (public/pages/social-images/, per Blueprint §12), not the broken legacy media/ path', function () {
    $content = $this->get('/social.htm')->getContent();

    expect($content)
        ->toContain('/pages/social-images/w2a.jpg')
        ->not->toContain('media/social-images/');
});

it('redirects the raw legacy pages/social.php path to /social.htm', function () {
    $this->get('/pages/social.php')->assertRedirect('/social.htm');
});
