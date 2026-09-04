@extends('layouts.app')

@section('title', $categoryModel->title)

{{--
    Batch 1 (category-1.htm/channel-1.htm parity): categories/category.php:44-45
    (`register_script('scripts/khotab_tables.js'); Plugins('datatables');`) —
    confirmed unconditional, not inside any `if`. Scoped to this page only via
    @push, matching this page's own real asset registration rather than making
    DataTables globally active in the shared layout.
--}}
@push('styles')
    <link href="/assets/global/plugins/datatables/datatables.min.css" rel="stylesheet" type="text/css"/>
    <link href="/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap-rtl.css" rel="stylesheet" type="text/css"/>
    {{--
        Full Design Parity Pass (category-{id}.htm): category.php:1-18's own
        literal inline <style> block — the Series card-grid's hover
        transform, confirmed live on production (category-1.htm's
        .telawah-author cards use exactly this transition).
    --}}
    <style>
        .telawah-author .thumbnail {
            height: 185px !important;
            position: relative;
            transition: all .3s ease-in-out
        }

        .telawah-author .thumbnail .telawa-author-name {
            position: absolute;
            bottom: 4px;
            width: calc(100% - 4px);
            transition: all .3s ease-in-out
        }

        .telawah-author .thumbnail:hover .telawa-author-name{
            transform: translateY(-10px);
        }
    </style>
@endpush

@push('scripts')
    <script src="/assets/global/plugins/datatables/datatables.min.js" type="text/javascript"></script>
    <script src="/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js" type="text/javascript"></script>
    <script src="/scripts/khotab_tables.js" type="text/javascript"></script>
@endpush

