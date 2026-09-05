@extends('layouts.app')

{{-- Premium CD directory; controller data and pagination behavior remain unchanged. --}}
@section('title', 'قسم الاسطوانات الدعوية')

@push('styles')
    <link href="/assets/frontend/pages/css/gallery.css" rel="stylesheet">
@endpush


@push('page-styles')
    <link href="/assets/frontend/layout/css/content-refresh.css" rel="stylesheet" type="text/css">
@endpush

@section('content')
    <x-page-chrome
        heading="قائمة الإسطوانات الدعوية"
        :breadcrumb="[['title' => 'الاسطوانات الدعوية', 'url' => '']]"
    />

    <div class="w2a-refresh-page w2a-cds-page">
        <x-content.premium-panel
            title="قائمة الإسطوانات العامة"
            icon="fa-dot-circle-o"
            description="مكتبة من الإسطوانات الدعوية المختارة، مرتبة لتصل إلى محتواها بسهولة."
        >
            <div class="w2a-cd-grid">
                @forelse ($items as $item)
                    @php
                        $listPhoto = $item->firstThumbnailFilename()
                            ? '/images/cds_image2/'.$item->firstThumbnailFilename()
                            : '/images/way2_cddefault.png';
                        $listThumb = \App\Domain\Content\Support\MediaUrl::thumbnail('h=260&w=260&src='.$listPhoto);
                    @endphp
                    <article class="w2a-cd-card">
                        <a href="/cds-item-{{ $item->id }}.htm" class="w2a-cd-card__link">
                            <span class="w2a-cd-card__media">
                                <img src="{{ $listThumb }}" alt="غلاف إسطوانة {{ $item->title }}" width="260" height="260" loading="lazy" decoding="async">
                                <span class="w2a-cd-card__play" aria-hidden="true"><i class="fa fa-play"></i></span>
                            </span>
                            <span class="w2a-cd-card__content">
                                <strong>{{ $item->title }}</strong>
                                <span>عرض محتويات الإسطوانة <i class="fa fa-angle-left" aria-hidden="true"></i></span>
                            </span>
                        </a>
                    </article>
                @empty
                    <div class="w2a-empty-state w2a-cd-grid__empty">
                        <i class="fa fa-folder-open-o" aria-hidden="true"></i>
                        <strong>لا توجد إسطوانات متاحة حالياً.</strong>
                    </div>
                @endforelse
            </div>
            <div class="w2a-refresh-pagination" aria-label="صفحات الإسطوانات">
                {{ $items->onEachSide(1)->links('components.content.premium-pagination') }}
            </div>
        </x-content.premium-panel>
    </div>
@endsection
