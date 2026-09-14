@extends('layouts.app')

@section('title', 'أحدث المواد بقسم التلاوات')

@push('styles')
    <link rel="stylesheet" href="/fatawa/css/new-style.css">
    <link href="https://fonts.googleapis.com/css?family=Cairo|Reem+Kufi" rel="stylesheet">
@endpush

@section('content')
    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding flex flex-column gap-5">
            <x-content.premium-panel title="أحدث التلاوات المضافة بالموقع" icon="fa-volume-up">
                @if ($telawahs->isNotEmpty())
                    <div class="w2a-qualities-list">
                        @foreach ($telawahs as $telawah)
                            <article class="w2a-quality-card">
                                <div class="w2a-quality-meta">
                                    <span class="w2a-quality-num" aria-hidden="true">{{ $loop->iteration }}</span>
                                    <div class="w2a-quality-title-wrap">
                                        <a class="w2a-quality-title"
                                            href="/recite-item-{{ $telawah->id }}.htm">{{ $telawah->title }}</a>
                                        <div class="w2a-quality-badges">
                                            <span class="w2a-quality-badge-fmt">MP3</span>
                                            @if (!empty($telawah->group_title))
                                                <span>
                                                    <i class="fa fa-user" aria-hidden="true"></i>
                                                    <a href="/recite-group-{{ $telawah->auth_id }}.htm"
                                                        style="color: inherit; text-decoration: none;">{{ $telawah->group_title }}</a>
                                                </span>
                                            @endif
                                            <span>
                                                <i class="fa fa-eye" aria-hidden="true"></i>
                                                الزيارات:
                                                <span
                                                    style="font-weight: 600;">{{ number_format((int) $telawah->hits) }}</span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="w2a-quality-actions">
                                    <a href="/recite-item-{{ $telawah->id }}.htm" class="w2a-quality-play-btn"
                                        style="text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                                        <i class="fa fa-headphones" aria-hidden="true"></i> استماع
                                    </a>
                                    <a href="/recite-download-{{ $telawah->id }}.htm" class="w2a-quality-down-btn"
                                        style="text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                                        <i class="fa fa-download" aria-hidden="true"></i> تحميل
                                    </a>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="w2a-tree-empty" role="status">
                        <i class="fa fa-info-circle" aria-hidden="true"></i>
                        <h5>عفوا ، لا توجد تلاوات مضافة حالياً</h5>
                    </div>
                @endif
            </x-content.premium-panel>
        </div>

        <aside class="col-lg-3 col-md-4 col-sm-5 nopadding flex flex-column gap-5" aria-label="الشريط الجانبي">
            @if ($mostDownloaded->isNotEmpty())
                <x-content.sidebar-ranking title="الأكثر تحميلاً" icon="fa-cloud-download" :items="$mostDownloaded" type="telawah"
                    meta="downloads" />
            @endif
        </aside>
    </div>
@endsection
