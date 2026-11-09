@extends('layouts.admin')

@section('title', 'مسح قسم فرعي')

@section('content')
    {{-- OLD DeleteGroup()'s confirmation page (delgroup.htm); a refusal (CDs or, a safety correction, sub-groups) comes back here. --}}
    <x-admin-portlet title="مسح قسم فرعي" color="red" icon="fa fa-trash-o">
        @include('admin.mirrors._errors')
        <form method="post" action="{{ route('admin.cds.groups.destroy', ['cdGroup' => $group->id]) }}">
            @csrf
            @method('DELETE')
            <center>
                <p><font class="title">هل ترغب في مسح قسم '' {{ $group->title }} '' من قاعدة بيانات الموقع????</font></p>
                <button type="submit" class="btn red">إحذف قسم</button>
            </center>
        </form>
        <center><a href="/cds-group-{{ $group->id }}.htm">( الرجوع للخلف )</a></center>
    </x-admin-portlet>
@endsection
