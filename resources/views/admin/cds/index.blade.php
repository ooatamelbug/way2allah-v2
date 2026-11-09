@extends('layouts.admin')

@section('title', 'الاسطوانات الدعوية')

@section('content')
    {{-- OLD Admin Migration Phase 6 — CD / Album Downloads. Releases of one
         group in the public order; the position column replaces OLD's
         drag-to-order page (Order_w2acd_in_group). --}}
    <x-admin-portlet title="الاسطوانات الدعوية" color="blue" icon="fa fa-dot-circle-o">
        @include('admin.mirrors._errors')
        <p>
            <a href="{{ route('admin.cds.create', ['group' => $groupId]) }}" class="btn green"><i class="fa fa-plus"></i> اضافة اسطوانة جديدة</a>
            <a href="/w2acd/cds.php?id={{ $groupId }}" target="_blank" class="btn default">عرض القائمة في الموقع</a>
        </p>
        @if ($groups->isNotEmpty())
            <form method="get" action="{{ route('admin.cds.index') }}" class="form-inline" style="margin-bottom:10px">
                <select name="group" class="form-control">
                    <option value="0" @selected($groupId === 0)>القائمة الرئيسية</option>
                    @foreach ($groups as $group)
                        <option value="{{ $group->id }}" @selected($groupId === (int) $group->id)>{{ $group->title }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn default">عرض</button>
            </form>
        @endif

        <form method="post" action="{{ route('admin.cds.order', ['group' => $groupId]) }}">
            @csrf
            <table class="table table-striped table-hover table-light">
                <thead><tr><th>الترتيب</th><th>#</th><th>عنوان الإسطوانة</th><th>الروابط</th><th>الصور</th><th>مرات المشاهدة</th><th></th></tr></thead>
                <tbody>
                    @forelse ($releases as $i => $release)
                        <tr>
                            <td><input type="number" name="position[{{ $release->id }}]" value="{{ $i + 1 }}" min="1" class="form-control" style="width:80px" dir="ltr"></td>
                            <td>{{ $release->id }}</td>
                            <td><a href="/cds-item-{{ $release->id }}.htm" target="_blank">{{ $release->title }}</a></td>
                            <td>{{ count($release->mirrorLinks()) }}</td>
                            <td>{{ count($release->thumbnailFilenames()) }}</td>
                            <td>{{ (int) $release->hits }}</td>
                            <td style="white-space:nowrap">
                                <a href="{{ route('admin.cds.edit', $release) }}" class="btn btn-sm blue">تعديل</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">لا توجد إسطوانات في هذا القسم.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if ($releases->count() > 1)
                <button type="submit" class="btn default">حفظ الترتيب</button>
            @endif
        </form>
    </x-admin-portlet>
@endsection
