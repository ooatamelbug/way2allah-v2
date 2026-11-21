{{--
    F01 / B6c-1 — one advertising position (OLD `show_ads_byposition($pos)`). Renders nothing, and queries
    nothing, while `ads.rendering_enabled` is off. Not placed in any public view in B6c-1.
    No view/click counting, no onclick, no script: every value goes through Blade escaping.
    Position 12 is an inert, CLOSED <dialog> (never opened in B6c-1; requires `ads.popup_enabled` too).
--}}
@props(['position'])
@inject('adSlots', \App\Domain\Content\Ads\AdSlotService::class)
@php($ad = $adSlots->present((int) $position))
@if ($ad !== null)
    @if ($ad['popup'])
        <dialog class="w2a-ad-popup" data-ad-position="12" aria-label="إعلان">
            <form method="dialog"><button type="submit" class="w2a-ad-popup-close" aria-label="إغلاق">&times;</button></form>
            @if ($ad['href'] !== null)
                <a href="{{ $ad['href'] }}" rel="nofollow noopener"><img src="{{ $ad['src'] }}" width="700" height="500" alt=""></a>
            @else
                <img src="{{ $ad['src'] }}" width="700" height="500" alt="">
            @endif
        </dialog>
    @else
        @php($image = '<img border="0" src="'.e($ad['src']).'" alt="">')
        @php($inner = $ad['href'] !== null ? '<a href="'.e($ad['href']).'" target="_blank" rel="nofollow noopener">'.$image.'</a>' : $image)
        {!! $ad['wrapper'] !== null ? '<div class="'.e($ad['wrapper']).'">'.$inner.'</div>' : $inner !!}
    @endif
@endif
