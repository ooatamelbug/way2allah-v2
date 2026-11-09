@extends('layouts.admin')

@section('title', 'الملفات الدعائية — البحث عن مادة')

@section('content')
    {{-- OLD Admin Migration Phase 5 — any admin may open an item to request
         a promotional file, as OLD's "طلب رئيسية" allowed. --}}
    <x-admin-portlet title="الملفات الدعائية — البحث عن مادة" color="blue" icon="fa fa-star">
        @include('admin.mirrors._errors')
        <form method="get" action="{{ route('admin.advfiles.lookup') }}" class="form-inline">
            <label>رقم المادة <input type="number" name="item" min="1" class="form-control"></label>
            <button type="submit" class="btn blue">عرض</button>
        </form>
    </x-admin-portlet>
@endsection
