@extends('layouts.admin')

@section('title', 'إحصائيات المرئيات و الصوتيات - الجودة البديلة')

@section('content')
    {{-- Portlet caption from legacy `khotab/stats.php`'s own portlet caption — distinct from the page title above (AdminCP Final Page-Level Visual-Parity Verification, 2026-08-22). --}}
    <x-admin-portlet title="جودات بديلة باحجام غير متطابقة بنسبة اقل من 96%">
        <form method="post" action="{{ route('admin.link-quality.mirror.recompute') }}">
            @csrf
            <button type="submit" class="btn green">تحديث النسب</button>
        </form>

        <p>{{ $mirrors->total() }} مادة</p>

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
                @foreach ($mirrors as $index => $mirror)
                    <tr>
                        <td>{{ $mirrors->firstItem() + $index }}</td>
                        <td>
                            <a href="https://www.way2allah.com/khotab-item-{{ $mirror->khid }}.htm" target="_blank">{{ $mirror->khotabItem->title ?? '' }}</a>
                            <br><small>{{ $mirror->id }}: {{ $mirror->comment }}</small>
                            <br><small>{{ $mirror->link }}</small>
                        </td>
                        <td>{{ number_format((int) $mirror->linksize) }}</td>
                        <td>{{ $mirror->time ? date('Y-m-d', $mirror->time) : '' }}</td>
                        <td>
                            {{ $mirror->checktime > 0 ? date('Y-m-d', $mirror->checktime) : 'لم يتم بعد' }}
                            <form method="post" action="{{ route('admin.link-quality.mirror.recheck', $mirror) }}" style="display:inline">
                                @csrf
                                <button type="submit" title="إعادة الفحص"><i class="fa fa-refresh"></i> إعادة الفحص</button>
                            </form>
                        </td>
                        <td>
                            {{ number_format((int) $mirror->online) }} ({{ $mirror->percent }}%)
                            <form method="post" action="{{ route('admin.link-quality.mirror.fix-size', $mirror) }}" style="display:inline">
                                @csrf
                                <button type="submit" title="تعديل حجم المادة"><i class="fa fa-check-circle"></i> تعديل حجم المادة</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{ $mirrors->links() }}
    </x-admin-portlet>
@endsection
