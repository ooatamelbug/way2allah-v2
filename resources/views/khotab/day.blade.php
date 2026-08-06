@extends('layouts.app')

{{-- IF-016 fix: title reflects the browsed date, not a nonexistent $Author. --}}
@section('title', 'المواد المنشورة بتاريخ ' . date('Y-m-d', $date))

@section('content')
    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
            <h1>المواد المنشورة بتاريخ {{ date('Y-m-d', $date) }}</h1>
            <section aria-label="قائمة المواد">
                <ul>
                    @foreach ($items as $item)
                        <li><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                    @endforeach
                </ul>
            </section>
        </div>

        <aside class="col-lg-3 col-md-4 col-sm-5 nopadding" aria-label="الشريط الجانبي">
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
