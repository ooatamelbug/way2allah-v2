@extends('layouts.admin')

@section('title', 'سجل الإدارة')

@section('content')
    {{-- OLD Admin Migration Phase 2. The authoritative Laravel trail
         (admin_audit_log). OLD's legacy log is a separate page, linked
         below, and is never merged into this one. --}}
    <x-admin-portlet title="سجل الإدارة — السجل المعتمد" color="blue" icon="fa fa-history">
        @include('admin.mirrors._errors')
        <p>
            <a href="{{ route('admin.audit.legacy') }}">عرض سجل النظام القديم (للاطلاع فقط) ←</a>
        </p>

        <form method="get" action="{{ route('admin.audit.index') }}" class="form-inline" style="margin-bottom:15px">
            <select name="module" class="form-control">
                <option value="">كل الأقسام</option>
                @foreach ($modules as $m)
                    <option value="{{ $m }}" @selected(($filters['module'] ?? '') === $m)>{{ $m }}</option>
                @endforeach
            </select>
            <input type="text" name="action" value="{{ $filters['action'] ?? '' }}" placeholder="الإجراء" class="form-control">
            <input type="text" name="entity_type" value="{{ $filters['entity_type'] ?? '' }}" placeholder="نوع الكيان" class="form-control">
            <input type="number" name="entity_id" value="{{ $filters['entity_id'] ?? '' }}" min="1" placeholder="رقم الكيان" class="form-control">
            <input type="number" name="actor" value="{{ $filters['actor'] ?? '' }}" min="1" placeholder="رقم المشرف" class="form-control">
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control">
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control">
            <button type="submit" class="btn blue">تصفية</button>
            <a href="{{ route('admin.audit.index') }}" class="btn default">إلغاء</a>
        </form>

        <table class="table table-striped table-hover table-light">
            <thead>
                <tr><th>الوقت</th><th>المشرف</th><th>الإجراء</th><th>القسم</th><th>الكيان</th><th>IP</th><th>التفاصيل</th></tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    @php($link = $links[$entry->id])
                    <tr>
                        <td style="white-space:nowrap">{{ date('Y-m-d H:i:s', (int) $entry->created_at) }}</td>
                        <td>
                            @if ($entry->actor)
                                {{ $entry->actor->aid }}
                            @else
                                {{-- The actor row is gone; the stored aid is
                                     exactly why Phase 0 keeps both. --}}
                                {{ $entry->actor_aid }} <span class="text-muted">(حساب محذوف)</span>
                            @endif
                            <span class="text-muted">#{{ $entry->actor_admin_id }}</span>
                        </td>
                        <td>{{ $entry->action }}</td>
                        <td>{{ $entry->module }}</td>
                        <td style="white-space:nowrap">
                            @if ($link['url'])
                                <a href="{{ $link['url'] }}">{{ $entry->entity_type }} #{{ $entry->entity_id }}</a>
                            @else
                                {{ $entry->entity_type }} #{{ $entry->entity_id }}
                                @if ($link['known'] && $link['exists'] === false)
                                    <span class="text-muted">(محذوف)</span>
                                @endif
                            @endif
                        </td>
                        <td dir="ltr">{{ $entry->ip }}</td>
                        <td>
                            @php($meta = \App\Domain\Admin\Support\AuditMetadataPresenter::present($entry->metadata))
                            @if ($meta === [])
                                <span class="text-muted">—</span>
                            @else
                                @include('admin.audit._metadata_tree', ['data' => $meta])
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">لا توجد سجلات مطابقة.</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $entries->links() }}
    </x-admin-portlet>
@endsection
