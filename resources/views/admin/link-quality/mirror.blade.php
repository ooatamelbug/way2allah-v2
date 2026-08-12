@extends('layouts.admin')

@section('title', 'إحصائيات المرئيات و الصوتيات - الجودة البديلة')

@section('content')
    <form method="post" action="{{ route('admin.link-quality.mirror.recompute') }}">
        @csrf
        <button type="submit">تحديث النسب</button>
    </form>

    <p>{{ $mirrors->total() }} مادة</p>

    <table>
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
                            <button type="submit" title="إعادة الفحص">إعادة الفحص</button>
                        </form>
                    </td>
                    <td>
                        {{ number_format((int) $mirror->online) }} ({{ $mirror->percent }}%)
                        <form method="post" action="{{ route('admin.link-quality.mirror.fix-size', $mirror) }}" style="display:inline">
                            @csrf
                            <button type="submit" title="تعديل حجم المادة">تعديل حجم المادة</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{ $mirrors->links() }}
@endsection
