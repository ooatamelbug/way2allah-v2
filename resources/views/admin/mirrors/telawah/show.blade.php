@extends('layouts.admin')

@section('title', 'الوصلات البديلة: '.$item->title)

@section('content')
    <x-admin-portlet :title="'التلاوة #'.$item->id.' — '.$item->title" color="blue" icon="fa fa-book">
        @include('admin.mirrors._errors')
        <p>
            عدد الوصلات: <strong>{{ $mirrors->count() }}</strong>
            &nbsp;·&nbsp; <a href="/recite-item-{{ $item->id }}.htm">عرض التلاوة في الموقع ←</a>
        </p>
        <table class="table table-striped table-hover table-light">
            <thead>
                <tr><th>#</th><th>وصف الوصلة</th><th>الوصلة</th><th>الحجم</th><th>النوع</th><th>نسخة احتياطية</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($mirrors as $mirror)
                    <tr>
                        <td>{{ $mirror->id }}</td>
                        <td>{{ $mirror->comment }}</td>
                        <td dir="ltr" style="word-break:break-all">{{ $mirror->link }}</td>
                        <td>{{ number_format(((int) $mirror->size) / 1048576, 2) }} MB</td>
                        <td>{{ (string) $mirror->video === '1' ? 'مرئية' : 'صوتية' }}</td>
                        <td>{{ (string) $mirror->backupme === '1' ? 'نعم' : 'لا' }}</td>
                        <td style="white-space:nowrap">
                            @if ($canEdit)
                                <a href="{{ route('admin.mirrors.telawah.edit', $mirror) }}" class="btn btn-sm blue">تعديل</a>
                            @endif
                            @if ($canDelete)
                                <form method="post" action="{{ route('admin.mirrors.telawah.destroy', $mirror) }}" style="display:inline"
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
                    <tr><td colspan="7">لا توجد وصلات بديلة لهذه التلاوة.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-admin-portlet>
@endsection
