@extends('layouts.admin')

@section('title', 'إضافة مكان جديد')

@section('content')
    <form method="post" action="{{ route('admin.locations.store') }}">
        @csrf
        <label>اسم المكان <input type="text" name="name"></label>
        <label>خط طول <input type="text" name="loc_long"></label>
        <label>خط عرض <input type="text" name="loc_lat"></label>
        <label>العنوان <input type="text" name="address"></label>
        <label>الدولة <input type="text" name="country"></label>
        <label>التعليق <textarea name="comment"></textarea></label>
        <label><input type="checkbox" name="virtual" value="1"> مكان وهمي</label>
        <label><input type="checkbox" name="hidden" value="1"> مخفي</label>
        <button type="submit">اضافة</button>
    </form>
@endsection
