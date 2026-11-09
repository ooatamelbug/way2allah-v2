@extends('layouts.admin')

@section('title', 'حذف الداعية')

@section('content')
    {{-- OLD DeleteAuthor() (modules/Khotab/admin/index.php:2486). --}}
    <x-admin-portlet title="حذف الداعية" color="red" icon="fa fa-trash-o">
        <form method="post" action="{{ route('admin.khotab.authors.destroy', ['author' => $author->id, 'media' => $media]) }}">
            @csrf
            @method('DELETE')
            <center>
                <p><font class="title">هل ترغب في مسح '' {{ $author->prename }} {{ $author->name }} '' من قاعدة بيانات الموقع????</font></p>
                <button type="submit" class="btn red">نـعـم مـتـأكـد</button>
            </center>
        </form>
        <center><a href="/khotab-{{ $media }}-{{ $author->id }}.htm">( الرجوع للخلف )</a></center>
    </x-admin-portlet>
@endsection
