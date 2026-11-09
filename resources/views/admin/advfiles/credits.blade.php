@extends('layouts.admin')

@section('title', 'قائمة الملفات الدعائية')

@section('content')
    {{-- OLD Admin Migration Phase 5 — OLD Credit(): files with credit > 0,
         largest first; each credit editable in one submission. --}}
    <x-admin-portlet title="قائمة الملفات الدعائية" color="blue" icon="fa fa-sort-amount-desc">
        @include('admin.mirrors._errors')
        <p>لاحظ أنه يتم ترتيب الملفات من الأكبر إلى الأصغر من حيث عدد مرات العرض</p>
        <form method="post" action="{{ route('admin.advfiles.credits.save') }}">
            @csrf
            <table class="table table-striped table-light">
                <thead><tr><th>م</th><th>عنوان المادة</th><th>مرات العرض</th><th></th></tr></thead>
                <tbody>
                    @forelse ($files as $i => $file)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $titles[$file->id] ?? ('#'.$file->id) }}</td>
                            <td><input type="number" name="credit[{{ $file->id }}]" value="{{ (int) $file->credit }}" min="0" max="999999" class="form-control" dir="ltr" style="width:110px"></td>
                            <td><a href="{{ route('admin.advfiles.item', $file->id) }}" class="btn btn-sm blue">تعديل</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="4">لا توجد ملفات لها مرات عرض.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if ($files->isNotEmpty())
                <button type="submit" class="btn green">حفظ</button>
            @endif
        </form>
    </x-admin-portlet>
@endsection
