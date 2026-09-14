@extends('layouts.app')

@section('title', $categoryModel->title)

@section('content')
    <h3 class="page-title">{{ $categoryModel->title }}</h3>

    <div class="page-bar">
        <ul class="page-breadcrumb">
            <li><i class="fa fa-home"></i><a href="/">الرئيسية</a><i class="fa fa-angle-right"></i></li>
            <li><a href="/var-categories.htm">التصنيفات الموضوعية</a><i class="fa fa-angle-right"></i></li>
            @foreach ($breadcrumbTrail as $crumb)
                @if (!$loop->last)
                    <li><a href="/var-category-{{ $crumb->id }}.htm">{{ $crumb->title }}</a><i
                            class="fa fa-angle-right"></i></li>
                @else
                    <li>{{ $crumb->title }}</li>
                @endif
            @endforeach
        </ul>
    </div>

    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding flex flex-column gap-5">
            @if ($items->isNotEmpty())
                <x-content.premium-panel title="قائمة المواد" icon="fa-video-camera">
                    <x-content.anasheed-media-grid :items="$items" />
                </x-content.premium-panel>
            @endif

            @if (!empty($categoryModel->description))
                <x-content.premium-panel title="نبذة عن التصنيف" icon="fa-info-circle">
                    <p>{{ $categoryModel->description }}</p>
                </x-content.premium-panel>
            @endif
        </div>

        <aside class="col-lg-3 col-md-4 col-sm-5 nopadding flex flex-column gap-5" aria-label="الشريط الجانبي">
            <x-content.premium-panel title="اخترنا لك هذه المادة" icon="fa-star">
                <x-content.featured-items :items="$randomFeatured" />
            </x-content.premium-panel>

            <x-content.sidebar-ranking title="الأكثر تحميلاً" icon="fa-fire" :items="$mostDownloaded" type="khotab"
                meta="downloads" />

            <x-content.sidebar-ranking title="جديد المواد" icon="fa-clock-o" :items="$mostRecent" type="khotab"
                meta="recent" />
        </aside>
    </div>
@endsection
