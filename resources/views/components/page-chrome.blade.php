{{--
    Sitewide Page Title / Breadcrumb Parity Audit — the shared `title()` /
    `breadcrumb()` DOM (`functions.php:453-543`), extracted once because
    9 confirmed pages need the identical shape (the audit's Option B).
    Deliberately NOT used by every page: `var-item-{id}.htm` already has
    its own proven-correct implementation (not migrated here), and
    `fatawa-channels.htm` uses a genuinely different legacy mechanism
    (`page_bar_channels()` — bare `<h1>`, hand-rolled breadcrumb) this
    component must not be forced onto.

    No DB queries, no domain-model awareness (Category/AnasheedGroup/etc.)
    — callers pass plain data. `heading` is the finished string (or null
    to omit the `<h3>` entirely — the audit's `LEGACY_BUG_NOT_FOR_
    REPRODUCTION` decision for khotab-video-today.htm/video-advanced-
    search.htm's confirmed empty-heading bug: omit, don't render empty).
    The decorative `<i class=\fa fa-gift\">` icon legacy always emits
    before the heading is a confirmed malformed/dead artifact (same
    decision) — not reproduced, not replaced with a working one.

    `breadcrumb` is a list of ['title' => string, 'url' => string|null]
    in display order (Home is NOT included — always rendered here,
    matching legacy's unconditional Home item). `array_key_exists('url', ...)`
    mirrors legacy's own `isset($item['url'])` check exactly: a present-
    but-empty `'url' => ''` still renders as `<a href="">`, matching
    `recite.htm`'s real production breadcrumb; only an absent key renders
    plain text (`breadcrumb_items()`, functions.php:516-538). The last
    item always gets a trailing `<i class=""></i>` — legacy's own
    `$class = ''` for the final item, never an omitted icon.
--}}
@props([
    'heading' => null,
    'breadcrumb' => [],
])
@if ($heading !== null)
    <h3 class="page-title">{{ $heading }}</h3>
@endif

<div class="page-bar">
    <ul class="page-breadcrumb">
        <li><i class="fa fa-home"></i><a href="/">الرئيسية</a><i class="fa fa-angle-right"></i></li>
        @foreach ($breadcrumb as $item)
            <li>@if (array_key_exists('url', $item))<a href="{{ $item['url'] }}">{{ $item['title'] }}</a>@else{{ $item['title'] }}@endif<i class="{{ $loop->last ? '' : 'fa fa-angle-right' }}"></i></li>
        @endforeach
    </ul>
</div>
