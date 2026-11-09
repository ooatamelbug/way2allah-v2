@extends('layouts.admin')

@section('title', 'سجل النظام القديم')

@section('content')
    {{-- OLD Admin Migration Phase 2. A read-only view of OLD's
         nuke_islamic_logs, reproducing OLD listlogs() (includes/logs.php:103):
         one module at a time, hidden = '0' only, newest first. Shown for
         coexistence; it is NOT the authoritative trail. --}}
    <x-admin-portlet title="سجل النظام القديم — للاطلاع فقط" color="yellow" icon="fa fa-archive">
        @include('admin.mirrors._errors')
        <div class="alert alert-warning">
            هذا السجل مأخوذ من النظام القديم (nuke_islamic_logs) كما هو، وليس السجل المعتمد للإدارة.
            <a href="{{ route('admin.audit.index') }}">السجل المعتمد ←</a>
        </div>

        <form method="get" action="{{ route('admin.audit.legacy') }}" class="form-inline" style="margin-bottom:15px">
            <select name="module" class="form-control">
                @foreach ($modules as $m)
                    <option value="{{ $m }}" @selected($module === $m)>{{ $m }}</option>
                @endforeach
            </select>
            <input type="text" name="aid" value="{{ $filters['aid'] ?? '' }}" placeholder="المشرف" class="form-control">
            <input type="text" name="action" value="{{ $filters['action'] ?? '' }}" placeholder="الإجراء" class="form-control">
            <input type="text" name="type" value="{{ $filters['type'] ?? '' }}" placeholder="النوع" class="form-control">
            <input type="number" name="target_id" value="{{ $filters['target_id'] ?? '' }}" min="1" placeholder="الرقم" class="form-control">
            <button type="submit" class="btn yellow">تصفية</button>
        </form>

        <table class="table table-striped table-hover table-light">
            <thead>
                <tr><th>#</th><th>الوقت</th><th>المشرف</th><th>النوع</th><th>الرقم</th><th>الإجراء</th><th>التفاصيل</th><th>IP</th></tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row->id }}</td>
                        <td style="white-space:nowrap">{{ $row->mytime ? date('Y-m-d H:i:s', (int) $row->mytime) : '' }}</td>
                        <td>{{ $row->aid }}</td>
                        <td>{{ $row->type }}</td>
                        <td>{{ $row->target_id }}</td>
                        <td>{{ $row->action }}</td>
                        {{-- Legacy extrainfo is a rendered HTML string (raw
                             <a href=...> wrappers). Tags are stripped and the
                             remaining text escaped — it is never output raw. --}}
                        <td dir="auto" style="word-break:break-all">{{ strip_tags((string) $row->extrainfo) }}</td>
                        <td dir="ltr">{{ $row->ip }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8">لا توجد سجلات مطابقة.</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $rows->links() }}
    </x-admin-portlet>
@endsection
