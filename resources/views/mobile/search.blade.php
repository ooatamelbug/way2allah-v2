@extends('layouts.app')

@section('title', 'الجوال الدعوي - بحث')

@section('content')
    <x-page-chrome heading="بحث في قسم الموبايل" :breadcrumb="[['title' => 'قسم الموبايل', 'url' => '/mobiles.htm'], ['title' => 'بحث', 'url' => '']]" />
    <div class="container-fluid">
        @include('mobile._menu')
        @if ($query === '')
            <p>اكتب كلمة للبحث.</p>
        @else
            @if ($categories->isNotEmpty())
                <x-content.premium-panel title="تصنيفات مطابقة" icon="fa-folder">
                    @foreach ($categories as $c)<div><a href="/mobiles-cat{{ $c->cid }}.htm">{{ $c->path() }}</a></div>@endforeach
                </x-content.premium-panel>
            @endif
            <x-content.premium-panel :title="'نتائج البحث عن: '.$query" icon="fa-search">
                @forelse ($materials as $material)
                    @include('mobile._material', ['material' => $material])
                @empty
                    <p>لا يوجد نتائج للبحث</p>
                @endforelse
                @if ($materials->lastPage() > 1)
                    <p>الصفحات:
                        @for ($p = 1; $p <= $materials->lastPage(); $p++)
                            @if ($p === $materials->currentPage()) <strong>{{ $p }}</strong>
                            @else <a href="{{ route('mobile.search', ['query' => $query, 'orderby' => $order, 'show' => $perPage, 'page' => $p]) }}">{{ $p }}</a>
                            @endif
                        @endfor
                    </p>
                @endif
            </x-content.premium-panel>
        @endif
    </div>
@endsection
