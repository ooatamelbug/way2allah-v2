@extends('layouts.app')

@section('title', 'قائمة القنوات الفضائية')

@section('content')
    <section aria-label="قائمة القنوات">
        <ul>
            <li><a href="/fatawa-channel-0.htm">بدون قناة</a></li>
            @foreach ($channels as $channel)
                <li><a href="/fatawa-channel-{{ $channel->id }}.htm">{{ $channel->title }}</a></li>
            @endforeach
        </ul>
        {{ $channels->links() }}
    </section>
@endsection
