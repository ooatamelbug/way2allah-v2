@extends('layouts.admin')

@section('title', 'حذف نشيد')

@section('content')
    {{-- OLD DeleteAnasheed()'s confirmation page (deleteanasheed.htm). The safe delete (owner, 2026-10-23)
         also removes the item's own mirrors, comments, technical row and frame image, and releases its categories. --}}
    <x-admin-portlet title="حذف نشيد" color="red" icon="fa fa-trash-o">
        @include('admin.mirrors._errors')
        <form method="post" action="{{ route('admin.anasheed.items.destroy', $item) }}">
            @csrf
            @method('DELETE')
            <center>
                <p><font class="title">هل ترغب في مسح النشيد التالي<br>'' {{ $item->title }} ''????</font></p>
                <p>سيتم أيضا حذف الوصلات البديلة ({{ $mirrors }}) والتعليقات ({{ $comments }}) وصورة المادة الخاصة بهذا النشيد.</p>
                <button type="submit" class="btn red">نعم متأكد</button>
            </center>
        </form>
        <center><a href="/var-item-{{ $item->id }}.htm">( الرجوع للخلف )</a></center>
    </x-admin-portlet>
@endsection
