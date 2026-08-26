@extends('layouts.admin')

@section('title', 'إحصائيات المرئيات و الصوتيات - الجودة المتوسطة')

@section('content')
    {{--
        Portlet caption reconstructed from legacy `stats_khotab.php`'s own
        portlet caption (AdminCP Final Page-Level Visual-Parity
        Verification, 2026-08-22) — distinct from the page's document
        `<title>` above, matching legacy's own page-title vs portlet-title
        split (not derived one from the other).
    --}}
    <x-admin-portlet title="الصوتيات و المرئيات باحجام غير متطابقة بنسبة اقل من 96%">
        <form method="post" action="{{ route('admin.link-quality.khotab.recompute') }}">
            @csrf
            <button type="submit" class="btn green">تحديث النسب</button>
        </form>

        {{-- Navigation orphan fix (`/admincp/` Login + Dashboard Completion): admin.link-quality.khotab.large-files already exists and works, but had no in-view link anywhere — same permission gate (khotab.repair) as this page, same module, real related capability. --}}
        <p><a href="{{ route('admin.link-quality.khotab.large-files') }}">الملفات كبيرة الحجم</a></p>

        <p>{{ $khotabItems->total() }} مادة</p>

        <table class="table table-striped table-hover table-light">
            <thead>
                <tr>
                    <th>#</th>
                    <th>اسم المادة الرئيسية</th>
                    <th>حجم المادة</th>
                    <th>تاريخ الإضافة</th>
                    <th>آخر تاريخ للفحص</th>
                    <th>نتيجة الفحص</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($khotabItems as $index => $khotabItem)
                    <tr>
                        <td>{{ $khotabItems->firstItem() + $index }}</td>
                        <td>
                            <a href="https://www.way2allah.com/khotab-item-{{ $khotabItem->id }}.htm" target="_blank">{{ $khotabItem->title }}</a>
                            <br><small>{{ $khotabItem->link }}</small>
                        </td>
                        <td>{{ number_format((int) $khotabItem->linksize) }}</td>
                        <td>{{ $khotabItem->time ? date('Y-m-d', $khotabItem->time) : '' }}</td>
                        <td>
                            {{ $khotabItem->checktime > 0 ? date('Y-m-d', $khotabItem->checktime) : 'لم يتم بعد' }}
                            <form method="post" action="{{ route('admin.link-quality.khotab.recheck', $khotabItem) }}" style="display:inline">
                                @csrf
                                <button type="submit" title="إعادة الفحص"><i class="fa fa-refresh"></i> إعادة الفحص</button>
                            </form>
                        </td>
                        <td>
                            {{ number_format((int) $khotabItem->online) }} ({{ $khotabItem->percent }}%)
                            <form method="post" action="{{ route('admin.link-quality.khotab.fix-size', $khotabItem) }}" style="display:inline">
                                @csrf
                                <button type="submit" title="تعديل حجم المادة"><i class="fa fa-check-circle"></i> تعديل حجم المادة</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{ $khotabItems->links() }}
    </x-admin-portlet>
@endsection
