@extends('layouts.admin')

@section('title', 'إضافة مشرف جديد')

@section('content')
    <form method="post" action="{{ route('admin.staff.store') }}">
        @csrf
        <label>رقم العضو بالمنتدى <input type="text" name="vbuid" required></label>
        <button type="submit">اضف</button>
    </form>
@endsection
