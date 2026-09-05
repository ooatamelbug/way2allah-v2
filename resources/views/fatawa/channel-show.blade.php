@extends('layouts.app')

{{--
    Legacy-Source Reconstruction (fatawa-channel-{id}.htm): production
    pretty URL (`https://way2allah.com/fatawa-channel-{id}.htm`) currently
    404s and `modules.php` (the `.htaccess:291` dispatcher target) does
    not exist in this snapshot — SOURCE_UNRECOVERABLE for that specific
    file. The real render logic is directly recoverable, though:
    `fatawa/channel_fatawa.php` (read in full) is the file `modules.php`'s
    `op=list_Fatawa_for_channel` op must dispatch to (same naming/content
    match as the already-established `fatawa-channels.php` ↔
    `fatawa-channels.htm` precedent), and its own raw path
    (`https://way2allah.com/fatawa/channel_fatawa.php?id=...`) is LIVE and
    was used to verify this reconstruction (LIVE_RENDER_VERIFIED for that
    path, not the pretty URL).

    channel_fatawa.php:22's real document title:
    'قناة '.$title.' ('.$channel->sat_title.') - الفتاوى المرئية - الطريق إلى الله'
    — already includes the sitename, and header.php:26 appends
    ' - '.$sitename AGAIN unconditionally on top of that — the same
    confirmed genuine double-suffix pattern already reproduced for
    var-item-{id}.htm (not a mistake to "fix" here either).
--}}
@section('title', 'قناة '.$title.' ('.($channelModel->satellite->title ?? '').') - الفتاوى المرئية - '.config('app.name'))

{{--
    channel_fatawa.php:24-25 registers `fatawa/css/new-style.css` (a real
    file, confirmed on disk at legacy-project/fatawa/css/new-style.css)
    and the Cairo|Reem+Kufi Google Fonts link. The stylesheet is
    ASSET_PRESENT but NOT locally reachable — no `public/fatawa` symlink
    exists (confirmed: `public/` only symlinks css/images/scripts/media/
    assets/w2a_autocomplete/w2a_autocomplete, not a nested `fatawa/`
    path) — same already-established finding as fatawa/topics-index.blade.php's
    own comment on this exact file ("not wired into the shared layout").
    Not pushed here either, for the same reason; only the remote,
    always-reachable Google Fonts link is added.
--}}
@push('styles')
    <link href="https://fonts.googleapis.com/css?family=Cairo|Reem+Kufi" rel="stylesheet">
@endpush

