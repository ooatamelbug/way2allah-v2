@extends('layouts.app')

{{--
    Visual parity audit (khotab-video-17.htm, 2026-08-18) Batch 1:
    author.php:33's <title> is the SAME `$title` variable as the
    `<h3 class="page-title">` text below (both come from `$header['title']`
    and `title($title)` respectively) — confirmed live identical
    ("مرئيات {author}" for op=video, not just the bare author name).
--}}
@section('title', $pageTitle)

@section('content')
    {{--
        Visual parity audit (khotab-video-17.htm, 2026-08-18) Batch 1:
        page-title + breadcrumb restored from author.php:53-57. Malformed
        legacy icon before the h3 (functions.php:541-543's `title()`) is
        the same confirmed authoring bug already not-reproduced elsewhere
        in this engagement. Breadcrumb's first two segments both point at
        `/khotab-{op}.htm` (author.php:53-54's own two entries share that
        exact URL) — the author-list page already restored in an earlier
        batch. Final segment uses `href=""` (author.php:55 sets `'url'=>''`
        explicitly, not omitted — breadcrumb_items() wraps it in `<a>`
        regardless of the empty value, same pattern already established
        for khotab/item.blade.php and khotab/authors.blade.php).
    --}}
    <div class="page-bar">
        <ul class="page-breadcrumb">
            <li><i class="fa fa-home"></i><a href="/">الرئيسية</a><i class="fa fa-angle-right"></i></li>
            <li><a href="/khotab-{{ $op }}.htm">{{ $opTitle }}</a><i class="fa fa-angle-right"></i></li>
            <li><a href="/khotab-{{ $op }}.htm">قائمة الدعاة</a><i class="fa fa-angle-right"></i></li>
            <li><a href="">{{ trim(($authorModel->prename ?? '').' '.($authorModel->name ?? '')) }}</a><i class=""></i></li>
        </ul>
    </div>
    <h3 class="page-title">{{ $pageTitle }}</h3>

    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
            @if($op !== 'pdf')
                {{--
                    Visual parity audit (khotab-video-17.htm, 2026-08-18)
                    Batch 2: ListGroup()'s exact row markup
                    (khotab/functions.php:360-402) — count (`fa-play-circle-o`)
                    always shown, channel badge (`fa-television`) conditional
                    on `channel_id`. `count`/`channel`/`channel_id` were
                    already selected by ContentListingService::groupsByAuthor()
                    (confirmed by reading it — no controller/query change
                    needed). Table id="tabelgrp" restored for markup parity
                    only — the khotab_tables.js DataTables enhancement that
                    targets it is a separate, deferred investigation, not
                    added here.
                --}}
                <div class="col-md-12 col-sm-12">
                    <div class="portlet box blue">
                        <div class="portlet-title">
                            <div class="caption"><i class="fa fa-child"></i> قائمة المجموعات</div>
                        </div>
                        <div class="portlet-body series-overflow">
                            <table class="table table-striped table-hover" id="tabelgrp">
                                <tbody>
                                    @foreach ($groups as $group)
                                        <tr>
                                            <td class="">
                                                <div class="row">
                                                    <div class="col-lg-12">
                                                        <h5>
                                                            <a href="/khotab-group-{{ $group->id }}.htm">{{ $group->title }}</a>
                                                        </h5>
                                                        <div class="row page-header color_00a">
                                                            <div class="col-md-3 col-xs-6 text-blue">
                                                                <span>
                                                                    <i class="fa fa-play-circle-o"></i>
                                                                    المواد:
                                                                    {{ $group->count }}
                                                                </span>
                                                            </div>
                                                            @if(!empty($group->channel_id))
                                                                <div class="col-md-3 col-xs-6 text-blue">
                                                                    <span>
                                                                        <i class="fa fa-television"></i>
                                                                        القناة:
                                                                        <a href="/channel-{{ $group->channel_id }}.htm"><img width="24" height="24" src="/images/channels/{{ $group->channel_id }}.png" alt=""></a>
                                                                    </span>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{--
                    Visual parity audit (khotab-video-17.htm, 2026-08-18)
                    Batch 2: ListSeries()'s exact row markup
                    (khotab/functions.php:452-495) — date (`fa-calendar`)
                    and last-updated (`fa-refresh`) both always shown
                    (`tinydate()` reproduced inline as `date('Y-m-d', ...)`
                    — a one-line equivalent, not a broad helper port), then
                    count/channel same as Groups.
                --}}
                <div class="col-md-12 col-sm-12">
                    <div class="portlet box blue">
                        <div class="portlet-title">
                            <div class="caption"><i class="fa fa-child"></i> قائمة السلاسل</div>
                        </div>
                        <div class="portlet-body series-overflow">
                            <table class="table table-striped table-hover" id="tableser">
                                <tbody>
                                    @foreach ($series as $item)
                                        <tr>
                                            <td class="">
                                                <div class="row">
                                                    <div class="col-lg-12">
                                                        <h5>
                                                            <a href="/khotab-series-{{ $item->id }}.htm">{{ $item->title }}</a>
                                                        </h5>
                                                        <div class="row page-header color_00a">
                                                            <div class="col-md-3 col-xs-6 text-blue">
                                                                <span>
                                                                    <i class="fa fa-calendar"></i>
                                                                    {{ $item->time ? date('Y-m-d', $item->time) : '' }}
                                                                </span>
                                                            </div>
                                                            <div class="col-md-3 col-xs-6 text-blue">
                                                                <span>
                                                                    <i class="fa fa-refresh"></i>
                                                                    {{ $item->lastupdate ? date('Y-m-d', $item->lastupdate) : '' }}
                                                                </span>
                                                            </div>
                                                            <div class="col-md-3 col-xs-6 text-blue">
                                                                <span>
                                                                    <i class="fa fa-play-circle-o"></i>
                                                                    المواد:
                                                                    {{ $item->count }}
                                                                </span>
                                                            </div>
                                                            @if(!empty($item->channel_id))
                                                                <div class="col-md-3 col-xs-6 text-blue">
                                                                    <span>
                                                                        <i class="fa fa-television"></i>
                                                                        القناة:
                                                                        <a href="/channel-{{ $item->channel_id }}.htm">
                                                                            <img width="24" height="24" border="0" src="/images/channels/{{ $item->channel_id }}.png" alt="" />
                                                                        </a>
                                                                    </span>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            {{--
                Visual parity audit (khotab-video-17.htm, 2026-08-18)
                Batch 2: ListKhotab()'s default-branch row markup
                (khotab/functions.php:643-706 — the only mode reachable
                from author.php, confirmed by re-reading the full
                function). date/comments/views always shown (default mode
                is neither 'day' nor 'fixed'/'new'); channel badge
                conditional on channel_id; duration (`fa-clock-o`,
                LegacyDurationFormatter — verified against 2 real items'
                raw `adur` values, byte-identical to live legacy) only
                when the *formatted* value isn't "00:00:00" — matching
                ListKhotab()'s own check, which runs on `$item->adur`
                AFTER it's already been reassigned to the formatted
                string, not the raw millisecond value.
                ContentListingService::khotabItemsDefault()'s own SELECT
                list already matches this function's default branch
                field-for-field — no controller/query change needed.
            --}}
            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-child"></i> قائمة المواد</div>
                    </div>
                    <div class="portlet-body series-overflow series-overflow-auto">
                        <table class="table table-striped table-hover" id="tabelkht">
                            <tbody>
                                @foreach ($items as $item)
                                    {{--
                                        `?? 0` — this same table also renders
                                        khotabPdfItemsByAuthor()'s result set
                                        when $op==='pdf' (an existing,
                                        pre-batch behavior: author.php
                                        actually calls a different function,
                                        ListPDF(), for that op — not traced
                                        or matched here, out of this batch's
                                        scope), whose SELECT has no `adur`
                                        column at all. Missing duration for
                                        pdf items resolves to "00:00:00",
                                        i.e. hidden — never a fatal error.
                                    --}}
                                    @php($duration = \App\Domain\Content\Support\LegacyDurationFormatter::format((int) ($item->adur ?? 0)))
                                    <tr>
                                        <td class="">
                                            <div class="row">
                                                <div class="col-lg-12">
                                                    <h5>
                                                        <div class="row">
                                                            <div class="col-sm-12 col-lg-8">
                                                                <a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a>
                                                            </div>
                                                        </div>
                                                    </h5>
                                                    <div class="row page-header color_00a">
                                                        <div class="col-md-3 col-xs-6 text-blue">
                                                            <span>
                                                                <i class="fa fa-calendar"></i>
                                                                {{ $item->time ? date('Y-m-d', $item->time) : '' }}
                                                            </span>
                                                        </div>
                                                        <div class="col-md-3 col-xs-6 text-blue">
                                                            <span>
                                                                <i class="fa fa-commenting-o"></i>
                                                                التعليقات:
                                                                {{ $item->comments }}
                                                            </span>
                                                        </div>
                                                        <div class="col-md-3 col-xs-6 text-blue">
                                                            <span>
                                                                <i class="fa fa-eye"></i>
                                                                مشاهدات:
                                                                {{ number_format($item->hits) }}
                                                            </span>
                                                        </div>
                                                        @if(!empty($item->channel_id))
                                                            <div class="col-md-3 col-xs-6 text-blue">
                                                                <span>
                                                                    <i class="fa fa-television"></i>
                                                                    القناة:
                                                                    <a href="/channel-{{ $item->channel_id }}.htm">
                                                                        <img width="24" height="24" border="0" src="/images/channels/{{ $item->channel_id }}.png" alt="" />
                                                                    </a>
                                                                </span>
                                                            </div>
                                                        @endif
                                                        @if($duration !== '00:00:00')
                                                            <div class="col-md-3 col-xs-6 text-blue">
                                                                <span>
                                                                    <i class="fa fa-clock-o"></i>
                                                                    {{ $duration }}
                                                                </span>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{--
                author.php:80-90's hand-rolled description block — no
                w2a_open_div() here in legacy, so (unlike every other
                portlet on this page) there is deliberately no
                portlet-title/caption/icon header, just the bare
                .portlet.box.blue > .portlet-body wrapper.
            --}}
            @if(!empty($authorModel->description))
                <div class="col-md-12 col-sm-12">
                    <div class="portlet box blue">
                        <div class="portlet-body">
                            <section aria-label="نبذة عن الداعية">{{ $authorModel->description }}</section>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <aside class="col-lg-3 col-md-4 col-sm-5 nopadding" aria-label="الشريط الجانبي">
            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-child"></i> الملف الشخصي</div>
                    </div>
                    <div class="portlet-body">
                        <div class="profile-userpic">
                            <img src="{{ $authorModel->displayImageUrl() }}" alt="">
                        </div>
                    </div>
                </div>
            </div>

            {{--
                Visual parity audit (khotab-video-17.htm, 2026-08-18)
                Batch 1: the video/audio promotional banner
                (author.php:110-138) — previously missing entirely.
                Self-link to this exact page (khotab-{op}-{author}.htm),
                confirmed live: width=192 height=71, portlet-body
                class="text-center" (from $data['class']), images/video.gif
                and images/audio.gif already exist on disk (served via the
                existing public/images symlink — no new asset needed).
                pdf op has no such banner in legacy (only video/audio
                branches exist), reproduced as the same @if/@elseif shape.
            --}}
            @if($op === 'video')
                <div class="col-md-12 col-sm-12">
                    <div class="portlet box blue">
                        <div class="portlet-title">
                            <div class="caption"><i class="fa fa-child"></i> مرئيات الداعية</div>
                        </div>
                        <div class="portlet-body text-center">
                            <a href="/khotab-video-{{ $authorModel->id }}.htm">
                                <img border="0" src="/images/video.gif" width="192" height="71" alt="">
                            </a>
                        </div>
                    </div>
                </div>
            @elseif($op === 'audio')
                <div class="col-md-12 col-sm-12">
                    <div class="portlet box blue">
                        <div class="portlet-title">
                            <div class="caption"><i class="fa fa-child"></i> صوتيات الداعية</div>
                        </div>
                        <div class="portlet-body text-center">
                            <a href="/khotab-audio-{{ $authorModel->id }}.htm">
                                <img border="0" src="/images/audio.gif" width="192" height="71" alt="">
                            </a>
                        </div>
                    </div>
                </div>
            @endif

            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-child"></i> اخترنا لك هذه المادة</div>
                    </div>
                    <div class="portlet-body">
                        <ul>
                            @foreach ($randomFeatured as $item)
                                <li><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-child"></i> الأكثر تحميلا</div>
                    </div>
                    <div class="portlet-body">
                        <ul>
                            @foreach ($mostDownloaded as $item)
                                @isset($item->thumb)
                                    <li class="media">
                                        <a class="pull-left" href="/khotab-item-{{ $item->id }}.htm"><img class="media-object" src="{{ $item->thumb }}" alt="{{ $item->title }}" style="width: 60px; height: 40px;"></a>
                                        <div class="media-body"><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></div>
                                    </li>
                                @else
                                    <li><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                                @endisset
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-child"></i> جديد المواد</div>
                    </div>
                    <div class="portlet-body">
                        <ul>
                            @foreach ($mostRecent as $item)
                                @isset($item->thumb)
                                    <li class="media">
                                        <a class="pull-left" href="/khotab-item-{{ $item->id }}.htm"><img class="media-object" src="{{ $item->thumb }}" alt="{{ $item->title }}" style="width: 60px; height: 40px;"></a>
                                        <div class="media-body"><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></div>
                                    </li>
                                @else
                                    <li><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                                @endisset
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </aside>
    </div>
@endsection
