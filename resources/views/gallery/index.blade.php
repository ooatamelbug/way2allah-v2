@extends('layouts.app')

@section('title', 'التصميمات الدعوية')

@section('content')
    <section aria-label="التصميمات الدعوية">
        <div class="row">
            @foreach ($albums as $album)
                <div class="col-lg-4 col-md-6 col-sm-6 col-xs-12">
                    <a href="/gallery-{{ $album->album_id }}.htm">
                        <img src="{{ optional($album->thumbnailImage())->url }}" alt="{{ $album->title }}">
                        <h5>{{ $album->title }}</h5>
                    </a>
                    <span>{{ $album->count }} صورة</span>
                </div>
            @endforeach
        </div>
    </section>
@endsection
