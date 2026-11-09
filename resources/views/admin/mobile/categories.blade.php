@extends('layouts.admin')

@section('title', 'قسم الموبايل — التصنيفات')

@section('content')
    <x-admin-portlet title="التصنيفات" color="blue" icon="fa fa-folder">
        @include('admin.mirrors._errors')
        @include('admin.mobile._nav')
        <table class="table table-striped table-light">
            <thead><tr><th>#</th><th>التصنيف</th><th>المواد</th><th></th></tr></thead>
            <tbody>
                @foreach ($tree as $category)
                    @foreach ([$category, ...$category->children] as $c)
                        <tr>
                            <td>{{ $c->cid }}</td>
                            <td>@if ($c->parentid > 0) &nbsp;&nbsp;— @endif{{ $c->title }}</td>
                            <td>{{ $c->materials_count }}</td>
                            <td style="white-space:nowrap">
                                <a href="{{ route('admin.mobile.categories.edit', $c->cid) }}" class="btn btn-sm blue">تعديل</a>
                                <form method="post" action="{{ route('admin.mobile.categories.destroy', $c->cid) }}" style="display:inline"
                                      onsubmit="return confirm('حذف التصنيف #{{ $c->cid }} وكل ما فيه من مواد{{ $c->parentid == 0 ? ' وتصنيفات فرعية' : '' }}؟')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm red">حذف</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </x-admin-portlet>

    <x-admin-portlet title="إضافة تصنيف" color="green" icon="fa fa-plus">
        <form method="post" action="{{ route('admin.mobile.categories.store') }}">
            @csrf
            <label>الاسم <input type="text" name="title" maxlength="50" required class="form-control"></label>
            <label>تحت التصنيف
                <select name="parentid" class="form-control">
                    <option value="0">تصنيف رئيسي</option>
                    @foreach ($tree as $c)<option value="{{ $c->cid }}">{{ $c->title }}</option>@endforeach
                </select>
            </label>
            <label style="display:block">الوصف <textarea name="cdescription" rows="3" class="form-control"></textarea></label>
            <button type="submit" class="btn green">أضف</button>
        </form>
    </x-admin-portlet>

    <x-admin-portlet title="نقل المواد بين التصنيفات" color="blue" icon="fa fa-exchange">
        <form method="post" action="{{ route('admin.mobile.categories.transfer') }}" class="form-inline" onsubmit="return confirm('نقل كل مواد التصنيف؟')">
            @csrf
            <label>من <select name="from" class="form-control">@foreach ($all as $o)<option value="{{ $o['cid'] }}">{{ $o['label'] }}</option>@endforeach</select></label>
            <label>إلى <select name="to" class="form-control">@foreach ($all as $o)<option value="{{ $o['cid'] }}">{{ $o['label'] }}</option>@endforeach</select></label>
            <button type="submit" class="btn default">نقل</button>
        </form>
    </x-admin-portlet>
@endsection
