@props(['href', 'title', 'subtitle' => null, 'icon' => 'fa-play'])

<li>
    <a href="{{ $href }}" class="w2a-homecss-item">
        <div class="w2a-homecss-icon" aria-hidden="true"><i class="fa {{ $icon }}"></i></div>
        <div class="w2a-homecss-text">
            <span class="top_video">{{ $title }}</span>
            @if (filled($subtitle))
                <span class="w2a-homecss-sub"><i class="fa fa-user" aria-hidden="true"></i> {{ $subtitle }}</span>
            @endif
        </div>
        <span class="w2a-homecss-arrow" aria-hidden="true"><i class="fa fa-chevron-left"></i></span>
    </a>
</li>
