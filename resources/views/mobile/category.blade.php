@extends('layouts.app')

@section('title', 'الجوال الدعوي - '.$category->title)

@section('content')
    <x-page-chrome :heading="$category->title" :breadcrumb="[['title' => 'قسم الموبايل', 'url' => '/mobiles.htm'], ['title' => $path, 'url' => '']]" />
    <div class="container-fluid">
        @include('mobile._flash')
        @include('mobile._menu')
        @if ($children->isNotEmpty())
            <x-content.premium-panel title="التصنيفات الفرعية" icon="fa-folder-open">
                @foreach ($children as $child)
                    <div><a href="/mobiles-cat{{ $child->cid }}.htm">{{ $child->title }}</a></div>
                @endforeach
            </x-content.premium-panel>
        @endif
        <x-content.premium-panel :title="$category->title" icon="fa-mobile">
            <p>ترتيب المواد حسب:
                @foreach (\App\Domain\Content\Services\Mobile\MobileCatalog::ORDER_LABELS as $key => $label)
                    @if ($key === $order) <strong>{{ $label }}</strong> @else <a href="/mobile{{ $category->cid }}-orderby{{ $key }}.htm">{{ $label }}</a> @endif
                    @if (! $loop->last) | @endif
                @endforeach
            </p>
            @forelse ($materials as $material)
                @include('mobile._material', ['material' => $material])
            @empty
                <p>لا توجد مواد في هذا التصنيف.</p>
            @endforelse
            @if ($materials->lastPage() > 1)
                <p>الصفحات:
                    @for ($p = 1; $p <= $materials->lastPage(); $p++)
                        @if ($p === $materials->currentPage()) <strong>{{ $p }}</strong>
                        @else <a href="/mobile{{ $category->cid }}-{{ ($p - 1) * $perPage }}-{{ $order }}-{{ $perPage }}.htm">{{ $p }}</a>
                        @endif
                    @endfor
                </p>
            @endif
        </x-content.premium-panel>
    </div>
@endsection
