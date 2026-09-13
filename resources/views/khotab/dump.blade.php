@extends('layouts.app')

@section('title', 'أحدث 50 درس مفرغ')

@section('content')
    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding flex flex-column gap-5">
            <x-content.premium-panel title="أحدث 50 درس مفرغ" icon="fa-file-text-o">
                <x-content.khotab-item-list :items="$items" pdf show-author :show-comments="false" :show-views="false" />
            </x-content.premium-panel>
        </div>

        <aside class="col-lg-3 col-md-4 col-sm-5 nopadding flex flex-column gap-5" aria-label="الشريط الجانبي">
            <x-content.sidebar-ranking title="الأكثر تحميلاً" icon="fa-fire" :items="$mostDownloaded" type="khotab"
                meta="downloads" />
        </aside>
    </div>
@endsection