@section('content')
    {{-- Shared Page Chrome Parity Audit: replaces the previous bare <nav><a> list (missing Home, the "التصنيفات الموضوعية" root label, and the real page-breadcrumb DOM) with the shared chrome. --}}
    <x-page-chrome :heading="$categoryModel->title" :breadcrumb="$breadcrumbTrail" />

    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
            {{--
                Final Conditional-Branch Audit (category-487.htm):
                categories/functions.php's ListMediaCoverage() (lines
                201-310) — category.php:80-82's `if($cat_id==487)` gate,
                reproduced in the controller (not here) since it depends
                on $items, already computed for the Khotab section below.
                Additive, rendered BEFORE Series/Khotab — confirmed live on
                production (50 cards, real standing nav link to
                category-487.htm). Sub-category listing
                (`SELECT * FROM nuke_w2a_cat WHERE main_cat=487 ORDER BY id
                DESC`, no LIMIT) is a genuinely separate dataset from the
                gating query — see `mediaCoverageSubcategories()`'s own
                docblock. The 10 hardcoded category-id -> logo mappings are
                the exact same convention already established for
                home.blade.php's cat-487 partial (home_functions.php's
                sibling `list_latest_cat_487()`) — same 10 ids, same
                filenames, reused verbatim rather than re-derived. Unlike
                ListSeries()'s `$Item->title` bug, `$Item` here IS the real,
                defined loop variable (`foreach ($resultcat as $Item)`) —
                title attribute correctly shows the sub-category's real
                title, confirmed live (`title="طوفان الأقصى"`, not empty).
            --}}
            @if ($mediaCoverageSubcategories->isNotEmpty())
                <div id="" class="col-md-12 col-sm-12">
                    <div class="portlet box blue">
                        <div class="portlet-title">
                            <div class="caption"><i class="fa fa-star" aria-hidden="true"></i> برامج حصرية لشبكة الطريق إلى الله</div>
                        </div>
                        <div class="portlet-body ">
                            <div class="w2a-exclusive-shows-wrap">
                                <div class="w2a-exclusive-shows-grid">
                                @foreach ($mediaCoverageSubcategories as $item)
                                    @php($logo = match ((int) $item->id) {
                                        613 => '/images/logos/Salon.gif',
                                        612 => '/images/logos/wasabeko.gif',
                                        611 => '/images/logos/LwAreftmoh.gif',
                                        610 => '/images/logos/AlamtanyAya.gif',
                                        609 => '/images/logos/KadaShbab.gif',
                                        603 => '/images/logos/AbgadiaAsaria.gif',
                                        601 => '/images/logos/RamdanKarab.gif',
                                        592 => '/images/logos/ayatTotla.gif',
                                        562 => '/images/logos/AnRab.gif',
                                        618 => '/images/logos/RamdanKarab6.gif',
                                        default => '/images/tvnoise.gif',
                                    })
                                    <a href="/category-{{ $item->id }}.htm" class="w2a-exclusive-card">
                                        <div class="w2a-exclusive-banner-wrap">
                                            <img src="{{ $logo }}" alt="{{ $item->title }}" class="w2a-exclusive-banner-img" width="240" height="160" loading="lazy" decoding="async">
                                            <span class="w2a-exclusive-badge"><i class="fa fa-star" aria-hidden="true"></i> برنامج حصري</span>
                                            <span class="w2a-exclusive-overlay" aria-hidden="true">
                                                <span class="w2a-exclusive-overlay-icon"><i class="fa fa-play"></i></span>
                                            </span>
                                        </div>
                                        <div class="w2a-exclusive-body">
                                            <h3 class="w2a-exclusive-title">{{ $item->title }}</h3>
                                            <div class="w2a-exclusive-cta">
                                                <span>تصفح البرنامج</span>
                                                <i class="fa fa-angle-left" aria-hidden="true"></i>
                                            </div>
                                        </div>
                                    </a>
                                @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{--
                Full Design Parity Pass (category-{id}.htm): categories/functions.php's
                ListSeries() (lines 78-197) — re-read in full. Its richer
                <table>/<tr> markup (lines 97-192) is entirely HTML-commented
                out in source (confirmed on live production too — the same
                comment block is present verbatim in the page source). The
                ONLY active output per series is a `.telawah-author` card
                (lines 167-189): a static placeholder image (ALWAYS
                images/tvnoise.gif — no thumbnail/channel/count/date field
                is ever rendered, despite the query selecting them), and a
                title+author-name link. `ContentListingService::seriesByCategoryAndGroup()`
                already selects every field ListSeries()'s query does —
                unchanged, no query gap.

                Confirmed legacy bug, reproduced not fixed: the <img>'s
                `title` attribute reads `$Item->title` (capital I) — a
                variable never defined inside ListSeries() (the loop
                variable is lowercase `$item`) — so it always renders
                `title=""`, confirmed byte-for-byte on live production
                (`title="" width="" height=""`). Link target is
                `category-series-{id}-{catId}.htm` (confirmed live,
                NOT `khotab-series-{id}.htm` — the previous markup's link
                went to the wrong route entirely).

                Whole portlet (including its wrapper) only renders when
                `$w2adb->num_rows>0` — no empty-state markup exists for
                this section in source, so it's omitted entirely when
                $series is empty, not rendered with a "no series" message.
            --}}
            @if ($series->isNotEmpty())
                <div id="" class="col-md-12 col-sm-12">
                    <div class="portlet box blue">
                        <div class="portlet-title">
                            <div class="caption"><i class="fa fa-child"></i> قائمة السلاسل</div>
                        </div>
                        <div class="portlet-body ">
                            <div class="row telawat_authors_list">
                                @foreach ($series as $item)
                                    <div class="col-xs-12 col-sm-6 col-md-4 col-lg-3 telawah-author">
                                        <div class="thumbnail">
                                            <img src="https://way2allah.com//images/tvnoise.gif" title="" width="" height="">
                                            <div class="telawa-author-name text-center">
                                                <a href="/category-series-{{ $item->id }}-{{ $categoryModel->id }}.htm">
                                                    {{ $item->title }}
                                                    <br>
                                                    {{ $item->prename }} {{ $item->name }}
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{--
                Batch 1 (category-1.htm/channel-1.htm parity): categories/functions.php's
                ListKhotab() (line 318) — genuinely active `id="tabelkht"` table,
                verified against BOTH source and live production HTML (unlike
                this same file's ListSeries(), whose table markup is HTML-commented
                out in source and in production — that section is deliberately
                left untouched this batch). `$ob->mode` is referenced in the
                legacy conditionals below but never defined anywhere in
                category.php's call chain (confirmed: zero matches for `$ob`),
                so every `$ob->mode != 'x'` comparison is `null != 'x'` — always
                true — meaning date/comments/duration/channel-badge all render
                unconditionally here, reproduced as such rather than guessing
                a $ob source that doesn't exist.
            --}}
            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-child"></i> قائمة المواد</div>
                    </div>
                    <div class="portlet-body series-overflow series-overflow-auto">
                        <table class="table table-striped table-hover" id="tabelkht">
                            <tbody>
                                @foreach ($items as $item)
                                    <tr><td class="">
                                        <div class="row"><div class="col-lg-12">
                                            <h5>
                                                <div class="row">
                                                    <div class="col-sm-12 col-lg-6">
                                                        <a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a>
                                                    </div>
                                                    @if(!empty($item->name))
                                                        <div class="col-sm-12 col-lg-6">
                                                            {{ $item->prename }} :
                                                            <a href="/khotab-video-{{ $item->authID }}.htm">{!! str_replace(' ', '&nbsp;', e($item->name)) !!}</a>
                                                        </div>
                                                    @endif
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
                </div>
            </div>

            {{--
                Full Design Parity Pass: category.php:88-98's real portlet
                wrapper — the previous markup was a bare <section>, no
                portlet/wrapper at all.
            --}}
            @if(!empty($categoryModel->description))
                <div id="" class="col-md-12 col-sm-12">
                    <div class="portlet box blue">
                        <div class="portlet-body ">
                            <p>{{ $categoryModel->description }}</p>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{--
            Full Design Parity Pass: category.php:105-130's 3 sidebar
            portlets, each built via w2a_open_div() around randomitems()/
            topitems() — the previous markup was 3 bare <h3>/<ul> lists
            with no portlet wrapper, no thumbnails, and no per-item
            metadata line at all.

            "اخترنا لك هذه المادة" (randomitems(), functions.php:1092-1141):
            gif==1 -> khotab_gifs, else khotab_frames, bucketed by
            floor(id/1000) — no file_exists() gate in this function
            (a real, confirmed difference from topitems() below), matching
            the same reconstruction already established for khotab-series-
            {id}.htm's identical randomitems() sidebar box.

            "الأكثر تحميلا"/"جديد المواد" (topitems(), functions.php:992-1090):
            now that ContentSidebarWidget::khotabMostDownloadedByCategory()/
            khotabMostRecentByCategory() append ->thumb via the
            already-established topitemsThumb() helper (G-13-01), the real
            <li class="media"> markup is reproduced here matching
            khotab-video-today.htm's identical topitems() sidebar boxes.
            The mode='hits' box shows a download count, mode='time' shows
            CoolShortDate() (LegacyShortDateFormatter — a DIFFERENT
            function from ListKhotab()'s own tinydate()/date('Y-m-d')).
            Legacy's own malformed unclosed-quote `onerror` attribute
            (confirmed live on production — the missing `"` after
            `$item->title` swallows `onerror="` into the alt text, so the
            fallback handler never actually registers) is not reproduced,
            same as the already-established day.blade.php precedent for
            this identical shared function — functionally inert either way.
        --}}
        <aside class="col-lg-3 col-md-4 col-sm-5 nopadding" aria-label="الشريط الجانبي">
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

            <div id="" class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-cloud-download"></i> الأكثر تحميلا</div>
                    </div>
                    <div class="portlet-body ">
                        <x-content.top-items :items="$mostDownloaded" />
                    </div>
                </div>
            </div>

            <div id="" class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-flash"></i> جديد المواد</div>
                    </div>
                    <div class="portlet-body ">
                        <x-content.top-items :items="$mostRecent" mode="time" />
                    </div>
                </div>
            </div>
        </aside>
    </div>
@endsection
