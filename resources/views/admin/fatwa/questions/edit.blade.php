@extends('layouts.admin')

@section('title', 'تعديل بيانات سؤال')

@section('content')
    {{-- Q2 «تعديل السؤال» — OLD fatwa_edit_general_question() + fatwa_general_question_edit.htm: the same fields. The question's topics are not editable here. --}}
    <x-admin-portlet title="تعديل بيانات سؤال" color="green" icon="fa fa-pencil-square-o">
        @include('admin.mirrors._errors')
        <form method="post" action="{{ route('admin.fatwa.questions.update', $question) }}" dir="rtl">
            @csrf
            @method('PUT')
            <x-admin.contextual-return />
            <p><label>نص السؤال : </label><input name="title" type="text" value="{{ old('title', $question->question_text) }}" size="60" maxlength="255" required></p>
            @include('admin.fatwa._seo', ['record' => $question])
            <button type="submit" class="btn green">تعديل</button>
            <a href="/fatawa-all-{{ $question->id }}.htm" class="btn default">( الرجوع للخلف )</a>
        </form>
    </x-admin-portlet>
@endsection
