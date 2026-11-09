@extends('layouts.admin')

@section('title', 'حذف قسم')

@section('content')
    {{-- OLD DeleteGroup()'s confirmation page (delete_group.htm); a refusal (recitations or, a safety correction, sub-groups) comes back here. --}}
    <x-admin-portlet :title="'حذف قسم '.$group->title" color="red" icon="fa fa-trash-o">
        @include('admin.mirrors._errors')
        <form method="post" action="{{ route('admin.telawah.groups.destroy', $group) }}">
            @csrf
            @method('DELETE')
            <center>
                <p><font class="title">هل ترغب في مسح قسم '' {{ $group->title }} '' من قاعدة بيانات الموقع????</font></p>
                <button type="submit" class="btn red">إحذف القسم</button>
            </center>
        </form>
        <center><a href="/recite-group-{{ $group->id }}.htm">( الرجوع للخلف )</a></center>
    </x-admin-portlet>
@endsection
