@extends('layouts.admin')

@section('title', 'إضافة مجموعة')

@section('content')
    {{-- OLD AddGroup(id, vedio) (modules/Khotab/admin/index.php:2581): the same fields, labels and defaults. --}}
    <x-admin-portlet :title="'إضافة مجموعة إلى '.trim(($author->prename ?? '').' '.($author->name ?? ''))" color="green" icon="fa fa-folder">
        @include('admin.mirrors._errors')
        <form method="post" action="{{ route('admin.khotab.authors.groups.store', ['author' => $author->id, 'media' => $media]) }}">
            @csrf
            <p dir="rtl"><b>العنوان</b>&nbsp;<input type="text" name="Title" value="{{ old('Title') }}" size="40" maxlength="100"></p>
            <p dir="rtl"><b>اسم القناة :</b>
                <select name="Channel" size="1">
                    <option value="0">بدون قناة</option>
                    @foreach ($channels as $channel)
                        <option value="{{ $channel->id }}" @selected((int) old('Channel') === (int) $channel->id)>{{ $channel->title }}</option>
                    @endforeach
                </select>
            </p>
            <p><b>وصف المجموعة :</b></p>
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
            <textarea cols="68" rows="10" name="Des" class="form-control">{{ old('Des') }}</textarea>
            <br>
            <center><button type="submit" class="btn green">أضــف مجموعة</button></center>
        </form>
        <p><a href="/khotab-{{ $media }}-{{ $author->id }}.htm" class="btn default">رجوع</a></p>
    </x-admin-portlet>
@endsection
