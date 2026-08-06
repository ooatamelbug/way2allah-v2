@extends('layouts.app')

{{-- channels/channels.php. $panelTitle is deliberately blank — IF-011,
     the legacy panel title comes from an undefined $Anasheed variable. --}}

@section('title', 'قائمة القنوات الفضائية')

@section('content')
    <section aria-label="{{ $panelTitle }}">
        <ul>
            @foreach ($channels as $channel)
                <li>
                    <a href="/channel-{{ $channel->id }}.htm">
                        {{ $channel->title }} — {{ $channel->freq }} / {{ $channel->polar }} / {{ $channel->srate }} / {{ $channel->fec }}
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
@endsection
