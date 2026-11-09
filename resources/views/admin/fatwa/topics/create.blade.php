@extends('layouts.admin')

@section('title', 'إضافة موضوع')

@section('content')
    {{-- C1 «اضافة موضوع للتصنيف» — OLD w2a_fatwa_add() + fatwa_add.htm: the same fields and defaults. --}}
    <x-admin-portlet :title="'إضافة موضوع جديد تحت تصنيف '.$category->title" color="green" icon="fa fa-plus">
        @include('admin.mirrors._errors')
        <form method="post" action="{{ route('admin.fatwa.categories.topics.store', $category) }}" dir="rtl">
            @csrf
            <x-admin.contextual-return />
            <p><label>اسم الموضوع : </label><input name="title" type="text" value="{{ old('title') }}" class="inputText" size="30" maxlength="255" required></p>
            @include('admin.fatwa._seo', ['record' => null])
            <button type="submit" class="btn green">إرسال</button>
            <a href="/fatawa-topics-{{ $category->id }}-1.htm" class="btn default">( الرجوع للخلف )</a>
        </form>
    </x-admin-portlet>
@endsection
