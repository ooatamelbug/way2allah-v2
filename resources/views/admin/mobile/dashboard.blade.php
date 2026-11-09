@extends('layouts.admin')

@section('title', 'قسم الموبايل')

@section('content')
    <x-admin-portlet title="قسم الموبايل" color="blue" icon="fa fa-mobile">
        @include('admin.mirrors._errors')
        @include('admin.mobile._nav')
        <table class="table table-light">
            <tr><th>المواد المنشورة</th><td>{{ $materials }}</td></tr>
            <tr><th>التصنيفات</th><td>{{ $categories }}</td></tr>
            <tr><th>مواد مقترحة بانتظار المراجعة</th><td><a href="{{ route('admin.mobile.submissions') }}">{{ $submissions }}</a></td></tr>
            <tr><th>مواد عليها بلاغات وصلات لا تعمل</th><td><a href="{{ route('admin.mobile.broken') }}">{{ $broken }}</a></td></tr>
            <tr><th>طلبات تعديل</th><td><a href="{{ route('admin.mobile.corrections') }}">{{ $corrections }}</a></td></tr>
        </table>
        <form method="post" action="{{ route('admin.mobile.recalculate') }}" onsubmit="return confirm('إعادة حساب التقييمات من الأصوات المسجلة؟')">
            @csrf
            <button type="submit" class="btn default">إعادة حساب التقييمات</button>
            <span class="text-muted">يعيد حساب تقييم كل مادة لها أصوات مسجلة.</span>
        </form>
    </x-admin-portlet>
@endsection
