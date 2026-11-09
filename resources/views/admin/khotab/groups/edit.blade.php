@extends('layouts.admin')

@section('title', 'تعديل مجموعة')

@section('content')
    {{-- OLD EditGroup() (modules/Khotab/admin/index.php:2710): the same fields, labels and defaults. --}}
    <x-admin-portlet title="تعديل مجموعة" color="green" icon="fa fa-folder">
        @include('admin.mirrors._errors')
        <form method="post" action="{{ route('admin.khotab.groups.update', $group) }}">
            @csrf
            @method('PUT')
            <p dir="rtl">
                <b>عنوان المجموعة : </b>
                <input type="text" name="title" value="{{ old('title', $group->title) }}" size="48" maxlength="100">
                <label><input type="checkbox" name="C1" value="1" @checked(old('C1', (int) $group->hidden === 1))> مخفي</label>
            </p>
            <p dir="rtl">
                <b>اسم القناة : </b>
                <select name="Channel" size="1">
                    <option value="0">بدون قناة</option>
                    @foreach ($channels as $channel)
                        <option value="{{ $channel->id }}" @selected((int) old('Channel', $group->channel_id) === (int) $channel->id)>{{ $channel->title }}</option>
                    @endforeach
                </select>
            </p>
            <p><b>وصف المجموعة :</b></p>
            <textarea cols="68" rows="10" name="description" class="form-control">{{ old('description', $group->description) }}</textarea>
            <hr>
            <p><b>SEO :</b></p>
            <textarea cols="68" rows="2" name="meta_keywords" placeholder="Meta Keywords" class="form-control">{{ old('meta_keywords', $group->meta_keywords) }}</textarea>
            <br>
            <textarea cols="68" rows="5" name="meta_description" placeholder="Meta Description" class="form-control">{{ old('meta_description', $group->meta_description) }}</textarea>
            <br>
            <label><input type="checkbox" name="meta_index" value="1" @checked(old('meta_index', ! empty($group->meta_index)))> Index</label><br>
            <label><input type="checkbox" name="meta_follow" value="1" @checked(old('meta_follow', ! empty($group->meta_follow)))> Follow</label>
            <hr>
            <p><b>التعليق :</b></p>
            <textarea cols="68" rows="10" name="Des" class="form-control">{{ old('Des', $group->des) }}</textarea>
            <br>
            <button type="submit" class="btn green">تعديل المجموعة</button>
            <a href="/khotab-group-{{ $group->id }}.htm" class="btn default">رجوع</a>
        </form>
    </x-admin-portlet>
@endsection
