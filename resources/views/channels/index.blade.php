@extends('layouts.app')

{{-- channels/channels.php. $panelTitle is deliberately blank — IF-011,
     the legacy panel title comes from an undefined $Anasheed variable. --}}

@section('title', 'قائمة القنوات الفضائية')

@section('content')
    {{--
        G-13-08 (media/visual parity phase): channels.php:42-43 — a flat,
        non-bucketed `images/channels/{id}.png` per channel, unrelated to
        MediaPathResolver's convention. Now reachable via the real
        public/images symlink (G-13-02).
    --}}
    <section aria-label="{{ $panelTitle }}">
        <ul>
            @foreach ($channels as $channel)
                <li>
                    <a href="/channel-{{ $channel->id }}.htm">
                        <img src="/images/channels/{{ $channel->id }}.png" alt="{{ $channel->title }}">
                        {{ $channel->title }} — {{ $channel->freq }} / {{ $channel->polar }} / {{ $channel->srate }} / {{ $channel->fec }}
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
@endsection
