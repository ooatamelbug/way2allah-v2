@php
    $word = $kind === 'docx';
    $label = $word ? 'وورد' : 'تفريغ';
@endphp
@extends('layouts.admin')

@section('title', $item->title)

@section('content')
    {{-- OLD UploadPdf / KhotabEditPdf (admin_add_pdf.htm / admin_edit_pdf.htm) and Uploaddocx /
         KhotabEditdocx (admin_add_docx.htm / admin_edit_docx.htm), modules/Khotab/admin/index.php. --}}
    <x-admin-portlet :title="$item->title" color="green" :icon="$word ? 'fa fa-file-word-o' : 'fa fa-file-pdf-o'">
        @include('admin.mirrors._errors')
        @if ($replacing)
            <div align="center" style="font-size:18px; font-weight:bold; color:#930">سيتم حذف الملف القديم إذا قمت برفع ملف أخر.</div>
        @endif
        <form method="post" action="{{ route('admin.khotab.items.'.$kind.'.upload', ['item' => $item->id]) }}" enctype="multipart/form-data" dir="rtl">
            @csrf
            <center>
                <font class="title">رفع ملف {{ $label }} للمادة : {{ $item->title }}</font><br>
                <b><font class="title">لـ{{ $author->prename ?? '' }} {{ $author->name ?? '' }}</font></b>
            </center>
            <br>
            <font class="title"><b>اختر ملف ال{{ $label }} من جهازك</b></font><br>
            <p><b>ملف ال{{ $label }} : </b>
                <input type="file" name="file2upl" accept="{{ $word ? '.docx' : '.pdf,application/pdf' }}" required>
            </p>
            {{-- OLD's text said 500 KB; OLD's own check allowed 15,000,000 bytes, which this enforces. --}}
            <center>
                امتداد الملفات المسموح بها : {{ $kind }}<br>
                أقصى حجم مسموح به 15 ميجا بايت
            </center>
            <br>
            <center><button type="submit" class="btn green">اضف هذا الملف</button></center>
        </form>
        <center><a href="/khotab-item-{{ $item->id }}.htm">( الرجوع للخلف )</a></center>
    </x-admin-portlet>
@endsection
