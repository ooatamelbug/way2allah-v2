{{--
    advanced-search/index.php equivalent. Combined mawad+series (+topics
    for fatawa) response, per the approved deviation from legacy's
    two-request ajax_mawad/ajax_series split (SearchController's own
    docblock). gallery/cds are not offered as department options —
    confirmed dead code in legacy.
--}}
@extends('layouts.app')

@section('title', 'البحث المتقدم')

@section('content')
    <form method="post" action="/search.htm">
        @csrf
        <div class="form-group">
            <label for="kh_title">اسم السلسلة أو المادة</label>
            <input type="text" name="kh_title" id="kh_title" value="{{ $title }}">
        </div>
        <div class="form-group">
            <label for="kh_dept">القسم</label>
            <select name="kh_dept" id="kh_dept">
                <option value="">إختر</option>
                @foreach ($departments as $value => $label)
                    <option value="{{ $value }}" @selected($department === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label for="kh_author_name">الشيخ</label>
            <input type="text" name="kh_author_name" id="kh_author_name" value="{{ $authorId ?: '' }}">
        </div>
        <div class="form-group">
            <label for="kh_channel">القناة</label>
            <input type="text" name="kh_channel" id="kh_channel" value="{{ $channelId ?: '' }}">
        </div>
        <div class="form-group">
            <label for="kh_from">من</label>
            <input type="text" name="kh_from" id="kh_from" value="{{ $from }}">
            <label for="kh_to">إلى</label>
            <input type="text" name="kh_to" id="kh_to" value="{{ $to }}">
        </div>
        <button type="submit" name="kh_search">بحث</button>
    </form>

    @if (! $valid)
        @if ($title !== '' || $department !== '')
            <p>يجب إدخال عنوان لا يقل عن 4 أحرف واختيار قسم</p>
        @endif
    @else
        <section aria-label="نتائج البحث">
            @if ($mawad !== null)
                <h2>المواد</h2>
                <ul>
                    @forelse ($mawad as $item)
                        <li>{{ $item->title ?? $item->question_text ?? '' }}</li>
                    @empty
                        <li>لا يوجد مواد تطابق نتائج البحث</li>
                    @endforelse
                </ul>
                {{ $mawad->links() }}
            @endif

            @if ($series !== null)
                <h2>السلاسل</h2>
                <ul>
                    @forelse ($series as $item)
                        <li>{{ $item->title ?? $item->question_text ?? '' }}</li>
                    @empty
                        <li>لا يوجد سلاسل تطابق نتائج البحث</li>
                    @endforelse
                </ul>
                {{ $series->links() }}
            @endif

            @if ($topics !== null)
                <h2>الموضوعات</h2>
                <ul>
                    @forelse ($topics as $item)
                        <li>{{ $item->topic_name }}</li>
                    @empty
                        <li>لا يوجد موضوعات تطابق نتائج البحث</li>
                    @endforelse
                </ul>
                {{ $topics->links() }}
            @endif
        </section>
    @endif
@endsection
