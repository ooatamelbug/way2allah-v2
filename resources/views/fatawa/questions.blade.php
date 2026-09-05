@extends('layouts.app')

{{--
    Full Design Parity Pass (2026-08-22) — fatawa-group-{topic}-{category}.htm
    ONLY. Full source re-read (`fatawa/subtobics.php`, `fatawa/functions.php`'s
    `page_bar()`/`get_all_questions()`/`mostdownload()`/`recentlyadd()`),
    cross-checked against a fresh live fetch (`fatawa/subtobics.php?id=1621&cat_id=49`,
    LIVE_RENDER_VERIFIED, no drift from source). Independently verified, NOT
    inferred from `tobics.php`/`topics-show.blade.php` — this page's own
    source has real, different fields (answer count + view count, not topic
    count + date; a gated main-list portlet, not an unconditional one; a
    topic-description portlet tobics.php has no equivalent of).

    `<title>` — subtobics.php:21's own header string is 'الفتاوى المرئية | '.
    topic_name($cat_id).' | موضوع '.$topic->topic_name — a 3-part title
    (category title AND topic name), distinct from tobics.php's 2-part
    title. No manual sitename concat: same single-suffix convention as
    topics-show.blade.php (the layout's own `@yield('title') - {sitename}`
    already matches).

    Meta tags (meta_keywords/meta_description/meta_index/meta_follow,
    subtobics.php:25-30): NOT implemented — no page anywhere in this
    migration (Fatawa or otherwise) has ever reproduced legacy's meta-tag
    output; a consistent, sitewide, already-established deferral, not a
    gap introduced or newly ignored here.
--}}
@section('title', 'الفتاوى المرئية | '.$categoryModel->title.' | موضوع '.$topicModel->topic_name)

{{--
    subtobics.php:22-24 registers fatawa/css/new-style.css and the
    Cairo|Reem+Kufi Google Fonts link. new-style.css is ASSET_UNREACHABLE_
    LOCALLY (no public/fatawa symlink — same already-established finding
    as topics-show.blade.php/channel-show.blade.php/channels-index.blade.php);
    not pushed here either.
--}}
@push('styles')
    <link href="https://fonts.googleapis.com/css?family=Cairo|Reem+Kufi" rel="stylesheet">
@endpush

@section('content')
    {{--
        page_bar($cat_id, $id) (subtobics.php:34) — the SAME category
        ancestor chain as tobics.php's page_bar($id) call, PLUS one extra
        trailing breadcrumb item for the topic itself
        (fatawa/functions.php:284-285), a branch topics-show.blade.php
        never exercises. Note this final item's icon comes BEFORE its
        link, not after like every other breadcrumb item — reproduced
        exactly, not "corrected" to match the others.
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
            <li>
                <i class="fa fa-angle-right"></i>
                <a href="/fatawa-group-{{ $topicModel->id }}-{{ $categoryModel->id }}.htm"> موضوع {{ $topicModel->topic_name }} </a>
            </li>
        </ul>
    </div>

    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
            {{-- get_all_questions() — the WHOLE portlet is gated on count > 0 (subtobics.php:44), unlike tobics.php's unconditional topics portlet. --}}
            @if ($generalQuestions->total() > 0)
                <div id="" class="col-md-12 col-sm-12">
                    <div class="portlet box blue">
                        <div class="portlet-title">
                            <div class="caption"> <i class="fa fa-question"></i>الأسئلة المضافة في الموضوع   </div>
                        </div>
                        <div class="portlet-body series-overflow">
                            <div class="portlet-body">
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
                                                                    <span class="">
                                                                        <i class="fa fa-play-circle-o"></i>
                                                                        عدد الفتاوى:
                                                                        {{ $answerCounts[$question->id] ?? 0 }}
                                                                    </span>
                                                                </div>
                                                                <div class="col-sm-6 col-xs-12">
                                                                    <span class="">
                                                                        <i class="fa fa-eye"></i>
                                                                        المشاهدات:
                                                                        {{ $question->num_view }}
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
            @endif

            {{-- topic description — bare portlet-body, no title/caption/icon, gated on non-empty (subtobics.php:81). --}}
            @if (! empty($topicModel->description))
                <div id="" class="col-md-12 col-sm-12">
                    <div class="portlet box blue">
                        <div class="portlet-body ">
                            <p>
                                {!! $topicModel->description !!}
                            </p>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <aside class="col-lg-3 col-md-4 col-sm-5 nopadding" aria-label="الشريط الجانبي">
            {{--
                mostdownload($cat_id)/recentlyadd($cat_id) — CATEGORY-scoped
                (confirmed via subtobics.php:104/117, not assumed to match
                tobics.php). Same real link shape as topics-show.blade.php's
                already-fixed sidebar: /fatawa-all-{general_question_id}.htm#{id},
                class="add".
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
