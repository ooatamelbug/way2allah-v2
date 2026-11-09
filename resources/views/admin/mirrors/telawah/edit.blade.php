@extends('layouts.admin')

@section('title', 'تعديل الوصلة #'.$mirror->id)

@section('content')
    {{-- OLD's Telawah edit form (admin_edit_mirror.htm): description, link,
         audio/video, backup. The size is shown, not edited — OLD's form has
         no size field. --}}
    <x-admin-portlet :title="'تعديل الوصلة #'.$mirror->id" color="blue" icon="fa fa-pencil">
        @include('admin.mirrors._errors')
        <p>
            @if ($item)
                التلاوة: <a href="{{ route('admin.mirrors.telawah.show', $item) }}">#{{ $item->id }} — {{ $item->title }}</a>
            @else
                <span class="text-danger">التلاوة #{{ (int) $mirror->telawa_id }} غير موجودة.</span>
            @endif
        </p>
        <form method="post" action="{{ route('admin.mirrors.telawah.update', $mirror) }}">
            @csrf
            <x-admin.contextual-return />
            @method('PUT')
            <label>وصف الوصلة <input type="text" name="comment" value="{{ old('comment', $mirror->comment) }}" maxlength="255" class="form-control"></label>
            <label>وصلة التحميل <input type="text" name="link" value="{{ old('link', $mirror->link) }}" maxlength="255" dir="ltr" class="form-control"></label>
            <p class="text-muted">الحجم: {{ number_format(((int) $mirror->size) / 1048576, 2) }} MB</p>
            <label><input type="radio" name="vedio" value="0" @checked((string) old('vedio', (string) $mirror->video === '1' ? '1' : '0') === '0')> مادة صوتية</label>
            <label><input type="radio" name="vedio" value="1" @checked((string) old('vedio', (string) $mirror->video === '1' ? '1' : '0') === '1')> مادة مرئية</label>
            <label><input type="checkbox" name="backupme" value="1" @checked(old('backupme', (string) $mirror->backupme === '1'))> إنزال نسخة إحتياطية ضمن أرشيف الموقع</label>
            <button type="submit" class="btn blue">تعديل الوصلة</button>
        </form>
    </x-admin-portlet>

    @if ($canDelete)
        <x-admin-portlet title="حذف الوصلة" color="red" icon="fa fa-trash-o">
            <form method="post" action="{{ route('admin.mirrors.telawah.destroy', $mirror) }}"
                  onsubmit="return confirm('حذف الوصلة #{{ $mirror->id }}؟')">
                @csrf
                <x-admin.contextual-return />
                @method('DELETE')
                <button type="submit" class="btn red"><i class="fa fa-trash-o"></i> حذف الوصلة</button>
            </form>
        </x-admin-portlet>
    @endif
@endsection
