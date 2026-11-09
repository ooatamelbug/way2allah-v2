@extends('layouts.admin')

@section('title', 'تعديل الوصلة #'.$mirror->id)

@section('content')
    <x-admin-portlet :title="'تعديل الوصلة #'.$mirror->id" color="blue" icon="fa fa-pencil">
        @include('admin.mirrors._errors')
        <p>
            @if ($item)
                المادة: <a href="{{ route('admin.mirrors.khotab.show', $item) }}">#{{ $item->id }} — {{ $item->title }}</a>
            @else
                {{-- Orphan: editable, movable and deletable (3E.1 I-4). --}}
                <span class="text-danger">المادة #{{ (int) $mirror->khid }} غير موجودة.</span>
            @endif
        </p>
        <form method="post" action="{{ route('admin.mirrors.khotab.update', $mirror) }}">
            @csrf
            <x-admin.contextual-return />
            @method('PUT')
            <label>التعليق <input type="text" name="comment" value="{{ old('comment', $mirror->comment) }}" maxlength="255" class="form-control"></label>
            <label>وصلة التحميل <input type="text" name="link" value="{{ old('link', $mirror->link) }}" dir="ltr" class="form-control"></label>
            {{-- No size field: Khotab's edit never wrote linksize (3E.1 C10). --}}
            <label><input type="radio" name="vedio" value="0" @checked((string) old('vedio', (int) $mirror->vedio) === '0')> مادة صوتية</label>
            <label><input type="radio" name="vedio" value="1" @checked((string) old('vedio', (int) $mirror->vedio) === '1')> مادة مرئية</label>
            <label><input type="checkbox" name="backupme" value="1" @checked(old('backupme', (int) $mirror->backupme === 1))> إنزال نسخة إحتياطية ضمن أرشيف الموقع</label>
            <button type="submit" class="btn blue">تعديل الوصلة</button>
        </form>
    </x-admin-portlet>

    @if ($canDelete)
        <x-admin-portlet title="حذف الوصلة" color="red" icon="fa fa-trash-o">
            <form method="post" action="{{ route('admin.mirrors.khotab.destroy', $mirror) }}"
                  onsubmit="return confirm('حذف الوصلة #{{ $mirror->id }}؟')">
                @csrf
                <x-admin.contextual-return />
                @method('DELETE')
                <button type="submit" class="btn red"><i class="fa fa-trash-o"></i> حذف الوصلة</button>
            </form>
        </x-admin-portlet>
    @endif

    @unless ($item)
        <x-admin-portlet title="نقل الوصلة إلى مادة أخرى" color="yellow" icon="fa fa-exchange">
            <form method="post" action="{{ route('admin.mirrors.khotab.move', $mirror) }}" class="form-inline">
                @csrf
                <x-admin.contextual-return />
                <label>رقم المادة الهدف <input type="number" name="target_item_id" min="1" required class="form-control"></label>
                <button type="submit" class="btn yellow">نقل</button>
            </form>
        </x-admin-portlet>
    @endunless
@endsection
