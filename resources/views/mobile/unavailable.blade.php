@extends('layouts.app')

@section('title', 'الملف غير متوفر')

@section('content')
    <x-page-chrome heading="الملف غير متوفر حالياً" :breadcrumb="[['title' => 'قسم الموبايل', 'url' => '/mobiles.htm'], ['title' => $material->title, 'url' => '/mobileview-details-'.$material->lid.'.htm']]" />
    <div class="container-fluid">
        <x-content.premium-panel title="الملف غير متوفر حالياً" icon="fa-exclamation-triangle">
            <p>عذراً، ملف المادة <strong>{{ $material->title }}</strong> غير متوفر حالياً على الموقع.</p>
            <p><a href="/mobile-broken-{{ $material->lid }}.htm">أخبرنا عن وصلة لا تعمل</a> · <a href="/mobileview-details-{{ $material->lid }}.htm">العودة إلى المادة</a></p>
        </x-content.premium-panel>
    </div>
@endsection
