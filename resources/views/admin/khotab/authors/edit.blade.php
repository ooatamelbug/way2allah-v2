@extends('layouts.admin')

@section('title', 'تعديل بيانات الداعية')

@section('content')
    {{-- OLD EditAuthor() + admin_edit_author.htm (modules/Khotab/admin/index.php:2357): the same fields,
         labels and defaults; the image upload of the same page is a normal form here. --}}
    <x-admin-portlet title="تعديل بيانات الداعية" color="green" icon="fa fa-user">
        @include('admin.mirrors._errors')
        <form method="post" action="{{ route('admin.khotab.authors.update', ['author' => $author->id, 'media' => $media]) }}">
            @csrf
            @method('PUT')
            <fieldset><legend>بيانات الداعية</legend>
                <div class="form-group"><label>اللقب :</label>
                    <input name="PreName" class="form-control" value="{{ old('PreName', $author->prename) }}" type="text" maxlength="20"></div>
                <div class="form-group"><label>الاسم :</label>
                    <input name="Name" class="form-control" value="{{ old('Name', $author->name) }}" type="text" maxlength="100"></div>
                <div class="form-group"><label>التعليق :</label>
                    <input name="Des" class="form-control" value="{{ old('Des', $author->des) }}" type="text"></div>
                <div class="form-group"><label>الوصف :</label>
                    <textarea rows="4" name="description" class="form-control">{{ old('description', $author->description) }}</textarea></div>
                <div class="form-group"><label>كود البلوك :</label>
                    <textarea cols="68" rows="10" name="block" class="form-control" dir="ltr">{{ old('block', $author->block) }}</textarea></div>
                <label><input type="checkbox" name="C1" value="1" @checked(old('C1', (int) $author->hidden === 1))> مخفي</label>
                <hr>
                <p><b>SEO :</b></p>
                <textarea cols="68" rows="2" name="meta_keywords" placeholder="Meta Keywords" class="form-control">{{ old('meta_keywords', $author->meta_keywords) }}</textarea>
                <br>
                <textarea cols="68" rows="5" name="meta_description" placeholder="Meta Description" class="form-control">{{ old('meta_description', $author->meta_description) }}</textarea>
                <br>
                <label><input type="checkbox" name="meta_index" value="1" @checked(old('meta_index', ! empty($author->meta_index)))> Index</label><br>
                <label><input type="checkbox" name="meta_follow" value="1" @checked(old('meta_follow', ! empty($author->meta_follow)))> Follow</label>
                <br><br>
                <button type="submit" class="btn green">تعديل</button>
            </fieldset>
        </form>

        <hr>
        <div>
            @if ($image !== null)
                <img src="{{ $image }}" alt="" style="width:200px;border:solid 1px #dedede;padding:10px">
            @endif
            <form method="post" action="{{ route('admin.khotab.authors.image', ['author' => $author->id, 'media' => $media]) }}" enctype="multipart/form-data">
                @csrf
                <br>صورة الداعية الإفتراضية
                <br><input type="file" name="photoimg" accept=".jpg,image/jpeg" required>
                <small>jpg ، أقل من واحد ميجا</small>
                <br><button type="submit" class="btn default">رفع الصورة</button>
            </form>
        </div>
        <p><a href="/khotab-{{ $media }}-{{ $author->id }}.htm" class="btn default">رجوع</a></p>
    </x-admin-portlet>
@endsection
