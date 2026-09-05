@extends('layouts.app')

@section('title', 'أحدث 50 درس مفرغ')

@section('content')
    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
            <section class="portlet box blue" aria-label="أحدث 50 درس مفرغ">
                <div class="portlet-title"><div class="caption"><i class="fa fa-child"></i> قائمة المواد</div></div>
                <div class="portlet-body">
                    <x-content.khotab-item-list :items="$items" pdf show-author :show-comments="false" :show-views="false" />
                </div>
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
