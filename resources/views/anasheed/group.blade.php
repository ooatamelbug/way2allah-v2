@extends('layouts.app')

@section('title', $groupModel->title)

@section('content')
    <div class="row service-box margin-bottom-40">
        @if($subGroups->isNotEmpty())
            <section aria-label="قائمة الأقسام الفرعية">
                <ul>
                    @foreach ($subGroups as $subGroup)
                        <li>
                            <a href="/var-group-{{ $subGroup->id }}.htm">
                                <img src="{{ $subGroup->thumbUrl() }}" alt="{{ $subGroup->title }}">
                                <span>{{ $subGroup->title }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section aria-label="قائمة المواد">
            {{-- G-13-09 (media/visual parity phase): anasheed/functions.php:326-340's
                 list_anasheed() — a raw (non-thumbnails.php) frame image per item,
                 no file_exists() gate (confirmed dead fallback check in source). --}}
            @forelse ($items as $item)
                <div>
                    <a href="/var-item-{{ $item->id }}.htm">
                        <img src="{{ $item->frameThumbUrl() }}" alt="{{ $item->title }}" height="67">
                    </a>
                    <a href="/var-item-{{ $item->id }}.htm">{{ $item->title }}</a>
                </div>
            @empty
                <p>عفوا ، لا يوجد مواد مضافة في هذا القسم</p>
            @endforelse
            {{ $items->links() }}
        </section>

        {{--
            G-13 closure (Anasheed Group Sidebar parity fix): group.php
            (full file read) never calls most_downloaded_recent_sidebar()
            — unlike anasheed/item.blade.php, this page has no "most
            downloaded"/"most recent" sidebar in legacy. Removed.
        --}}

        @if(!empty($groupModel->description))
            <section aria-label="وصف القسم">{{ $groupModel->description }}</section>
        @endif
    </div>
@endsection
