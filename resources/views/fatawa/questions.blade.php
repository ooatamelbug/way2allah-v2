@extends('layouts.app')

@section('title', $topicModel->topic_name)

@section('content')
    <nav aria-label="مسار التصنيف">
        <a href="/fatawa-topics-{{ $categoryModel->id }}-1.htm">{{ $categoryModel->title }}</a>
    </nav>

    <section aria-label="الأسئلة">
        <ul>
            @foreach ($generalQuestions as $question)
                <li><a href="/fatawa-all-{{ $question->id }}.htm">{{ $question->question_text }}</a></li>
            @endforeach
        </ul>
        {{ $generalQuestions->links() }}
    </section>
@endsection
