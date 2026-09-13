@extends('layouts.app')

@section('title', $pageTitle)

@section('content')
    <x-page-chrome :heading="$pageTitle" :breadcrumb="[
        ['title' => $opTitle, 'url' => '/khotab-' . $op . '.htm'],
        ['title' => 'قائمة الدعاة', 'url' => '/khotab-' . $op . '.htm'],
        ['title' => trim(($authorModel->prename ?? '') . ' ' . ($authorModel->name ?? '')), 'url' => ''],
    ]" />

    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding flex flex-column gap-5">
            @if ($op !== 'pdf')
                <x-content.premium-panel title="قائمة المجموعات" icon="fa-folder">
                    <x-content.media-collection-grid :items="$groups" type="group" secondary="channel" />
                </x-content.premium-panel>

                <x-content.premium-panel title="قائمة السلاسل" icon="fa-list-ol">
                    <x-content.media-collection-grid :items="$series" secondary="channel" />
                </x-content.premium-panel>
            @endif

            <x-content.premium-panel title="قائمة المواد" icon="fa-play-circle">
                <x-content.khotab-item-list :items="$items" :video="$op === 'video'" :pdf="$op === 'pdf'" />
            </x-content.premium-panel>

            @if (!empty($authorModel->description))
                <x-content.premium-panel title="نبذة عن الداعية" icon="fa-user">
                    <section aria-label="نبذة عن الداعية">{{ $authorModel->description }}</section>
                </x-content.premium-panel>
            @endif
        </div>

        <aside class="col-lg-3 col-md-4 col-sm-5 nopadding flex flex-column gap-5" aria-label="الشريط الجانبي">
            <section class="w2a-refresh-panel w2a-author-profile-card">
                <img class="w2a-author-avatar" src="{{ $authorModel->displayImageUrl() }}" alt="{{ $authorModel->name }}">
                <h3 class="w2a-author-name">{{ $authorModel->prename }} {{ $authorModel->name }}</h3>
            </section>

            @if ($op === 'video')
                <a href="/khotab-video-{{ $authorModel->id }}.htm" class="w2a-author-banner w2a-author-banner--video">
                    <i class="fa fa-video-camera" aria-hidden="true"></i>
                    <span>مرئيات الداعية</span>
                </a>
            @elseif($op === 'audio')
                <a href="/khotab-audio-{{ $authorModel->id }}.htm" class="w2a-author-banner w2a-author-banner--audio">
                    <i class="fa fa-volume-up" aria-hidden="true"></i>
                    <span>صوتيات الداعية</span>
                </a>
            @endif

            <x-content.premium-panel title="اخترنا لك هذه المادة" icon="fa-star">
                <x-content.featured-items :items="$randomFeatured" />
            </x-content.premium-panel>

            <x-content.sidebar-ranking title="الأكثر تحميلاً" icon="fa-fire" :items="$mostDownloaded" type="khotab"
                meta="downloads" />

            <x-content.sidebar-ranking title="جديد المواد" icon="fa-history" :items="$mostRecent" type="khotab"
                meta="recent" />
        </aside>
    </div>
@endsection
