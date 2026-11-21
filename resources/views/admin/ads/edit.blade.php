@extends('layouts.admin')

@section('title', 'تعديل إعلان')

@section('content')
    <x-admin-portlet :title="'تعديل إعلان #'.(int) $ad->id" color="blue" icon="fa fa-pencil">
        @include('admin.mirrors._errors')
        <p>
            <a href="{{ route('admin.ads.index') }}">عودة إلى قائمة الإعلانات</a>
            &nbsp;·&nbsp; عدد مرات الظهور: {{ (int) $ad->num_view }} &nbsp;·&nbsp; عدد النقرات: {{ (int) $ad->num_click }}
            &nbsp;·&nbsp; الحالة: {{ \App\Domain\Admin\Support\LegacyAds::status($ad) }}
        </p>
        <form method="post" action="{{ route('admin.ads.update', $ad) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('admin.ads._form')
            <button type="submit" class="btn blue">تعديل</button>
        </form>
    </x-admin-portlet>
@endsection
