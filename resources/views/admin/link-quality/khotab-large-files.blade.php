@extends('layouts.admin')

{{--
    AdminCP Final Page-Level Visual-Parity Verification (2026-08-22):
    legacy `stats_khotab_200mb.php` reuses the SAME page `<title>`/h1 as
    `stats_khotab.php` ("إحصائيات المرئيات و الصوتيات - الجودة المتوسطة",
    a real legacy inconsistency, not fixed here) — the distinct text is
    the PORTLET caption below, not the page title. This page's `@section
    ('title')` previously held the portlet-caption text instead of the
    page title; corrected to match source.
--}}
@section('title', 'إحصائيات المرئيات و الصوتيات - الجودة المتوسطة')

@section('content')
    <x-admin-portlet title="الصوتيات و المرئيات باحجام اكبر من 200 ميجا بايت">
        <p>{{ $khotabItems->total() }} مادة</p>

        <table class="table table-striped table-hover table-light">
            <thead>
                <tr>
                    <th>#</th>
                    <th>اسم المادة</th>
                    <th>تنزيل المادة</th>
                    <th>حجم المادة</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($khotabItems as $index => $khotabItem)
                    <tr>
                        <td>{{ $khotabItems->firstItem() + $index }}</td>
                        <td>
                            <a href="https://www.way2allah.com/khotab-item-{{ $khotabItem->id }}.htm" target="_blank">{{ $khotabItem->title }}</a>
                        </td>
                        <td><a href="{{ $khotabItem->link }}" target="_blank">تنزيل</a></td>
                        <td>{{ number_format((int) $khotabItem->linksize) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{ $khotabItems->links() }}

        <textarea rows="20" cols="100">{{ $khotabItems->pluck('link')->implode("\n") }}</textarea>
    </x-admin-portlet>
@endsection
