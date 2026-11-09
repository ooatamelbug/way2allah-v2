@extends('layouts.admin')

@php($heading = $parent === null ? 'اضافة قارىء بقسم التلاوات' : 'اضافة قسم فرعى')

@section('title', $heading)

@section('content')
    {{-- OLD AddGroup(id) + add_group.htm (modules/Telawah/admin/index.php:439): «الاسم» on Main (id = 0), «العنوان» under a group. --}}
    <x-admin-portlet :title="$parent === null ? $heading : $heading.' إلى '.$parent->title" color="green" icon="fa fa-sitemap">
        @include('admin.mirrors._errors')
        <form method="post" action="{{ $parent === null ? route('admin.telawah.main.groups.store') : route('admin.telawah.groups.subgroups.store', $parent) }}">
            @csrf
            <p><b>{{ $parent === null ? 'الاسم' : 'العنوان' }} :</b>&nbsp;<input type="text" name="Title" value="{{ old('Title') }}" size="40" maxlength="150" required></p>
            <p><b>التعليق :</b>&nbsp;<input type="text" name="Des" value="{{ old('Des') }}" size="48"></p>
            <button type="submit" class="btn green">إضافة</button>
            <a href="{{ $parent === null ? '/recite.htm' : '/recite-group-'.$parent->id.'.htm' }}" class="btn default">رجوع</a>
        </form>
    </x-admin-portlet>
@endsection
