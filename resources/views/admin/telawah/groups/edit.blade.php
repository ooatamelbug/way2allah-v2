@extends('layouts.admin')

@section('title', 'تعديل قسم')

@section('content')
    {{-- OLD EditGroup() + group_edit.htm: title and comment only. OLD's hidden parent reset is not reproduced (owner, 2026-10-29). --}}
    <x-admin-portlet :title="'تعديل قسم: '.$group->title" color="green" icon="fa fa-pencil-square-o">
        @include('admin.mirrors._errors')
        <form method="post" action="{{ route('admin.telawah.groups.update', $group) }}">
            @csrf
            @method('PUT')
            <p><b>عنوان القسم : </b><input type="text" name="title" value="{{ old('title', $group->title) }}" size="48" maxlength="150" required></p>
            <p><b>التعليق : </b><input type="text" name="Des" value="{{ old('Des', $group->des) }}" size="48"></p>
            <button type="submit" class="btn green">تعديل القسم</button>
            <a href="/recite-group-{{ $group->id }}.htm" class="btn default">رجوع</a>
        </form>
    </x-admin-portlet>
@endsection
