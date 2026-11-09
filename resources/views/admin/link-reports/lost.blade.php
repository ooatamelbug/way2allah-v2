@extends('layouts.admin')

@section('title', 'الوصلات التي لا تعمل')

@section('content')
    {{-- OLD Admin Migration Phase 2 — the listing half of OLD's
         KhotabLostedLink: items whose primary link was never verified
         (down = 0). OLD's repair form had no handler and is not reproduced;
         its edit-item link is externally owned (BC#6). --}}
    <x-admin-portlet title="الوصلات التي لا تعمل - المرئيات" color="red" icon="fa fa-chain-broken">
        <p><strong>عدد الوصلات التي لا تعمل = {{ number_format($items->total()) }} وصلة</strong></p>
        <table class="table table-striped table-hover table-light">
            <thead>
                <tr><th>#</th><th>المادة</th><th>الشيخ</th><th>المجموعة</th><th>السلسلة</th><th>الوصلة</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td>{{ $item->id }}</td>
                        <td>{{ $item->title }}</td>
                        <td>{{ trim(($item->authorModel?->prename ?? '').' '.($item->authorModel?->name ?? '')) }}</td>
                        <td>{{ $item->group?->title }}</td>
                        <td>{{ $item->series?->title }}</td>
                        <td dir="ltr" style="word-break:break-all">{{ $item->link }}</td>
                        <td style="white-space:nowrap">
                            <a href="{{ route('admin.link-quality.khotab.item', $item) }}" class="btn btn-sm blue">صحة الروابط</a>
                            <a href="{{ route('admin.mirrors.khotab.show', $item) }}" class="btn btn-sm default">الوصلات البديلة</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">لا توجد وصلات معطلة.</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $items->links() }}
    </x-admin-portlet>
@endsection
