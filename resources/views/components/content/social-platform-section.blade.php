@props([
    'title',
    'icon',
    'items',
    'variant' => 'standard',
])

<x-content.premium-panel
    :title="$title"
    :icon="$icon"
    :description="'روابط موثوقة لمتابعة شبكة الطريق إلى الله على '.$title"
    class="w2a-social-section w2a-social-section--{{ $variant }}"
>
    <div class="w2a-social-grid">
        @foreach ($items as $page)
            <a
                class="w2a-social-card"
                href="{{ trim($page['link']) }}"
                title="{{ $page['name'] }}"
                target="_blank"
                rel="noopener noreferrer"
            >
                <span class="w2a-social-card__media">
                    <img
                        src="{{ \App\Domain\Content\Support\MediaUrl::asset('social-images/'.$page['image']) }}"
                        alt="{{ $page['alt'] }}"
                        width="180"
                        height="180"
                        loading="lazy"
                        decoding="async"
                    >
                </span>
                <span class="w2a-social-card__content">
                    <strong>{{ $page['name'] }}</strong>
                    <span>زيارة المنصة <i class="fa fa-external-link" aria-hidden="true"></i></span>
                </span>
            </a>
        @endforeach
    </div>
</x-content.premium-panel>
