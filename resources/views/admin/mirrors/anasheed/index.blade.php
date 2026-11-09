@extends('layouts.admin')

@section('title', 'الوصلات البديلة - الأناشيد')

@section('content')
    {{-- OLD Admin Migration Phase 1. Lookup, not a listing — see
         admin/mirrors/khotab/index.blade.php. --}}
    <x-admin-portlet title="الوصلات البديلة - الأناشيد" color="blue" icon="fa fa-link">
        @include('admin.mirrors._errors')
        <form method="get" action="{{ route('admin.mirrors.anasheed.index') }}" class="form-inline" style="margin-bottom:15px">
            <label>رقم المادة <input type="number" name="item" min="1" class="form-control"></label>
            <button type="submit" class="btn blue">عرض وصلات المادة</button>
        </form>
        <form method="get" action="{{ route('admin.mirrors.anasheed.index') }}" class="form-inline">
            <label>رقم الوصلة <input type="number" name="mirror" min="1" class="form-control"></label>
            <button type="submit" class="btn default">فتح الوصلة</button>
        </form>
    </x-admin-portlet>
@endsection
