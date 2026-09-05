@props([
    'title',
    'icon' => 'fa-star',
    'description' => null,
    'bodyClass' => '',
])

<section {{ $attributes->class(['w2a-refresh-panel']) }}>
    <header class="w2a-refresh-panel__header">
        <span class="w2a-refresh-panel__icon" aria-hidden="true">
            <i class="fa {{ $icon }}"></i>
        </span>
        <div class="w2a-refresh-panel__heading">
            <h2>{{ $title }}</h2>
            @if ($description)
                <p>{{ $description }}</p>
            @endif
        </div>
    </header>
    <div @class(['w2a-refresh-panel__body', $bodyClass])>
        {{ $slot }}
    </div>
</section>
