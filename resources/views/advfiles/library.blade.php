@extends('layouts.app')

@section('title', 'الملفات الدعائية')

{{-- khotab-advfiles.htm — OLD MainPage(): preachers with promotional files,
     by name, with each one's count (the Phase 5 `advfile` counter). --}}
@section('content')
    <x-page-chrome heading="الملفات الدعائية" :breadcrumb="[['title' => 'الملفات الدعائية', 'url' => '']]" />
    <x-content.premium-panel title="قائمة الدعاة" icon="fa-users">
        <table class="table table-striped table-hover">
            <thead><tr><th>م</th><th>الاسم</th><th>الملفات</th></tr></thead>
            <tbody>
                @forelse ($authors as $author)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td width="100%"><a href="/khotab-advfiles-author-{{ $author->id }}.htm"><b>{{ $author->name }}</b></a></td>
                        <td>{{ (int) $author->advfile }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3">لا توجد ملفات دعائية.</td></tr>
                @endforelse
            </tbody>
            <tfoot><tr><th>الكل</th><td colspan="2">{{ $authors->count() }} طلب</td></tr></tfoot>
        </table>
    </x-content.premium-panel>
@endsection
