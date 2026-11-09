@extends('layouts.admin')

@section('title', 'قسم الموبايل — بلاغات الوصلات')

@section('content')
    <x-admin-portlet title="بلاغات وصلات لا تعمل" color="blue" icon="fa fa-chain-broken">
        @include('admin.mirrors._errors')
        @include('admin.mobile._nav')
        <table class="table table-striped table-light">
            <thead><tr><th>المادة</th><th>الرابط</th><th>المبلغون</th><th>البلاغات</th><th></th></tr></thead>
            <tbody>
                @forelse ($reports as $lid => $group)
                    @php($m = $materials[$lid] ?? null)
                    <tr>
                        <td>@if ($m)<a href="{{ route('admin.mobile.materials.edit', $lid) }}">#{{ $lid }} {{ $m->title }}</a>@else #{{ $lid }} (محذوفة) @endif</td>
                        <td dir="ltr" style="word-break:break-all">{{ $m?->url }}</td>
                        <td>{{ $group->pluck('modifysubmitter')->filter()->unique()->implode('، ') ?: '—' }}</td>
                        <td>{{ $group->count() }}</td>
                        <td style="white-space:nowrap">
                            <form method="post" action="{{ route('admin.mobile.broken.destroy-material', $lid) }}" style="display:inline" onsubmit="return confirm('حذف المادة #{{ $lid }} وكل طلباتها؟')">@csrf @method('DELETE')<button type="submit" class="btn btn-sm red">حذف المادة</button></form>
                            <form method="post" action="{{ route('admin.mobile.broken.ignore', $lid) }}" style="display:inline">@csrf @method('DELETE')<button type="submit" class="btn btn-sm default">تجاهل البلاغ</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">لا توجد بلاغات.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-admin-portlet>
@endsection
