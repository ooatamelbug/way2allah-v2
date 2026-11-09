@extends('layouts.admin')

@section('title', 'الوصلات البديلة - التلاوات')

@section('content')
    {{-- Closure correction (2026-10-15) — Telawah mirrors. Lookup, not a
         listing, as the Khotab and Anasheed pages. Edit and delete only:
         OLD disabled Telawah mirror insert itself. --}}
    <x-admin-portlet title="الوصلات البديلة - التلاوات" color="blue" icon="fa fa-link">
        @include('admin.mirrors._errors')
        <form method="get" action="{{ route('admin.mirrors.telawah.index') }}" class="form-inline" style="margin-bottom:15px">
            <label>رقم التلاوة <input type="number" name="item" min="1" class="form-control"></label>
            <button type="submit" class="btn blue">عرض وصلات التلاوة</button>
        </form>
        <form method="get" action="{{ route('admin.mirrors.telawah.index') }}" class="form-inline">
            <label>رقم الوصلة <input type="number" name="mirror" min="1" class="form-control"></label>
            <button type="submit" class="btn default">فتح الوصلة</button>
        </form>
    </x-admin-portlet>
@endsection
