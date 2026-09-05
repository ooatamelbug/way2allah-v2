{{-- Premium fatwa-day presentation; date routes, pagination, and calendar JavaScript contracts remain unchanged. --}}
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

@push('page-styles')
    <link href="/assets/frontend/layout/css/content-refresh.css" rel="stylesheet" type="text/css">
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
    <div class="w2a-refresh-page w2a-fatwa-day-page">
        <div class="w2a-fatwa-day-hero">
            <x-content.premium-panel title="فتاوى مختارة" icon="fa-hand-pointer-o"
                description="إجابات مختارة بعناية عن أسئلة تهم المسلم في حياته اليومية." class="w2a-featured-fatwas">
                <div class="w2a-featured-fatwa-grid">
                    @forelse ($featured as $item)
                        <a class="w2a-featured-fatwa-card"
                            href="/fatawa-all-{{ str_replace('|', '', $item->general_question_id) }}.htm#{{ $item->id }}">
                            <span class="w2a-featured-fatwa-card__icon" aria-hidden="true"><i
                                    class="fa fa-question"></i></span>
                            <strong>{{ $item->question_text }}</strong>
                            <span>اقرأ الفتوى <i class="fa fa-angle-right" aria-hidden="true"></i></span>
                        </a>
                    @empty
                        <p class="w2a-empty-state">لا توجد فتاوى مختارة حالياً.</p>
                    @endforelse
                </div>
            </x-content.premium-panel>

            <x-content.premium-panel title="تقويم الطريق إلى الله" icon="fa-calendar"
                description="اختر يوماً للاطلاع على الفتاوى التي أضيفت فيه." class="w2a-fatwa-calendar-panel">
                <div class="calendar" aria-label="تقويم الفتاوى">
                    <div class="group calendar-header">
                        <p class="pointer center monthname" aria-live="polite">&nbsp;</p>
                        <p class="pointer arrow minusmonth"><span aria-label="الشهر السابق">&rarr;</span></p>
                        <p class="pointer arrow addmonth"><span aria-label="الشهر التالي">&larr;</span></p>
                    </div>

                    <ul class="group calendar-days" aria-hidden="true">
                        <li>السبت</li>
                        <li>الأحد</li>
                        <li>الاثنين</li>
                        <li>الثلاثاء</li>
                        <li>الأربعاء</li>
                        <li>الخميس</li>
                        <li>الجمعة</li>
                    </ul>
                    <ul class="group calendar-body">
                        <!-- Dates go in here -->
                    </ul>
                </div>
            </x-content.premium-panel>
        </div>

        <x-content.premium-panel :title="'الفتاوى المضافة بتاريخ ' . $displayDate" icon="fa-calendar-check-o"
            description="أحدث الأسئلة والإجابات المنشورة في التاريخ المحدد." class="w2a-fatwa-results">
            @include('fatawa.partials.pagination')
            <div class="w2a-fatwa-list" id="sample_5">
                @forelse ($questions as $question)
                    <article class="w2a-fatwa-row">
                        <span class="w2a-fatwa-row__icon" aria-hidden="true"><i class="fa fa-commenting-o"></i></span>
                        <div class="w2a-fatwa-row__main">
                            <a class="w2a-fatwa-row__question"
                                href="/fatawa-all-{{ str_replace('|', '', $question->general_question_id) }}.htm#{{ $question->id }}">{{ $question->question_text }}</a>
                            <div class="w2a-fatwa-row__meta">
                                <span><i class="fa fa-user" aria-hidden="true"></i> الشيخ: <a
                                        href="/auther-questions-{{ $question->auther_id }}.htm">{{ $question->auth_prename }}
                                        {{ $question->auth_name }}</a></span>
                                <span>
                                    <i class="fa fa-television" aria-hidden="true"></i>
                                    مكان إصدار الفتوى:
                                    @if ($question->channel_exists_id)
                                        <a class="w2a-fatwa-channel" href="/fatawa-channel-{{ $question->channel_id }}.htm"
                                            aria-label="عرض فتاوى القناة">
                                            <img width="28" height="28"
                                                src="/images/channels/{{ $question->channel_id }}.png" alt="شعار القناة"
                                                loading="lazy" decoding="async">
                                        </a>
                                    @else
                                        <a href="/fatawa-channel-0.htm">بدون قناة</a>
                                    @endif
                                </span>
                            </div>
                        </div>
                        <a class="w2a-fatwa-row__action"
                            href="/fatawa-all-{{ str_replace('|', '', $question->general_question_id) }}.htm#{{ $question->id }}"
                            aria-label="قراءة الفتوى">
                            <i class="fa fa-angle-left" aria-hidden="true"></i>
                        </a>
                    </article>
                @empty
                    <div class="w2a-empty-state">
                        <i class="fa fa-calendar-times-o" aria-hidden="true"></i>
                        <strong>لا توجد فتاوى مضافة في هذا التاريخ</strong>
                        <span>استخدم التقويم لاختيار يوم آخر.</span>
                    </div>
                @endforelse
            </div>
            @include('fatawa.partials.pagination')
        </x-content.premium-panel>
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
                        var getdate = 'href="' + url + i + '-' + (this.themonth + 1) + '-' + this.theyear + '-' + 1 +
                            '.htm"';
                        //get current day
                        var newdate = new Date((this.themonth + 1) + '/' + i + '/' + this.theyear).getTime();
                        //check if date in future
                        if (newdate > new Date("{{ $calCutoff }}").getTime()) getdate =
                            'style="pointer-events: none;color: #ccc;"';
                        //check if date in past
                        if (newdate < new Date('2011-9-30').getTime()) getdate =
                            'style="pointer-events: none;color: #ccc;"';

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
