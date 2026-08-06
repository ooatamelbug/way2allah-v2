@extends('layouts.admin')

@section('title', 'ساوندكلاود الرئيسية')

@section('content')
    <form method="post" action="{{ route('admin.soundcloud.update') }}">
        @csrf
        <label>رقم المقطع بالساوندكلاود <input type="text" name="soundcloud" value="{{ $trackId }}"></label>
        <button type="submit">تحديث</button>
    </form>
@endsection
