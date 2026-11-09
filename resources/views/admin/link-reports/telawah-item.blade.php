@extends('layouts.admin')

@section('title', 'صحة الرابط: '.$item->title)

@section('content')
    {{-- Contextual surface C5 — link health for one Telawah item, mirroring
         Phase 2's Khotab page. The buttons post to the EXISTING
         LinkQualityStatsController routes, unchanged. Fix-size is offered only
         where a measured size exists and differs (on online = 0 it would
         overwrite the recorded size with zero). --}}
    <x-admin-portlet :title="'صحة الرابط — التلاوة #'.$item->id.' — '.$item->title" color="blue" icon="fa fa-heartbeat">
        <table class="table table-striped table-light">
            <thead>
                <tr><th>الوصلة</th><th>linksize</th><th>online</th><th>percent</th><th>down</th><th>trial</th><th>checktime</th><th></th></tr>
            </thead>
            <tbody>
                <tr>
                    <td dir="ltr" style="word-break:break-all">{{ $item->link }}</td>
                    <td>{{ (int) $item->linksize }}</td>
                    <td>{{ (int) $item->online }}</td>
                    <td>{{ $item->percent }}</td>
                    <td>{{ $item->down }}</td>
                    <td>{{ $item->trial }}</td>
                    <td style="white-space:nowrap">{{ $item->checktime ? date('Y-m-d H:i', (int) $item->checktime) : '—' }}</td>
                    <td style="white-space:nowrap">
                        <form method="post" action="{{ route('admin.link-quality.telawah.recheck', $item) }}" style="display:inline">
                            @csrf
                            <button type="submit" class="btn btn-sm blue">إعادة الفحص</button>
                        </form>
                        @if ((int) $item->online > 0 && (int) $item->online !== (int) $item->linksize)
                            <form method="post" action="{{ route('admin.link-quality.telawah.fix-size', $item) }}" style="display:inline">
                                @csrf
                                <button type="submit" class="btn btn-sm green">اعتماد الحجم المقاس</button>
                            </form>
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>
        <p><a href="/recite-item-{{ $item->id }}.htm">عرض التلاوة في الموقع ←</a></p>
    </x-admin-portlet>
@endsection
