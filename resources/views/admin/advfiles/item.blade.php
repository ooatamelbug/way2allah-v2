@extends('layouts.admin')

@section('title', 'الملف الدعائي: '.$item->title)

@section('content')
    {{-- OLD Admin Migration Phase 5 — one item's promotional-file state and
         the actions its state allows. Item editing stays on the existing
         Khotab pages. --}}
    <x-admin-portlet :title="'المادة #'.$item->id.' — '.$item->title" color="blue" icon="fa fa-star">
        @include('admin.mirrors._errors')
        <p>
            الحالة: <strong>{{ $stateLabel }}</strong>
            &nbsp;·&nbsp; <a href="{{ route('khotab.item.show', $item->id) }}" target="_blank">صفحة المادة</a>
        </p>

        @if ($file)
            <p>مرات العرض المتبقية لهذا الملف = <strong>{{ (int) $file->credit }}</strong></p>
            @if ($file->audio_url)
                <p>الملف الصوتي: <span dir="ltr" style="word-break:break-all">{{ $file->audio_url }}</span></p>
            @endif
            <p>كود الملف (يُعرض كنص):</p>
            <pre dir="ltr" style="max-height:220px;overflow:auto;white-space:pre-wrap">{{ $file->code }}</pre>
        @endif

        <div style="margin-top:10px">
            @if ($state === 0)
                <form method="post" action="{{ route('admin.advfiles.request', $item->id) }}" style="display:inline">
                    @csrf
                    <button type="submit" class="btn green">طلب رئيسية</button>
                </form>
            @endif
            @if ($canManage && $state === -1)
                <a href="{{ route('admin.advfiles.approve.form', $item->id) }}" class="btn green">موافقة وإضافة الملف</a>
                <form method="post" action="{{ route('admin.advfiles.reject', $item->id) }}" style="display:inline"
                      onsubmit="return confirm('رفض طلب الملف الدعائي لهذه المادة؟')">
                    @csrf
                    <button type="submit" class="btn red">رفض الطلب</button>
                </form>
            @endif
            @if ($canManage && $state > 0)
                @if ($file)
                    <a href="{{ route('admin.advfiles.edit', $item->id) }}" class="btn blue">تعديل الملف</a>
                @endif
                <form method="post" action="{{ route('admin.advfiles.unpublish', $item->id) }}" style="display:inline"
                      onsubmit="return confirm('هل ترغب في مسح الملف الدعائي لهذه المادة؟')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn red"><i class="fa fa-trash-o"></i> مسح الملف</button>
                </form>
            @endif
        </div>
    </x-admin-portlet>
@endsection
