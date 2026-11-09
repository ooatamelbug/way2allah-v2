@extends('layouts.admin')

@section('title', 'إضافة قسم فرعي')

@section('content')
    {{-- OLD AddGroup() + addgroup.htm (modules/w2acd/admin/index.php): the same fields and labels; the parent is this group.
         M4 (Phase 3): from cds-main $group is null — a top-level group; OLD's empty «القسم الرئيسي» shows «بدون مجموعة». --}}
    <x-admin-portlet title="إضافة قسم فرعي" color="green" icon="fa fa-sitemap">
        @include('admin.mirrors._errors')
        <form method="post" action="{{ $group ? route('admin.cds.groups.subgroups.store', ['cdGroup' => $group->id]) : route('admin.cds.groups.store') }}">
            @csrf
            <fieldset><legend>بيانات القسم</legend>
                <p><label>عنوان القسم :</label> <input type="text" name="Title" value="{{ old('Title') }}" maxlength="100" required></p>
                <p><label>القسم الرئيسي :</label> <input type="text" value="{{ $group ? $group->title : 'بدون مجموعة' }}" disabled></p>
                <p><label>التعليق على القسم :</label><br><textarea name="Des" rows="3" cols="60">{{ old('Des') }}</textarea></p>
                <small>لا تزيد عن 250 حرف على الأكثر.</small>
            </fieldset>
            <br>
            <button type="submit" class="btn green">أضــف قسم فرعي</button>
        </form>
        <center><a href="{{ $group ? '/cds-group-'.$group->id.'.htm' : '/cds-main.htm' }}">( الرجوع للخلف )</a></center>
    </x-admin-portlet>
@endsection
