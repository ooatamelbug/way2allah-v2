@extends('layouts.app')

{{--
    `chat_room.htm` Owner-Approved Partial Reconstruction. Legacy source
    (`chat_room/chat_rooms.php`, re-read in full) renders 5 sections;
    OWNER_DECISION: live FlashChat rooms (`list_chat_rooms()`) and the
    weekly live-lesson schedule (`list_today_lessons()`, `nuke_hedaya_lessons`)
    are retired — the corrected business-confirmation record (decision-log
    #14) is "FlashChat = NO, Zoom = NO, no replacement of any kind" — and
    are intentionally OMITTED SILENTLY here: no room listing, no empty-room
    message, no lesson schedule, no retired/unavailable notice. Only the
    3 still-active recorded-lesson-discovery sections are reconstructed,
    kept in their original main-column/sidebar positions.
--}}
@section('title', 'غرفة الهداية الدعوية - '.config('app.name'))

@push('styles')
    {{--
        register_css('css/custom.css') (chat_rooms.php:5) — genuinely used
        by the retained markup below: .author-block.author .author-name
        and .recent_list both have real rules in this file (confirmed by
        direct grep), unlike the fatawa task's page-specific new-style.css
        which had no confirmed selector usage.
    --}}
    <link rel="stylesheet" href="/css/custom.css">
@endpush

@section('content')
    {{--
        title()/breadcrumb() — the shared chrome mechanism (confirmed live
        on production: <h3 class="page-title">...</h3> + .page-bar), NOT
        the fatawa-specific page_bar() family. <x-page-chrome> applies
        directly. chat_rooms.php:12's breadcrumb item has no 'url' key at
        all (not even '') — renders as plain text, matching production's
        real `<li>غرفة الهداية<i class=""></i></li>` with no <a>. The
        malformed legacy "gift" icon before the heading is a confirmed
        dead artifact (component's own docblock) — not reproduced.
    --}}
    <x-page-chrome
        heading="الغرف الصوتية - غرفة الهداية الدعوية"
        :breadcrumb="[['title' => 'غرفة الهداية']]"
    />

    <div class="row service-box margin-bottom-40 sh-w2a-block">
        <div class="col-xs-12 col-sm-12 col-md-9 telawah-item-content">
            <div class="row">
                {{--
                    list_most_chat_room_authors() (functions.php:196-232) —
                    w2a_open_div() portlet, real query
                    (ContentListingService::mostActiveAuthorsAtLocation()),
                    ORDER BY lessons_count DESC LIMIT 15 (not alphabetical
                    — a deliberately different query from authorsByLocation()).
                    Author image: fallbackImageUrl() (get_author_img_src()'s
                    own contract — see controller docblock), not
                    displayImageUrl().
                --}}
                <div id="" class="col-md-12 col-sm-12">
                    <div class="portlet box blue">
                        <div class="portlet-title">
                            <div class="caption">
                                <i class="fa fa-users"></i>
                                الدعاة الأكثر مشاركة في الغرفة
                            </div>
                        </div>
                        <div class="portlet-body ">
                            <div class="w2a-chat-authors-grid">
                                @forelse ($mostActiveAuthors as $item)
                                    @php($authorName = trim($item->prename.' '.$item->name))
                                    <a href="/chat_author_{{ $item->id }}.htm" class="w2a-chat-author-card">
                                        <span class="w2a-chat-author-avatar-wrap">
                                            <img src="{{ $authorImages->get($item->id)?->fallbackImageUrl() }}" alt="{{ $authorName }}" width="48" height="48" loading="lazy" decoding="async">
                                        </span>
                                        <span class="w2a-chat-author-info">
                                            <span class="w2a-chat-author-name">{{ $authorName }}</span>
                                            <span class="w2a-chat-author-count"><i class="fa fa-microphone" aria-hidden="true"></i> {{ number_format((int) $item->lessons_count) }} درس</span>
                                        </span>
                                    </a>
                                @empty
                                    <div class="text-center alert alert-danger">عفوا ، لا يوجد نتائج</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xs-12 col-sm-12 col-md-3 nopadding">
            <div class="row">
                {{--
                    most_viewed_chat_lessons()/most_recent_chat_lessons()
                    (functions.php:233-271) — ContentSidebarWidget's
                    already-migrated, byte-for-byte equivalents. Legacy
                    only calls w2a_open_div() when results exist
                    ($TotalList > 0) — no empty-state markup for these two
                    at all (unlike the authors section above), reproduced
                    via @if/isNotEmpty() rather than @forelse.
                --}}
                @if ($mostViewed->isNotEmpty())
                    <div id="" class="col-md-12 col-sm-12">
                        <div class="portlet box blue top_side">
                            <div class="portlet-title">
                                <div class="caption">
                                    <i class="fa fa-eye"></i>
                                    أكثر دروس الغرفة مشاهدة
                                </div>
                            </div>
                            <div class="portlet-body ">
                                <x-content.chat-lesson-list :items="$mostViewed" />
                            </div>
                        </div>
                    </div>
                @endif

                @if ($mostRecent->isNotEmpty())
                    <div id="" class="col-md-12 col-sm-12">
                        <div class="portlet box blue top_side">
                            <div class="portlet-title">
                                <div class="caption">
                                    <i class="fa fa-flash"></i>
                                    أجدد تسجيلات الغرفة
                                </div>
                            </div>
                            <div class="portlet-body ">
                                <x-content.chat-lesson-list :items="$mostRecent" />
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
