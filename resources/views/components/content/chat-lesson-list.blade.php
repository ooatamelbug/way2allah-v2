@props(['items'])

<ul class="w2a-chat-sidebar-list">
    @foreach ($items as $item)
        <li>
            <a href="/chat_lesson_{{ $item->id }}.htm" class="w2a-chat-sidebar-item">
                <span class="w2a-chat-sidebar-icon" aria-hidden="true">
                    <i class="fa {{ (int) $item->vedio === 1 ? 'fa-video-camera' : 'fa-headphones' }}"></i>
                </span>
                <span class="w2a-chat-sidebar-title">{{ trim((string) $item->title) }}</span>
            </a>
        </li>
    @endforeach
</ul>
