@extends('layouts.admin')

{{-- streamcode is trusted, admin-authored embed HTML/JS (Channel's own
     docblock) — rendered unescaped deliberately, not a template bug. --}}

@section('title', 'تعديل بث قناة: '.$channel->title)

@section('content')
    {{--
        CONFIRMED_PAGE_MARKUP_GAP, fixed (AdminCP Final Page-Level
        Visual-Parity Verification, 2026-08-22): legacy `edit_stream.php`
        has 2 real, functional portlets — the edit form, and (only when a
        streamcode is already set) a separate "current code" preview
        portlet rendering the live embed. Both real, neither demo — no
        owner decision approved merging them, so they are reconstructed
        as 2 portlets here, not 1.
    --}}
    <x-admin-portlet :title="'تعديل بث قناة : '.$channel->title">
        <form method="post" action="{{ route('admin.broadcasting.update', $channel) }}">
            @csrf
            @method('PUT')
            <textarea name="streamcode" style="width:100%; height:200px;">{{ $channel->streamcode }}</textarea>
            <br><br>
            <button type="submit" class="btn green-haze">حفظ كود البث</button>
        </form>
    </x-admin-portlet>

    @if ($channel->streamcode)
        <x-admin-portlet :title="'الكود الحالي لـ '.$channel->title">
            <center>{!! $channel->streamcode !!}</center>
        </x-admin-portlet>
    @endif
@endsection
