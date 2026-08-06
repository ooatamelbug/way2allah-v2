@extends('layouts.admin')

@section('title', 'قائمة المشرفين')

@section('content')
    <a href="{{ route('admin.staff.create') }}">إضافة مشرف</a>
    <table>
        <thead>
            <tr>
                <th>الاسم</th>
                <th>البريد الألكتروني</th>
                <th>الرتبة</th>
                <th>الصلاحيات</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($staff as $admin)
                <tr>
                    <td>{{ $admin->aid }}</td>
                    <td>{{ $admin->email }}</td>
                    <td>{{ $admin->radminsuper ? 'مدير عام' : 'مشرف' }}</td>
                    <td><a href="{{ route('admin.permissions.edit', $admin) }}">تعديل الصلاحيات</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
