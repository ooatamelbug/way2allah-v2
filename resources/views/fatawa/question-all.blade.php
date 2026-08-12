{{--
    answer.php / answer2.php equivalent. The two legacy files share this
    exact data (query + counter logic, `fatawa.md` §5) and differ only in
    markup/CSS/element ordering — which layout is canonical has not been
    decided (approved technical plan §3.4/§7). This view renders the same
    data neutrally: plain semantic markup, matching neither file's
    specific CSS class scheme nor its specific ordering of the
    action-icons/details-table. Not a final design — a placeholder that
    preserves behavior without silently resolving the open layout question.
--}}
@extends('layouts.app')

@section('title', $generalQuestionModel->question_text)

@section('content')
    <article>
        <h1>{{ $generalQuestionModel->question_text }}</h1>

        <section aria-label="إجابات المشايخ">
            @foreach ($answers as $answer)
                <div>
                    <h2>{{ $answer->author_prename }} {{ $answer->author_name }}</h2>
                    <div>{!! $answer->answer_text !!}</div>
                    <a href="/fatawa-download-{{ $answer->id }}.htm">تحميل</a>
                </div>
            @endforeach
        </section>
    </article>
@endsection
