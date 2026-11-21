@extends('layouts.admin')

@section('title', 'إضافة إعلان جديد')

@section('content')
    <x-admin-portlet title="إضافة إعلان جديد" color="green" icon="fa fa-plus">
        @include('admin.mirrors._errors')
        <p><a href="{{ route('admin.ads.index') }}">عودة إلى قائمة الإعلانات</a></p>
        <form method="post" action="{{ route('admin.ads.store') }}" enctype="multipart/form-data">
            @csrf
            @include('admin.ads._form')
            <button type="submit" class="btn green">اضافة</button>
        </form>
    </x-admin-portlet>
@endsection
