@extends('layouts.admin')

@section('title', 'قائمة فريق الرفع بموقع ارشيف')

@section('content')
    {{-- Portlet caption from legacy `khotab/uploaders.php`'s own portlet caption — same text this page's `@section('title')` already used (AdminCP Final Page-Level Visual-Parity Verification, 2026-08-22). --}}
    <x-admin-portlet title="قائمة فريق الرفع بموقع ارشيف">
        <form method="post" action="{{ route('admin.uploaders.recompute') }}" style="display:inline">
            @csrf
            <button type="submit" class="btn btn-success">تحديث الإحصائيات</button>
        </form>
        <form method="post" action="{{ route('admin.uploaders.vblink') }}" style="display:inline">
            @csrf
            <button type="submit" class="btn btn-success">البحث بالمنتدى</button>
        </form>

        <table class="table table-striped table-hover table-light">
            <thead>
                <tr>
                    <th><a href="{{ route('admin.uploaders.index', ['sort' => 'username', 'order' => $order]) }}">الاسم</a></th>
                    <th><a href="{{ route('admin.uploaders.index', ['sort' => 'email', 'order' => $order]) }}">البريد الألكتروني</a></th>
                    <th><a href="{{ route('admin.uploaders.index', ['sort' => 'date', 'order' => $order]) }}">احدث تاريخ للرفع</a></th>
                    <th><a href="{{ route('admin.uploaders.index', ['sort' => 'count', 'order' => $order]) }}">عدد المواد</a></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($uploaders as $uploader)
                    <tr>
                        <td>{{ $uploader->username }}</td>
                        <td>{{ $uploader->email }}</td>
                        <td>{{ $uploader->last_upload }}</td>
                        <td>{{ $uploader->counter }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-admin-portlet>
@endsection
