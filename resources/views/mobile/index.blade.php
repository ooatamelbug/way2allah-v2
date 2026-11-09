@extends('layouts.app')

@section('title', 'الجوال الدعوي')

@section('content')
    <x-page-chrome heading="قسم الموبايل" :breadcrumb="[['title' => 'قسم الموبايل', 'url' => '']]" />
    <div class="container-fluid">
        @include('mobile._flash')
        @include('mobile._menu')
        <x-content.premium-panel title="التصنيف الرئيسي للمواد" icon="fa-mobile">
            <div class="row">
                @foreach ($categories as $category)
                    <div class="col-sm-6" style="margin-bottom:12px">
                        <strong><a href="/mobiles-cat{{ $category->cid }}.htm">{{ $category->title }}</a></strong>
                        @php($badge = \App\Domain\Content\Services\Mobile\MobileCatalog::newBadge($latestByCategory[$category->cid] ?? null))
                        @if ($badge) <span class="label label-success">جديد</span> @endif
                        @if (trim($category->cdescription) !== '')
                            <div class="text-muted" style="white-space:pre-line">{{ \App\Domain\Content\Services\Mobile\MobileSite::plainText($category->cdescription) }}</div>
                        @endif
                        @if ($category->firstChildren->isNotEmpty())
                            <div style="font-size:12px">
                                @foreach ($category->firstChildren as $child)
                                    <a href="/mobiles-cat{{ $child->cid }}.htm">{{ $child->title }}</a>@if (! $loop->last), @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
            <p class="text-muted">يوجد حاليا {{ $materialCount }} مادة في {{ $categoryCount }} تصنيف</p>
        </x-content.premium-panel>
    </div>
@endsection
