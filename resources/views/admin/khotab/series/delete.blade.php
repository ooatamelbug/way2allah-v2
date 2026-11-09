@extends('layouts.admin')

@section('title', 'حذف سلسلة')

@section('content')
    {{-- OLD DeleteSeries() + admin_del_series.htm (modules/Khotab/admin/index.php:2223). --}}
    <x-admin-portlet title="حذف سلسلة" color="red" icon="fa fa-trash-o">
        <form method="post" action="{{ route('admin.khotab.series.destroy', $series) }}">
            @csrf
            @method('DELETE')
            <center>
                <p><font class="title">هل ترغب في مسح سلسلة {{ $author->prename ?? '' }} {{ $author->name ?? '' }} بعنوان<br>''{{ $series->title }}''????</font></p>
                <button type="submit" class="btn red">نعم متأكد</button>
            </center>
        </form>
        <center><a href="javascript:history.go(-1)">( الرجوع للخلف )</a></center>
    </x-admin-portlet>
@endsection
