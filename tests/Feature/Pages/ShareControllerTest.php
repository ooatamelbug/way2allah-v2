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