@section('content')
    {{--
        Legacy-Source Reconstruction: channel_fatawa.php:29's
        `page_bar_channels('قائمة القنوات الفضائية', $id)`
        (fatawa/functions.php:321-352) — a DIFFERENT, hand-rolled chrome
        mechanism from the shared `title()`/`breadcrumb()` pair (and from
        the Shared Page Chrome Parity Audit's `<x-page-chrome>` component,
        deliberately NOT used here). Reproduced exactly as read from
        source: an empty `<h1 style="">` (no text at all — legacy's own
        real behavior, not a bug to "fix" with a heading), Home's own
        `<li>` has NO trailing separator icon (unlike the shared
        breadcrumb() shape), every subsequent `<li>` puts its
        `fa-angle-right` icon BEFORE the link (not after), and there is no
        "last item" special case at all — the channel `<li>` ends the same
        way every other item does. `$channel_id`/`$channelname`
        (functions.php:330-331) match `$channelModel->id`/`$title` exactly
        (same `nuke_sat_channels` row) — reused rather than re-queried.
    --}}
    <h1 style=""></h1>
    <div class="page-bar">
        <ul class="page-breadcrumb">
            <li><i class="fa fa-home"></i><a href="/"> الرئيسية</a></li><li> <i class="fa fa-angle-right"></i><a href="/fatawa.htm">الفتاوى </a></li><li> <i class="fa fa-angle-right"></i><a href="/fatawa-channels.htm">قائمة القنوات الفضائية</a></li><li> <i class="fa fa-angle-right"></i><a href="/fatawa-channel-{{ $channelModel->id }}.htm">{{ $title }}</a></li>
        </ul>
    </div>

    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
            {{--
                channel_fatawa.php:39-104's "بيانات قناة" info portlet —
                previously entirely missing (the prior view had only 2
                bare <img> tags, no portlet, no metadata). Legacy wraps
                this in `if($channel){...}` (channel_fatawa.php:35) — since
                the controller already 404s via findOrFail() for a missing
                channel, this box is unconditional here (the guard is
                already satisfied by the time this view renders).
                Satellite icon + satellite-name link both point at
                /fatawa-channels.htm (channel_fatawa.php:48,53 — the SAME
                target for both, not a typo, reproduced as found). Channel
                logo path (G-13-10, already-established): flat
                /images/channels/{id}.png. Channel logo's own link
                (channel_fatawa.php:63, `$fatawaurl.'channel_fatawa.php?id='.$channel->id`)
                is a raw-legacy-PHP self-reference to this exact page's
                content — translated to the equivalent, already-existing
                Laravel pretty route (/fatawa-channel-{id}.htm), the same
                "translate a legacy self-link to its real migrated
                equivalent" rule already applied sitewide, not a new route.
                Beam/coverage image: HARDCODED images/beams/1.png
                (channel_fatawa.php:69) — PROTECTED, already-established,
                NOT `$channel->beam`-driven, unchanged here.
            --}}
            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-info"></i>بيانات قناة {{ $title }}</div>
                    </div>
                    <div class="portlet-body">
                        <div class="row">
                            <div class="col-lg-2 col-md-6 col-sm-6 text-sm-center">
                                <a href="/fatawa-channels.htm"><img src="https://way2allah.com/images/admin/satellite-icon.png"></a>
                            </div>
                            <div class="col-lg-3 col-md-6 col-sm-6 text-sm-center">
                                <div class="ch-item">اسم القناة : {{ $title }} </div>
                                <div class="ch-item">القمر الصناعي : <a href="/fatawa-channels.htm">{{ $channelModel->satellite->title ?? '' }}</a></div>
                                <div class="ch-item">الموقع المداري : {{ $position }}</div>
                                <div class="ch-item">التردد : {{ $channelModel->freq }}</div>
                                <div class="ch-item">الإستقطاب : {{ $channelModel->polar }}</div>
                                <div class="ch-item">معدل الترميز : {{ $channelModel->srate }}</div>
                                <div class="ch-item">معامل التصويب : {{ $channelModel->fec }}</div>
                                <div class="ch-item">التشفير : {{ $channelModel->enc }}</div>
                            </div>
                            <div class="col-lg-3 col-md-12 col-sm-12 pt-2 text-center channel_fatawa_cont">
                                <a href="/fatawa-channel-{{ $channelModel->id }}.htm"><img src="/images/channels/{{ $channelModel->id }}.png" style="max-width: 100%" alt="قناة إقرأ" title="قناة إقرأ"></a>
                            </div>
                            <div class="col-lg-4 col-md-12 col-sm-12 text-sm-center channel_fatawa_cont">
                                <div class="ch-beam">مجال التغطية<br><img src="/images/beams/1.png" class="img-responsive center-block"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{--
                channel_fatawa.php:106-140's questions-list portlet, real
                `get_all_channel_questions()` row markup (fatawa/functions.php:
                524-578) — table#sample_5, no visible header row (both
                <thead> and the bottom header <tr> are HTML-commented out
                in legacy source, confirmed by direct reading, not an
                oversight to "restore"). $question->topic/->author are
                already resolved by ContentListingService::fatwaQuestionsForChannel()
                (existing query, unchanged) — /fatawa-group-{id}-{parent}.htm
                and /auther-questions-{id}.htm are both already-established
                real routes used identically elsewhere (FatwaTopicController,
                FatwaAuthorController), not invented here.

                Pagination uses the shared public-site premium view. Query,
                page size, and generated route URLs remain unchanged.
            --}}
            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-plus"></i>الفتاوى المضافة على قناة {{ $title }}</div>
                    </div>
                    <div class="portlet-body series-overflow">
                        {{ $generalQuestions->onEachSide(1)->links('components.content.premium-pagination') }}
                        <table class="table table-striped table-hover" id="sample_5">
                            <tbody>
                                @foreach ($generalQuestions as $question)
                                    <tr>
                                        <td class="">
                                            <div class="row">
                                                <div class="col-lg-12">
                                                    <h5>
                                                        <a href="/fatawa-all-{{ $question->id }}.htm">{{ $question->question_text }}</a>
                                                    </h5>
                                                    <div class="row page-header color_00a">
                                                        <div class="col-sm-6 col-xs-12">
                                                            الموضوع:
                                                            <span class="text-blue">
                                                                <a href="/fatawa-group-{{ $question->topic->id ?? 0 }}-{{ $question->topic->parent_id ?? 0 }}.htm">{{ $question->topic->topic_name ?? '' }}</a>
                                                            </span>
                                                        </div>
                                                        <div class="col-sm-6 col-xs-12">
                                                            الشيخ:
                                                            <span class="text-blue">
                                                                <a href="/auther-questions-{{ $question->author->id ?? 0 }}.htm">{{ $question->author->prename ?? '' }} {{ $question->author->name ?? '' }}</a>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        {{ $generalQuestions->onEachSide(1)->links('components.content.premium-pagination') }}
                    </div>
                </div>
            </div>
        </div>

        <aside class="col-lg-3 col-md-4 col-sm-5 nopadding" aria-label="الشريط الجانبي">
            {{--
                channel_fatawa.php:145-170's sidebar — real portlet wrapper
                (previously a bare <h3>), and the real `mostdownload()`/
                `recentlyadd()` link shape (fatawa/functions.php:679-704):
                `/fatawa-all-{general_question_id}.htm#{id}`, class="add"
                — NOT `/fatawa-download-{id}.htm` (the prior markup's link,
                which doesn't match any real legacy href anywhere in this
                page's source). The underlying query
                (ContentSidebarWidget::fatwaMostDownloadedByChannel()/
                fatwaMostRecentByChannel()) already selects
                `general_question_id` — this was a view-only bug, no query
                change needed.
            --}}
            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-download"></i>الأكثر تحميلا </div>
                    </div>
                    <div class="portlet-body">
                        <ul class="news">
                            @foreach ($mostDownloaded as $item)
                                <li><a href="/fatawa-all-{{ str_replace('|', '', (string) $item->general_question_id) }}.htm#{{ $item->id }}" class="add">{{ $item->question_text }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-plus"></i>جديد المواد </div>
                    </div>
                    <div class="portlet-body">
                        <ul class="news">
                            @foreach ($mostRecent as $item)
                                <li><a href="/fatawa-all-{{ str_replace('|', '', (string) $item->general_question_id) }}.htm#{{ $item->id }}" class="add">{{ $item->question_text }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </aside>
    </div>
@endsection
