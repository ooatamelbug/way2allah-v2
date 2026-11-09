@extends('layouts.admin')

@section('title', 'تصنيفات المواد - الأناشيد')

@section('content')
    {{-- OLD Admin Migration Phase 3. Lookup, not a listing. --}}
    <x-admin-portlet title="تصنيفات المواد - الأناشيد" color="blue" icon="fa fa-tags">
        @include('admin.mirrors._errors')
        <form method="get" action="{{ route('admin.categories.anasheed.index') }}" class="form-inline">
            <label>رقم النشيد <input type="number" name="item" min="1" class="form-control"></label>
            <button type="submit" class="btn blue">تصنيفات النشيد</button>
        </form>
    </x-admin-portlet>
@endsection
