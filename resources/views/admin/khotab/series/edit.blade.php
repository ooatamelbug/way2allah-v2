@extends('layouts.admin')

@section('title', 'تعديل سلسلة')

@section('content')
    {{-- OLD EditSeries() + admin_edit_series.htm (modules/Khotab/admin/index.php:2034): the same fields, labels and defaults. --}}
    <x-admin-portlet title="تعديل سلسلة" color="green" icon="fa fa-list-ul">
        @include('admin.mirrors._errors')
        <form method="post" action="{{ route('admin.khotab.series.update', $series) }}">
            @csrf
            @method('PUT')
            <p dir="rtl">
                <b>عنوان السلسلة : </b>
                <input type="text" name="title" value="{{ old('title', $series->title) }}" size="48" maxlength="100"><br><br>
                <label><input type="checkbox" name="C1" value="1" @checked(old('C1', (int) $series->hidden === 1))>مخفي</label><br><br>
                <label><input type="checkbox" name="ramadan" value="1" @checked(old('ramadan', (int) $series->ramadan === 1))>برامج رمضان</label><br>
            </p>
            <p dir="rtl">
                <b>المجموعة : </b>
                <select name="group">
                    <option value="0">بدون مجموعة</option>
                    @foreach ($groups as $group)
                        <option value="{{ $group->id }}" @selected((int) old('group', $series->group_id) === (int) $group->id)>{{ $group->title }}</option>
                    @endforeach
                </select><br>
                *تغيير المجموعة للسلسلة قد يودي إلى تغيير القناة تلقائيا
            </p>
            <p dir="rtl">
                <b>القناة : </b>
                @if ((int) $series->group_id === 0)
                    <select name="Channel" size="1">
                        <option value="0">بدون قناة</option>
                        @foreach ($channels as $channel)
                            <option value="{{ $channel->id }}" @selected((int) old('Channel', $series->channel_id) === (int) $channel->id)>{{ $channel->title }}</option>
                        @endforeach
                    </select>
                @else
                    {{ $channelTitle }}
                @endif
                <br>
                *لا يمكنك تغيير القناة إلا في السلاسل التى لا تتبع أي مجموعة
            </p>
            <p><b>وصف السلسلة :</b></p>
            <textarea cols="68" rows="10" name="description" class="form-control">{{ old('description', $series->description) }}</textarea>
            <hr>
            <p><b>SEO :</b></p>
            <textarea cols="68" rows="2" name="meta_keywords" placeholder="Meta Keywords" class="form-control">{{ old('meta_keywords', $series->meta_keywords) }}</textarea>
            <br>
            <textarea cols="68" rows="5" name="meta_description" placeholder="Meta Description" class="form-control">{{ old('meta_description', $series->meta_description) }}</textarea>
            <br>
            <label><input type="checkbox" name="meta_index" value="1" @checked(old('meta_index', ! empty($series->meta_index)))> Index</label><br>
            <label><input type="checkbox" name="meta_follow" value="1" @checked(old('meta_follow', ! empty($series->meta_follow)))> Follow</label>
            <hr>
            <p><b>التعليق :</b></p>
            <textarea cols="68" rows="10" name="Des" class="form-control">{{ old('Des', $series->des) }}</textarea>
            <br>
            <button type="submit" class="btn green">تعديل السلسلة</button>
            <a href="/khotab-series-{{ $series->id }}.htm" class="btn default">رجوع</a>
        </form>
    </x-admin-portlet>
@endsection
