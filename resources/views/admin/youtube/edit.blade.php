@extends('layouts.admin')

@section('title', 'اليوتيوب الرئيسية')

@section('content')
    {{--
        CONFIRMED_PAGE_MARKUP_GAP, fixed (AdminCP Final Page-Level
        Visual-Parity Verification, 2026-08-22): legacy `youtube/index.php`
        wraps the add-video form in a purple portlet ("مقطع اليوتيوب") and
        each added video in its own "portlet light bordered" sub-portlet
        ("الفيديو رقم: X") — neither existed here before.

        A second gap, found and fixed in the AdminCP Final 12-Route
        Browser Visual Evidence pass (2026-08-23): `youtube/index.php:138`
        embeds each video as a real 560x315 YouTube iframe player, not the
        raw video id as text — found via the same real-source re-read
        that caught `broadcasting/index.blade.php`'s missing channel
        thumbnails.
    --}}
    <x-admin-portlet title="مقطع اليوتيوب">
        <form method="post" action="{{ route('admin.youtube.store') }}">
            @csrf
            <label>رابط المقطع باليوتيوب <input type="text" name="youtube" class="form-control"></label>
            <button type="submit" class="btn green">اضافة</button>
        </form>

        @foreach ($videoIds as $index => $videoId)
            <x-admin-portlet :title="'الفيديو رقم: '.($index + 1)" light icon="icon-cursor" width="6">
                <center>
                    <iframe width="560" height="315" src="https://www.youtube.com/embed/{{ $videoId }}?rel=0&amp;controls=0" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>
                </center>
                <form method="post" action="{{ route('admin.youtube.destroy', $index) }}" style="display:inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-circle red"><i class="fa fa-remove"></i> مسح الفيديو</button>
                </form>
            </x-admin-portlet>
        @endforeach
    </x-admin-portlet>
@endsection
