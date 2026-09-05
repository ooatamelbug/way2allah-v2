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
            <section class="portlet box blue" aria-label="تفاصيل المادة">
                <div class="portlet-title">
                    <div class="caption"><i class="fa {{ $khotabItem->vedio ? 'fa-video-camera' : 'fa-microphone' }}"></i> {{ $khotabItem->title }}</div>
                </div>
                <div class="portlet-body">
                    <x-content.media-details-card
                        :item="$khotabItem"
                        module="khotab"
                        :date="$khotabItem->time ? \App\Domain\Content\Support\LegacyShortDateFormatter::format((int) $khotabItem->time) : ''"
                        :size="\App\Domain\Content\Support\LegacyFileSizeFormatter::format((int) ($khotabItem->linksize ?? 0))"
                        :download-url="'/khotab-download-'.$khotabItem->id.'.htm'"
                        :speaker="trim(($authorModel->prename ?? '').' '.($authorModel->name ?? ''))"
                        :show-comment-action="false"
                        :show-share-action="false"
                    />
                </div>
            </section>

            <x-content.media-player-panel />

            <nav aria-label="التنقل بين الدروس" class="w2a-nav-prev-next">
                @if ($previousLesson)
                    <a href="/chat_lesson_{{ $previousLesson->id }}.htm" class="w2a-nav-item w2a-nav-prev">
                        <i class="fa fa-arrow-right" aria-hidden="true"></i>
                        <span class="w2a-nav-text"><span class="w2a-nav-label">المادة السابقة</span><span class="w2a-nav-title">{{ $previousLesson->title }}</span></span>
                    </a>
                @endif
                @if ($nextLesson)
                    <a href="/chat_lesson_{{ $nextLesson->id }}.htm" class="w2a-nav-item w2a-nav-next">
                        <span class="w2a-nav-text"><span class="w2a-nav-label">المادة التالية</span><span class="w2a-nav-title">{{ $nextLesson->title }}</span></span>
                        <i class="fa fa-arrow-left" aria-hidden="true"></i>
                    </a>
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
                    <x-content.chat-item-list :items="$relatedLessons" related />
                </section>
            @endif
        </div>

        <aside class="col-xs-12 col-sm-12 col-md-3" aria-label="الشريط الجانبي">
            <div id="author-info">
                <a href="/chat_author_{{ $authorModel->id }}.htm"><img src="{{ $authorModel->displayImageUrl() }}" alt="{{ $authorModel->prename }} {{ $authorModel->name }}"></a>
            </div>

            <h3>أكثر الدروس مشاهدة</h3>
            <x-content.chat-lesson-list :items="$mostViewed" />

            <h3>أجدد التسجيلات</h3>
            <x-content.chat-lesson-list :items="$mostRecent" />
        </aside>
    </div>

    <x-content.media-player-script />
@endsection
