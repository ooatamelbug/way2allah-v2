@extends('layouts.app')

@section('title', 'التصميمات الدعوية - ' . $albumModel->title)

@section('content')
    <section aria-label="ألبوم الصور">
        <h1>ألبوم : {{ $albumModel->title }}</h1>

        @if($images->isEmpty())
            <p>عفوا! لا يوجد صور مضافة في هذا الالبوم بعد.</p>
        @else
            <div class="row">
                @foreach ($images as $image)
                    <div class="col-lg-3 col-md-3 col-sm-4 col-xs-6">
                        <a href="{{ $image->url }}" class="lightbox" rel="album{{ $albumModel->album_id }}">
                            <img src="{{ $image->url }}" alt="{{ $albumModel->title }}">
                        </a>
                        <a href="/albumimg-download-{{ $image->image_id }}.htm">حفظ الصورة</a>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
@endsection
