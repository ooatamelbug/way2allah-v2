@extends('layouts.app')

{{--
    Title Gap Closure (2026-08-22): the reconciliation audit found day.php's
    real `$header['title']` (day.php:10-19,24-25) is the plain, date-
    INdependent 'المرئيات '/'الصوتيات ' string — confirmed against a fresh
    live fetch of khotab-video-today.htm ("<title>المرئيات  - ...</title>").
    IF-016's own premise (this comment used to read "title reflects the
    browsed date, not a nonexistent $Author") conflated the DOCUMENT title
    with day.php:100's separate, broken `title($Author->prename.' '.
    $Author->name)` call — that call feeds the visible `<h3>` HEADING, not
    this `<title>` tag, and its own $Author bug is correctly handled
    elsewhere (heading omitted entirely — see this file's own comment
    below, unchanged by this fix). The breadcrumb's date text
    ($breadcrumbTrail, KhotabDayController::render()) is a genuinely
    separate string built from day.php:88-99 and is NOT touched here.
--}}
@section('title', $video ? 'المرئيات ' : 'الصوتيات ')

@section('content')
    {{--
        Shared Page Chrome Parity Audit: day.php:90-101's breadcrumb
        restored — heading deliberately omitted (LEGACY_BUG_NOT_FOR_
        REPRODUCTION: `title($Author->prename.' '.$Author->name)` with
        `$Author` never assigned anywhere in this file, confirmed against
        fresh production as a literal `<h3 class="page-title"> </h3>`;
        IF-016's own document-`<title>` decision above stays untouched —
        that's a separate, already-correct, already-protected fix).
        Middle item ("تقسيم المواد بالتاريخ") has no `url` key in legacy
        (`array('title'=>'...', '')` — a positional, not `'url'=>`,
        second element), so it renders plain, matching the component's
        `array_key_exists` check.
    --}}
    <x-page-chrome :breadcrumb="$breadcrumbTrail" />

    <div class="row service-box margin-bottom-40">
        <div class="col-lg-4 col-md-5 col-sm-12">
            <div class="portlet box blue">
                <div class="portlet-title">
                    <div class="caption"><i class="fa fa-calendar"></i> البحث بالتاريخ</div>
                </div>
                <div class="portlet-body">
                    <form action="{{ $dateSearchAction }}" method="get" class="w2a-date-picker-form">
                        <label class="sr-only" for="w2a_archive_date">اختر تاريخ عرض المواد</label>
                        <input name="date" value="{{ date('Y-m-d', $date) }}" type="date" id="w2a_archive_date" class="w2a-date-picker-input" min="2005-07-01" required>
                        <button type="submit" class="w2a-date-picker-submit">
                            <i class="fa fa-search" aria-hidden="true"></i> استعراض المواد
                        </button>
                        <div class="w2a-date-presets" aria-label="اختصارات التاريخ">
                            <a href="{{ $todayUrl }}" class="w2a-date-preset-btn">اليوم</a>
                            <a href="{{ $yesterdayUrl }}" class="w2a-date-preset-btn">أمس</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-sm-12">
            {{--
                khotab/day.php:113 calls ListKhotab($ob) with $ob->mode='day'
                (khotab/functions.php:659,669,677) — author link shown (mode
                is fixed/new/day), date NOT shown (mode == 'day' excludes it),
                comments NOT shown (same exclusion), hits shown, conditional
                channel badge, conditional duration. Empty-state message
                verified against fresh live HTML (today's date, 2026-08-20,
                genuinely has zero published items in production).
            --}}
            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-child"></i> قائمة المواد</div>
                    </div>
                    <div class="portlet-body">
                        <x-content.khotab-item-list :items="$items" :video="$video" show-author :show-date="false" :show-comments="false" />
                    </div>
                </div>
            </div>
        </div>

        <aside class="col-sm-12" aria-label="مواد مقترحة">
            <div class="row">
            {{-- day.php:171 — mode='hits', "عدد مرات التحميل: X مرة". --}}
            <div class="col-md-6 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-cloud-download"></i> الأكثر تحميلا</div>
                    </div>
                    <div class="portlet-body">
                        <x-content.top-items :items="$mostDownloaded" />
                    </div>
                </div>
            </div>

            {{--
                day.php:181 — mode='time' (unlike khotab-series-{id}.htm's
                always-'hits' calls), verified against fresh live HTML:
                <small>بتاريخ: {date}</small>, not a hit count.
            --}}
            <div class="col-md-6 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-flash"></i> جديد المواد</div>
                    </div>
                    <div class="portlet-body">
                        <x-content.top-items :items="$mostRecent" mode="time" />
                    </div>
                </div>
            </div>
            </div>
        </aside>
    </div>
@endsection
