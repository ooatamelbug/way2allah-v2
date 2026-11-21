@extends('layouts.admin')

@section('title', 'تعديل الوصلة #'.$mirror->id)

@section('content')
    <x-admin-portlet :title="'تعديل الوصلة #'.$mirror->id" color="blue" icon="fa fa-pencil">
        @include('admin.mirrors._errors')
        <p>
            @if ($item)
                المادة: <a href="{{ route('admin.mirrors.anasheed.show', $item) }}">#{{ $item->id }} — {{ $item->title }}</a>
            @else
                <span class="text-danger">المادة #{{ (int) $mirror->khid }} غير موجودة.</span>
            @endif
        </p>
        <form method="post" action="{{ route('admin.mirrors.anasheed.update', $mirror) }}">
            @csrf
            <x-admin.contextual-return />
            @method('PUT')
            <label>وصف الوصلة <input type="text" name="title" value="{{ old('title', $mirror->title) }}" maxlength="255" class="form-control"></label>
            <label>وصلة التحميل <input type="text" name="link" value="{{ old('link', $mirror->link) }}" dir="ltr" class="form-control"></label>
            @include('admin.anasheed._link-warning', ['link' => (string) old('link', $mirror->link), 'showWhenEmpty' => true])
            {{-- Bytes, not megabytes: OLD's Anasheed edit took the stored byte
                 value directly. Honoured only for the mirror states OLD
                 honoured it for; see UpdateAnasheedMirrorAction. --}}
            @if ($sizeEditable)
                <label>الحجم (بايت) <input type="number" name="linksize" min="0" value="{{ old('linksize', (int) $mirror->linksize) }}" class="form-control"></label>
            @else
                <p class="text-muted">الحجم: {{ (int) $mirror->linksize }} بايت — لا يمكن تعديل الحجم لهذه الوصلة.</p>
            @endif
            <label><input type="radio" name="vedio" value="0" @checked((string) old('vedio', (int) $mirror->vedio) === '0')> مادة صوتية</label>
            <label><input type="radio" name="vedio" value="1" @checked((string) old('vedio', (int) $mirror->vedio) === '1')> مادة مرئية</label>
            <label><input type="checkbox" name="backupme" value="1" @checked(old('backupme', (int) $mirror->backupme === 1))> إنزال نسخة إحتياطية ضمن أرشيف الموقع</label>
            <button type="submit" class="btn blue">تعديل الوصلة</button>
        </form>
    </x-admin-portlet>

    @if ($canDelete)
        <x-admin-portlet title="حذف الوصلة" color="red" icon="fa fa-trash-o">
            <form method="post" action="{{ route('admin.mirrors.anasheed.destroy', $mirror) }}"
                  onsubmit="return confirm('حذف الوصلة #{{ $mirror->id }}؟')">
                @csrf
                <x-admin.contextual-return />
                @method('DELETE')
                <button type="submit" class="btn red"><i class="fa fa-trash-o"></i> حذف الوصلة</button>
            </form>
        </x-admin-portlet>
    @endif
@endsection
