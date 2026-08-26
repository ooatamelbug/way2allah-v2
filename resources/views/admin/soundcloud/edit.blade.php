@extends('layouts.admin')

@section('title', 'ساوندكلاود الرئيسية')

@section('content')
    <x-admin-portlet title="مقطع الساوندكلاود">
        <form method="post" action="{{ route('admin.soundcloud.update') }}">
            @csrf
            <label>رقم المقطع بالساوندكلاود <input type="text" name="soundcloud" value="{{ $trackId }}" class="form-control"></label>
            <button type="submit" class="btn green">تحديث</button>
        </form>
    </x-admin-portlet>
@endsection
