@extends('layouts.admin')

@section('title', 'حذف الداعية')

@section('content')
    {{-- OLD ConfirmDeleteAuthor()'s refusal while any item has author = X (hidden ones included). --}}
    <x-admin-portlet title="حذف الداعية" color="red" icon="fa fa-exclamation-triangle">
        <center>
            <font class="title">عفواً .... لن تتمكن من مسح '' {{ $author->prename }} {{ $author->name }} '' من قاعدة بيانات الموقع
                <br>يجب عليك أولا مسح جميع الخطب و الدروس الخاصة به أولا من الموقع
            </font>
        </center>
        <center><a href="/khotab-{{ $media }}-{{ $author->id }}.htm">( الرجوع للخلف )</a></center>
    </x-admin-portlet>
@endsection
