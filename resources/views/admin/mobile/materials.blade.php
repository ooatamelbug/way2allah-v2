@extends('layouts.admin')

@section('title', 'قسم الموبايل — المواد')

@section('content')
    <x-admin-portlet title="المواد" color="blue" icon="fa fa-mobile">
        @include('admin.mirrors._errors')
        @include('admin.mobile._nav')
        <form method="get" action="{{ route('admin.mobile.materials') }}" class="form-inline" style="margin-bottom:10px">
            <select name="cid" class="form-control"><option value="0">كل التصنيفات</option>@foreach ($categories as $o)<option value="{{ $o['cid'] }}" @selected($cid === $o['cid'])>{{ $o['label'] }}</option>@endforeach</select>
            <input type="text" name="q" value="{{ $q }}" placeholder="رقم أو عنوان" class="form-control">
            <button type="submit" class="btn default">بحث</button>
            <a href="{{ route('admin.mobile.materials.create') }}" class="btn green">أضف مادة</a>
        </form>
        <table class="table table-striped table-light">
            <thead><tr><th>#</th><th>العنوان</th><th>التاريخ</th><th>تحميل</th><th>أصوات</th><th></th></tr></thead>
            <tbody>
                @forelse ($materials as $m)
                    <tr>
                        <td>{{ $m->lid }}</td>
                        <td>{{ $m->title }}</td>
                        <td dir="ltr">{{ substr((string) $m->date, 0, 10) }}</td>
                        <td>{{ (int) $m->hits }}</td>
                        <td>{{ (int) $m->totalvotes }}</td>
                        <td><a href="{{ route('admin.mobile.materials.edit', $m->lid) }}" class="btn btn-sm blue">تعديل</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6">لا توجد مواد.</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $materials->links() }}
    </x-admin-portlet>
@endsection
