@extends('layouts.app')

@section('title', $authorModel->prename.' '.$authorModel->name)

@section('content')
    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
            <section aria-label="الأسئلة التي أفتى بها الشيخ">
                <ul>
                    {{-- Legacy's own link target (auther-all-fatawa-*) has no
                         implementing file anywhere in this module — reproduced
                         as-is, not silently redirected to fatawa-all-*. --}}
                    @foreach ($generalQuestions as $question)
                        <li>
                            <a href="/auther-all-fatawa-{{ $authorModel->id }}-{{ $question->id }}.htm">{{ $question->question_text }}</a>
                            @if ($question->topic)
                                <span>{{ $question->topic->topic_name }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
                {{ $generalQuestions->links() }}
            </section>
        </div>

        <aside class="col-lg-3 col-md-4 col-sm-5 nopadding" aria-label="الشريط الجانبي">
            <h3>الأكثر تحميلا</h3>
            <ul>
                @foreach ($mostDownloaded as $item)
                    <li><a href="/fatawa-download-{{ $item->id }}.htm">{{ $item->question_text }}</a></li>
                @endforeach
            </ul>

            <h3>جديد المواد</h3>
            <ul>
                @foreach ($mostRecent as $item)
                    <li><a href="/fatawa-download-{{ $item->id }}.htm">{{ $item->question_text }}</a></li>
                @endforeach
            </ul>
        </aside>
    </div>
@endsection
