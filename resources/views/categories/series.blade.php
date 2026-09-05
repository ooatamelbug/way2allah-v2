@extends('layouts.app')

@section('title', $seriesModel->title)

@push('styles')
    <link href="/assets/frontend/layout/css/category-series.css" rel="stylesheet" type="text/css">
@endpush

@section('content')
    {{--
        categories/series.php. Two DIFFERENT breadcrumb link patterns are
        used on this one page, confirmed by direct reading — not the same
        pattern twice:

        1. Main breadcrumb (series.php:41-43): Cat_Breadcrumb($cat_id, 1)
           — $lastlink=1 (leaf gets a link too) and $otherlinks defaults to
           1 (ancestors also linked) — so EVERY category crumb here is
           linked to category-{id}.htm; the true "current page" label is a
           separate, final, unlinked "سلسلة {title}" item appended after.
           This is the OPPOSITE of categories.htm's/var-category's own
           breadcrumb (IF-037), which leaves its last item unlinked — a
           real, confirmed difference between the two pages, not an
           inconsistency to "fix".

        2. Per-series-category breadcrumbs ("جميع تصنيفات مواد السلسلة",
           series.php:64, Ser_Cat_Breadcrumb() -> Cat_Breadcrumb($val, 1, 0,
           ...)): $lastlink=1 but $otherlinks=0 — here the LEAF is linked
           and ANCESTORS are plain text, one full separate breadcrumb block
           per category on the series' own `cat` column, only rendered
           when the main listing has items (series.php:55).
    --}}
    <div class="page-bar">
        <ul class="page-breadcrumb">
            <li><i class="fa fa-home"></i><a href="/">الرئيسية</a><i class="fa fa-angle-right"></i></li>
            <li><a href="/categories.htm">التصنيفات الموضوعية</a><i class="fa fa-angle-right"></i></li>
            @foreach ($categoryBreadcrumbTrail as $crumb)
                <li><a href="/category-{{ $crumb->id }}.htm">{{ $crumb->title }}</a><i class="fa fa-angle-right"></i></li>
            @endforeach
            <li>سلسلة {{ $seriesModel->title }}</li>
        </ul>
    </div>

    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
            @if ($items->isNotEmpty())
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption">{{ $seriesModel->title }}</div>
                    </div>
                    <div class="portlet-body">
                        <x-content.category-media-grid :items="$items" />
                    </div>
                </div>

                @if ($seriesCategoryTrails->isNotEmpty())
                    <div class="portlet box blue" id="cats-breadtcrumb">
                        <div class="portlet-title">
                            <div class="caption">جميع تصنيفات مواد السلسلة</div>
                        </div>
                        <div class="portlet-body">
                            @foreach ($seriesCategoryTrails as $trail)
                                <div class="page-bar">
                                    <ul class="page-breadcrumb">
                                        @foreach ($trail as $crumb)
                                            @if ($loop->last)
                                                <li><a href="/category-{{ $crumb->id }}.htm">{{ $crumb->title }}</a></li>
                                            @else
                                                <li>{{ $crumb->title }}<i class="fa fa-angle-right"></i></li>
                                            @endif
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif
        </div>

        <aside class="col-lg-3 col-md-4 col-sm-5 nopadding w2a-series-sidebar" aria-label="معلومات وتنزيلات السلسلة">
            <div class="col-md-12 col-sm-12">
                <section class="portlet box blue w2a-series-download-widget" aria-labelledby="series-download-title">
                    <div class="portlet-title">
                        <h2 class="caption" id="series-download-title"><i class="fa fa-cloud-download" aria-hidden="true"></i> تنزيل مواد السلسلة</h2>
                    </div>
                    <div class="portlet-body">
                        <x-content.series-download-panel :series="$seriesModel" :category="$categoryModel" />
                    </div>
                </section>
            </div>

            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption">اخترنا لك هذه المادة</div>
                    </div>
                    <div class="portlet-body">
                        <x-content.featured-items :items="$randomFeatured" />
                    </div>
                </div>
            </div>

            <div class="col-md-12 col-sm-12">
                <section class="portlet box blue w2a-series-list-widget" aria-labelledby="most-downloaded-title">
                    <div class="portlet-title">
                        <h2 class="caption" id="most-downloaded-title"><i class="fa fa-cloud-download" aria-hidden="true"></i> الأكثر تحميلا</h2>
                    </div>
                    <div class="portlet-body">
                        @if ($mostDownloaded->isNotEmpty())
                            <x-content.top-items :items="$mostDownloaded" />
                        @else
                            <p class="w2a-series-empty-state"><i class="fa fa-inbox" aria-hidden="true"></i> لا توجد مواد متاحة حاليًا</p>
                        @endif
                    </div>
                </section>
            </div>

            <div class="col-md-12 col-sm-12">
                <section class="portlet box blue w2a-series-list-widget" aria-labelledby="most-recent-title">
                    <div class="portlet-title">
                        <h2 class="caption" id="most-recent-title"><i class="fa fa-clock-o" aria-hidden="true"></i> جديد المواد</h2>
                    </div>
                    <div class="portlet-body">
                        @if ($mostRecent->isNotEmpty())
                            <x-content.top-items :items="$mostRecent" mode="time" />
                        @else
                            <p class="w2a-series-empty-state"><i class="fa fa-inbox" aria-hidden="true"></i> لا توجد مواد متاحة حاليًا</p>
                        @endif
                    </div>
                </section>
            </div>
        </aside>
    </div>
@endsection
