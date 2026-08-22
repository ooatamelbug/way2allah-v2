@extends('layouts.app')

{{--
    Legacy-Source Reconstruction (video-advanced-search.htm): production
    pretty URL 404s; `khotab/search.php` is the real handler (confirmed
    independently — its own `$breadcrumb_url = 'video-advanced-search.htm'`
    self-reference, plus header.php:259's real, unconditional sitewide
    nav link `<a href="video-advanced-search.htm">بحث فى المرئيات</a>`
    inside the "المرئيات" dropdown — no `.htaccess` rewrite rule exists
    for it either way, a genuine `LEGACY_PRETTY_URL_ORPHANED` finding, not
    a Laravel invention). `khotab/search.php`'s own raw path
    (`https://way2allah.com/khotab/search.php`) is LIVE — a read-only GET
    confirmed the page chrome (document title, `<h3 class="page-title">`
    empty per the confirmed $Author-null bug, single self-referencing
    breadcrumb item, portlet-wrapped form) matches this repo's source
    exactly. IMPORTANT, EXPLICITLY FLAGGED FINDING: that same live fetch's
    actual `<form>` fields do NOT match this repo's `khotab/search.php`
    source (live shows an 11-option department selector + free-text
    author/channel inputs + `action="search.htm"`, vs. this file's
    author/channel `<select>`s + self-submit) — production's real file
    has apparently been updated since this repo snapshot. This
    reconstruction follows the REPO'S OWN source (also what the
    already-built KhotabSearchController/ContentListingService query
    layer was built against) — reconciling with the newer live form is a
    separate, larger, out-of-scope redesign (a shared multi-department
    engine, `search.htm`/`advanced-search/index.php`), not decided here.
--}}
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

    {{--
        search.php:104-168's form portlet (w2a_open_div, fa-child icon) —
        previously a bare unstyled <label>/<input> list with no portlet,
        no Bootstrap form classes, no datepicker wiring. Field order
        unchanged (already correct): title, author, channel, from/to
        dates. `action=""` (search.php:122, self-submit) reproduced as an
        empty action rather than the previous hardcoded
        `route('khotab.search')` — self-submit works correctly from
        either real entry point (`/khotab/search` or
        `/video-advanced-search.htm`) without hardcoding one, exactly
        matching legacy's own behavior. `method="get"` (not legacy's
        `method="post"`) is KhotabSearchController's own already-approved,
        already-documented departure (bookmarkable/shareable search) —
        unchanged by this task. The 5 hidden `_h` mirror inputs
        (search.php:163-167) fed legacy's AJAX functions above — with
        those not ported, the hidden inputs would have no consumer either,
        so they're not added.
    --}}
    <div class="col-md-12 col-sm-12">
        <div class="portlet box blue">
            <div class="portlet-title">
                <div class="caption"><i class="fa fa-child"></i> البحث المتقدم في المرئيات</div>
            </div>
            <div class="portlet-body">
                <form class="form-horizontal" method="get" action="">
                    <div class="form-group">
                        <label for="title" class="col-sm-2 control-label">اسم السلسلة أو المادة :</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" id="title" name="title" value="{{ $title }}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="author" class="col-sm-2 control-label">الشيخ :</label>
                        <div class="col-sm-8">
                            <select class="form-control" id="author" name="author">
                                <option value="0">إختر</option>
                                @foreach ($authors as $authorOption)
                                    <option value="{{ $authorOption->id }}" @selected($authorId === $authorOption->id)>{{ $authorOption->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="channel" class="col-sm-2 control-label">القناة :</label>
                        <div class="col-sm-8">
                            <select class="form-control" id="channel" name="channel">
                                <option value="0">إختر</option>
                                @foreach ($channels as $channelOption)
                                    <option value="{{ $channelOption->id }}" @selected($channelId === $channelOption->id)>{{ $channelOption->title }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="from" class="col-sm-2 control-label">تاريخ الإضافة :</label>
                        <div class="col-sm-4">
                            <input type="text" class="form-control datepikerinput" id="from" name="from" value="{{ $from }}" placeholder="من">
                        </div>
                        <div class="col-sm-4">
                            <input type="text" class="form-control datepikerinput" id="to" name="to" value="{{ $to }}" placeholder="إلى">
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-md-offset-2 col-sm-8">
                            <input type="submit" name="kh_search" class="btn btn-primary" id="kh_search" value="بــحــث">
                        </div>
                    </div>
                </form>

                @if($titleTooShort)
                    <script type="text/javascript">alert('عفواً ، يجب إدخال أربعة أحرف على الأقل للبحث');</script>
                @endif
            </div>
        </div>
    </div>

    @if($series !== null)
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
        <div class="col-md-12 col-sm-12">
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
        <div class="col-md-12 col-sm-12">
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
    @endif
@endsection
