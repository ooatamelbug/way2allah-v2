@extends('layouts.admin')

@section('title', 'تعديل قسم')

@section('content')
    {{-- OLD EditGroup() (modules/Anasheed/admin/index.php): the same fields, labels, defaults and parent choices. --}}
    <x-admin-portlet :title="'تعديل قسم '.$group->title" color="green" icon="fa fa-folder">
        @include('admin.mirrors._errors')
        <form method="post" action="{{ route('admin.anasheed.groups.update', $group) }}">
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
                    <td>اسم القناة</td>
                    <td>
                        <select name="Channel" size="1">
                            <option value="0">بدون قناة</option>
                            @foreach ($channels as $channel)
                                <option value="{{ $channel->id }}" @selected((int) old('Channel', $group->channel_id) === (int) $channel->id)>{{ $channel->title }}</option>
                            @endforeach
                        </select>
                    </td>
                </tr>
                <tr>
                    <td>التعليق : </td>
                    <td><input type="text" name="Des" value="{{ old('Des', $group->des) }}" size="48"></td>
                </tr>
                <tr>
                    <td>الوصف : </td>
                    <td><textarea cols="68" rows="10" name="description" class="form-control">{{ old('description', $group->description) }}</textarea></td>
                </tr>
                <tr><td colspan="2"><hr></td></tr>
                <tr><td colspan="2">SEO :</td></tr>
                <tr><td colspan="2"><textarea cols="80" rows="2" name="meta_keywords" placeholder="Meta Keywords" class="form-control">{{ old('meta_keywords', $group->meta_keywords) }}</textarea></td></tr>
                <tr><td colspan="2"><textarea cols="80" rows="5" name="meta_description" placeholder="Meta Description" class="form-control">{{ old('meta_description', $group->meta_description) }}</textarea></td></tr>
                <tr>
                    <td colspan="2">
                        <label><input type="checkbox" name="meta_index" value="1" @checked(old('meta_index', ! empty($group->meta_index)))> Index</label><br><br>
                        <label><input type="checkbox" name="meta_follow" value="1" @checked(old('meta_follow', ! empty($group->meta_follow)))> Follow</label>
                    </td>
                </tr>
                <tr>
                    <td></td>
                    <td>
                        <button type="submit" class="btn green">تعديل القسم</button>
                        <a href="/var-group-{{ $group->id }}.htm" class="btn default">رجوع</a>
                    </td>
                </tr>
            </table>
        </form>
    </x-admin-portlet>
@endsection
