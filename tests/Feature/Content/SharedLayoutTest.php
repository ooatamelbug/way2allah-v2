<?php

/**
 * Visual/CSS parity phase — AddThis widget (`functions.php:749-757`'s
 * `share()` + `header.php:147-148`'s script), confirmed sitewide across 7
 * live production page types with no per-page caller found in local
 * source — rendered unconditionally in the shared layout instead of
 * per-controller. Tested against two structurally unrelated pages (a
 * static page and a DB-backed listing page) to prove it's a shared-layout
 * concern, not something that needs asserting on every individual page.
 */
it('every page renders the AddThis script exactly once, sitewide, matching header.php\'s unconditional include', function () {
    $content = $this->get('/privacy')->assertOk()->getContent();

    expect(substr_count($content, '//s7.addthis.com/js/300/addthis_widget.js'))->toBe(1);
});

it('every page renders the AddThis sharing container exactly once, before the page-specific content, matching share()\'s exact markup', function () {
    $content = $this->get('/privacy')->assertOk()->getContent();

    expect(substr_count($content, 'addthis_inline_share_toolbox addthis_sharing_toolbox'))->toBe(1)
        ->and($content)->toContain('style=" float: left;" class="addthis_inline_share_toolbox addthis_sharing_toolbox"');

    // Confirmed position: inside .main .container, before the page's own content.
    // "سياسة الخصوصية" also appears in <title> (in <head>, before .main entirely),
    // so this checks against a body-only heading ("مقدمة") instead, to prove the
    // AddThis block precedes the page's actual rendered content, not just the <head>.
    $addThisPos = strpos($content, 'addthis_inline_share_toolbox');
    $pagePos = strpos($content, 'مقدمة');

    expect($addThisPos)->not->toBeFalse()
        ->and($pagePos)->not->toBeFalse()
        ->and($addThisPos)->toBeLessThan($pagePos);
});

it('a second, structurally unrelated page also renders both exactly once — confirms this is a shared-layout concern, not a per-page one', function () {
    $content = $this->get('/')->assertOk()->getContent();

    expect(substr_count($content, '//s7.addthis.com/js/300/addthis_widget.js'))->toBe(1)
        ->and(substr_count($content, 'addthis_inline_share_toolbox addthis_sharing_toolbox'))->toBe(1);
});
