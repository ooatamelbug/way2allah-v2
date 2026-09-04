@props(['items', 'meta'])

<ul class="w2a-chat-sidebar-list">
    @foreach ($items as $item)
        <li>
            <a href="/var-item-{{ $item->id }}.htm" class="w2a-chat-sidebar-item">
                <span class="w2a-chat-sidebar-icon" aria-hidden="true"><i class="fa fa-film"></i></span>
                <span class="w2a-chat-sidebar-copy">
                    <span class="w2a-chat-sidebar-title">{{ $item->title }}</span>
                    <small>
                        @if ($meta === 'downloads')
                            <i class="fa fa-download" aria-hidden="true"></i> {{ number_format((int) $item->downcount) }} مرة
                        @else
                            <i class="fa fa-calendar" aria-hidden="true"></i> {{ \App\Domain\Content\Support\LegacyShortDateFormatter::format((int) $item->mytime) }}
                        @endif
                    </small>
                </span>
            </a>
        </li>
    @endforeach
</ul>
