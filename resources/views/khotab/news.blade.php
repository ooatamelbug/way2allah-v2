@extends('layouts.app')

@section('title', 'أحدث المواد')

@section('content')
    <div class="row service-box margin-bottom-40">
        <div class="col-md-9 col-sm-9 nopadding">
            <section aria-label="أحدث المواد المضافة">
                <ul>
                    @foreach ($items as $item)
                        <li><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                    @endforeach
                </ul>
            </section>

            @if($op !== 'pdf' && $fixedItems->isNotEmpty())
                <section aria-label="المواد المثبتة">
                    <ul>
                        @foreach ($fixedItems as $item)
                            <li><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        <div class="col-md-3 col-sm-3 nopadding">
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
        </div>
    </div>
@endsection
