@extends('layouts.admin')

@section('title', 'إضافة سلسلة')

@section('content')
    {{-- OLD InsertSeries() + admin_add_series.htm from a group page: group and channel fixed (shown disabled), Index/Follow ticked. --}}
    <x-admin-portlet :title="'إضافة سلسلة لـ'.trim(($author->prename ?? '').' '.($author->name ?? ''))" color="green" icon="fa fa-folder-open-o">
        @include('admin.mirrors._errors')
        <form method="post" action="{{ route('admin.khotab.groups.series.store', $group) }}">
            @csrf
            <p dir="rtl"><b>عنوان السلسلة : </b><input type="text" name="title" value="{{ old('title') }}" size="48" maxlength="35"></p>
            <p dir="rtl"><b>المجموعة : </b>
                <select disabled="disabled"><option selected>{{ $group->title }}</option></select>
            </p>
            <p dir="rtl"><b>اسم القناة : </b>
                <select disabled="disabled"><option selected>{{ $channel->title ?? 'بدون قناة' }}</option></select>
            </p>
            <p><b>وصف السلسلة :</b></p>
            <textarea cols="68" rows="10" name="description" class="form-control">{{ old('description') }}</textarea>
            <hr>
            <p><b>SEO :</b></p>
            <textarea cols="68" rows="2" name="meta_keywords" placeholder="Meta Keywords" class="form-control">{{ old('meta_keywords') }}</textarea>
            <br>
            <textarea cols="68" rows="5" name="meta_description" placeholder="Meta Description" class="form-control">{{ old('meta_description') }}</textarea>
            <br>
            <label><input type="checkbox" name="meta_index" value="1" @checked(old('meta_index', ! $errors->any()))> Index</label><br>
            <label><input type="checkbox" name="meta_follow" value="1" @checked(old('meta_follow', ! $errors->any()))> Follow</label>
            <hr>
            <p><b>التعليق :</b></p>
            <textarea cols="68" rows="10" name="MoreInfo" class="form-control">{{ old('MoreInfo') }}</textarea>
            <br>
            <center><button type="submit" class="btn green">أضف</button></center>
        </form>
        <p><a href="/khotab-group-{{ $group->id }}.htm" class="btn default">رجوع</a></p>
    </x-admin-portlet>
@endsection
