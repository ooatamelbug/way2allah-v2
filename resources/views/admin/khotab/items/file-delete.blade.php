@extends('layouts.admin')

@section('title', 'حذف مادة :'.$item->title)

@section('content')
    {{-- OLD KhotabDeletePdf / KhotabDeletedocx (admin_delete_pdf.htm / admin_delete_docx.htm); OLD's
         «نعم» was a GET link, here it is a form. --}}
    <x-admin-portlet :title="'حذف مادة :'.$item->title" color="red" icon="fa fa-trash-o">
        <form method="post" action="{{ route('admin.khotab.items.'.$kind.'.destroy', ['item' => $item->id]) }}">
            @csrf
            @method('DELETE')
            <center><span style="font-weight:bold; color:#936">هل أنت متأكد من أنك تريد حذف ملف {{ $kind === 'docx' ? 'الوورد' : 'التفريغ' }} لهذه المادة؟</span></center><br>
            <div align="center">
                <button type="submit" class="btn red">نعم</button>
                <a href="/khotab-item-{{ $item->id }}.htm" class="btn default">لا</a>
            </div>
        </form>
    </x-admin-portlet>
@endsection
