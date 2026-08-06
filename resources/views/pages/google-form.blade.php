@extends('layouts.app')

{{-- Shared template for the 3 structurally-identical embedded-Google-Form
     pages (pages.md §5: "differing only in title, embedded form URL, and
     iframe height" — the shared shape, not shared business logic, is what
     justifies one template; each page still gets its own route/controller,
     per the no-op=-dispatch routing principle). $formUrl and $iframeHeight
     are supplied per-page by each controller, copied verbatim from the
     corresponding legacy file. --}}

@section('title', $title)

@section('content')
    <div class="row service-box margin-bottom-40 sh-w2a-block">
        <div class="col-xs-12 col-sm-12 col-md-12 telawah-item-content nopadding">
            <div class="portlet-body series-overflow series-overflow-auto text-center">
                <h4 class="text-center">{{ $heading }}</h4>
                <iframe src="{{ $formUrl }}" width="100%"
                        height="{{ $iframeHeight }}" frameborder="0" marginheight="0" marginwidth="0">جارٍ التحميل…</iframe>
            </div>
        </div>
    </div>
@endsection
