@extends('layouts.admin')

@section('title', 'صحة الروابط: '.$item->title)

@section('content')
    {{-- OLD Admin Migration Phase 2 — contextual link health for one item.
         The recheck / fix-size buttons post to the EXISTING
         LinkQualityStatsController routes, unchanged; this page only offers
         them in context. Raw quality values are shown as stored, without
         inventing labels for them. Fix-size is offered only where a measured
         size exists and differs — on a never-measured link (online = 0) it
         would overwrite the recorded size with zero. --}}
    <x-admin-portlet :title="'صحة الروابط — المادة #'.$item->id.' — '.$item->title" color="blue" icon="fa fa-heartbeat">
        <p><a href="{{ route('admin.mirrors.khotab.show', $item) }}">إدارة الوصلات البديلة لهذه المادة ←</a></p>

        <table class="table table-striped table-light">
            <thead>
                <tr><th></th><th>الوصلة</th><th>linksize</th><th>online</th><th>percent</th><th>down</th><th>trial</th><th>checktime</th><th></th></tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>الوصلة الأساسية</strong></td>
                    <td dir="ltr" style="word-break:break-all">{{ $item->link }}</td>
                    <td>{{ (int) $item->linksize }}</td>
                    <td>{{ (int) $item->online }}</td>
                    <td>{{ $item->percent }}</td>
                    <td>{{ $item->down }}</td>
                    <td>{{ $item->trial }}</td>
                    <td style="white-space:nowrap">{{ $item->checktime ? date('Y-m-d H:i', (int) $item->checktime) : '—' }}</td>
                    <td style="white-space:nowrap">
                        <form method="post" action="{{ route('admin.link-quality.khotab.recheck', $item) }}" style="display:inline">
                            @csrf
                            <button type="submit" class="btn btn-sm blue">إعادة الفحص</button>
                        </form>
                        @if ((int) $item->online > 0 && (int) $item->online !== (int) $item->linksize)
                            <form method="post" action="{{ route('admin.link-quality.khotab.fix-size', $item) }}" style="display:inline">
                                @csrf
                                <button type="submit" class="btn btn-sm green">اعتماد الحجم المقاس</button>
                            </form>
                        @endif
                    </td>
                </tr>
                @foreach ($mirrors as $mirror)
                    <tr>
                        <td>وصلة بديلة #{{ $mirror->id }}</td>
                        <td dir="ltr" style="word-break:break-all">{{ $mirror->link }}</td>
                        <td>{{ (int) $mirror->linksize }}</td>
                        <td>{{ (int) $mirror->online }}</td>
                        <td>{{ $mirror->percent }}</td>
                        <td>{{ $mirror->down }}</td>
                        <td>{{ $mirror->trial }}</td>
                        <td style="white-space:nowrap">{{ $mirror->checktime ? date('Y-m-d H:i', (int) $mirror->checktime) : '—' }}</td>
                        <td style="white-space:nowrap">
                            <form method="post" action="{{ route('admin.link-quality.mirror.recheck', $mirror) }}" style="display:inline">
                                @csrf
                                <button type="submit" class="btn btn-sm blue">إعادة الفحص</button>
                            </form>
                            @if ((int) $mirror->online > 0 && (int) $mirror->online !== (int) $mirror->linksize)
                                <form method="post" action="{{ route('admin.link-quality.mirror.fix-size', $mirror) }}" style="display:inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm green">اعتماد الحجم المقاس</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-admin-portlet>
@endsection
