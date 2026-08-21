<?php

/**
 * Shared Page Chrome Parity Audit — `<x-page-chrome>`
 * (resources/views/components/page-chrome.blade.php), the shared
 * legacy `title()`/`breadcrumb()` DOM (`functions.php:453-543`).
 * Structural assertions throughout, not mere text presence.
 */

it('renders no heading at all when heading is omitted (default null)', function () {
    $html = $this->blade('<x-page-chrome :breadcrumb="[[\'title\' => \'x\']]" />');
    $html = (string) $html;

    expect($html)->not->toContain('page-title');
});

it('renders the heading as a bare <h3 class="page-title"> with no decorative icon, when a meaningful heading is supplied', function () {
    $html = $this->blade('<x-page-chrome heading="عنوان الصفحة" :breadcrumb="[]" />');
    $html = (string) $html;

    expect($html)->toContain('<h3 class="page-title">عنوان الصفحة</h3>');
    // The confirmed legacy malformed-icon bug (functions.php:541-543) must
    // never be reproduced — LEGACY_BUG_NOT_FOR_REPRODUCTION.
    expect($html)->not->toContain('fa-gift');
    expect($html)->not->toContain("class=\\fa");
});

it('always renders the .page-bar > ul.page-breadcrumb container and an unconditional Home item', function () {
    $html = $this->blade('<x-page-chrome :breadcrumb="[]" />');
    $html = (string) $html;

    expect($html)->toContain('<div class="page-bar">');
    expect($html)->toContain('<ul class="page-breadcrumb">');
    expect($html)->toMatch('#<li><i class="fa fa-home"></i><a href="/">الرئيسية</a><i class="fa fa-angle-right"></i></li>#');
});

it('renders an intermediate item with a real href and the fa-angle-right separator', function () {
    $html = $this->blade('<x-page-chrome :breadcrumb="[[\'title\' => \'وسيط\', \'url\' => \'/somewhere.htm\'], [\'title\' => \'current\']]" />');
    $html = (string) $html;

    expect($html)->toMatch('#<li>\s*<a href="/somewhere.htm">وسيط</a>\s*<i class="fa fa-angle-right"></i>\s*</li>#');
});

it('renders an item with url="" as a real empty-href anchor, matching legacy isset() semantics (not omitted, not plain text)', function () {
    $html = $this->blade('<x-page-chrome :breadcrumb="[[\'title\' => \'فارغ\', \'url\' => \'\']]" />');
    $html = (string) $html;

    expect($html)->toContain('<a href="">فارغ</a>');
});

it('renders an item with no url key at all as plain text, not a link', function () {
    $html = $this->blade('<x-page-chrome :breadcrumb="[[\'title\' => \'نص فقط\']]" />');
    $html = (string) $html;

    expect($html)->not->toContain('<a href="">نص فقط</a>');
    expect($html)->toMatch('#<li>\s*نص فقط\s*<i#');
});

it('gives the final breadcrumb item an empty trailing <i class=""></i>, not fa-angle-right and not omitted — matching breadcrumb_items()\'s own $class="" for the last item', function () {
    $html = $this->blade('<x-page-chrome :breadcrumb="[[\'title\' => \'a\', \'url\' => \'/a\'], [\'title\' => \'current\']]" />');
    $html = (string) $html;

    // Last item: no angle-right icon.
    expect(substr_count($html, 'fa-angle-right'))->toBe(2); // Home item + the one non-final breadcrumb item — NOT the final one.
    expect($html)->toMatch('#<li>\s*current\s*<i class="">\s*</i>\s*</li>#');
});

it('preserves ancestor ordering exactly as supplied (no re-sorting)', function () {
    $html = $this->blade('<x-page-chrome :breadcrumb="[[\'title\' => \'الجد\', \'url\' => \'/a\'], [\'title\' => \'الأب\', \'url\' => \'/b\'], [\'title\' => \'الحالي\']]" />');
    $html = (string) $html;

    $posGrandparent = strpos($html, 'الجد');
    $posParent = strpos($html, 'الأب');
    $posCurrent = strpos($html, 'الحالي');

    expect($posGrandparent)->toBeLessThan($posParent);
    expect($posParent)->toBeLessThan($posCurrent);
});

it('heading (when present) is rendered before the breadcrumb, matching legacy\'s own title()-then-breadcrumb() call order', function () {
    $html = $this->blade('<x-page-chrome heading="H" :breadcrumb="[[\'title\' => \'x\']]" />');
    $html = (string) $html;

    expect(strpos($html, 'page-title'))->toBeLessThan(strpos($html, 'page-bar'));
});
