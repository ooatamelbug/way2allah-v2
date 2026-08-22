@extends('layouts.app')

{{--
    help/share.php. Legacy has zero interactive/server-side sharing
    mechanism (Task 6.3 investigation §6) — every banner is a plain,
    static `<img>`, and stays that way here. All ~25 banner URLs are
    confirmed broken (no `/w2a/` directory exists anywhere in this
    codebase) — kept exactly as legacy references them, per Business
    Confirmation #2's explicit "temporary placeholders are acceptable"
    approval; no new placeholder/asset pipeline was built. Not reopened
    or touched by this task.

    Chrome/Portlet Gap Closure (2026-08-22): share.php:6-18 uses the same
    shared `title()`/`breadcrumb()` mechanism as about.php — single
    breadcrumb item (`['title'=>'إنشر الموقع','url'=>'']`), reproduced via
    the already-existing <x-page-chrome> component. share.php:22-27's
    `w2a_open_div(['title'=>'إنشر الموقع','width'=>'12','icon'=>'fa-child'])`
    real portlet wrapper was previously entirely absent — replaced with a
    fabricated `.telawah-item-content`/`.portlet-body series-overflow
    series-overflow-auto` wrapper that matches no real class in this
    file's own source. Also corrected: the inner content wrapper is
    `<div class="share text-center">` (share.php:29), not
    `portlet-body series-overflow series-overflow-auto text-center` —
    the banner-loop content/data itself (below) is untouched.
--}}

@section('title', 'إنشر الموقع')

@section('content')
    <x-page-chrome heading="إنشر الموقع" :breadcrumb="[['title' => 'إنشر الموقع', 'url' => '']]" />

    <div class="row service-box margin-bottom-40">
        <div id="" class="col-md-12 col-sm-12">
            <div class="portlet box blue">
                <div class="portlet-title">
                    <div class="caption"><i class="fa fa-child"></i> إنشر الموقع</div>
                </div>
                <div class="portlet-body ">
                    <div class="share text-center">
                        <p><b>زوار موقع الطريق إلى الله</b></p>
                        <p><b>ساهم معنا في نشر الموقع بوضع هذه البانرات في المنتديات</b></p>
                        <p><b>ونشرها عبر الإيميل ودعوة أصدقائك لزيارة موقع الطريق إلى الله</b></p>
                        <p><b>التصميمات بمقاسات مختلفة</b></p>

                        @foreach ($bannerGroups as $sizeLabel => $banners)
                            <p><b>{{ $sizeLabel }}</b></p>
                            @foreach ($banners as $banner)
                                <p>
                                    <img alt="" border="0" src="{{ $bannerBaseUrl.$banner['file'] }}"
                                         @if($banner['width']) width="{{ $banner['width'] }}" @endif
                                         @if($banner['height']) height="{{ $banner['height'] }}" @endif
                                    >
                                </p>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
