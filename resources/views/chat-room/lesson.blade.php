@extends('layouts.app')

{{-- Replaces chat_room/lesson.php (browse/detail branch — op=getit/op=getmirror
     live at their own routes, see ChatRoomLessonController::download()). --}}

@section('title', $khotabItem->title.' - غرفة الهداية الدعوية')

@section('content')
    <nav aria-label="غرفة الهداية">
        <a href="/chat_room.htm">غرفة الهداية</a> /
        <a href="/chat_author_{{ $authorModel->id }}.htm">{{ $authorModel->prename }} {{ $authorModel->name }}</a> /
        {{ $khotabItem->title }}
    </nav>

    <div class="row service-box sh-w2a-block">
        <div class="col-xs-12 col-sm-12 col-md-9 telawah-item-content">
            <section class="mada-details" aria-label="تفاصيل المادة">
                <h1>{{ $khotabItem->title }}</h1>
                <p>{{ $authorModel->prename }} {{ $authorModel->name }}</p>
                <p>عدد الزيارات: {{ $khotabItem->hits }}</p>
                <p>عدد مرات الحفظ: {{ $khotabItem->downcount }}</p>
                <a href="/khotab-download-{{ $khotabItem->id }}.htm">حفظ المادة</a>
            </section>

            <nav aria-label="التنقل بين الدروس" class="w2a_next_previous_mada">
                @if ($previousLesson)
                    <a href="/chat_lesson_{{ $previousLesson->id }}.htm">{{ $previousLesson->title }}</a>
                @endif
                @if ($nextLesson)
                    <a href="/chat_lesson_{{ $nextLesson->id }}.htm">{{ $nextLesson->title }}</a>
                @endif
            </nav>

            <section aria-label="قائمة الجودات المختلفة للدرس">
                <h3>قائمة الجودات المختلفة للدرس</h3>
                <ul>
                    @foreach ($mirrors as $mirror)
                        <li><a href="/khotab-mirror-{{ $khotabItem->id }}-{{ $mirror->id }}.htm">{{ $mirror->comment }}</a></li>
                    @endforeach
                </ul>
            </section>

            @if ($relatedLessons->isNotEmpty())
                <section aria-label="روابط ذات صلة">
                    <h3>روابط ذات صلة</h3>
                    <ul>
                        @foreach ($relatedLessons as $related)
                            <li>
                                <a href="/chat_lesson_{{ $related->id }}.htm">{{ $related->title }}</a>
                                — <a href="/chat_author_{{ $related->author_id }}.htm">{{ $related->prename }} {{ $related->name }}</a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
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
