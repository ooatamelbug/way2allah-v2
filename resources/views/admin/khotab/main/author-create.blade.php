@extends('layouts.admin')

@section('title', 'إضافة شيخ')

@section('content')
    {{-- OLD InsertAuthor() + insert_author.htm (modules/Khotab/admin/index.php:2303): the same fields,
         labels and defaults. OLD's «صورة للشيخ» field was never read by ConfirmInsertAuthor and is not
         offered; the image is uploaded from «تعديل بيانات الداعية». --}}
    <x-admin-portlet title="إضافة شيخ إلى قاعدة البيانات" color="green" icon="fa fa-user-plus">
        @include('admin.mirrors._errors')
        <form method="post" action="{{ route('admin.khotab.main.authors.store', ['media' => $media]) }}">
            @csrf
            <p dir="rtl"><b>اللقب</b>&nbsp;<input type="text" name="PreName" value="{{ old('PreName', 'الشيخ') }}" size="8" maxlength="20"></p>
            <p dir="rtl"><b>الاسم</b>&nbsp;<input type="text" name="Name" value="{{ old('Name') }}" size="40" maxlength="100"></p>
            <p><b>الوصف :</b></p>
            <textarea cols="68" rows="10" name="description" class="form-control">{{ old('description') }}</textarea>
            <p dir="rtl"><b>التعليق</b>&nbsp;<input type="text" name="Des" value="{{ old('Des') }}" size="70" maxlength="256"></p>
            <p>كود البلوك</p>
            <textarea cols="68" rows="25" name="block" class="form-control" dir="ltr">{{ old('block') }}</textarea>
            <hr>
            <p><b>SEO :</b></p>
            <textarea cols="68" rows="2" name="meta_keywords" placeholder="Meta Keywords" class="form-control">{{ old('meta_keywords') }}</textarea>
            <br>
            <textarea cols="68" rows="5" name="meta_description" placeholder="Meta Description" class="form-control">{{ old('meta_description') }}</textarea>
            <br>
            <label><input type="checkbox" name="meta_index" value="1" @checked(old('meta_index', ! $errors->any()))> Index</label><br>
            <label><input type="checkbox" name="meta_follow" value="1" @checked(old('meta_follow', ! $errors->any()))> Follow</label>
            <br>
            <center><button type="submit" class="btn green">أضــف الآن</button></center>
        </form>
        <p><a href="/khotab-{{ $media }}.htm" class="btn default">رجوع</a></p>
    </x-admin-portlet>
@endsection
