@extends('layouts.admin')

@section('title', 'إضافة سؤال')

@section('content')
    {{-- T1 «اضافة سؤال للموضوع» — OLD fatwa_add_general_question() + fatwa_general_question_add.htm: the same fields and defaults. --}}
    <x-admin-portlet :title="'إضافة سؤال الى موضوع '.$topic->topic_name" color="green" icon="fa fa-plus">
        @include('admin.mirrors._errors')
        <form method="post" action="{{ route('admin.fatwa.topics.questions.store', $topic) }}" dir="rtl">
            @csrf
            <x-admin.contextual-return />
            <p><label>نص السؤال : </label><input name="title" type="text" value="{{ old('title') }}" size="60" maxlength="255" required></p>
            @include('admin.fatwa._seo', ['record' => null])
            <button type="submit" class="btn green">اضف</button>
            <a href="/fatawa-group-{{ $topic->id }}-{{ $topic->parent_id }}.htm" class="btn default">( الرجوع للخلف )</a>
        </form>
    </x-admin-portlet>
@endsection
