{{--
    fatwa-today.php equivalent. The client-side month-grid calendar widget
    and the fatwa-date-{d}-{m}-{y}-{page}.htm route it links to are NOT
    reproduced here — no file in this module confirms what implements that
    parameter shape (see FatwaDayController's docblock). Only the
    confirmed "today" behavior is rendered.
--}}
@extends('layouts.app')

@section('title', 'الفتاوى بتاريخ الإضافة')

@section('content')
    <div class="row service-box">
        <div class="col-xs-12 col-sm-5">
            <section aria-label="فتاوى مختارة">
                <ul>
                    @foreach ($featured as $item)
                        <li><a href="/fatawa-all-{{ str_replace('|', '', $item->general_question_id) }}.htm">{{ $item->question_text }}</a></li>
                    @endforeach
                </ul>
            </section>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <h2>الفتاوى المضافة بتاريخ {{ $displayDate }}</h2>
            <ul>
                @foreach ($questions as $question)
                    <li>
                        <a href="/fatawa-{{ $question->id }}.htm">{{ $question->question_text }}</a>
                        <span>{{ $question->auth_prename }} {{ $question->auth_name }}</span>
                    </li>
                @endforeach
            </ul>
            {{ $questions->links() }}
        </div>
    </div>
@endsection
