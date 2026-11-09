@extends('layouts.admin')

@section('title', 'إضافة تلاوة')

@section('content')
    {{-- OLD InsertTelawah() + add_telawa_form.html: the same fields, labels and defaults (audio checked, «لا يوجد» sura). --}}
    <x-admin-portlet :title="'إضافة تلاوة جديد ضمن قسم : '.$group->title" color="green" icon="fa fa-plus">
        @include('admin.mirrors._errors')
        <form method="post" action="{{ route('admin.telawah.groups.items.store', $group) }}" dir="rtl">
            @csrf
            <p><b>السورة : </b>
                <select name="sorah">
                    <option value="" @selected((string) old('sorah', '') === '')>لا يوجد</option>
                    @foreach ($suras as $sura)
                        <option value="{{ $sura->id }}" @selected((string) old('sorah') === (string) $sura->id)>{{ $sura->name }}</option>
                    @endforeach
                </select>
            </p>
            <p><b>عنوان التلاوة : </b><input type="text" name="title" value="{{ old('title') }}" size="30" maxlength="256" required></p>
            <p><b>نوع التلاوة : </b>
                <label><input type="radio" name="vedio" value="1" @checked(old('vedio') === '1')>مرئي</label>&nbsp;&nbsp;&nbsp;
                <label><input type="radio" name="vedio" value="0" @checked(old('vedio', '0') === '0')>صوتي</label>
            </p>
            <p><b>وصلة التلاوة : </b><input type="text" name="Telawahfile" value="{{ old('Telawahfile') }}" size="60" dir="ltr" required></p>
            <p><b>الحجم بالميجا بايت : </b><input type="text" name="Telawahsize" value="{{ old('Telawahsize') }}" size="8"></p>
            <p>التعليق على التلاوة</p>
            <textarea cols="50" rows="10" name="MoreInfo" maxlength="255" class="form-control">{{ old('MoreInfo') }}</textarea>
            <br>
            <button type="submit" class="btn green">أضف</button>
            <a href="/recite-group-{{ $group->id }}.htm" class="btn default">رجوع</a>
        </form>
    </x-admin-portlet>
@endsection
