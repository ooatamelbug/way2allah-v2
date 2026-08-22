@extends('layouts.app')

{{--
    `khotab-group-{id}.htm` Full Design Parity Pass. `khotab/group.php`
    re-read in full, plus `khotab/functions.php`'s OWN `ListSeries($ob,
    $hidden)`/`ListKhotab($ob,$op_title,$hidden)` (stdClass-`$ob` shaped —
    a DIFFERENT function from categories/functions.php's same-named,
    int-parameter versions; do not confuse the two). Every portlet on this
    page uses `fa-child` uniformly (confirmed live: exactly 6 `fa-child`
    icons, zero of any other portlet-caption icon) — not the varied icons
    other already-migrated pages use.

    Confirmed, source-proven quirk: group.php:113,122 call
    `topitems('hits', "author='{id}' AND vedio='{v}'", $orderby, 5)` for
    BOTH sidebar boxes — the "جديد المواد" (newest) call ALSO passes
    `mode='hits'`, not `'time'`. `topitems()`'s display label is keyed off
    `$mode`, not the SQL `$orderby` — so both boxes render "عدد مرات
    التحميل: X مرة", confirmed live (`جديد المواد`'s own items show a
    download count, not a date). Do NOT copy khotab/day.blade.php's
    correct-`mode='time'` convention here — this page's own source proves
    a different (bugged) call.

    `ListSeries()`/`ListKhotab()` each nest a SECOND, literal
    `<div class="portlet-body ...">` INSIDE `w2a_open_div()`'s own
    `portlet-body` wrapper (khotab/functions.php:439,638) — confirmed
    live (double-nested `portlet-body` divs) — reproduced exactly, not
    collapsed into one.

    Title Gap Closure (2026-08-22): group.php's own `$title` (line 10)
    never includes the sitename — `header.php:26`'s own unconditional
    `$header['title'] . ' - ' . $sitename` is the ONLY place it gets
    appended, exactly once. This section used to also append
    `.config('app.name')` on top of the shared layout's own automatic
    `@yield('title') - {{ config('app.name') }}`, producing a genuine
    double-suffix — confirmed wrong against a fresh live fetch (single
    suffix). Removed; the layout's own append is the only one now.
--}}
@section('title', 'مجموعة '.$groupModel->title.' - '.$authorName)

{{--
    `assets/global/scripts/datatable.js` investigated (Title/DataTables Gap
    Closure, 2026-08-22), not added: `classes/plugins.php`'s own
    `case "datatables":` block (functions.php's `Plugins('datatables')`,
    group.php:13) does register it as one of 5 assets — a real, confirmed
    legacy asset load, not a hallucination. But the file itself
    (read in full) only DEFINES an unused global `Datatable` wrapper
    class/function (an AJAX/server-side/group-actions helper, Metronic
    theme style) — it never self-executes. `scripts/khotab_tables.js`
    (also read in full) is fully self-contained: it defines its own
    `TableDatatablesScroller` module, calls plain jQuery `.dataTable()`
    directly on `#tableser`/`#tabelkht`, and never references the global
    `Datatable` symbol at all. CONFIGURED_BUT_INERT — real asset,
    zero functional or observable effect on this page — not added here.
--}}
@push('styles')
    <link href="/assets/global/plugins/datatables/datatables.min.css" rel="stylesheet" type="text/css"/>
    <link href="/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap-rtl.css" rel="stylesheet" type="text/css"/>
@endpush

@push('scripts')
    <script src="/assets/global/plugins/datatables/datatables.min.js" type="text/javascript"></script>
    <script src="/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js" type="text/javascript"></script>
    <script src="/scripts/khotab_tables.js" type="text/javascript"></script>
@endpush

