@extends('layouts.app')

{{-- live.php — hardcoded channel 51, script only, no details/sidebar,
     no visit-counting (IF-010). --}}

@section('title', 'البث المباشر')

@section('content')
    <div class="channel-script-container text-center">
        {!! $channel->streamcode !!}
    </div>
@endsection
