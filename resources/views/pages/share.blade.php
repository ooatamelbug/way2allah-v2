@extends('layouts.app')

{{--
    help/share.php. Legacy has zero interactive/server-side sharing
    mechanism (Task 6.3 investigation §6) — every banner is a plain,
    static `<img>`, and stays that way here. All ~25 banner URLs are
    confirmed broken (no `/w2a/` directory exists anywhere in this
    codebase) — kept exactly as legacy references them, per Business
    Confirmation #2's explicit "temporary placeholders are acceptable"
    approval; no new placeholder/asset pipeline was built.
--}}

@section('title', 'إنشر الموقع')

@section('content')
    <div class="row service-box margin-bottom-40">
        <div class="col-xs-12 col-sm-12 col-md-12 telawah-item-content nopadding">
            <div class="portlet-body series-overflow series-overflow-auto text-center">
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
@endsection
