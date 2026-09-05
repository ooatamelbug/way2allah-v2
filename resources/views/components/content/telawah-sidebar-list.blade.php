@props(['items', 'meta'])

<ul class="w2a-chat-sidebar-list">
    @foreach ($items as $item)
        <li>
            <a href="/recite-item-{{ $item->id }}.htm" class="w2a-chat-sidebar-item">
                <span class="w2a-chat-sidebar-icon" aria-hidden="true">
                    <i class="fa {{ $meta === 'downloads' ? 'fa-volume-up' : 'fa-flash' }}"></i>
                </span>
                <span class="w2a-chat-sidebar-copy">
                    <span class="w2a-chat-sidebar-title">{{ $item->title }}</span>
                    @if ($meta === 'downloads' && isset($item->downcount))
                        <small><i class="fa fa-download" aria-hidden="true"></i> {{ number_format((int) $item->downcount) }} مرة</small>
                    @elseif ($meta === 'date' && ! empty($item->mytime))
                        <small><i class="fa fa-calendar" aria-hidden="true"></i> {{ \App\Domain\Content\Support\LegacyShortDateFormatter::format((int) $item->mytime) }}</small>
                    @endif
                </span>
            </a>
        </li>
    @endforeach
</ul>
