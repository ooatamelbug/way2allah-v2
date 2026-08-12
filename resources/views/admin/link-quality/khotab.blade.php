@extends('layouts.admin')

@section('title', 'إحصائيات المرئيات و الصوتيات - الجودة المتوسطة')

@section('content')
    <form method="post" action="{{ route('admin.link-quality.khotab.recompute') }}">
        @csrf
        <button type="submit">تحديث النسب</button>
    </form>

    <p>{{ $khotabItems->total() }} مادة</p>

    <table>
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
                            <button type="submit" title="إعادة الفحص">إعادة الفحص</button>
                        </form>
                    </td>
                    <td>
                        {{ number_format((int) $khotabItem->online) }} ({{ $khotabItem->percent }}%)
                        <form method="post" action="{{ route('admin.link-quality.khotab.fix-size', $khotabItem) }}" style="display:inline">
                            @csrf
                            <button type="submit" title="تعديل حجم المادة">تعديل حجم المادة</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{ $khotabItems->links() }}
@endsection
