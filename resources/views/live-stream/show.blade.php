@extends('layouts.app')

{{-- live-channel.php. `streamcode` is trusted admin-authored HTML/JS —
     rendered with {!! !!}, never {{ }} (see Channel model's docblock). --}}

@section('title', 'البث المباشر - قناة ' . $channel->title)

@section('content')
    <section aria-label="البث المباشر">
        <div class="channel-script-container text-center">
            {!! $channel->streamcode !!}
        </div>
    </section>

    <section aria-label="بيانات القناة">
        <h2>بيانات قناة {{ $channel->title }}</h2>
        {{-- G-13-08 (media/visual parity phase): live-stream/functions.php:89
             — the same flat images/channels/{id}.png as channels/index. --}}
        <img src="/images/channels/{{ $channel->id }}.png" alt="{{ $channel->title }}">
        <table>
            <tr><th>اسم القناة</th><td>{{ $channel->title }}</td></tr>
            <tr><th>القمر الصناعي</th><td>{{ $channel->satellite->title ?? '' }}</td></tr>
            <tr>
                <th>الموقع المداري</th>
                <td>{{ str_replace(['W', 'E'], ['غربا ', 'شرقا '], $channel->satellite->pos ?? '') }}</td>
            </tr>
            <tr><th>التردد</th><td>{{ $channel->freq }}</td></tr>
            <tr><th>الإستقطاب</th><td>{{ str_replace(['H', 'V'], ['أفقي', 'رأسي'], (string) $channel->polar) }}</td></tr>
            <tr><th>معدل الترميز</th><td>{{ $channel->srate }}</td></tr>
            <tr><th>معامل التصويب</th><td>{{ $channel->fec }}</td></tr>
            <tr><th>التشفير</th><td>{{ str_replace('FREE', 'مجانية', (string) $channel->enc) }}</td></tr>
            <tr><th>عدد الدروس</th><td>{{ $channel->khotab }}</td></tr>
            {{-- +1: legacy displays the visit count including the current visit,
                 ahead of the actual UPDATE (functions.php:133) — preserved exactly. --}}
            <tr><th>عدد الزيارات</th><td>{{ (int) $channel->ch_visits + 1 }}</td></tr>
        </table>
        <img src="/images/beams/{{ $channel->beamForDisplay() }}.png" alt="مجال التغطية">
    </section>

    <aside aria-label="الشريط الجانبي">
        <h3>اكثر المواد مشاهدة</h3>
        <ul>
            @foreach ($mostViewed as $item)
                <li><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
            @endforeach
        </ul>
        <h3>جديد القناة</h3>
        <ul>
            @foreach ($mostRecent as $item)
                <li><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
            @endforeach
        </ul>
    </aside>
@endsection
