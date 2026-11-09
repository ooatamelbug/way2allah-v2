@extends('layouts.admin')

@section('title', 'تصنيفات المواد - المرئيات')

@section('content')
    {{-- OLD Admin Migration Phase 3. Lookup, not a listing. --}}
    <x-admin-portlet title="تصنيفات المواد - المرئيات" color="blue" icon="fa fa-tags">
        @include('admin.mirrors._errors')
        <form method="get" action="{{ route('admin.categories.khotab.index') }}" class="form-inline" style="margin-bottom:15px">
            <label>رقم المادة <input type="number" name="item" min="1" class="form-control"></label>
            <button type="submit" class="btn blue">تصنيفات المادة</button>
        </form>
        <form method="get" action="{{ route('admin.categories.khotab.index') }}" class="form-inline" style="margin-bottom:15px">
            <label>رقم السلسلة <input type="number" name="series" min="1" class="form-control"></label>
            <button type="submit" class="btn default">تصنيفات السلسلة</button>
        </form>
        <form method="get" action="{{ route('admin.categories.khotab.index') }}" class="form-inline">
            <label>رقم المجموعة <input type="number" name="group" min="1" class="form-control"></label>
            <button type="submit" class="btn default">تصنيفات المجموعة</button>
        </form>
    </x-admin-portlet>
@endsection
