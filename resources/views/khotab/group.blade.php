@extends('layouts.app')

@section('title', 'مجموعة ' . $groupModel->title)

@section('content')
    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
            <section aria-label="قائمة السلاسل">
                <ul>
                    @foreach ($series as $item)
                        <li><a href="/khotab-series-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                    @endforeach
                </ul>
            </section>

            <section aria-label="قائمة المواد">
                <ul>
                    @foreach ($items as $item)
                        <li><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                    @endforeach
                </ul>
            </section>

            @if(!empty($groupModel->description))
                <section aria-label="وصف المجموعة">{{ $groupModel->description }}</section>
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
