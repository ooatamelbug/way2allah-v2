@extends('layouts.admin')

@section('title', 'تعديل تلاوة')

@section('content')
    {{-- OLD EditTelawah() + edit_telawah.htm: the same fields, labels, defaults and group choices. --}}
    <x-admin-portlet :title="'تعديل تلاوة '.$item->title" color="green" icon="fa fa-pencil-square-o">
        @include('admin.mirrors._errors')
        <form method="post" action="{{ route('admin.telawah.items.update', $item) }}" dir="rtl">
            @csrf
            @method('PUT')
            @php($sorah = (string) old('sorah', (int) $item->sorah > 0 ? (string) $item->sorah : ''))
            <p><b>السورة : </b>
                <select name="sorah">
                    <option value="" @selected($sorah === '')>لا يوجد</option>
                    @foreach ($suras as $sura)
                        <option value="{{ $sura->id }}" @selected($sorah === (string) $sura->id)>{{ $sura->name }}</option>
                    @endforeach
                </select>
            </p>
            <p><b>عنوان التلاوة : </b><input type="text" name="title" value="{{ old('title', $item->title) }}" size="30" maxlength="256" required></p>
            @php($vedio = (string) old('vedio', (int) $item->vedio === 1 ? '1' : '0'))
            <p><b>نوع التلاوة : </b>
                <label><input type="radio" name="vedio" value="1" @checked($vedio === '1')>مرئى</label>&nbsp;&nbsp;&nbsp;
                <label><input type="radio" name="vedio" value="0" @checked($vedio === '0')>صوتى</label>
            </p>
            <p><b>وصلة التلاوة : </b><input type="text" name="Telawah_link" value="{{ old('Telawah_link', $item->link) }}" size="100" dir="ltr" required></p>
            <p><b>الحجم بالميجا بايت : </b><input type="text" name="Telawahsize" value="{{ old('Telawahsize', $sizeField) }}" size="8"></p>
            <p>التعليق على التلاوة</p>
            <textarea cols="40" rows="15" name="Des" maxlength="255" class="form-control">{{ old('Des', $item->notes) }}</textarea>
            <p><b>القسم: </b>
                <select name="group">
                    @foreach ($groups as $option)
                        <option value="{{ $option['id'] }}" @selected(old('group') !== null ? (int) old('group') === $option['id'] : $option['selected'])>{{ $option['title'] }}</option>
                    @endforeach
                </select>
            </p>
            <button type="submit" class="btn green">تعديل التلاوة</button>
            <a href="/recite-item-{{ $item->id }}.htm" class="btn default">رجوع</a>
        </form>
    </x-admin-portlet>
@endsection
