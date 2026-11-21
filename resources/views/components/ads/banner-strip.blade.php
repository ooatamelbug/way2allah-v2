{{--
    F01 / B6c-1 — OLD's home banner strip (`old-project/index.php:345-361`). Renders nothing, and queries nothing,
    while `ads.rendering_enabled` is off. Not placed on the home page in B6c-1. Only eligible selections (existing,
    visible item with a valid banner) are drawn; forum banners only with safe URLs. All values escaped.
--}}
@inject('bannerStrip', \App\Domain\Content\Ads\HomeBannerStripService::class)
@php($strip = $bannerStrip->present())
@if ($strip !== null)
    <div class="w2a_home_banners">
        @isset($strip['forum'][1])
            <a href="{{ $strip['forum'][1]['href'] }}" rel="noopener"><img src="{{ $strip['forum'][1]['src'] }}" border="0" alt="{{ $strip['forum'][1]['title'] }}"></a>
        @endisset
        @if ($strip['item'] !== null)
            <a href="{{ route('khotab.item.show', $strip['item']['id']) }}" class="w2a_large_banner"><img src="{{ $strip['item']['src'] }}" border="0" alt="{{ $strip['item']['title'] }}"@if ($strip['item']['width'] > 0) width="{{ $strip['item']['width'] }}" height="{{ $strip['item']['height'] }}"@endif></a>
        @endif
        @isset($strip['forum'][2])
            <a href="{{ $strip['forum'][2]['href'] }}" rel="noopener"><img src="{{ $strip['forum'][2]['src'] }}" border="0" alt="{{ $strip['forum'][2]['title'] }}"></a>
        @endisset
    </div>
@endif
