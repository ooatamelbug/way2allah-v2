@extends('layouts.admin')

@section('title', 'تعديل موضوع')

@section('content')
    {{-- T2 «تعديل الموضوع» — OLD w2a_fatwa_edit() + fatwa_edit.htm: the same fields. The topic's category is not editable. --}}
    <x-admin-portlet :title="'تعديل موضوع '.$topic->topic_name" color="green" icon="fa fa-pencil-square-o">
        @include('admin.mirrors._errors')
        <form method="post" action="{{ route('admin.fatwa.topics.update', $topic) }}" dir="rtl">
            @csrf
            @method('PUT')
            <x-admin.contextual-return />
            <p><label>اسم الموضوع : </label><input type="text" name="topicname" value="{{ old('topicname', $topic->topic_name) }}" class="inputText" size="30" maxlength="255" required></p>
            @include('admin.fatwa._seo', ['record' => $topic])
            <button type="submit" class="btn green">موافق</button>
            <a href="/fatawa-group-{{ $topic->id }}-{{ $topic->parent_id }}.htm" class="btn default">( الرجوع للخلف )</a>
        </form>
    </x-admin-portlet>
@endsection
