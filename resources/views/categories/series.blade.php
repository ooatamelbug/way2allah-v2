@extends('layouts.app')

@section('title', $seriesModel->title)

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
                        <ul>
                            @foreach ($items as $item)
                                <li><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                            @endforeach
                        </ul>
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

        <div class="col-lg-3 col-md-4 col-sm-5 nopadding">
            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption">تنزيل مواد السلسلة</div>
                    </div>
                    <div class="portlet-body">
                        {{-- .grx GetRight bulk-download links kept as real hrefs, not yet built — same deferred-scope precedent as khotab_send_friend()/categories/downitems.php elsewhere. --}}
                        <a href="/khotab-series-{{ $seriesModel->id }}-{{ $categoryModel->id }}.grx">
                            <img src="/images/admin/icons/series_download2.png" alt="">
                            <br>تحميل مواد التصنيف
                        </a>
                        <br>
                        <a href="/khotab-series-{{ $seriesModel->id }}.grx">
                            <img src="/images/admin/icons/series_download2.png" alt="">
                            <br>تحميل السلسلة بالكامل
                        </a>
                        <br>
                        <a href="http://download.getright.com/getright-download.exe">
                            <img src="/images/admin/icons/getright.png" alt="رابط برنامج التحميل">
                            <br>برنامج التحميل
                        </a>
                        <br>
                        قبل تحميل السلسلة يجب أن يكون برنامج (getright) مثبت على جهازك وسوف يقوم بتحميل السلسلة مباشرة بمجرد الضغط على تحميل السلسلة. لتحميل البرنامج إضغط على رابط تحميل البرنامج
                    </div>
                </div>
            </div>

            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption">اخترنا لك هذه المادة</div>
                    </div>
                    <div class="portlet-body">
                        <ul class="news">
                            @foreach ($randomFeatured as $item)
                                <li><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption">الأكثر تحميلا</div>
                    </div>
                    <div class="portlet-body">
                        <ul class="news">
                            @foreach ($mostDownloaded as $item)
                                <li><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption">جديد المواد</div>
                    </div>
                    <div class="portlet-body">
                        <ul class="news">
                            @foreach ($mostRecent as $item)
                                <li><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
