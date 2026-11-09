@extends('layouts.admin')

@section('title', 'قسم الموبايل — مواد مقترحة')

@section('content')
    <x-admin-portlet title="مواد مقترحة بانتظار المراجعة" color="blue" icon="fa fa-inbox">
        @include('admin.mirrors._errors')
        @include('admin.mobile._nav')
        @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
        @forelse ($submissions as $s)
            <div style="border:1px solid #ddd;padding:10px;margin-bottom:12px">
                <p><strong>#{{ $s->lid }}</strong> — المرسل: {{ $s->submitter !== '' ? $s->submitter : 'لا يوجد' }}</p>
                <form method="post" action="{{ route('admin.mobile.submissions.approve', $s->lid) }}">
                    @csrf
                    @include('admin.mobile._material-fields', ['values' => $s->only(['cid', 'title', 'url', 'description', 'name', 'email', 'filesize', 'version', 'homepage'])])
                    <button type="submit" class="btn green">موافقة ونشر</button>
                </form>
                <form method="post" action="{{ route('admin.mobile.submissions.reject', $s->lid) }}" style="margin-top:6px" onsubmit="return confirm('رفض المادة المقترحة #{{ $s->lid }}؟')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn red">رفض</button>
                </form>
            </div>
        @empty
            <p>لا توجد مواد مقترحة.</p>
        @endforelse
    </x-admin-portlet>
@endsection
