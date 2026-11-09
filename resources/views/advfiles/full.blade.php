@extends('layouts.app')

@section('title', 'الملف الدعائي'.($item ? ' - '.$item->title : ''))

{{-- khotab-advfilefull-{id}.htm — OLD ShowAdFile($id, 1): the promotional file
     inside the site layout. Rendered through SafeLegacyHtmlRenderer, never raw. --}}
@section('content')
    <x-page-chrome :heading="'الملف الدعائي'.($item ? ' - '.$item->title : '')" :breadcrumb="array_values(array_filter([
        ['title' => 'الملفات الدعائية', 'url' => '/khotab-advfiles.htm'],
        $item ? ['title' => $item->title, 'url' => '/khotab-item-'.$item->id.'.htm'] : null,
        ['title' => 'الملف الدعائي', 'url' => ''],
    ]))" />
    @contextualAdmin
        @include('advfiles._admin')
    @endcontextualAdmin
    <x-content.premium-panel title="الملف الدعائي" icon="fa-bullhorn">
        <div class="w2a-advfile text-center">{!! $html !!}</div>
    </x-content.premium-panel>
@endsection
