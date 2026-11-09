@extends('layouts.admin')

@section('title', 'حذف مادة')

@section('content')
    {{-- OLD DeleteKhotab() + admin_del_khotab.htm (modules/Khotab/admin/index.php:1717). --}}
    <x-admin-portlet title="حذف مادة" color="red" icon="fa fa-trash-o">
        <form method="post" action="{{ route('admin.khotab.items.destroy', ['item' => $item->id]) }}">
            @csrf
            @method('DELETE')
            <center>
                <p><font class="title">هل ترغب في مسح درس {{ $author->prename ?? '' }} {{ $author->name ?? '' }} بعنوان<br>''{{ $item->title }}''????</font></p>
                <button type="submit" class="btn red">نعم متأكد</button>
            </center>
        </form>
        <center><a href="/khotab-item-{{ $item->id }}.htm">( الرجوع للخلف )</a></center>
    </x-admin-portlet>
@endsection
