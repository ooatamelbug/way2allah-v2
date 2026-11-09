@extends('layouts.app')

@section('title', 'أخبرنا عن وصلة لا تعمل - '.$material->title)

@section('content')
    <x-page-chrome heading="أخبرنا عن وصلة لا تعمل" :breadcrumb="[['title' => 'قسم الموبايل', 'url' => '/mobiles.htm'], ['title' => $material->title, 'url' => '/mobileview-details-'.$material->lid.'.htm'], ['title' => 'وصلة لا تعمل', 'url' => '']]" />
    <div class="container-fluid">
        <x-content.premium-panel title="أخبرنا عن وصلة لا تعمل" icon="fa-chain-broken">
            <p>هل رابط المادة <strong>{{ $material->title }}</strong> لا يعمل؟ سيتحقق منه فريق الموقع.</p>
            <form method="post" action="{{ route('mobile.broken', $material->lid) }}">
                @csrf
                <button type="submit" class="btn btn-warning">أبلغ عن الوصلة</button>
            </form>
        </x-content.premium-panel>
    </div>
@endsection
