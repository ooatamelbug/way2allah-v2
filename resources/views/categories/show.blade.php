@extends('layouts.app')

@section('title', $categoryModel->title)

@section('content')
    <nav aria-label="التصنيفات الموضوعية">
        @foreach ($breadcrumbTrail as $crumb)
            <a href="/category-{{ $crumb->id }}.htm">{{ $crumb->title }}</a>
        @endforeach
    </nav>

    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
            {{-- G-13-12 (media/visual parity phase): categories/functions.php's
                 own ListSeries()/ListKhotab() (lines ~154, ~397 — distinct
                 functions from khotab module's same-named ones) — the same
                 conditional 24x24 images/channels/{id}.png convention. --}}
            <section aria-label="قائمة السلاسل">
                <ul>
                    @foreach ($series as $item)
                        <li>
                            <a href="/khotab-series-{{ $item->id }}.htm">{{ $item->title }}</a>
                            @if(!empty($item->channel_id))
                                <a href="/channel-{{ $item->channel_id }}.htm"><img width="24" height="24" src="/images/channels/{{ $item->channel_id }}.png" alt=""></a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>

            <section aria-label="قائمة المواد">
                <ul>
                    @foreach ($items as $item)
                        <li>
                            <a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a>
                            @if(!empty($item->channel_id))
                                <a href="/channel-{{ $item->channel_id }}.htm"><img width="24" height="24" src="/images/channels/{{ $item->channel_id }}.png" alt=""></a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>

            @if(!empty($categoryModel->description))
                <section aria-label="وصف التصنيف">{{ $categoryModel->description }}</section>
            @endif
        </div>

        <aside class="col-lg-3 col-md-4 col-sm-5 nopadding" aria-label="الشريط الجانبي">
            <h3>اخترنا لك هذه المادة</h3>
            <ul>
                @foreach ($randomFeatured as $item)
                    <li><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                @endforeach
            </ul>

            <h3>الأكثر تحميلا</h3>
            <ul>
                @foreach ($mostDownloaded as $item)
                    <li><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                @endforeach
            </ul>

            <h3>جديد المواد</h3>
            <ul>
                @foreach ($mostRecent as $item)
                    <li><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                @endforeach
            </ul>
        </aside>
    </div>
@endsection
