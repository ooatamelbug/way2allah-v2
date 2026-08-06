@extends('layouts.app')

{{-- Replaces chat_room/author.php. --}}

@section('title', $authorModel->prename.' '.$authorModel->name.' - غرفة الهداية الدعوية')

@section('content')
    <nav aria-label="غرفة الهداية"><a href="/chat_room.htm">غرفة الهداية</a> / {{ $authorModel->prename }} {{ $authorModel->name }}</nav>

    <div class="row service-box margin-bottom-40 sh-w2a-block">
        <div class="col-xs-12 col-sm-12 col-md-9 telawah-item-content">
            <section aria-label="قائمة المجموعات">
                <h3>قائمة المجموعات</h3>
                <ul>
                    @foreach ($groups as $group)
                        <li><a href="/khotab-group-{{ $group->id }}.htm">{{ trim($group->title) }}</a> ({{ $group->count }})</li>
                    @endforeach
                </ul>
            </section>

            <section aria-label="قائمة السلاسل">
                <h3>قائمة السلاسل</h3>
                <ul>
                    @foreach ($series as $item)
                        <li><a href="/khotab-series-{{ $item->id }}.htm">{{ trim($item->title) }}</a> ({{ $item->count }})</li>
                    @endforeach
                </ul>
            </section>

            <section aria-label="قائمة المواد">
                <h3>قائمة المواد</h3>
                <ul>
                    @foreach ($items as $item)
                        <li><a href="/chat_lesson_{{ $item->id }}.htm">{{ trim($item->title) }}</a></li>
                    @endforeach
                </ul>
            </section>
        </div>

        <aside class="col-xs-12 col-sm-12 col-md-3" aria-label="الشريط الجانبي">
            <div id="author-info">
                <a href="/chat_author_{{ $authorModel->id }}.htm"><img src="{{ $authorModel->displayImageUrl() }}" alt="{{ $authorModel->prename }} {{ $authorModel->name }}"></a>
            </div>

            <h3>أكثر الدروس مشاهدة</h3>
            <ul>
                @foreach ($mostViewed as $item)
                    <li><a href="/chat_lesson_{{ $item->id }}.htm">{{ $item->title }}</a></li>
                @endforeach
            </ul>

            <h3>أجدد التسجيلات</h3>
            <ul>
                @foreach ($mostRecent as $item)
                    <li><a href="/chat_lesson_{{ $item->id }}.htm">{{ $item->title }}</a></li>
                @endforeach
            </ul>
        </aside>
    </div>
@endsection
