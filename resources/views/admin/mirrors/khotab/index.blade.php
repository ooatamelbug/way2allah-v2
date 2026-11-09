@extends('layouts.admin')

@section('title', 'الوصلات البديلة - المرئيات')

@section('content')
    {{-- OLD Admin Migration Phase 1. A lookup, not a listing: mirrors are
         administered from their item, as in OLD. The mirror-id lookup also
         reaches a mirror whose item no longer exists, so such a mirror can
         still be edited, moved or deleted (3E.1 I-4) — without this page
         ever listing orphans. --}}
    <x-admin-portlet title="الوصلات البديلة - المرئيات" color="blue" icon="fa fa-link">
        @include('admin.mirrors._errors')
        <form method="get" action="{{ route('admin.mirrors.khotab.index') }}" class="form-inline" style="margin-bottom:15px">
            <label>رقم المادة <input type="number" name="item" min="1" class="form-control"></label>
            <button type="submit" class="btn blue">عرض وصلات المادة</button>
        </form>
        <form method="get" action="{{ route('admin.mirrors.khotab.index') }}" class="form-inline">
            <label>رقم الوصلة <input type="number" name="mirror" min="1" class="form-control"></label>
            <button type="submit" class="btn default">فتح الوصلة</button>
        </form>
    </x-admin-portlet>
@endsection
