@extends('layouts.admin')

@section('title', 'حذف مجموعة')

@section('content')
    {{-- OLD DeleteGroup()'s confirmation page (modules/Khotab/admin/index.php:2826). --}}
    <x-admin-portlet title="حذف مجموعة" color="red" icon="fa fa-trash-o">
        <form method="post" action="{{ route('admin.khotab.groups.destroy', $group) }}">
            @csrf
            @method('DELETE')
            <center>
                <p><font class="title">هل ترغب في مسح مجموعة '' {{ $group->title }} '' من قاعدة بيانات الموقع????</font></p>
                <button type="submit" class="btn red">إحذف مجموعة</button>
            </center>
        </form>
        <center><a href="javascript:history.go(-1)">( الرجوع للخلف )</a></center>
    </x-admin-portlet>
@endsection
