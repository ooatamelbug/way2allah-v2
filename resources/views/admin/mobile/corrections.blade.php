@extends('layouts.admin')

@section('title', 'قسم الموبايل — طلبات التعديل')

@section('content')
    <x-admin-portlet title="طلبات تعديل المواد" color="blue" icon="fa fa-edit">
        @include('admin.mirrors._errors')
        @include('admin.mobile._nav')
        @forelse ($requests as $r)
            @php($m = $materials[$r->lid] ?? null)
            <div style="border:1px solid #ddd;padding:10px;margin-bottom:12px">
                <p><strong>طلب #{{ $r->requestid }}</strong> على المادة #{{ $r->lid }} — من: {{ $r->modifysubmitter ?: '—' }}</p>
                <table class="table table-condensed">
                    <thead><tr><th></th><th>الحالي</th><th>المقترح</th></tr></thead>
                    @foreach (['title' => 'العنوان', 'url' => 'الرابط', 'cid' => 'التصنيف', 'description' => 'الوصف', 'name' => 'صاحب المادة', 'email' => 'البريد', 'filesize' => 'الحجم', 'version' => 'الإصدار', 'homepage' => 'صفحته'] as $field => $label)
                        @php($current = $m?->{$field})
                        @php($proposed = $r->{$field})
                        <tr @if ((string) $current !== (string) $proposed) style="background:#fff7e6" @endif>
                            <th>{{ $label }}</th>
                            <td style="white-space:pre-line;word-break:break-all">{{ $field === 'cid' ? ($categories[$current]->title ?? $current) : \Illuminate\Support\Str::limit((string) $current, 300) }}</td>
                            <td style="white-space:pre-line;word-break:break-all">{{ $field === 'cid' ? ($categories[$proposed]->title ?? $proposed) : \Illuminate\Support\Str::limit((string) $proposed, 300) }}</td>
                        </tr>
                    @endforeach
                </table>
                @if ($m)
                    <form method="post" action="{{ route('admin.mobile.corrections.accept', $r->requestid) }}" style="display:inline">@csrf<button type="submit" class="btn green">قبول التعديل</button></form>
                @endif
                <form method="post" action="{{ route('admin.mobile.corrections.ignore', $r->requestid) }}" style="display:inline">@csrf @method('DELETE')<button type="submit" class="btn default">تجاهل</button></form>
            </div>
        @empty
            <p>لا توجد طلبات تعديل.</p>
        @endforelse
    </x-admin-portlet>
@endsection
