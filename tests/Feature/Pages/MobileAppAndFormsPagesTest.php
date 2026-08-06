<?php

it('serves the mobile-app page with its real content and links', function () {
    $this->get('/mobile-app')
        ->assertOk()
        ->assertSee('تطبيق الطريق إلى الله')
        ->assertSee('https://play.google.com/store/apps/details?id=com.way2allah.app', false)
        ->assertSee('/app-mockup.png', false);
});

it('serves the visitor-feedback page (renamed from estebian) with the correct embedded form', function () {
    $this->get('/visitor-feedback')
        ->assertOk()
        ->assertSee('إستبيان زوار شبكة الطريق إلى الله')
        ->assertSee('1FAIpQLScO4qokMiiajzHXR3sC1WTbNEMND_6KA_q3q62DOj7IBH4Jqw', false)
        ->assertSee('height="4100"', false);
});

it('serves the Quran-memorization application page, IF-008: iframe URL has no embedded=true param, unlike its siblings', function () {
    $response = $this->get('/quran-memorization-application');

    $response->assertOk()->assertSee('1FAIpQLSdnZsVVBeAH6wRpWwo7B_Hv45b2ErkBBVqrr2bEtBTENy5d3w', false);

    expect($response->getContent())
        ->toContain('/viewform"') // no query string at all on this one, reproduced exactly
        ->not->toContain('1FAIpQLSdnZsVVBeAH6wRpWwo7B_Hv45b2ErkBBVqrr2bEtBTENy5d3w/viewform?embedded=true');
});

it('serves the volunteer page (renamed from tatw3-w2a-team) with the correct embedded form', function () {
    $this->get('/volunteer')
        ->assertOk()
        ->assertSee('نموذج التطوع بفرق عمل شبكة الطريق إلى الله')
        ->assertSee('1FAIpQLSey90EU6LJY9pTm6qsRSgDOVZPeSNmgz8vrh4jwRVdTnNRGIQ', false)
        ->assertSee('height="3000"', false);
});

it('redirects all 4 raw legacy paths to their new routes', function () {
    $this->get('/pages/mobile-app.php')->assertRedirect('/mobile-app');
    $this->get('/pages/estebian.php')->assertRedirect('/visitor-feedback');
    $this->get('/pages/mo7fzat-quran.php')->assertRedirect('/quran-memorization-application');
    $this->get('/pages/tatw3-w2a-team.php')->assertRedirect('/volunteer');
});
