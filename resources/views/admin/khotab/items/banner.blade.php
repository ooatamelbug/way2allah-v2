@extends('layouts.admin')

@section('title', $item->title)

@section('content')
    {{-- OLD Detailes() (admin, modules/Khotab/admin/index.php:873) + admin_add_banner.htm: the banner
         upload form; for an item that has a banner, OLD's warning and the current banner. --}}
    <x-admin-portlet :title="$item->title" color="green" icon="fa fa-picture-o">
        @include('admin.mirrors._errors')
        <form method="post" action="{{ route('admin.khotab.items.banner.upload', ['item' => $item->id]) }}" enctype="multipart/form-data" dir="rtl">
            @csrf
            <font class="title"><b>اختر ملف البانر من جهازك</b></font><br>
            <p><b>ملف البانر : </b>
                <input type="file" name="file2upl" accept=".gif,image/gif" required>
            </p>
            <center>
                امتداد الملفات المسموح بها : gif <br>
                أقصى حجم مسموح به 40 كيلو بايت
            </center>
            <br>
            <button type="submit" class="btn green">اضف هذا الملف</button>
        </form>
        @if ((int) $item->banner === 1)
            <p><b><font color="#FF0000">رفع أي بانر جديد سيؤدي إلى مسح البانر الحالي</font></b></p>
            @if ($current !== null)
                <p align="center"><b>بانر المادة</b><br><img src="{{ $current }}" border="0" alt="{{ $item->title }}"></p>
            @endif
        @endif
        <center><a href="/khotab-item-{{ $item->id }}.htm">( الرجوع للخلف )</a></center>
    </x-admin-portlet>
@endsection
