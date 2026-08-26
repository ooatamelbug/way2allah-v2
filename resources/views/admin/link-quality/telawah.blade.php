@extends('layouts.admin')

@section('title', 'إحصائيات التلاوات')

@section('content')
    {{-- Portlet caption from legacy `telawah/stats.php`'s own portlet caption — distinct from the page title above (AdminCP Final Page-Level Visual-Parity Verification, 2026-08-22). --}}
    <x-admin-portlet title="تلاوات باحجام غير متطابقة بنسبة اقل من 96%">
        <form method="post" action="{{ route('admin.link-quality.telawah.recompute') }}">
            @csrf
            <button type="submit" class="btn green">تحديث النسب</button>
        </form>

        <p>{{ $telawahItems->total() }} مادة</p>

        <table class="table table-striped table-hover table-light">
            <thead>
                <tr>
                    <th>#</th>
                    <th>اسم المادة الرئيسية و الجودة البديلة</th>
                    <th>حجم المادة</th>
                    <th>تاريخ الإضافة</th>
                    <th>آخر تاريخ للفحص</th>
                    <th>نتيجة الفحص</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($telawahItems as $index => $telawahItem)
                    <tr>
                        <td>{{ $telawahItems->firstItem() + $index }}</td>
                        <td>
                            <a href="https://way2allah.com/recite-item-{{ $telawahItem->id }}.htm" target="_blank">{{ $telawahItem->title }}</a>
                        </td>
                        <td>{{ number_format((int) $telawahItem->linksize) }}</td>
                        <td>{{ $telawahItem->mytime ? date('Y-m-d', $telawahItem->mytime) : '' }}</td>
                        <td>
                            {{ $telawahItem->checktime > 0 ? date('Y-m-d', $telawahItem->checktime) : 'لم يتم بعد' }}
                            <form method="post" action="{{ route('admin.link-quality.telawah.recheck', $telawahItem) }}" style="display:inline">
                                @csrf
                                <button type="submit" title="إعادة الفحص"><i class="fa fa-refresh"></i> إعادة الفحص</button>
                            </form>
                        </td>
                        <td>
                            {{ number_format((int) $telawahItem->online) }} ({{ $telawahItem->percent }}%)
                            <form method="post" action="{{ route('admin.link-quality.telawah.fix-size', $telawahItem) }}" style="display:inline">
                                @csrf
                                <button type="submit" title="تعديل حجم المادة"><i class="fa fa-check-circle"></i> تعديل حجم المادة</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{ $telawahItems->links() }}
    </x-admin-portlet>
@endsection
