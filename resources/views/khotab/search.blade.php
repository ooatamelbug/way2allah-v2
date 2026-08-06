@extends('layouts.app')

{{--
    khotab/search.php. IF-023 fix: title reflects the page itself, not an
    undefined $Author. IF-024 fix: titleTooShort only blocks the search
    when a title was actually supplied.
--}}

@section('title', 'البحث المتقدم في المرئيات')

@section('content')
    <section aria-label="البحث المتقدم في المرئيات">
        <form method="get" action="{{ route('khotab.search') }}">
            <label for="title">اسم السلسلة أو المادة</label>
            <input type="text" id="title" name="title" value="{{ $title }}">

            <label for="author">الشيخ</label>
            <select id="author" name="author">
                <option value="0">إختر</option>
                @foreach ($authors as $author)
                    <option value="{{ $author->id }}" @selected($authorId === $author->id)>{{ $author->name }}</option>
                @endforeach
            </select>

            <label for="channel">القناة</label>
            <select id="channel" name="channel">
                <option value="0">إختر</option>
                @foreach ($channels as $channel)
                    <option value="{{ $channel->id }}" @selected($channelId === $channel->id)>{{ $channel->title }}</option>
                @endforeach
            </select>

            <label for="from">من تاريخ</label>
            <input type="text" id="from" name="from" value="{{ $from }}">

            <label for="to">إلى تاريخ</label>
            <input type="text" id="to" name="to" value="{{ $to }}">

            <button type="submit">بــحــث</button>
        </form>

        @if($titleTooShort)
            <p role="alert">عفواً ، يجب إدخال أربعة أحرف على الأقل للبحث</p>
        @endif
    </section>

    @if($series !== null)
        <section aria-label="قائمة السلاسل">
            <h3>قائمة السلاسل</h3>
            @forelse($series as $item)
                <div>
                    <a href="/khotab-series-{{ $item->id }}.htm">{{ $item->title }}</a>
                    — <a href="/khotab-video-{{ $item->author_id }}.htm">{{ $item->prename }} {{ $item->name }}</a>
                </div>
            @empty
                <p>لا يوجد سلاسل تطابق نتائج البحث</p>
            @endforelse
            {{ $series->links() }}
        </section>

        <section aria-label="قائمة المواد">
            <h3>قائمة المواد</h3>
            @forelse($items as $item)
                <div>
                    <a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a>
                    — <a href="/khotab-video-{{ $item->author }}.htm">{{ $item->prename }} {{ $item->name }}</a>
                </div>
            @empty
                <p>لا يوجد مواد تطابق نتائج البحث</p>
            @endforelse
            {{ $items->links() }}
        </section>
    @endif
@endsection
