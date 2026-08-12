@extends('layouts.app')

@section('title', 'أحدث 50 فتوى مرئية')

@section('content')
    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
            <section aria-label="أحدث الفتاوى">
                <ul>
                    @foreach ($latestQuestions as $question)
                        @php
                            $generalQuestionId = str_starts_with((string) $question->general_question_id, '|')
                                ? explode('|', $question->general_question_id)[1]
                                : $question->general_question_id;
                        @endphp
                        <li>
                            <a href="/fatawa-all-{{ $generalQuestionId }}.htm">{{ $question->question_text }}</a>
                            <a href="/auther-questions-{{ $question->auth_id }}.htm">{{ $question->auth_prename }} {{ $question->auth_name }}</a>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>

        {{-- more.php:82 calls mostdownload(0,0,$id) with an undefined $id
             — confirmed dead, always renders an empty box. Reproduced as
             empty, not a real widget. --}}
        <aside class="col-lg-3 col-md-4 col-sm-5 nopadding" aria-label="الشريط الجانبي">
            <h3>الأكثر تحميلا</h3>
            <ul></ul>
        </aside>
    </div>
@endsection
