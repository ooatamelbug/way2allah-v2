@extends('layouts.app')

@section('title', 'الجوال الدعوي - '.$heading)

@section('content')
    <x-page-chrome :heading="$heading" :breadcrumb="[['title' => 'قسم الموبايل', 'url' => '/mobiles.htm'], ['title' => $heading, 'url' => '']]" />
    <div class="container-fluid">
        @include('mobile._menu')
        <x-content.premium-panel :title="$heading" icon="fa-list">
            @forelse ($materials as $material)
                @include('mobile._material', ['material' => $material])
            @empty
                <p>لا توجد مواد.</p>
            @endforelse
        </x-content.premium-panel>
    </div>
@endsection
