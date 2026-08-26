@extends('layouts.admin')

@section('title', 'إضافة مكان جديد')

@section('content')
    {{--
        `open_div('بيانات الموقع', 'blue', '', 4)` / `open_div('الخريطة',
        'blue', '', 8)` (AdminCP Locations Map — Owner Decision Resolution,
        2026-08-22). See `resources/views/components/admin-location-map.blade.php`
        for the full map-behavior/owner-decision reasoning. Laravel's own
        `admin.locations.store` action already saves for real — the map is
        being added to a genuinely working save flow, not resurrecting
        legacy `add.php`'s own confirmed-dead (commented-out) INSERT.
    --}}
    <x-admin-portlet title="بيانات الموقع" color="blue" icon="fa fa-folder-open" width="4">
        <form method="post" action="{{ route('admin.locations.store') }}">
            @csrf
            <label>اسم المكان <input type="text" name="name" class="form-control"></label>
            <label>خط طول <input type="text" id="loc_long" name="loc_long" placeholder="اضغط على الخريطة لتعبئة الرقم" class="form-control"></label>
            <label>خط عرض <input type="text" id="loc_lat" name="loc_lat" placeholder="اضغط على الخريطة لتعبئة الرقم" class="form-control"></label>
            <label>العنوان <input type="text" name="address" class="form-control"></label>
            <label>الدولة <input type="text" name="country" class="form-control"></label>
            <label>التعليق <textarea name="comment" class="form-control"></textarea></label>
            <label><input type="checkbox" name="virtual" value="1"> مكان وهمي</label>
            <label><input type="checkbox" name="hidden" value="1"> مخفي</label>
            <button type="submit" class="btn green">اضافة</button>
        </form>
    </x-admin-portlet>

    <x-admin-portlet title="الخريطة" color="blue" icon="fa fa-folder-open" width="8">
        <x-admin-location-map />
    </x-admin-portlet>
@endsection
