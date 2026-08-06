@extends('layouts.app')

@section('title', 'البث المباشر للقنوات الفضائية الإسلامية')

@section('content')
    <section aria-label="قائمة القنوات">
        <ul>
            @forelse ($channels as $channel)
                <li>
                    <a href="/live-channel-{{ $channel->id }}.htm">{{ $channel->title }}</a>
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
