@extends('layouts.app')

@section('title', 'قسم الاسطوانات الدعوية')

@section('content')
    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
            <section aria-label="قائمة الإسطوانات">
                <div class="row">
                    @foreach ($items as $item)
                        <div>
                            <a href="/cds-item-{{ $item->id }}.htm">
                                <img src="/images/cds_image2/{{ $item->firstThumbnailFilename() ?? '' }}" alt="{{ $item->title }}">
                                <span>{{ $item->title }}</span>
                            </a>
                        </div>
                    @endforeach
                </div>
                {{ $items->links() }}
            </section>
        </div>

        <aside class="col-lg-3 col-md-4 col-sm-5 nopadding" aria-label="الشريط الجانبي">
            <h3>الأكثر تحميلا</h3>
            <ul>
                @foreach ($mostDownloaded as $item)
                    <li><a href="/cds-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                @endforeach
            </ul>

            <h3>احدث المواد</h3>
            <ul>
                @foreach ($mostRecent as $item)
                    <li><a href="/cds-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                @endforeach
            </ul>
        </aside>
    </div>
@endsection
