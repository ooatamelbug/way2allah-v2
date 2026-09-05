@extends('layouts.app')

{{--
    Full Design Parity Pass (2026-08-22) — fatawa-topics-{category}-{page}.htm
    ONLY. Full source re-read (`fatawa/tobics.php`, `fatawa/functions.php`'s
    `page_bar()`/`under_this_tasnif()`/`get_all_tasnifat()`/`mostdownload()`/
    `recentlyadd()`), cross-checked against a fresh live fetch
    (`fatawa/tobics.php?id=49`, LIVE_RENDER_VERIFIED, no drift from source).

    `<title>` — tobics.php:15's own header string is `'الفتاوى المرئية | '.
    topic_name($id)` (topic_name() queries nuke_w2a_cat despite its name —
    the CATEGORY's own title). No manual sitename concat needed: unlike
    channel-show.blade.php's confirmed genuine double-suffix case,
    tobics.php's own title string does NOT include the sitename itself —
    the layout's single `@yield('title') - {{ config('app.name') }}`
    already matches the live-confirmed single-suffix output exactly.
--}}
@section('title', 'الفتاوى المرئية | '.$categoryModel->title)

{{--
    tobics.php:16-18 registers fatawa/css/new-style.css and the
    Cairo|Reem+Kufi Google Fonts link. new-style.css is ASSET_UNREACHABLE_
    LOCALLY (confirmed: no public/fatawa symlink exists — same finding
    already established for channel-show.blade.php/channels-index.blade.php/
    question-all.blade.php), not pushed here either; only the always-
    reachable Google Fonts link is added.
--}}
@push('styles')
    <link href="https://fonts.googleapis.com/css?family=Cairo|Reem+Kufi" rel="stylesheet">
@endpush

@section('content')
    {{--
        page_bar($id) (fatawa/functions.php:239-292) — Fatawa-specific
        chrome, NOT the shared title()/breadcrumb() mechanism
        (topics-index.blade.php's own fatawa.htm uses that one instead —
        a different function, not reused here). Reproduced exactly,
        including the always-empty `<h1 style="">` and the unconditional
        angle-right icon after "الفتاوى المرئية" (no conditional check in
        source). Every ancestor link — including the CURRENT category's
        own entry — points to `fatawa-topics-{id}-1.htm`page 1, not
        legacy's own literal `fatawa-topics-{id}.htm` (missing the required
        page segment — a confirmed dead link in legacy itself, since only
        the 2-parameter .htaccess rule exists). This matches this same
        file's own already-established subcategory-link precedent below,
        not a new choice made here.
    --}}
    <h1 style=""></h1>
    <div class="page-bar">
        <ul class="page-breadcrumb">
            <li>
                <i class="fa fa-home"></i>
                <a href="/">الرئيسية</a>
                <i class="fa fa-angle-right"></i>
            </li>
            <li>
                <a href="/fatawa.htm">الفتاوى المرئية </a>
                <i class="fa fa-angle-right"></i>
            </li>
            @foreach ($ancestorChain as $ancestor)
                <li>
                    <a href="/fatawa-topics-{{ $ancestor->id }}-1.htm">{{ $ancestor->title }} </a>
                    @unless ($loop->last)
                        <i class="fa fa-angle-right"></i>
                    @endunless
                </li>
            @endforeach
        </ul>
    </div>

    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
            {{-- under_this_tasnif($id) — the whole portlet is gated on non-empty results (tobics.php:32's `if ($tasnif != '')`), unlike the topics portlet below which always renders. --}}
            @if ($subCategories->isNotEmpty())
                <div id="" class="col-md-12 col-sm-12">
                    <div class="portlet box blue">
                        <div class="portlet-title">
                            <div class="caption">
                                <img src="/assets/img/window.png">
                                التصنيفات المدرجة تحت هذا التصنيف </div>
                        </div>
                        <div class="portlet-body ">
                            <div class="portlet-body">
                                <table class="table table-striped table-hover" style="margin-bottom: 0px;" id="sample_5">
                                    <thead>
                                        <tr>
                                            <th> م </th>
                                            <th> اسم التصنيف </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($subCategories as $index => $sub)
                                            <tr>
                                                <td>{{ $index + 1 }}</td>
                                                <td><a href="/fatawa-topics-{{ $sub->id }}-1.htm">{{ $sub->title }}</a> </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- get_all_tasnifat($id) — always rendered, even with 0 topics (empty <tbody>, no gate in source). --}}
            <div id="" class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption">
                            <img src="/assets/img/quran-book (1).png">
                            الموضوعات المضافة في التصنيف </div>
                    </div>
                    <div class="portlet-body ">
                        <div class="portlet-body series-overflow series-overflow-auto">
                            <table class="table table-striped table-hover" id="sample_5">
                                <tbody>
                                    {{-- Route order: topic id first, category id second (.htaccess:301-302's t_id=$1&cat_id=$2). --}}
                                    @foreach ($topics as $topic)
                                        <tr>
                                            <td class="">
                                                <div class="row">
                                                    <div class="col-lg-12">
                                                        <h5>
                                                            <a href="/fatawa-group-{{ $topic->id }}-{{ $categoryModel->id }}.htm">{{ $topic->topic_name }}</a>
                                                        </h5>
                                                        <div class="row page-header color_00a">
                                                            <div class="col-sm-6 col-xs-12">
                                                                <span class="">
                                                                    <i class="fa fa-play-circle-o"></i>
                                                                    عدد الأسئلة:
                                                                    {{ $questionCounts[$topic->id] ?? 0 }}
                                                                </span>
                                                            </div>
                                                            <div class="col-sm-6 col-xs-12">
                                                                <span class="">
                                                                    <i class="fa fa-calendar-o"></i>
                                                                    {{ \App\Domain\Content\Support\ArabicDateConverter::convert($topic->db_insertion_date ?? '') }}
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
                        </div>
                        {{ $topics->onEachSide(1)->links('components.content.premium-pagination') }}
                    </div>
                </div>
            </div>
        </div>

        <aside class="col-lg-3 col-md-4 col-sm-5 nopadding" aria-label="الشريط الجانبي">
            {{--
                mostdownload($id)/recentlyadd($id) (functions.php:679-704) —
                real portlet wrapper and the real link shape:
                /fatawa-all-{general_question_id}.htm#{id}, class="add" —
                NOT /fatawa-download-{id}.htm (this file's prior markup,
                which matched no real legacy href). Same already-established
                fix as channel-show.blade.php's identical sidebar; the
                underlying query (ContentSidebarWidget::fatwaMostDownloadedByCategory()/
                fatwaMostRecentByCategory()) already selects general_question_id
                — view-only fix, no query change.
            --}}
            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-download"></i>الأكثر تحميلا </div>
                    </div>
                    <div class="portlet-body ">
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
                    <div class="portlet-body ">
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
