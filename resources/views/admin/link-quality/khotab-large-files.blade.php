@extends('layouts.admin')

@section('title', 'الصوتيات و المرئيات باحجام اكبر من 200 ميجا بايت')

@section('content')
    <p>{{ $khotabItems->total() }} مادة</p>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>اسم المادة</th>
                <th>حجم المادة</th>
                <th>تنزيل المادة</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($khotabItems as $index => $khotabItem)
                <tr>
                    <td>{{ $khotabItems->firstItem() + $index }}</td>
                    <td>
                        <a href="https://www.way2allah.com/khotab-item-{{ $khotabItem->id }}.htm" target="_blank">{{ $khotabItem->title }}</a>
                    </td>
                    <td>{{ number_format((int) $khotabItem->linksize) }}</td>
                    <td><a href="{{ $khotabItem->link }}" target="_blank">تنزيل</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{ $khotabItems->links() }}

    <textarea rows="20" cols="100">{{ $khotabItems->pluck('link')->implode("\n") }}</textarea>
@endsection
