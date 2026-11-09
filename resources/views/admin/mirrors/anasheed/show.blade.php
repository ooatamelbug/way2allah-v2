@extends('layouts.admin')

@section('title', 'الوصلات البديلة: '.$item->title)

@section('content')
    <x-admin-portlet :title="'المادة #'.$item->id.' — '.$item->title" color="blue" icon="fa fa-music">
        @include('admin.mirrors._errors')
        <p>
            عدد الوصلات: <strong>{{ $mirrors->count() }}</strong>
            &nbsp;·&nbsp; <span class="text-muted">العداد القديم: {{ (int) $item->mirror }}</span>
        </p>
        <table class="table table-striped table-hover table-light">
            <thead>
                <tr><th>#</th><th>وصف الوصلة</th><th>الوصلة</th><th>الحجم</th><th>النوع</th><th>نسخة احتياطية</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($mirrors as $mirror)
                    <tr>
                        <td>{{ $mirror->id }}</td>
                        <td>{{ $mirror->title }}</td>
                        <td dir="ltr" style="word-break:break-all">{{ $mirror->link }}</td>
                        <td>{{ number_format(((int) $mirror->linksize) / 1048576, 2) }} MB</td>
                        <td>{{ (int) $mirror->vedio === 1 ? 'مرئية' : 'صوتية' }}</td>
                        <td>{{ (int) $mirror->backupme === 1 ? 'نعم' : 'لا' }}</td>
                        <td style="white-space:nowrap">
                            @if ($canEdit)
                                <a href="{{ route('admin.mirrors.anasheed.edit', $mirror) }}" class="btn btn-sm blue">تعديل</a>
                            @endif
                            @if ($canDelete)
                                <form method="post" action="{{ route('admin.mirrors.anasheed.destroy', $mirror) }}" style="display:inline"
                                      onsubmit="return confirm('حذف الوصلة #{{ $mirror->id }}؟')">
                                    @csrf
                                    <x-admin.contextual-return />
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm red"><i class="fa fa-trash-o"></i> حذف</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">لا توجد وصلات بديلة لهذه المادة.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-admin-portlet>

    @if ($canAdd)
        <x-admin-portlet title="إضافة وصلة بديلة" color="green" icon="fa fa-plus">
            <form method="post" action="{{ route('admin.mirrors.anasheed.store', $item) }}">
                @csrf
                <x-admin.contextual-return />
                <label>وصف الوصلة <input type="text" name="title" value="{{ old('title') }}" maxlength="255" class="form-control"></label>
                <label>وصلة التحميل <input type="text" name="link" value="{{ old('link') }}" dir="ltr" required class="form-control"></label>
                <label>الحجم (ميجا بايت) <input type="text" name="linksize_mb" value="{{ old('linksize_mb') }}" class="form-control"></label>
                <label><input type="radio" name="vedio" value="0" @checked((string) old('vedio', (int) $item->vedio) === '0')> مادة صوتية</label>
                <label><input type="radio" name="vedio" value="1" @checked((string) old('vedio', (int) $item->vedio) === '1')> مادة مرئية</label>
                <label><input type="checkbox" name="backupme" value="1" @checked(old('backupme'))> إنزال نسخة إحتياطية ضمن أرشيف الموقع</label>
                <button type="submit" class="btn green">أضف الآن</button>
            </form>
        </x-admin-portlet>
    @endif
@endsection
