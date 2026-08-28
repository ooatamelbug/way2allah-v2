{{--
    Fatwa Today Visual/Structural Parity Pass (decision-log #44). Full
    re-read of `fatawa/fatwa-today.php` (362 lines) and its
    `get_all_questions_date()` per-row markup (`fatawa/functions.php:454-522`).
    Restored: the "فتاوى مختارة" (featured) portlet + `.thumbs` grid, the
    "تقويم الطريق إلى الله" calendar portlet (static shell AND its client-side
    widget — pure presentation, no new data dependency, see
    FatwaDayController's own docblock), the results portlet wrapper/title/
    icon, the real per-row `<tr>` structure (question+author two-column
    header, "مكان إصدار الفتوى" channel block), the real empty-state row,
    and the page-specific `<style>` block — all verbatim from source.

    Fatwa Date Route Completion (decision-log #46): pagination now uses
    `fatawa.partials.pagination`, reproducing legacy's real
    `pagination()` markup/windowing (including its own off-by-one `$num`
    quirk) with correct pretty-URL-per-page generation via the `$pageUrl`
    closure the controller supplies — no longer Laravel's default
    `$questions->links()`, which only ever produced `?page=N` query
    strings. This was upgraded now that `/fatwa-date-{d}-{m}-{y}-{page}.htm`
    is in scope and needs its own distinct per-page URL shape; see the
    partial's own docblock for the full reasoning. Placement (rendered
    twice — above and below the table) is unchanged from the original
    parity pass.

    No breadcrumb: `fatwa-today.php` never calls `page_bar()`/`title()`/
    `breadcrumb()` (confirmed — those calls exist elsewhere in
    `fatawa/functions.php` for OTHER fatawa pages, not this one) — legacy
    genuinely has none here, so `<x-page-chrome>` is correctly not used.

    Per-row date: legacy's own per-row date span is commented out in
    source (`fatawa/functions.php:507-512`, wrapped in an HTML comment) —
    genuinely dead/unrendered in legacy too, so it is not shown here either.

    Fatwa Calendar Visual Dependency Audit (decision-log #45): the
    calendar's actual styling (`.calendar`, `.calendar-ympicker`,
    `.calendar-header`, `.calendar-days`, `.calendar-body`, plus
    `.fatawa-mokhtara`/`.calendar-block`'s portlet background/border and
    `.pagination`'s styling) lives entirely in `fatawa/css/new-style.css`
    (656 lines) — a real file `fatwa-today.php:20-22` itself explicitly
    `<link>`s via its own `$header['css']['custom']`, NOT part of the
    inline `<style>` block below. This was missed in the original parity
    pass (only the inline block was ported). Root cause: the file was
    simply unreachable under `public/` (no `public/fatawa` path existed at
    all — the same already-documented `ASSET_UNREACHABLE_LOCALLY` gap
    affecting 6 other, unrelated fatawa pages — `topics-index`,
    `topics-show`, `question-all`, `channels-index`, `channel-show`,
    `questions` — none of which are touched by this fix). Fixed narrowly,
    matching this codebase's own existing `public/gallery/lightbox`
    precedent (a single nested subdirectory symlink, not the whole
    `fatawa/` tree, which also contains live legacy PHP scripts that must
    stay unreachable): `public/fatawa/css -> ../../../legacy-project/fatawa/css`.
    No image/font `url()` dependency in the stylesheet (grepped, none
    found) and no additional JS/plugin dependency (the calendar's own
    widget only needs jQuery, already loaded sitewide).
--}}
@extends('layouts.app')

@section('title', 'الفتاوى بتاريخ الإضافة')

@push('styles')
    <link rel="stylesheet" href="/fatawa/css/new-style.css">
    <style type="text/css">
        .addthis_sharing_toolbox {
            margin-left: -15px;
        }

        .thumbs {
            height: 11.7em;
            overflow: hidden;
            text-align: center;
            margin: 0px auto 5px;
        }

        .thumbs img {
            clear: both;
            display: block;
            margin: 5px auto;
            max-width: 100%;
        }

        .date_fatawa {
            margin-left: -15px;
        }

        @media only screen and (max-device-width: 768px) {
            .thumbs {
                float: none !important;
            }

            .date_fatawa {
                margin-left: 0px;
            }
        }
    </style>
@endpush

@php
    $calDate = \Carbon\Carbon::parse($date);
    // Fatwa Date Route Completion (decision-log #46): legacy's own
    // fatwa-today.php:176 computes this cutoff from bare `date('m-d-Y')`
    // — always the REAL current date, never the page's own `$date` — to
    // block future-dated calendar cells regardless of which date's page
    // is being viewed. Previously this used `$calDate` (the page's own
    // date), which was silently correct only because "today" was the
    // only date this page could ever represent; now that `/fatwa-date-*`
    // exists, that would incorrectly block every day past whatever
    // historical date is being viewed rather than past today.
    $calCutoff = str_replace('-0', '-', now()->format('m-d-Y'));
@endphp

@section('content')
    <br style="clear: left;">
    <div class="row service-box">
        <div class="col-xs-12 col-sm-5 fl-l">
            <div class="col-md-12 col-sm-12 fatawa-mokhtara">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-hand-pointer-o"></i>فتاوى مختارة </div>
                    </div>
                </div>
                <div class="row">
                    @foreach ($featured as $item)
                        <div class="thumbs col-xs-6">
                            <a href="/fatawa-all-{{ str_replace('|', '', $item->general_question_id) }}.htm#{{ $item->id }}">
                                <img src="/images/tvnoise.gif" alt="{{ $item->question_text }}" width="160" height="110">{{ $item->question_text }}
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="col-xs-12 col-sm-7 fl-l">
            <div class="col-md-12 col-xs-12 calendar-block">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-calendar"></i>تقويم الطريق إلى الله </div>
                    </div>
                </div>
                <div>
                    <div class="calendar">
                        <div class="group calendar-header">
                            <p class="pointer center monthname">&nbsp;</p>
                            <p class="pointer arrow minusmonth"><span>&rarr;</span></p>
                            <p class="pointer arrow addmonth"><span>&larr;</span></p>
                        </div>

                        <ul class="group calendar-days">
                            <li>السبت</li>
                            <li>الأحد</li>
                            <li>الاثنين</li>
                            <li>الثلاثاء</li>
                            <li>الاربعاء</li>
                            <li>الخميس</li>
                            <li>الجمعه</li>
                        </ul>
                        <ul class="group calendar-body">
                            <!-- Dates go in here -->
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12 col-sm-12">
            <div class="portlet box blue date_fatawa">
                <div class="portlet-title">
                    <div class="caption"> <i class="fa fa-calendar"></i>الفتاوى المضافة بتاريخ
                        {{ $displayDate }}</div>
                </div>
                <div class="portlet-body">
                    @include('fatawa.partials.pagination')
                    <table class="table table-striped table-hover" id="sample_5">
                        <tbody>
                            @forelse ($questions as $question)
                                <tr>
                                    <td class="">
                                        <div class="row">
                                            <div class="col-lg-12">
                                                <h5>
                                                    <div class="row">
                                                        <div class="col-sm-12 col-lg-8">
                                                            <a href="/fatawa-all-{{ str_replace('|', '', $question->general_question_id) }}.htm#{{ $question->id }}">{{ $question->question_text }}</a>
                                                        </div>

                                                        <div class="col-sm-12 col-lg-4">
                                                            الشيخ:
                                                            <a href="/auther-questions-{{ $question->auther_id }}.htm">{{ $question->auth_prename }} {{ $question->auth_name }}</a>
                                                        </div>
                                                    </div>
                                                </h5>
                                                <div class="row page-header color_00a">
                                                    <div class="col-sm-12 col-xs-12">
                                                        <span class="">
                                                            <i class="fa fa-play-circle-o"></i>
                                                            مكان إصدار الفتوى:
                                                            @if ($question->channel_exists_id)
                                                                <a href="/fatawa-channel-{{ $question->channel_id }}.htm">
                                                                    <img width="24" height="24" border="0" src="/images/channels/{{ $question->channel_id }}.png" alt="">
                                                                </a>
                                                            @else
                                                                <a href="/fatawa-channel-0.htm"> بدون قناه </a>
                                                            @endif
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">لا تـوجـــد فتاوى مضافـــة حاليا لهذا التصنيف</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    @include('fatawa.partials.pagination')
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            var d = new Date({{ $calDate->year }}, {{ $calDate->month - 1 }}, {{ $calDate->day }});

            var Calendar = {
                themonth: d.getMonth(), // The number of the month 0-11
                theyear: d.getFullYear(), // This year
                today: [d.getFullYear(), d.getMonth(), d.getDate()], // adds today style
                selectedDate: null, // set to today in init()
                years: [], // populated with last 10 years in init()
                months: ['يناير', 'فبراير', 'مارس', 'ابريل', 'مايو', 'يونيو', 'يوليو', 'اغسطس', 'سبتمبر', 'اكتوبر',
                    'نوفمبر',
                    'ديسمبر'
                ],

                init: function() {
                    this.selectedDate = this.today
                    // Populate the list of years in the month/year pulldown
                    var year = this.theyear;
                    for (var i = 0; i < 10; i++) {
                        this.years.push(year--);
                    }

                    this.bindUIActions();
                    this.render();
                },

                bindUIActions: function() {
                    // Create Years list and add to ympicker
                    for (var i = 0; i < this.years.length; i++)
                        $('<li>' + this.years[i] + '</li>').appendTo('.calendar-ympicker-years');
                    this.selectMonth();
                    this.selectYear(); // Add active class to current month n year

                    // Slide down year month picker
                    $('.monthname').click(function() {
                        $('.calendar-ympicker').css('transform', 'translateY(0)');
                    });

                    // Close year month picker without action
                    $('.close').click(function() {
                        $('.calendar-ympicker').css('transform', 'translateY(-100%)');
                    });

                    // Move calander to today
                    $('.today').click(function() {
                        Calendar.themonth = d.getMonth();
                        Calendar.theyear = d.getFullYear();
                        Calendar.selectMonth();
                        Calendar.selectYear();
                        Calendar.selectedDate = Calendar.today;
                        Calendar.render();
                        $('.calendar-ympicker').css('transform', 'translateY(-100%)');
                    });

                    // Click handlers for ympicker list items
                    $('.calendar-ympicker-months li').click(function() {
                        Calendar.themonth = $('.calendar-ympicker-months li').index($(this));
                        Calendar.selectMonth();
                        Calendar.render();
                        $('.calendar-ympicker').css('transform', 'translateY(-100%)');
                    });
                    $('.calendar-ympicker-years li').click(function() {
                        Calendar.theyear = parseInt($(this).text());
                        Calendar.selectYear();
                        Calendar.render();
                        $('.calendar-ympicker').css('transform', 'translateY(-100%)');
                    });

                    // Move the calendar pages
                    $('.minusmonth').click(function() {
                        Calendar.themonth += -1;
                        Calendar.changeMonth();
                    });
                    $('.addmonth').click(function() {
                        Calendar.themonth += 1;
                        Calendar.changeMonth();
                    });
                },

                // Adds class="active" to the selected month/year
                selectMonth: function() {
                    $('.calendar-ympicker-months li').removeClass('active');
                    $('.calendar-ympicker-months li:nth-child(' + (this.themonth + 1) + ')').addClass('active');
                },
                selectYear: function() {
                    $('.calendar-ympicker-years li').removeClass('active');
                    $('.calendar-ympicker-years li:nth-child(' + (this.years.indexOf(this.theyear) + 1) + ')').addClass(
                        'active');
                },

                // Makes sure that month rolls over years correctly
                changeMonth: function() {
                    if (this.themonth == 12) {
                        this.themonth = 0;
                        this.theyear++;
                        this.selectYear();
                    } else if (this.themonth == -1) {
                        this.themonth = 11;
                        this.theyear--;
                        this.selectYear();
                    }
                    this.selectMonth();
                    this.render();
                },

                // Helper functions for time calculations
                TimeCalc: {
                    firstDay: function(month, year) {
                        var fday = new Date(year, month, 1).getDay(); // Mon 1 ... Sat 6, Sun 0
                        if (fday === 0) fday = 7;
                        return fday + 1; // Mon 0 ... Sat 5, Sun 6
                    },
                    numDays: function(month, year) {
                        return new Date(year, month + 1, 0).getDate(); // Day 0 is the last day in the previous month
                    }
                },

                render: function() {
                    var days = this.TimeCalc.numDays(this.themonth, this.theyear), // get number of days in the month
                        fDay = this.TimeCalc.firstDay(this.themonth, this
                            .theyear), // find what day of the week the 1st lands on
                        daysHTML = '',
                        i;

                    $('.calendar p.monthname').text(this.months[this.themonth] + '  ' + this
                        .theyear); // add month name and year to calendar
                    for (i = 0; i < fDay; i++) { // place the first day of the month in the correct position
                        daysHTML += '<li class="noclick">&nbsp;</li>';
                    }
                    var url = window.location.href;
                    var url = url.split("/fatawa-");
                    var url = url[0].split("/fatwa-");
                    var url = url[0] + '/fatwa-date-';
                    // write out the days
                    for (i = 1; i <= days; i++) {

                        //get day url
                        var getdate = 'href="' + url + i + '-' + (this.themonth + 1) + '-' + this.theyear + '-' + 1 + '.htm"';
                        //get current day
                        var newdate = new Date((this.themonth + 1) + '/' + i + '/' + this.theyear).getTime();
                        //check if date in future
                        if (newdate > new Date("{{ $calCutoff }}").getTime()) getdate = 'style="pointer-events: none;color: #ccc;"';
                        //check if date in past
                        if (newdate < new Date('2011-9-30').getTime()) getdate = 'style="pointer-events: none;color: #ccc;"';

                        if (this.today[0] == this.selectedDate[0] &&
                            this.today[1] == this.selectedDate[1] &&
                            this.today[2] == this.selectedDate[2] &&
                            this.today[0] == this.theyear &&
                            this.today[1] == this.themonth &&
                            this.today[2] == i)
                            daysHTML += '<a ' + getdate + '><li class="active today">' + i + '</li></a>';
                        else if (this.today[0] == this.theyear &&
                            this.today[1] == this.themonth &&
                            this.today[2] == i)
                            daysHTML += '<a ' + getdate + '><li class="today">' + i + '</li></a>';
                        else if (this.selectedDate[0] == this.theyear &&
                            this.selectedDate[1] == this.themonth &&
                            this.selectedDate[2] == i)
                            daysHTML += '<a ' + getdate + '><li class="active">' + i + '</li></a>';
                        else
                            daysHTML += '<a ' + getdate + '><li>' + i + '</li></a>';

                        $('.calendar-body').html(daysHTML); // Only one append call
                    }

                    // Adds active class to date when clicked
                    $('.calendar-body li').click(function() { // toggle selected dates
                        if (!$(this).hasClass('noclick')) {
                            $('.calendar-body li').removeClass('active');
                            $(this).addClass('active');
                            Calendar.selectedDate = [Calendar.theyear, Calendar.themonth, $(this)
                                .text()
                            ]; // save date for reselecting
                        }
                    });
                }
            };

            Calendar.init();
        </script>
    @endpush
@endsection
