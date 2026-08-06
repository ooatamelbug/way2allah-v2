@extends('layouts.app')

@section('title', 'أحدث 50 درس مفرغ')

@section('content')
    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
            <section aria-label="أحدث 50 درس مفرغ">
                <ul>
                    @foreach ($items as $item)
                        <li>
                            <a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a>
                            — <a href="/khotab-pdf-{{ $item->author }}.htm">{{ $item->prename }} {{ $item->name }}</a>
                        </li>
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
        </aside>
    </div>
@endsection
