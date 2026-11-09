@extends('layouts.admin')

@section('title', 'إضافة صورة للقسم')

@section('content')
    {{-- OLD GroupIcon() + groupicon.htm (modules/Anasheed/admin/index.php): the current icon, then the upload form. --}}
    <x-admin-portlet :title="'إضافة صورة لمجموعة '.$group->title" color="green" icon="fa fa-picture-o">
        @include('admin.mirrors._errors')
        @if ($currentIcon !== null)
            <p><img src="{{ $currentIcon }}" alt="{{ $group->title }}" style="max-width:150px"></p>
        @endif
        <form method="post" action="{{ route('admin.anasheed.groups.icon.store', $group) }}" enctype="multipart/form-data">
            @csrf
            <p><b>اختر ملف الصورة من جهازك</b></p>
            <p dir="rtl"><b>ملف الصورة : </b><input type="file" name="icon" accept=".jpg,image/jpeg" required></p>
            <p>امتداد الملفات المسموح بها : jpg<br>أقصى حجم مسموح به {{ $maxKb }} كيلو بايت</p>
            <button type="submit" class="btn green">اضف هذا الملف</button>
            <a href="/var-group-{{ $group->id }}.htm" class="btn default">رجوع</a>
        </form>
    </x-admin-portlet>
@endsection
