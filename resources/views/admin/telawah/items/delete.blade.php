@extends('layouts.admin')

@section('title', 'حذف تلاوة')

@section('content')
    {{-- OLD DeleteTelawah()'s confirmation page (delete_telawa.htm). The safe delete (owner, 2026-10-29)
         also removes the recitation's own mirrors, comments and technical row. --}}
    <x-admin-portlet :title="'حذف تلاوة '.$item->title" color="red" icon="fa fa-trash-o">
        @include('admin.mirrors._errors')
        <form method="post" action="{{ route('admin.telawah.items.destroy', $item) }}">
            @csrf
            @method('DELETE')
            <center>
                <p><font class="title">هل ترغب في مسح التلاوة التالي<br>'' {{ $item->title }} ''????</font></p>
                <p>سيتم أيضا حذف الوصلات البديلة ({{ $mirrors }}) والتعليقات ({{ $comments }}) الخاصة بهذه التلاوة.</p>
                <button type="submit" class="btn red">نعم متأكد</button>
            </center>
        </form>
        <center><a href="/recite-item-{{ $item->id }}.htm">( الرجوع للخلف )</a></center>
    </x-admin-portlet>
@endsection
