@extends('layouts.admin')

@section('title', 'ملفات الرئيسية — قائمة الطلبات')

@section('content')
    {{-- OLD Admin Migration Phase 5 — OLD AdvfilesMain: items with a pending
         promotional-file request, oldest item id first. --}}
    <x-admin-portlet title="ملفات الرئيسية — قائمة الطلبات" color="blue" icon="fa fa-star">
        @include('admin.mirrors._errors')
        <p>
            <a href="{{ route('admin.advfiles.credits') }}" class="btn default">قائمة الملفات الدعائية (مرات العرض)</a>
            <a href="{{ route('admin.advfiles.lookup') }}" class="btn default">البحث عن مادة</a>
        </p>
        <table class="table table-striped table-hover table-light">
            <thead><tr><th>م</th><th>#</th><th>عنوان المادة</th><th></th></tr></thead>
            <tbody>
                @forelse ($pending as $i => $item)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $item->id }}</td>
                        <td><a href="{{ route('khotab.item.show', $item->id) }}" target="_blank">{{ $item->title }}</a></td>
                        <td style="white-space:nowrap">
                            <a href="{{ route('admin.advfiles.approve.form', $item->id) }}" class="btn btn-sm green">موافقة وإضافة الملف</a>
                            <form method="post" action="{{ route('admin.advfiles.reject', $item->id) }}" style="display:inline"
                                  onsubmit="return confirm('رفض طلب الملف الدعائي لهذه المادة؟')">
                                @csrf
                                <button type="submit" class="btn btn-sm red">رفض الطلب</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4">لا توجد طلبات.</td></tr>
                @endforelse
            </tbody>
            <tfoot><tr><th>الكل</th><td colspan="3">{{ $pending->count() }} طلب</td></tr></tfoot>
        </table>
    </x-admin-portlet>
@endsection
