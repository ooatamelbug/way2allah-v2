@extends('layouts.app')

@section('title', 'طلب تعديل مادة - '.$material->title)

@section('content')
    <x-page-chrome heading="طلب تعديل مادة" :breadcrumb="[['title' => 'قسم الموبايل', 'url' => '/mobiles.htm'], ['title' => $material->title, 'url' => '/mobileview-details-'.$material->lid.'.htm'], ['title' => 'طلب تعديل مادة', 'url' => '']]" />
    <div class="container-fluid">
        @include('mobile._flash')
        <x-content.premium-panel title="طلب تعديل مادة" icon="fa-edit" description="عدّل البيانات المقترحة، وسيراجعها فريق الموقع.">
            <form method="post" action="{{ route('mobile.correction', $material->lid) }}">
                @csrf
                @include('mobile._fields', ['values' => $material->only(['cid', 'title', 'url', 'description', 'name', 'email', 'filesize', 'version', 'homepage'])])
                <button type="submit" class="btn btn-primary">أرسل طلب التعديل</button>
            </form>
        </x-content.premium-panel>
    </div>
@endsection
