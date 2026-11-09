@extends('layouts.app')

@section('title', 'رأي المحررين - '.$material->title)

@section('content')
    <x-page-chrome :heading="'رأي المحررين: '.$material->title" :breadcrumb="[['title' => 'قسم الموبايل', 'url' => '/mobiles.htm'], ['title' => $material->title, 'url' => '/mobileview-details-'.$material->lid.'.htm'], ['title' => 'رأي المحررين', 'url' => '']]" />
    <div class="container-fluid">
        <x-content.premium-panel title="رأي المحررين" icon="fa-pencil">
            @if ($editorial)
                <h4>{{ $editorial->editorialtitle }}</h4>
                <p class="text-muted">{{ $editorial->editorialtimestamp }}</p>
                <div style="white-space:pre-line">{{ \App\Domain\Content\Services\Mobile\MobileSite::plainText($editorial->editorialtext) }}</div>
            @else
                <p>لا يوجد رأي للمحررين في هذه المادة.</p>
            @endif
        </x-content.premium-panel>
    </div>
@endsection
