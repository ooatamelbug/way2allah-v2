@extends('layouts.app')

{{-- OLD MoreAnasheed(): «أحدث المواد بقسم …» — pinned items, then the newest; selection is the controller's (visitor parity A1). --}}
@section('title', 'أحدث المواد بقسم ' . $group->title)

@section('content')
    <x-page-chrome :heading="'أحدث المواد بقسم ' . $group->title" :breadcrumb="[
        ['title' => $group->title, 'url' => '/var-group-' . $group->id . '.htm'],
        ['title' => 'أحدث المواد', 'url' => null],
    ]" />

    {{-- UI refresh (owner, 2026-11-04): the site's media cards in two panels; as in OLD ListNews(), an empty list is not shown. --}}
    <div class="w2a-refresh-page w2a-anasheed-news-page">
        @foreach ([
            ['label' => 'المواد المثبتة', 'heading' => 'المواد المثبتة بقسم: ' . $group->title, 'icon' => 'fa-thumb-tack', 'items' => $pinnedItems],
            ['label' => 'المواد الجديدة', 'heading' => 'المواد الجديدة بقسم: ' . $group->title, 'icon' => 'fa-clock-o', 'items' => $newestItems],
        ] as $list)
            @if ($list['items']->isNotEmpty())
                <section class="w2a-refresh-panel w2a-anasheed-news-panel" aria-label="{{ $list['label'] }}">
                    <header class="w2a-refresh-panel__header">
                        <span class="w2a-refresh-panel__icon" aria-hidden="true"><i class="fa {{ $list['icon'] }}"></i></span>
                        <div class="w2a-refresh-panel__heading">
                            <h2>{{ $list['heading'] }}</h2>
                        </div>
                    </header>
                    <div class="w2a-refresh-panel__body">
                        <div class="w2a-media-items-grid">
                            @foreach ($list['items'] as $item)
                                <a href="/var-item-{{ $item->id }}.htm" class="w2a-media-item-card">
                                    <span class="w2a-media-thumb-wrap">
                                        <img src="{{ $item->frameThumbUrl() }}" class="w2a-media-thumb" alt="{{ $item->title }}"
                                            width="320" height="180" loading="lazy" decoding="async">
                                    </span>
                                    <span class="w2a-media-item-body">
                                        <span class="w2a-media-item-title">{{ $item->title }}</span>
                                        @if ((int) $item->mytime > 0)
                                            <span class="w2a-media-item-meta">
                                                <span class="w2a-media-item-hits"><i class="fa fa-calendar" aria-hidden="true"></i>
                                                    <span>{{ \App\Domain\Content\Support\LegacyShortDateFormatter::format((int) $item->mytime) }}</span></span>
                                            </span>
                                        @endif
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endif
        @endforeach

        @if ($pinnedItems->isEmpty() && $newestItems->isEmpty())
            <div class="w2a-tree-empty" role="status">
                <i class="fa fa-info-circle" aria-hidden="true"></i>
                <h5>عفواً، لا توجد مواد مضافة في هذا القسم</h5>
            </div>
        @endif
    </div>
@endsection
