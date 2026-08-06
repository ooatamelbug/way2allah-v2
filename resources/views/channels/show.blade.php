@extends('layouts.app')

{{-- channels/channel.php. Note: no hardcoded domain in the channel logo
     path (legacy hardcodes http://way2allah.com/..., P-018, a confirmed
     anti-pattern already cataloged elsewhere in the audit — not
     reproduced here). --}}

@section('title', 'قناة ' . $channelModel->title)

@section('content')
    @include('channels._listing', ['showAuthorLinks' => true])

    <aside aria-label="بيانات القناة">
        <h2>قناة {{ $channelModel->title }}</h2>
        <img src="/images/channels/{{ $channelModel->id }}.png" alt="{{ $channelModel->title }}">
        <p>التردد: {{ $channelModel->freq }}</p>
        <p>الإستقطاب: {{ $channelModel->polar }}</p>
        <p>معدل الترميز: {{ $channelModel->srate }}</p>
        <p>معامل التصويب: {{ $channelModel->fec }}</p>
        <p>التشفير: {{ $channelModel->enc }}</p>

        <h3>الأكثر تحميلا</h3>
        <ul>
            @foreach ($mostDownloaded as $item)
                <li><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
            @endforeach
        </ul>

        <h3>جديد المواد</h3>
        <ul>
            @foreach ($mostRecent as $item)
                <li><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
            @endforeach
        </ul>
    </aside>
@endsection
