{{--
    Author Questions Visual Parity Pass (decision-log #50). Full re-read of
    `fatawa/auther_profile.php` (98 lines) and its real per-row markup
    (`get_all_auther_questions()`, `fatawa/functions.php:622-670`).

    Restored: the real `page_bar_auther()` breadcrumb (a DIFFERENT, hand-
    rolled shape than the shared `<x-page-chrome>` component — icon comes
    BEFORE each subsequent `<li>`'s `<a>`, not after; first item is "قائمة
    الدعاة" linking to `/fatawa-authors.htm`, not "الرئيسية"/"/" — this page
    genuinely never calls the shared `breadcrumb()` function `<x-page-chrome>`
    reproduces), the `.portlet.box.blue` wrapper (icon `fa-question`, real
    caption text "الأسئلة التى أفتى بها الشيخ" — legacy's own spelling,
    "التى" not "التي", preserved exactly), the real `<table id="sample_5">`
    with its `table_order` numbered column, the real per-row structure
    (question + "الموضوع التابع له السؤال" topic link), the 2 sidebar
    portlets ("الأكثر تحميلا"/fa-download, "جديد المواد"/fa-plus) with their
    REAL link target (`fatawa-all-{general_id}.htm#{id}`, `class="add"` —
    the previous version incorrectly linked to `/fatawa-download-{id}.htm`,
    a real but WRONG route for this context; fixed here, no new query —
    `general_question_id` was already selected by `ContentSidebarWidget`,
    just not used), and the page-specific `fatawa/css/new-style.css` link
    (already reachable via the `public/fatawa/css` symlink, decision-log
    #45 — simply never wired into this specific page before).

    Pagination: legacy's own `pagination($count)` call (twice, top+bottom)
    reproduced via `fatawa.partials.pagination` — the exact same partial
    `fatwa-today.htm`/`fatwa-date-*` already use (real pretty-URL contract,
    not `?page=`), not duplicated.

    Deliberately NOT restored: the top `<h1 style=""></h1>` `page_bar_auther()`
    emits before the breadcrumb — genuinely empty, no visible content or
    effect either way, not a "malformed bug" so much as inert markup; same
    standard already applied to other confirmed-harmless artifacts.
    No empty-state message: `get_all_auther_questions()` has no
    `$resultcount==0` fallback (unlike `get_all_questions_date()`'s real
    one) — a real, confirmed absence, not an oversight to "improve" with an
    invented message. No author photo: `auther_profile.php` never renders
    one (confirmed by the full source read) — `fatawa-authors.htm`'s own
    directory listing does, this detail page doesn't.
--}}
@extends('layouts.app')

@section('title', $authorModel->prename.' '.$authorModel->name)

@push('styles')
    <link rel="stylesheet" href="/fatawa/css/new-style.css">
@endpush

@section('content')
    <div class="page-bar">
        <ul class="page-breadcrumb">
            <li>
                <i class="fa fa-home"></i>
                <a href="/fatawa-authors.htm">قائمة الدعاة</a>
            </li>
            <li> <i class="fa fa-angle-right"></i>
                <a href="/auther-questions-{{ $authorModel->id }}.htm">{{ $authorModel->prename }} {{ $authorModel->name }} </a></li>
        </ul>
    </div>

    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"> <i class="fa fa-question"></i>الأسئلة التى أفتى بها الشيخ </div>
                    </div>
                    <div class="portlet-body series-overflow series-overflow-auto">
                        <div class="portlet-body">
                            @include('fatawa.partials.pagination', ['questions' => $generalQuestions])
                            <table class="table table-striped table-hover" id="sample_5">
                                <tbody>
                                    @php $rowNumber = $generalQuestions->firstItem() ?? 1; @endphp
                                    @foreach ($generalQuestions as $question)
                                        <tr>
                                            <td class="table_order">{{ $rowNumber++ }}</td>
                                            <td class="">
                                                <div class="row">
                                                    <div class="col-lg-12">
                                                        <h5>
                                                            <a href="/auther-all-fatawa-{{ $authorModel->id }}-{{ $question->id }}.htm">{{ $question->question_text }}</a>
                                                        </h5>
                                                        @if ($question->topic)
                                                            <div class="row page-header color_00a">
                                                                <div class="col-xs-12">
                                                                    الموضوع التابع له السؤال:
                                                                    <span class="text-blue">
                                                                        <a href="/fatawa-group-{{ $question->topic->id }}-{{ $question->topic->parent_id }}.htm">{{ $question->topic->topic_name }}</a>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @include('fatawa.partials.pagination', ['questions' => $generalQuestions])
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-4 col-sm-5 nopadding">
            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption">
                            <i class="fa fa-download"></i>الأكثر تحميلا
                        </div>
                    </div>
                    <div class="portlet-body">
                        <ul class="news">
                            @foreach ($mostDownloaded as $item)
                                <li><a href="/fatawa-all-{{ str_replace('|', '', $item->general_question_id) }}.htm#{{ $item->id }}" class="add">{{ $item->question_text }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption">
                            <i class="fa fa-plus"></i>جديد المواد
                        </div>
                    </div>
                    <div class="portlet-body">
                        <ul class="news">
                            @foreach ($mostRecent as $item)
                                <li><a href="/fatawa-all-{{ str_replace('|', '', $item->general_question_id) }}.htm#{{ $item->id }}" class="add">{{ $item->question_text }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
