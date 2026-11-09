@extends('layouts.admin')

@section('title', 'إضافة صورة للقسم')

@section('content')
    {{-- OLD GroupIcon() + groupicon.htm (modules/w2acd/admin/index.php): the current icon, then the upload form. --}}
    <x-admin-portlet :title="'إضافة صورة لمجموعة '.$group->title" color="green" icon="fa fa-picture-o">
        @include('admin.mirrors._errors')
        @if ($currentIcon !== null)
            <p align="center"><img src="{{ $currentIcon }}" alt="{{ $group->title }}" style="max-width:150px" data-current-icon></p>
        @endif
        <form method="post" action="{{ route('admin.cds.groups.icon.store', ['cdGroup' => $group->id]) }}" enctype="multipart/form-data" dir="rtl">
            @csrf
            <p><b>اختر ملف الصورة من جهازك</b></p>
            <p><b>ملف الصورة : </b><input type="file" name="file2upl" accept=".gif,.jpg,.jpeg,.png,image/gif,image/jpeg,image/png" required></p>
            <p>امتداد الملفات المسموح بها : gif - jpg - png<br>أقصى حجم مسموح به {{ $maxKb }} كيلو بايت</p>
            <button type="submit" class="btn green">اضف هذا الملف</button>
        </form>
        <center><a href="/cds-group-{{ $group->id }}.htm">( الرجوع للخلف )</a></center>
    </x-admin-portlet>
@endsection