@section('content')
    <x-page-chrome
        :heading="'مجموعة '.$groupModel->title.' - '.$authorName"
        :breadcrumb="$breadcrumbTrail"
    />

    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
            {{--
                ListSeries($ob,$hidden) — khotab/functions.php:409-508. Row
                link is /khotab-series-{id}.htm (no category suffix, unlike
                categories/functions.php's own ListSeries()). No author
                shown per row (this function never selects one). Real text
                empty-state ("لا توجد سلاسل مطابقة..."), not an omitted
                block — a different contract from categories' version.
            --}}
            <div id="" class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-child"></i> قائمة السلاسل</div>
                    </div>
                    <div class="portlet-body ">
                        <div class="portlet-body series-overflow">
                            @if ($series->isNotEmpty())
                                <table class="table table-striped table-hover" id="tableser">
                                    <tbody>
                                        @foreach ($series as $item)
                                            <tr>
                                                <td class="">
                                                    <div class="row">
                                                        <div class="col-lg-12">
                                                            <h5>
                                                                <a href="/khotab-series-{{ $item->id }}.htm">{{ $item->title }}</a>
                                                            </h5>
                                                            <div class="row page-header color_00a">
                                                                <div class="col-md-3 col-xs-6 text-blue">
                                                                    <span><i class="fa fa-calendar"></i> {{ date('Y-m-d', $item->time) }}</span>
                                                                </div>
                                                                <div class="col-md-3 col-xs-6 text-blue">
                                                                    <span><i class="fa fa-refresh"></i> {{ date('Y-m-d', $item->lastupdate) }}</span>
                                                                </div>
                                                                <div class="col-md-3 col-xs-6 text-blue">
                                                                    <span><i class="fa fa-play-circle-o"></i> المواد: {{ $item->count }}</span>
                                                                </div>
                                                                @if (!empty($item->channel_id))
                                                                    <div class="col-md-3 col-xs-6 text-blue">
                                                                        <span><i class="fa fa-television"></i> القناة:
                                                                            <a href="/channel-{{ $item->channel_id }}.htm"><img width="24" height="24" border="0" src="/images/channels/{{ $item->channel_id }}.png" alt=""></a>
                                                                        </span>
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <h5>لا توجد سلاسل مطابقة بقاعدة بيانات الموقع</h5>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{--
                ListKhotab($ob,'قائمة المواد',$hidden) —
                khotab/functions.php:511-730, the default ($ob->mode
                undefined) branch: no author link per row (the
                'fixed'/'new'/'day' author-div condition is always false
                here), date/comments/hits always shown, duration/channel
                conditional. Real text empty-state, same shape as Series.
            --}}
            <div id="" class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-child"></i> قائمة المواد</div>
                    </div>
                    <div class="portlet-body ">
                        <div class="portlet-body series-overflow series-overflow-auto"> <!-- series-overflow -->
                            @if ($items->isNotEmpty())
                                <table class="table table-striped table-hover" id="tabelkht">
                                    <tbody>
                                        @foreach ($items as $item)
                                            @php($duration = \App\Domain\Content\Support\LegacyDurationFormatter::format((int) ($item->adur ?? 0)))
                                            <tr>
                                                <td class="">
                                                    <div class="row">
                                                        <div class="col-lg-12">
                                                            <h5>
                                                                <div class="row">
                                                                    <div class="col-sm-12 col-lg-8">
                                                                        <a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a>
                                                                    </div>
                                                                </div>
                                                            </h5>
                                                            <div class="row page-header color_00a">
                                                                <div class="col-md-3 col-xs-6 text-blue">
                                                                    <span><i class="fa fa-calendar"></i> {{ date('Y-m-d', $item->time) }}</span>
                                                                </div>
                                                                <div class="col-md-3 col-xs-6 text-blue">
                                                                    <span><i class="fa fa-commenting-o"></i> التعليقات: {{ $item->comments }}</span>
                                                                </div>
                                                                <div class="col-md-3 col-xs-6 text-blue">
                                                                    <span><i class="fa fa-eye"></i> مشاهدات: {{ number_format($item->hits) }}</span>
                                                                </div>
                                                                @if (!empty($item->channel_id))
                                                                    <div class="col-md-3 col-xs-6 text-blue">
                                                                        <span><i class="fa fa-television"></i> القناة:
                                                                            <a href="/channel-{{ $item->channel_id }}.htm"><img width="24" height="24" border="0" src="/images/channels/{{ $item->channel_id }}.png" alt=""></a>
                                                                        </span>
                                                                    </div>
                                                                @endif
                                                                @if ($duration !== '00:00:00')
                                                                    <div class="col-md-3 col-xs-6 text-blue">
                                                                        <span><i class="fa fa-clock-o"></i> {{ $duration }}</span>
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <h5>لا توجد مواد مطابقة بقاعدة بيانات الموقع</h5>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            @if (!empty($groupModel->description))
                <div id="" class="col-md-12 col-sm-12">
                    <div class="portlet box blue">
                        <div class="portlet-body ">
                            <p>{{ $groupModel->description }}</p>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-3 col-md-4 col-sm-5 nopadding">
            {{--
                group.php:84-95's "الملف الشخصي" box — get_author_img()
                called directly, no author_image priority check (confirmed
                by direct re-read: group.php never wraps this in an
                author_image-first check, unlike khotab/author.php:104) —
                fallbackImageUrl() is the correct match here, not
                displayImageUrl().
            --}}
            <div id="" class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-child"></i> الملف الشخصي</div>
                    </div>
                    <div class="portlet-body ">
                        <div class="profile-userpic">
                            <img src="{{ $authorModel?->fallbackImageUrl() }}" class="img-responsive" alt="">
                        </div>
                    </div>
                </div>
            </div>

            {{-- group.php:104's randomitems() — same shared function, same no-args call already reconstructed for category-{id}.htm/gallery.htm. --}}
            <div id="" class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-child"></i> اخترنا لك هذه المادة</div>
                    </div>
                    <div class="portlet-body ">
                        @foreach ($randomFeatured as $item)
                            @php($photo = ((int) ($item->gif ?? 0)) === 1
                                ? \App\Domain\Content\Support\MediaPathResolver::path('khotab_gifs', $item->id, 'gif')
                                : \App\Domain\Content\Support\MediaPathResolver::path('khotab_frames', $item->id, 'jpg'))
                            <div class="thumbnail">
                                <img src="/{{ $photo }}" alt="{{ $item->title }}" style="width: 100%; height: 160px; display: block;">
                                <div class="caption">
                                    <h3>{{ $item->name }}</h3>
                                    <p><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{--
                group.php:107-114's "الأكثر تحميلا" — topitems('hits', "author=...AND vedio=...", "hits DESC", 5).
            --}}
            <div id="" class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-child"></i> الأكثر تحميلا</div>
                    </div>
                    <div class="portlet-body ">
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
                group.php:116-123's "جديد المواد" — topitems('hits', "author=...AND vedio=...", "time DESC", 5).
                mode='hits' here too (see top-of-file docblock) — same
                "عدد مرات التحميل" label as the box above, confirmed live,
                NOT a date label.
            --}}
            <div id="" class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-child"></i> جديد المواد</div>
                    </div>
                    <div class="portlet-body ">
                        <ul class="media-list">
                            @foreach ($mostRecent as $item)
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
        </div>
    </div>
@endsection
