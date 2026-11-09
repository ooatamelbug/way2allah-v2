@extends('layouts.app')

{{-- Replaces chat_room/lesson.php (browse/detail branch — op=getit/op=getmirror
     live at their own routes, see ChatRoomLessonController::download()). --}}

@section('title', $khotabItem->title.' - غرفة الهداية الدعوية')

@section('content')
    {{-- Gen 2 chat_room/lesson.php: title('غرفة الهداية - {lesson}') + breadcrumb(غرفة الهداية → chat_room.htm,
         {author} → chat_author_{id}.htm, {lesson}). Shared title()/breadcrumb() chrome. --}}
    <x-page-chrome :heading="'غرفة الهداية - '.$khotabItem->title" :breadcrumb="[
        ['title' => 'غرفة الهداية', 'url' => '/chat_room.htm'],
        ['title' => $authorModel->prename.' '.$authorModel->name, 'url' => '/chat_author_'.$authorModel->id.'.htm'],
        ['title' => $khotabItem->title],
    ]" />

    <div class="row service-box margin-bottom-40 w2a-media-layout">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding flex flex-column gap-5">
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

            <x-content.premium-panel title="قائمة الجودات المختلفة للدرس" icon="fa-files-o">
                <ul>
                    @foreach ($mirrors as $mirror)
                        <li><a href="/khotab-mirror-{{ $khotabItem->id }}-{{ $mirror->id }}.htm">{{ $mirror->comment }}</a></li>
                    @endforeach
                </ul>
            </x-content.premium-panel>

            @if ($relatedLessons->isNotEmpty())
                <x-content.premium-panel title="روابط ذات صلة" icon="fa-link">
                    <x-content.chat-item-list :items="$relatedLessons" related />
                </x-content.premium-panel>
            @endif
        </div>

        <aside class="col-lg-3 col-md-4 col-sm-5 nopadding flex flex-column gap-5" aria-label="الشريط الجانبي">
            <section class="w2a-refresh-panel w2a-author-profile-card" id="author-info">
                <a href="/chat_author_{{ $authorModel->id }}.htm"><img class="w2a-author-avatar" src="{{ $authorModel->displayImageUrl() }}" alt="{{ $authorModel->prename }} {{ $authorModel->name }}"></a>
                <h3 class="w2a-author-name"><a href="/chat_author_{{ $authorModel->id }}.htm">{{ $authorModel->prename }} {{ $authorModel->name }}</a></h3>
            </section>

            <x-content.premium-panel title="أكثر الدروس مشاهدة" icon="fa-eye">
                <x-content.chat-lesson-list :items="$mostViewed" />
            </x-content.premium-panel>

            <x-content.premium-panel title="أجدد التسجيلات" icon="fa-clock-o">
                <x-content.chat-lesson-list :items="$mostRecent" />
            </x-content.premium-panel>
        </aside>
    </div>

    <x-content.media-player-script />
@endsection
