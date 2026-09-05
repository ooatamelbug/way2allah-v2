@extends('layouts.app')

{{-- Premium presentation for the existing Laravel search contract and result sets. --}}
@section('title', 'البحث المتقدم في المرئيات')

{{--
    search.php:9-11 has scripts/khotab_date.js, scripts/khotab_tables.js,
    w2a_css/datepicker.css all commented out (confirmed, unlike
    khotab/day.php's own unconditional versions) — only `plugins('datepicker')`
    (classes/plugins.php:137-140) is real, registering these exact 2
    assets. ACTUALLY_EFFECTIVE: `.datepikerinput` is a real class on the
    kh_from/kh_to fields below.
--}}
@push('styles')
    <link href="/assets/global/plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css" rel="stylesheet" type="text/css"/>
@endpush

@push('page-styles')
    <link href="/assets/frontend/layout/css/content-refresh.css" rel="stylesheet" type="text/css">
@endpush

@push('scripts')
    <script src="/assets/global/plugins/bootstrap-datepicker/js/bootstrap-datepicker.js" type="text/javascript"></script>
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
            $(".datepikerinput").datepicker({
                format: "dd.mm.yyyy",
                weekStart: 6,
                startDate: "1/7/2005",
                todayBtn: true,
                language: "ar",
                orientation: "top right",
                todayHighlight: true,
            });
        });
    </script>
    {{--
        search.php:51-88's `advanced_search_khotab(p)`/`advanced_search_series(p)`
        — legacy's own AJAX-paginated-fragment functions — deliberately
        NOT ported. KhotabSearchController's already-approved architecture
        (documented in its own class docblock, unchanged by this task)
        renders both result sets in one GET request instead of legacy's
        POST+AJAX split; these two functions would have no real caller in
        that shape (Laravel's `->links()` never invokes them) —
        CONFIGURED_BUT_INERT if added, so left out.
    --}}
@endpush

@section('content')
    {{--
        search.php:95-98's chrome: `title($Author->prename.' '.$Author->name)`
        — the same confirmed `$Author`-null empty-heading bug already
        established for khotab-video-today.htm (LEGACY_BUG_NOT_FOR_
        REPRODUCTION — omit, don't render empty). `$breadcrumb[] =
        ['title'=>$title,'url'=>$siteurl.'video-advanced-search.htm']` — a
        single, self-referencing item — the SHARED breadcrumb() shape
        (confirmed: plain `breadcrumb($breadcrumb)` call, not
        `page_bar_channels()`), so the shared <x-page-chrome> component
        applies here, unlike the two Fatawa-channel pages.
    --}}
    <x-page-chrome :breadcrumb="[['title' => 'البحث المتقدم في المرئيات', 'url' => '/video-advanced-search.htm']]" />

    {{-- Field names, GET behavior, self-submit URL, and datepicker hooks are intentionally unchanged. --}}
    <div class="w2a-refresh-page w2a-video-search-page">
        <x-content.premium-panel
            title="البحث المتقدم في المرئيات"
            icon="fa-search"
            description="اعثر على السلسلة أو المادة المرئية بدقة باستخدام الداعية أو القناة أو تاريخ الإضافة."
            class="w2a-search-panel"
        >
            <form class="w2a-advanced-search" method="get" action="">
                <div class="w2a-field w2a-field--wide">
                    <label for="title">اسم السلسلة أو المادة</label>
                    <span class="w2a-field__control">
                        <i class="fa fa-file-video-o" aria-hidden="true"></i>
                        <input type="search" class="form-control" id="title" name="title" value="{{ $title }}" placeholder="اكتب كلمات البحث" autocomplete="off">
                    </span>
                </div>
                <div class="w2a-field">
                    <label for="author">الداعية</label>
                    <span class="w2a-field__control">
                        <i class="fa fa-user" aria-hidden="true"></i>
                        <select class="form-control" id="author" name="author">
                            <option value="0">كل الدعاة</option>
                            @foreach ($authors as $authorOption)
                                <option value="{{ $authorOption->id }}" @selected($authorId === $authorOption->id)>{{ $authorOption->name }}</option>
                            @endforeach
                        </select>
                    </span>
                </div>
                <div class="w2a-field">
                    <label for="channel">القناة</label>
                    <span class="w2a-field__control">
                        <i class="fa fa-television" aria-hidden="true"></i>
                        <select class="form-control" id="channel" name="channel">
                            <option value="0">كل القنوات</option>
                            @foreach ($channels as $channelOption)
                                <option value="{{ $channelOption->id }}" @selected($channelId === $channelOption->id)>{{ $channelOption->title }}</option>
                            @endforeach
                        </select>
                    </span>
                </div>
                <fieldset class="w2a-field w2a-field--dates">
                    <legend>تاريخ الإضافة</legend>
                    <div class="w2a-date-range">
                        <label for="from" class="sr-only">من تاريخ</label>
                        <span class="w2a-field__control">
                            <i class="fa fa-calendar" aria-hidden="true"></i>
                            <input type="text" class="form-control datepikerinput" id="from" name="from" value="{{ $from }}" placeholder="من تاريخ" inputmode="numeric">
                        </span>
                        <label for="to" class="sr-only">إلى تاريخ</label>
                        <span class="w2a-field__control">
                            <i class="fa fa-calendar-check-o" aria-hidden="true"></i>
                            <input type="text" class="form-control datepikerinput" id="to" name="to" value="{{ $to }}" placeholder="إلى تاريخ" inputmode="numeric">
                        </span>
                    </div>
                </fieldset>
                <div class="w2a-search-actions">
                    <button type="submit" name="kh_search" class="w2a-primary-action" id="kh_search" value="1">
                        <i class="fa fa-search" aria-hidden="true"></i>
                        <span>عرض نتائج البحث</span>
                    </button>
                    <a href="/video-advanced-search.htm" class="w2a-secondary-action">مسح الحقول</a>
                </div>
            </form>

            @if($titleTooShort)
                <div class="w2a-form-alert" role="alert">
                    <i class="fa fa-exclamation-circle" aria-hidden="true"></i>
                    عفواً، يجب إدخال أربعة أحرف على الأقل للبحث.
                </div>
            @endif
        </x-content.premium-panel>
    </div>

    @if($series !== null)
        <div class="w2a-refresh-page w2a-video-search-results">
        {{--
            search.php:230-410's ListSearchSeries() row markup — table#tabelgrp,
            title highlighted via the same title_sub()-equivalent helper
            already used elsewhere (LegacySearchRendering::highlight()),
            author link + conditional channel badge, "المواد:" count with
            fa-play-circle-o, the exact red empty-state message, and the
            "الكل" (total) summary row. Channel-badge author segment uses
            $item->author_id directly (the series query already selects
            it correctly — no bug here, unlike the khotab table below).
        --}}
        <div class="col-md-12 col-sm-12 w2a-search-results-block">
            <div class="portlet box blue">
                <div class="portlet-title">
                    <div class="caption"><i class="fa fa-child"></i> قائمة السلاسل</div>
                </div>
                <div class="portlet-body series-overflow series-overflow-auto">
                    @if($series->total() === 0)
                        <div style="color:RED; font-weight:bold; font-size:15px; margin:auto; width:50%">لا يوجد سلاسل تطابق نتائج البحث....</div>
                    @endif
                    <table class="table table-striped table-hover" id="tabelgrp">
                        <tbody>
                            @forelse($series as $item)
                                <tr>
                                    <td class="table_order">{{ $loop->iteration + ($series->currentPage() - 1) * $series->perPage() }}</td>
                                    <td>
                                        <div class="row"><div class="col-lg-12">
                                            <h5>
                                                <div class="row">
                                                    <div class="col-sm-12 col-lg-8">
                                                        <a href="/khotab-series-{{ $item->id }}.htm">{!! \App\Domain\Content\Support\LegacySearchRendering::highlight(trim((string) $item->title), $title) !!}</a>
                                                    </div>
                                                    <div class="col-sm-12 col-lg-4">
                                                        الداعية:
                                                        <a href="/khotab-video-{{ $item->author_id }}.htm">{{ $item->prename }} {{ $item->name }}</a>
                                                    </div>
                                                </div>
                                            </h5>
                                            <div class="row page-header color_00a">
                                                @if($item->channel_id > 0)
                                                    <div class="col-md-3 col-xs-6 text-blue">
                                                        <span>
                                                            <i class="fa fa-television"></i>
                                                            القناة:
                                                            <a href="/channel-{{ $item->channel_id }}-{{ $item->author_id }}.htm"><img width="24" height="24" border="0" src="/images/channels/{{ $item->channel_id }}.png"></a>
                                                        </span>
                                                    </div>
                                                @endif
                                                <div class="col-md-3 col-xs-6 text-blue">
                                                    <span>
                                                        <i class="fa fa-play-circle-o"></i>
                                                        المواد:
                                                        {{ $item->count }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div></div>
                                    </td>
                                </tr>
                            @empty
                            @endforelse
                            @if($series->total() > 0)
                                <tr><th scope="row">الكل</th><td colspan="2">{{ $series->total() }} مادة</td></tr>
                            @endif
                        </tbody>
                    </table>
                    <div class="row"><div class="col-xs-12">{{ $series->links() }}</div></div>
                </div>
            </div>
        </div>

        {{--
            search.php:413-601's ListSearchKhotab() row markup — same
            table#tabelgrp shape, "الزيارات:" hits with fa-eye instead of
            the series box's count/fa-play-circle-o. IF-018 already fixed
            the confirmed legacy bug where BOTH this row's author link AND
            its channel-badge link read the undefined `$Khotab->author_id`
            (the khotab query never selects `author_id`, only `author` —
            confirmed by direct re-read of ListSearchKhotab(), functions.php:
            550,568) — $item->author (the real, correct column, already
            used by the existing author link below) is used for the new
            channel badge too, applying that same already-established fix
            consistently rather than introducing a fresh instance of the
            same bug.
        --}}
        <div class="col-md-12 col-sm-12 w2a-search-results-block">
            <div class="portlet box blue">
                <div class="portlet-title">
                    <div class="caption"><i class="fa fa-child"></i> قائمة المواد</div>
                </div>
                <div class="portlet-body series-overflow series-overflow-auto">
                    @if($items->total() === 0)
                        <div style="color:RED; font-weight:bold; font-size:15px; margin:auto; width:50%">لا يوجد مواد تطابق نتائج البحث....</div>
                    @endif
                    <table class="table table-striped table-hover" id="tabelgrp">
                        <tbody>
                            @forelse($items as $item)
                                <tr>
                                    <td class="table_order">{{ $loop->iteration + ($items->currentPage() - 1) * $items->perPage() }}</td>
                                    <td>
                                        <div class="row"><div class="col-lg-12">
                                            <h5>
                                                <div class="row">
                                                    <div class="col-sm-12 col-lg-8">
                                                        <a href="/khotab-item-{{ $item->id }}.htm">{!! \App\Domain\Content\Support\LegacySearchRendering::highlight(trim((string) $item->title), $title) !!}</a>
                                                    </div>
                                                    <div class="col-sm-12 col-lg-4">
                                                        الداعية:
                                                        <a href="/khotab-video-{{ $item->author }}.htm">{{ $item->prename }} {{ $item->name }}</a>
                                                    </div>
                                                </div>
                                            </h5>
                                            <div class="row page-header color_00a">
                                                @if($item->channel_id > 0)
                                                    <div class="col-md-3 col-xs-6 text-blue">
                                                        <span>
                                                            <i class="fa fa-television"></i>
                                                            القناة:
                                                            <a href="/channel-{{ $item->channel_id }}-{{ $item->author }}.htm"><img width="24" height="24" border="0" src="/images/channels/{{ $item->channel_id }}.png"></a>
                                                        </span>
                                                    </div>
                                                @endif
                                                <div class="col-md-3 col-xs-6 text-blue">
                                                    <span>
                                                        <i class="fa fa-eye"></i>
                                                        الزيارات:
                                                        {{ $item->hits }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div></div>
                                    </td>
                                </tr>
                            @empty
                            @endforelse
                            @if($items->total() > 0)
                                <tr><th scope="row">الكل</th><td colspan="2">{{ $items->total() }} مادة</td></tr>
                            @endif
                        </tbody>
                    </table>
                    <div class="row"><div class="col-xs-12">{{ $items->links() }}</div></div>
                </div>
            </div>
        </div>
        </div>
    @endif
@endsection
