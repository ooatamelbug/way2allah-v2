@extends("layouts.app")

@section("title", "أحدث المواد")

@section("content")
    <div class="row service-box margin-bottom-40">
        <div class="col-md-9 col-sm-8 nopadding">
            <x-content.premium-panel title="أحدث المواد المضافة" icon="fa-video-camera">
                <x-content.khotab-item-list :items="$items" :video="$op === "video"" :pdf="$op === "pdf"" show-author />
            </x-content.premium-panel>

            @if($op !== "pdf" && $fixedItems->isNotEmpty())
                <x-content.premium-panel title="المواد المثبتة" icon="fa-thumb-tack">
                    <x-content.khotab-item-list :items="$fixedItems" :video="$op === "video"" show-author :show-comments="false" />
                </x-content.premium-panel>
            @endif
        </div>

        <aside class="col-md-3 col-sm-4 nopadding" aria-label="الشريط الجانبي">
            <x-content.premium-panel title="اخترنا لك" icon="fa-star">
                <x-content.featured-items :items="$randomFeatured" />
            </x-content.premium-panel>

            <x-content.sidebar-ranking
                title="الأكثر تحميلاً"
                icon="fa-fire"
                :items="$mostDownloaded"
                type="khotab"
                meta="downloads"
            />
        </aside>
    </div>
@endsection
