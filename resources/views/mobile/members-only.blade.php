@extends('layouts.app')

@section('title', 'الجوال الدعوي')

@section('content')
    <x-page-chrome heading="قسم الموبايل" :breadcrumb="[['title' => 'قسم الموبايل', 'url' => '/mobiles.htm']]" />
    <div class="container-fluid">
        <x-content.premium-panel title="للأعضاء المسجلين فقط" icon="fa-lock">
            <p>هذه الخدمة متاحة للأعضاء المسجلين فقط. الرجاء تسجيل الدخول في المنتدى ثم المحاولة مرة أخرى.</p>
            <p><a href="/mobiles.htm">صفحة الموبايل الرئيسية</a></p>
        </x-content.premium-panel>
    </div>
@endsection
