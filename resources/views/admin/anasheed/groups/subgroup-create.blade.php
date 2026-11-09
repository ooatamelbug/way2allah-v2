@extends('layouts.admin')

@section('title', 'إضافة قسم فرعي')

@section('content')
    {{-- OLD AddGroup() + addgroup.htm (modules/Anasheed/admin/index.php): the same fields, labels and defaults. --}}
    <x-admin-portlet :title="'إضافة قسم فرعي إلى '.$group->title" color="green" icon="fa fa-sitemap">
        @include('admin.mirrors._errors')
        <form method="post" action="{{ route('admin.anasheed.groups.subgroups.store', $group) }}">
            @csrf
            <p><b>العنوان</b>&nbsp;<input type="text" name="Title" value="{{ old('Title') }}" size="40" maxlength="100" required></p>
            <p><b>اسم القناة : </b>
                <select name="Channel" size="1">
                    <option value="0">بدون قناة</option>
                    @foreach ($channels as $channel)
                        <option value="{{ $channel->id }}" @selected((int) old('Channel') === (int) $channel->id)>{{ $channel->title }}</option>
                    @endforeach
                </select>
            </p>
            <p><b>التعليق :</b><input type="text" name="Des" value="{{ old('Des') }}" size="48"></p>
            <p><b>الوصف :</b></p>
            <textarea cols="68" rows="10" name="description" class="form-control">{{ old('description') }}</textarea>
            <hr>
            <p><b>SEO :</b></p>
            <textarea cols="68" rows="2" name="meta_keywords" placeholder="Meta Keywords" class="form-control">{{ old('meta_keywords') }}</textarea>
            <br>
            <textarea cols="68" rows="5" name="meta_description" placeholder="Meta Description" class="form-control">{{ old('meta_description') }}</textarea>
            <br>
            <label><input type="checkbox" name="meta_index" value="1" @checked(old('meta_index', ! $errors->any()))> Index</label><br>
            <label><input type="checkbox" name="meta_follow" value="1" @checked(old('meta_follow', ! $errors->any()))> Follow</label>
            <br><br>
            <button type="submit" class="btn green">أضــف قسم فرعي</button>
            <a href="/var-group-{{ $group->id }}.htm" class="btn default">رجوع</a>
        </form>
    </x-admin-portlet>
@endsection
