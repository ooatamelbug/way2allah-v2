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
        <div class="col-xs-12">
            <div class="w2a-date-banner">
                <div class="w2a-date-banner-icon" aria-hidden="true"><i class="fa fa-calendar"></i></div>
                <div>
                    <span class="w2a-date-banner-label">الأرشيف اليومي</span>
                    <h2>المواد المنشورة بتاريخ: {{ $formattedDateLabel }}</h2>
                </div>
            </div>
        </div>

        <div class="col-lg-9 col-md-8 col-sm-12">
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

        <aside class="col-lg-3 col-md-4 col-sm-12" aria-label="الشريط الجانبي">
            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-calendar"></i> البحث بالتاريخ</div>
                    </div>
                    <div class="portlet-body">
                        <form action="{{ $dateSearchAction }}" method="get" class="w2a-date-picker-form">
                            <label for="w2a_archive_date">اختر تاريخ عرض المواد</label>
                            <div class="w2a-date-input-wrap">
                                <i class="fa fa-calendar" aria-hidden="true"></i>
                                <input name="date" value="{{ date('Y-m-d', $date) }}" type="date" id="w2a_archive_date" min="2005-07-01" required>
                            </div>
                            <button type="submit" class="w2a-date-submit">
                                <i class="fa fa-search" aria-hidden="true"></i>
                                <span>عرض المواد</span>
                            </button>
                            <div class="w2a-date-presets" aria-label="اختصارات التاريخ">
                                <a href="{{ $todayUrl }}">اليوم</a>
                                <a href="{{ $yesterdayUrl }}">أمس</a>
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
