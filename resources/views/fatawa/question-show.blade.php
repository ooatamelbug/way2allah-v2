@extends('layouts.app')

@section('title', $fatwaQuestion->question_text)

@section('content')
    @if ($categoryModel)
        <nav aria-label="مسار التصنيف">
            <a href="/fatawa-topics-{{ $categoryModel->id }}.htm">{{ $categoryModel->title }}</a>
        </nav>
    @endif

    <article>
        <h1>{{ $fatwaQuestion->question_text }}</h1>

        @if ($fatwaQuestion->author)
            <p aria-label="الشيخ">{{ $fatwaQuestion->author->prename }} {{ $fatwaQuestion->author->name }}</p>
        @endif

        <div aria-label="الإجابة">{!! $fatwaQuestion->answer_text !!}</div>

        <a href="/fatawa-download-{{ $fatwaQuestion->id }}.htm">تحميل</a>
    </article>
@endsection
