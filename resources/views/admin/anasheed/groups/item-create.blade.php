@extends('layouts.admin')

@section('title', 'اضافة مادة جديدة')

@section('content')
    {{--
        OLD InsertAnasheed() + insertanasheed.htm (the production-matching
        working copy): the same fields, labels and defaults, including
        «نشر علي المنصة» (is_published, checked by default). Under the roots
        57 and 12 OLD offered no author («غير متاح بهذا القسم», posted as 0).
    --}}
    <x-admin-portlet title="اضافة مادة جديدة" color="green" icon="fa fa-plus">
        @include('admin.mirrors._errors')
        <p><b>إضافة مادة جديدة ضمن قسم : </b>{{ $group->title }}</p>
        <form method="post" action="{{ route('admin.anasheed.groups.items.store', $group) }}">
            @csrf
            <p dir="rtl"><b>عنوان المادة : </b><input type="text" name="title" size="48" maxlength="250" value="{{ old('title') }}" required></p>
            <p dir="rtl"><b>نوع المادة :&nbsp;&nbsp;&nbsp; </b>
                <label><input type="radio" value="0" name="vedio" @checked(old('vedio') === '0')>مادة صوتية</label>&nbsp;&nbsp;&nbsp;&nbsp;
                <label><input type="radio" value="1" name="vedio" @checked(old('vedio', '1') === '1')>مادة مرئية</label>
            </p>
            <p dir="rtl"><b>التثبيت بقائمة المزيد :&nbsp;&nbsp;&nbsp; </b>
                <label><input type="radio" value="0" name="fixed" @checked(old('fixed', '0') === '0')>غير مثبتة</label>&nbsp;&nbsp;&nbsp;&nbsp;
                <label><input type="radio" value="1" name="fixed" @checked(old('fixed') === '1')>مثبتة</label>
            </p>
            <p dir="rtl"><b>وصلة المادة : </b><input type="text" name="Anasheedfile" size="88" dir="ltr" value="{{ old('Anasheedfile') }}" required></p>
            @include('admin.anasheed._link-warning', ['link' => old('Anasheedfile')])
            <p dir="rtl"><b>اسم القناة : </b>
                <select name="Channel" size="1">
                    <option value="" @selected(old('Channel', '') === '')>غير مصنف</option>
                    <option value="0" @selected(old('Channel') === '0')>بدون قناة</option>
                    @foreach ($channels as $channel)
                        <option value="{{ $channel->id }}" @selected((string) old('Channel') === (string) $channel->id)>{{ $channel->title }}</option>
                    @endforeach
                </select>
            </p>
            <p dir="rtl"><b>إختر الشيخ : </b>
                @if ($authors === null)
                    غير متاح بهذا القسم<input type="hidden" name="Author" value="0">
                @else
                    <select name="Author" size="1">
                        <option value="" @selected(old('Author', '') === '')>غير مصنف</option>
                        <option value="0" @selected(old('Author') === '0')>بدون شيخ</option>
                        @foreach ($authors as $author)
                            <option value="{{ $author->id }}" @selected((string) old('Author') === (string) $author->id)>{{ $author->name }}</option>
                        @endforeach
                    </select>
                @endif
            </p>
            <p dir="rtl"><b>الحجم بالميجا بايت : </b><input type="text" name="Anasheedsize" size="8" value="{{ old('Anasheedsize') }}"></p>
            <p>التعليق على المادة</p>
            <textarea cols="55" rows="7" name="MoreInfo" maxlength="255" class="form-control">{{ old('MoreInfo') }}</textarea>
            <p><b>وصف المادة :</b></p>
            <textarea cols="68" rows="10" name="description" class="form-control">{{ old('description') }}</textarea>
            <hr>
            <p><b>SEO :</b></p>
            <textarea cols="68" rows="2" name="meta_keywords" placeholder="Meta Keywords" class="form-control">{{ old('meta_keywords') }}</textarea>
            <br>
            <textarea cols="68" rows="5" name="meta_description" placeholder="Meta Description" class="form-control">{{ old('meta_description') }}</textarea>
            <br>
            <label><input type="checkbox" name="meta_index" value="1" @checked(old('meta_index', ! $errors->any()))> Index</label><br>
            <label><input type="checkbox" name="meta_follow" value="1" @checked(old('meta_follow', ! $errors->any()))> Follow</label><br>
            <label><input type="checkbox" name="is_published" value="1" @checked(old('is_published', ! $errors->any()))> نشر علي المنصة</label>
            <br><br>
            <button type="submit" class="btn green">أضف</button>
            <a href="/var-group-{{ $group->id }}.htm" class="btn default">رجوع</a>
        </form>
    </x-admin-portlet>
@endsection
