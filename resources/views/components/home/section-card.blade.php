{{--
    G-02 (Homepage Migration Blueprint §3): reproduces `functions.php:84-148`'s
    `w2a_open_div()`/`w2a_close_div()` markup exactly (col-md/col-sm wrapper,
    portlet box, portlet-title/caption with the icon special-case for
    'fa-quran', portlet-body). Extracted as a single small component because
    all 14 of the homepage's data-bearing sections repeat this identical
    wrapper — the one abstraction the Blueprint's architecture section
    explicitly allows ("a single small section-card component is allowed
    ONLY if the exact repeated wrapper genuinely requires it").
--}}
@props([
    'title',
    'icon' => 'fa-cogs',
    'color' => 'blue',
    'width' => '12',
    'widthSm' => '12',
    'bodyClass' => '',
])
<div class="col-md-{{ $width }} col-sm-{{ $widthSm }}">
    <div class="portlet box {{ $color }}">
        <div class="portlet-title">
            <div class="caption">
                @if ($icon === 'fa-quran')
                    <img style="width: 25px;" src="/images/icons/islamic_kuran.png" alt="">
                @else
                    <i class="fa {{ $icon }}"></i>
                @endif
                {{ $title }}
            </div>
        </div>
        <div class="portlet-body {{ $bodyClass }}">
            {{ $slot }}
        </div>
    </div>
</div>
