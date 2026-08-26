@extends('layouts.admin')

@section('title', 'تعديل: '.$location->title)

@section('content')
    {{--
        Same portlet reconstruction as locations/create.blade.php — see
        that file and `resources/views/components/admin-location-map.blade.php`
        for the map-behavior/owner-decision reasoning (AdminCP Locations
        Map — Owner Decision Resolution, 2026-08-22). Unlike legacy's own
        `edit.php` (whose map always centers on a hardcoded default,
        never the location being edited — a confirmed bug, not
        reproduced), this page's map centers on and marks the location's
        actual stored coordinates.
    --}}
    <x-admin-portlet title="بيانات الموقع" color="blue" icon="fa fa-folder-open" width="4">
        <form method="post" action="{{ route('admin.locations.update', $location) }}">
            @csrf
            @method('PUT')
            <label>اسم المكان <input type="text" name="name" value="{{ $location->title }}" class="form-control"></label>
            <label>خط طول <input type="text" id="loc_long" name="loc_long" value="{{ $location->lng }}" class="form-control"></label>
            <label>خط عرض <input type="text" id="loc_lat" name="loc_lat" value="{{ $location->lat }}" class="form-control"></label>
            <label>العنوان <input type="text" name="address" value="{{ $location->address }}" class="form-control"></label>
            <label>الدولة <input type="text" name="country" value="{{ $location->country }}" class="form-control"></label>
            <label>التعليق <textarea name="comment" class="form-control">{{ $location->des }}</textarea></label>
            <label><input type="checkbox" name="virtual" value="1" @checked($location->type == 2)> مكان وهمي</label>
            <label><input type="checkbox" name="hidden" value="1" @checked($location->hidden == 1)> مخفي</label>
            <button type="submit" class="btn green">تعديل</button>
        </form>
    </x-admin-portlet>

    <x-admin-portlet title="الخريطة" color="blue" icon="fa fa-folder-open" width="8">
        <x-admin-location-map :lat="$location->lat" :lng="$location->lng" />
    </x-admin-portlet>
@endsection
