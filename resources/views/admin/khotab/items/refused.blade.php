@extends('layouts.admin')

@section('title', $title)

@section('content')
    {{-- An OLD refusal, on its own page as OLD showed it. --}}
    <x-admin-portlet :title="$title" color="red" icon="fa fa-exclamation-triangle">
        <center>
            <b>{{ $item->title }}</b><br><br>
            <b>{{ $message }}</b>
        </center>
        <center><a href="/khotab-item-{{ $item->id }}.htm">( الرجوع إلى صفحة المادة )</a></center>
    </x-admin-portlet>
@endsection
