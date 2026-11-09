@extends('layouts.admin')

@section('title', 'حذف قسم')

@section('content')
    {{-- OLD DeleteGroup()'s confirmation page (delgroup.htm); a refusal (items or, a safety correction, sub-groups) comes back here. --}}
    <x-admin-portlet title="حذف قسم" color="red" icon="fa fa-trash-o">
        @include('admin.mirrors._errors')
        <form method="post" action="{{ route('admin.anasheed.groups.destroy', $group) }}">
            @csrf
            @method('DELETE')
            <center>
                <p><font class="title">هل ترغب في مسح قسم '' {{ $group->title }} '' من قاعدة بيانات الموقع????</font></p>
                <button type="submit" class="btn red">إحذف قسم</button>
            </center>
        </form>
        <center><a href="/var-group-{{ $group->id }}.htm">( الرجوع للخلف )</a></center>
    </x-admin-portlet>
@endsection
