@extends('layouts.app')

{{-- channels/author.php. IF-012: "Most Downloaded"/"Newest" sidebar boxes
     are deliberately empty here (no equivalent of channels/show.blade.php's
     $mostDownloaded/$mostRecent sections) — legacy never populates them on
     this specific page. The "Recommended For You" (randomitems()) box is
     not reproduced at all — deferred, needs a real content model
     (Wave 4). --}}

@section('title', ($authorRow->prename ?? '') . ' ' . ($authorRow->name ?? '') . ' - قناة ' . $channelModel->title)

@section('content')
    @include('channels._listing', ['showAuthorLinks' => false])

    <aside aria-label="الملف الشخصي">
        {{-- media/authors/sq/{id}.png — a third, non-bucketed media
             convention (IF-013), distinct from MediaPathResolver's
             floor(id/1000) scheme. --}}
        <img src="/media/authors/sq/{{ $author }}.png" alt="">

        <h3>الأكثر تحميلا</h3>
        <h3>جديد المواد</h3>
    </aside>
@endsection
