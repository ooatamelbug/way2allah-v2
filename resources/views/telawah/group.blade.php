@extends('layouts.app')

@section('title', 'قسم التلاوات - ' . $groupModel->title)

@section('content')
    {{--
        G-13-07 (media/visual parity phase): `telawah/functions.php:164`'s
        `list_telawat_groups($id)` (`group.php:34`) — same hardcoded
        `images/telawah.gif` as authors.blade.php's own listing, now
        reachable via the real `public/images` symlink (G-13-02).
    --}}
    @if($subGroups->isNotEmpty())
        <section aria-label="قائمة الأقسام الفرعية">
            <x-content.reciter-directory :groups="$subGroups" subgroups />
        </section>
    @endif

    <section class="portlet box blue" aria-label="قائمة السور القرآنية">
        <div class="portlet-title">
            <div class="caption"><i class="fa fa-headphones"></i> قائمة السور القرآنية</div>
        </div>
        <div class="portlet-body">
            <x-content.telawah-track-list :items="$items" />
        </div>
    </section>

    <x-content.media-player-panel />
    <x-content.media-player-script />
@endsection
