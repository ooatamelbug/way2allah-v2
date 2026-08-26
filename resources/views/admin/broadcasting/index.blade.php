@extends('layouts.admin')

@section('title', 'البث المباشر')

@section('content')
    {{--
        AdminCP Final 12-Route Browser Visual Evidence (2026-08-23):
        legacy `index.php:16-49`'s `op=editstream` branch renders each
        channel as a real 110x110 thumbnail image
        (`images/channels/{id}.png`) inside a right-floated
        `span.attt`/`a`/`img`, NOT a plain text link in a list — found via
        a fresh, full read of the real source after noticing this page's
        list looked unexpectedly plain in a real browser screenshot.
        `images/channels/` is real, present, and already served (the
        existing `public/images` symlink -> `legacy-project/images`).
    --}}
    <x-admin-portlet title="تعديل بث قناة">
        @if ($channels->isEmpty())
            <p>لا توجد قنوات لديها كود بث حاليًا.</p>
        @else
            @foreach ($channels as $channel)
                <span class="attt" style="width:120px;height:120px;float:right">
                    <a href="{{ route('admin.broadcasting.edit', $channel) }}">
                        <img src="{{ asset('images/channels/'.$channel->id.'.png') }}" alt="" height="110" width="110">
                    </a>
                </span>
            @endforeach
            <div class="clearfix"></div>
        @endif
    </x-admin-portlet>
@endsection
