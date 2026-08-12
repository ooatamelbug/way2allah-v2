@extends('layouts.app')

@section('title', 'الفتاوى المرئية')

@section('content')
    {{--
        `/fatawa.htm`'s real legacy source is `fatawa/fatawa.php` -> `tree.php`
        (routed via .htaccess:278 `modules.php?name=Fatwa`) — NOT
        `fatawa/tobics.php` (that file backs `/fatawa-topics-{id}-{page}.htm`,
        FatwaTopicController::show(), a separate route/view). `title()` and
        `breadcrumb()` below are functions.php:541/453's shared, sitewide
        functions (tree.php:40-41), reproduced for their output on this
        specific page — not fatawa's own page_bar() (that's tobics.php's
        breadcrumb, a different function).

        legacy home link (`{siteurl}index.php`, functions.php:466) adapted
        to "/", same rationale as the header/footer step: index.php isn't a
        Laravel route and the hardcoded production siteurl must not be used.
        `fatawa-categories.htm` (tree.php:38-39's breadcrumb targets) is not
        yet a migrated route — kept as-is, will 404 until it exists, same
        "don't invent new routes" rule as the navbar links.

        Portlet-wrapped sidebar ("احدث التصنيفات المضافة" / "التصنيفات
        الأكثر نشاطاً", tree.php:82-128) reuses the SAME $categories
        collection the controller already passes (no new controller/service
        query) — sorted/limited in the view only, approximating
        tasnifat_latestadd()/tasnifat_active()'s own `ORDER BY id DESC
        LIMIT 10` / `ORDER BY q_count DESC LIMIT 10` queries (fatawa.php:
        147-161) over the same underlying category pool.

        NOT reproduced (out of scope for this step — a wrapper/breadcrumb
        pass, not a rebuild): showtree()'s nested checkbox-accordion tree
        widget (fatawa.php:44-146) — it walks the FULL multi-level category
        hierarchy (3 fixed levels) and depends on fatawa/css/new-style.css
        classes (`tree-demo`, `arrowStyle`, `group_list`, `sub_group_list`)
        that are not wired into the shared layout. The existing flat
        top-level category list is kept, now inside the correct portlet
        wrapper instead of a bare `<ul>`.
    --}}
    <i class="fa fa-gift"></i><h3 class="page-title">الفتاوى المرئية</h3>

    <div class="page-bar">
        <ul class="page-breadcrumb">
            <li>
                <i class="fa fa-home"></i>
                <a href="/">الرئيسية</a>
                <i class="fa fa-angle-right"></i>
            </li>
            <li>
                <a href="fatawa-categories.htm">الفتاوى</a>
                <i class="fa fa-angle-right"></i>
            </li>
            <li>
                <a href="fatawa-categories.htm">تصنيفات الفتاوى الموضوعية</a>
            </li>
        </ul>
    </div>

    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7">
            <div class="portlet blue-hoki box">
                <div class="portlet-title">
                    <div class="caption">
                        <i class="fa fa-cogs"></i>تصفح فتاوى الموقع تبعاً للتصنيف الموضوعي
                    </div>
                </div>
                <div class="portlet-body">
                    <ul>
                        @foreach ($categories as $category)
                            <li><a href="/fatawa-topics-{{ $category->id }}-1.htm">{{ $category->title }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-4 col-sm-5 nopadding">
            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-plus"></i>احدث التصنيفات المضافة</div>
                    </div>
                    <div class="portlet-body">
                        <ul class="news">
                            @foreach ($categories->sortByDesc('id')->take(10) as $category)
                                <li><a class="add" href="/fatawa-topics-{{ $category->id }}-1.htm">{{ $category->title }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-fire"></i>التصنيفات الأكثر نشاطاً</div>
                    </div>
                    <div class="portlet-body">
                        <ul class="news">
                            @foreach ($categories->sortByDesc('q_count')->take(10) as $category)
                                <li><a class="add" href="/fatawa-topics-{{ $category->id }}-1.htm">{{ $category->title }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
