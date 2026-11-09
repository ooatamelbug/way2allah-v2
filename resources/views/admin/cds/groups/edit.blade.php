@extends('layouts.admin')

@section('title', 'تعديل قسم')

@section('content')
    {{-- OLD EditGroup() (modules/w2acd/admin/index.php): the same fields, labels and parent choices. --}}
    <x-admin-portlet :title="'تعديل قسم '.$group->title" color="green" icon="fa fa-folder">
        @include('admin.mirrors._errors')
        <form method="post" action="{{ route('admin.cds.groups.update', ['cdGroup' => $group->id]) }}">
            @csrf
            @method('PUT')
            <table class="gr_tab" width="100%" cellpadding="10">
                <tr>
                    <td>عنوان القسم : </td>
                    <td><input type="text" name="title" value="{{ old('title', $group->title) }}" size="48" maxlength="100" required></td>
                </tr>
                <tr>
                    <td>القسم الرئيسي : </td>
                    <td>
                        <select name="parent_id">
                            @foreach ($parents as $option)
                                <option value="{{ $option['id'] }}" @selected(old('parent_id') !== null ? (int) old('parent_id') === $option['id'] : $option['selected'])>{{ $option['title'] }}</option>
                            @endforeach
                        </select>
                    </td>
                </tr>
                <tr>
                    <td>التعليق : </td>
                    <td><input type="text" name="Des" value="{{ old('Des', $group->des) }}" size="48"></td>
                </tr>
                <tr>
                    <td></td>
                    <td>
                        <button type="submit" class="btn green">تعديل القسم</button>
                        <a href="/cds-group-{{ $group->id }}.htm" class="btn default">رجوع</a>
                    </td>
                </tr>
            </table>
        </form>
    </x-admin-portlet>
@endsection
