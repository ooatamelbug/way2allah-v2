@extends('layouts.admin')

@section('title', 'تعديل: '.$location->title)

@section('content')
    <form method="post" action="{{ route('admin.locations.update', $location) }}">
        @csrf
        @method('PUT')
        <label>اسم المكان <input type="text" name="name" value="{{ $location->title }}"></label>
        <label>خط طول <input type="text" name="loc_long" value="{{ $location->lng }}"></label>
        <label>خط عرض <input type="text" name="loc_lat" value="{{ $location->lat }}"></label>
        <label>العنوان <input type="text" name="address" value="{{ $location->address }}"></label>
        <label>الدولة <input type="text" name="country" value="{{ $location->country }}"></label>
        <label>التعليق <textarea name="comment">{{ $location->des }}</textarea></label>
        <label><input type="checkbox" name="virtual" value="1" @checked($location->type == 2)> مكان وهمي</label>
        <label><input type="checkbox" name="hidden" value="1" @checked($location->hidden == 1)> مخفي</label>
        <button type="submit">تعديل</button>
    </form>
@endsection
