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

{{--
    khotab-video-today.htm parity: khotab/day.php:20-23 registers
    scripts/khotab_date.js, scripts/khotab_tables.js, w2a_css/datepicker.css,
    and Plugins('datepicker') unconditionally. Verified against fresh live
    HTML (khotab-video-today.htm): the real, effective plugin is
    bootstrap-datepicker (assets/global/plugins/bootstrap-datepicker/{css,js}) —
    its option names (format/weekStart/startDate/todayBtn/orientation/
    todayHighlight) match the real init call at day.php's own footer script
    exactly. w2a_css/datepicker.css does NOT exist anywhere in this session's
    legacy-project source snapshot (confirmed: no w2a_css/ directory at all)
    — SOURCE_UNRECOVERABLE, left un-ported rather than guessed at; the
    bootstrap-datepicker assets alone are what the live page's actual
    datepicker.min.js call depends on. khotab_date.js is genuinely loaded
    (register_script is unconditional) but targets `#datetimepicker2`, an
    id that does not exist anywhere in day.php's own form — CONFIGURED, not
    ACTUALLY_EFFECTIVE; loaded here to match legacy's real asset list, not
    because it does anything on this page.
--}}
@push('styles')
    <link href="/assets/global/plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css" rel="stylesheet" type="text/css"/>
@endpush

@push('scripts')
    <script src="/assets/global/plugins/bootstrap-datepicker/js/bootstrap-datepicker.js" type="text/javascript"></script>
    <script src="/scripts/khotab_date.js" type="text/javascript"></script>
    <script>
        ;(function($){
            $.fn.datepicker.dates['ar'] = {
                days: ["الأحد", "الاثنين", "الثلاثاء", "الأربعاء", "الخميس", "الجمعة", "السبت", "الأحد"],
                daysShort: ["أحد", "اثنين", "ثلاثاء", "أربعاء", "خميس", "جمعة", "سبت", "أحد"],
                daysMin: ["ح", "ن", "ث", "ع", "خ", "ج", "س", "ح"],
                months: ["يناير", "فبراير", "مارس", "أبريل", "مايو", "يونيو", "يوليو", "أغسطس", "سبتمبر", "أكتوبر", "نوفمبر", "ديسمبر"],
                monthsShort: ["يناير", "فبراير", "مارس", "أبريل", "مايو", "يونيو", "يوليو", "أغسطس", "سبتمبر", "أكتوبر", "نوفمبر", "ديسمبر"],
                today: "هذا اليوم",
            };
        }(jQuery));
        jQuery(document).ready(function() {
            $('#form_datetime_1').datepicker({
                format: "dd.mm.yyyy",
                language: "ar",
                weekStart: 6,
                startDate: "1/7/2005",
                todayBtn: true,
                orientation: "top right",
                todayHighlight: true,
            });
        });
    </script>
@endpush

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
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
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
                        @if($items->isEmpty())
                            <h5>لا توجد مواد مطابقة بقاعدة بيانات الموقع</h5>
                        @else
                            <div class="portlet-body series-overflow series-overflow-auto">
                                <table class="table table-striped table-hover" id="tabelkht">
                                    <tbody>
                                        @foreach ($items as $item)
                                            <tr><td class="">
                                                <div class="row"><div class="col-lg-12">
                                                    <h5>
                                                        <div class="row">
                                                            <div class="col-sm-12 col-lg-8">
                                                                <a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a>
                                                            </div>
                                                            @if(!empty($item->name))
                                                                <div class="col-sm-12 col-lg-4">
                                                                    الداعية:
                                                                    <a href="/khotab-{{ $video ? 'video' : 'audio' }}-{{ $item->author }}.htm">{!! str_replace(' ', '&nbsp;', e($item->name)) !!}</a>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </h5>
                                                    <div class="row page-header color_00a">
                                                        <div class="col-md-3 col-xs-6 text-blue">
                                                            <span><i class="fa fa-eye"></i> مشاهدات: {{ number_format($item->hits) }}</span>
                                                        </div>
                                                        @if(!empty($item->channel_id))
                                                            <div class="col-md-3 col-xs-6 text-blue">
                                                                <span><i class="fa fa-television"></i> القناة:
                                                                    <a href="/channel-{{ $item->channel_id }}.htm"><img width="24" height="24" src="/images/channels/{{ $item->channel_id }}.png" alt=""></a>
                                                                </span>
                                                            </div>
                                                        @endif
                                                        @php($duration = \App\Domain\Content\Support\LegacyDurationFormatter::format((int) ($item->adur ?? 0)))
                                                        @if($duration !== '00:00:00')
                                                            <div class="col-md-3 col-xs-6 text-blue">
                                                                <span><i class="fa fa-clock-o"></i> {{ $duration }}</span>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div></div>
                                            </td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <aside class="col-lg-3 col-md-4 col-sm-5 nopadding" aria-label="الشريط الجانبي">
            {{-- day.php:124-155 — "البحث بالتاريخ" date-search form, real input/value/datepicker trigger. --}}
            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-child"></i> البحث بالتاريخ</div>
                    </div>
                    <div class="portlet-body">
                        <form action="" method="post" class="form-horizontal form-bordered">
                            <div class="form-body">
                                <div class="input-group date form_datetime">
                                    <input name="date" value="{{ date('Y-m-d', $date) }}" type="text" id="form_datetime_1" data-date-format="yyyy-mm-dd" size="16" readonly
                                           class="form-control">
                                    <span class="input-group-btn">
                                        <button class="btn default date-set" type="button">
                                            <i class="fa fa-calendar"></i>
                                        </button>
                                    </span>
                                </div>
                            </div>
                            <div class="form-actions">
                                <div class="row"><br>
                                    <div class="col-md-offset-3 col-md-9">
                                        <button type="submit" class="btn green btn-outline">
                                            <i class="fa fa-check"></i> ابحث
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- day.php:171 — mode='hits', "عدد مرات التحميل: X مرة". --}}
            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-cloud-download"></i> الأكثر تحميلا</div>
                    </div>
                    <div class="portlet-body">
                        <ul class="media-list">
                            @foreach ($mostDownloaded as $item)
                                <li class="media">
                                    <a class="pull-left" href="javascript:;"><img class="media-object" src="{{ $item->thumb }}" alt="{{ $item->title }}" style="width: 60px; height: 40px;"></a>
                                    <div class="media-body">
                                        <a href="/khotab-item-{{ $item->id }}.htm"><h5 class="media-heading">{{ $item->title }}</h5></a>
                                        <small>عدد مرات التحميل: {{ number_format($item->hits) }} مرة</small>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

            {{--
                day.php:181 — mode='time' (unlike khotab-series-{id}.htm's
                always-'hits' calls), verified against fresh live HTML:
                <small>بتاريخ: {date}</small>, not a hit count.
            --}}
            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-flash"></i> جديد المواد</div>
                    </div>
                    <div class="portlet-body">
                        <ul class="media-list">
                            @foreach ($mostRecent as $item)
                                <li class="media">
                                    <a class="pull-left" href="javascript:;"><img class="media-object" src="{{ $item->thumb }}" alt="{{ $item->title }}" style="width: 60px; height: 40px;"></a>
                                    <div class="media-body">
                                        <a href="/khotab-item-{{ $item->id }}.htm"><h5 class="media-heading">{{ $item->title }}</h5></a>
                                        <small>بتاريخ: {{ \App\Domain\Content\Support\LegacyShortDateFormatter::format((int) $item->time) }}</small>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </aside>
    </div>
@endsection
