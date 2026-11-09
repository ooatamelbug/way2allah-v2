@extends('layouts.admin')

@section('title', 'تعديل تصنيف')

@section('content')
    <x-admin-portlet :title="'تعديل تصنيف: '.$path" color="blue" icon="fa fa-pencil">
        @include('admin.mirrors._errors')
        <form method="post" action="{{ route('admin.mobile.categories.update', $category->cid) }}">
            @csrf
            @method('PUT')
            <label style="display:block">الاسم <input type="text" name="title" value="{{ old('title', $category->title) }}" maxlength="50" required class="form-control"></label>
            <label style="display:block">الوصف <textarea name="cdescription" rows="4" class="form-control">{{ old('cdescription', $category->cdescription) }}</textarea></label>
            <button type="submit" class="btn blue">حفظ</button>
            <a href="{{ route('admin.mobile.categories') }}" class="btn default">رجوع</a>
        </form>
    </x-admin-portlet>
@endsection
