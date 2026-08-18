@extends('layouts.app')

@section('title', 'البث المباشر للقنوات الفضائية الإسلامية')

@section('content')
    {{--
        G-13-08 (media/visual parity phase): list_live_channels()
        (functions.php:24) shows a flat images/channels/{id}.png per row.
        most_viewed_channels() (functions.php:36-55, the sidebar below)
        does NOT — confirmed by direct reading, it's a plain
        <i class="fa fa-desktop"></i> icon, not an <img> — so the sidebar
        below is intentionally left as plain links, not a gap.
    --}}
    <section aria-label="قائمة القنوات">
        <ul>
            @forelse ($channels as $channel)
                <li>
                    <a href="/live-channel-{{ $channel->id }}.htm">
                        <img src="/images/channels/{{ $channel->id }}.png" alt="{{ $channel->title }}" width="100" height="75">
                        <span>{{ $channel->title }}</span>
                    </a>
                </li>
            @empty
                <li>لا توجد قنوات متاحة حاليا</li>
            @endforelse
        </ul>
    </section>

    <aside aria-label="أكثر القنوات مشاهدة">
        <h2>أكثر القنوات مشاهدة</h2>
        <ul>
            @foreach ($mostViewedChannels as $channel)
                <li><a href="/live-channel-{{ $channel->id }}.htm">{{ $channel->title }}</a></li>
            @endforeach
        </ul>
    </aside>
@endsection
