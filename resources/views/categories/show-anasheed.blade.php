@extends('layouts.app')

@section('title', $categoryModel->title)

@section('content')
    {{--
        categories/category.php's op=var branch (var-category-{id}.htm) ->
        ListVar(). Breadcrumb reproduces category.php:68-69's exact
        construction: a static "التصنيفات الموضوعية" item (linked to the
        var-slug categories index, var-categories.htm — itself not yet
        built, kept as-is per the established "real link, route may not
        exist yet" precedent) followed by Cat_Breadcrumb($cat_id)'s
        ancestor chain (functions.php:493-511) — every ancestor linked
        with the same var- slug, the current category itself unlinked
        (Cat_Breadcrumb's own $lastlink=0 default).
    --}}
    <i class="fa fa-gift"></i><h3 class="page-title">{{ $categoryModel->title }}</h3>

    <div class="page-bar">
        <ul class="page-breadcrumb">
            <li><i class="fa fa-home"></i><a href="/">الرئيسية</a><i class="fa fa-angle-right"></i></li>
            <li><a href="/var-categories.htm">التصنيفات الموضوعية</a><i class="fa fa-angle-right"></i></li>
            @foreach ($breadcrumbTrail as $crumb)
                @if (!$loop->last)
                    <li><a href="/var-category-{{ $crumb->id }}.htm">{{ $crumb->title }}</a><i class="fa fa-angle-right"></i></li>
                @else
                    <li>{{ $crumb->title }}</li>
                @endif
            @endforeach
        </ul>
    </div>

    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
            {{--
                ListVar() (categories/functions.php:427-489): the entire
                portlet is only rendered `if ($w2adb->num_rows>0)` — no
                "no results" message when empty, reproduced exactly via
                @if below rather than always rendering an empty table.
            --}}
            @if ($items->isNotEmpty())
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption">قائمة المواد</div>
                    </div>
                    <div class="portlet-body">
                        <table class="table table-striped table-hover" id="tabelkht">
                            <thead>
                                <tr>
                                    <th>اسم المادة</th>
                                    <th>تاريخ</th>
                                    <th class="hidden-xs">مدة</th>
                                    <th class="hidden-xs">القناة</th>
                                    <th class="hidden-xs">تعليق</th>
                                    <th class="hidden-xs">تصفح</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($items as $item)
                                    @php
                                        // functions.php:357-365 Duration() — ms input, HH:MM:SS (or 00:MM:SS under 1000h).
                                        $durationMs = (int) $item->adur;
                                        $durationSeconds = intdiv($durationMs, 1000);
                                        $duration = $durationMs > 60 * 60 * 1000
                                            ? gmdate('h:i:s', $durationSeconds)
                                            : '00:'.gmdate('i:s', $durationSeconds);
                                    @endphp
                                    <tr>
                                        <td><a href="/var-item-{{ $item->id }}.htm">{{ trim((string) $item->title) }}</a></td>
                                        <td>{{ $item->time ? date('Y-m-d', $item->time) : '' }}</td>
                                        <td class="hidden-xs">{{ $duration }}</td>
                                        <td class="hidden-xs"><a href="/channel-{{ $item->channel_id }}-{{ $categoryModel->id }}.htm">{{ $item->channel }}</a></td>
                                        <td class="hidden-xs">{{ $item->comments }}</td>
                                        <td class="hidden-xs">{{ $item->hits }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if(!empty($categoryModel->description))
                <div class="portlet box blue">
                    <div class="portlet-body">
                        <p>{{ $categoryModel->description }}</p>
                    </div>
                </div>
            @endif
        </div>

        {{--
            Sidebar reproduces category.php:103-130 exactly: unconditional
            randomitems()/topitems() calls, hardcoded to nuke_islamic_khotab
            regardless of op=var — confirmed by direct reading, not
            reproduced from the anasheed table. See CategoryController::
            showAnasheed()'s docblock for the full citation.
        --}}
        <aside class="col-lg-3 col-md-4 col-sm-5 nopadding" aria-label="الشريط الجانبي">
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
        </aside>
    </div>
@endsection
