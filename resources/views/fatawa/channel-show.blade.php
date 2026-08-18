@extends('layouts.app')

@section('title', $channelModel->title)

@section('content')
    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
            {{--
                G-13-10 (media/visual parity phase): channel_fatawa.php:62-70
                — channel logo (flat images/channels/{id}.png, same
                convention as G-13-08) and a coverage-area "beam" image.
                The beam image is confirmed HARDCODED to images/beams/1.png
                in this specific page — NOT `$channel->beam`-driven like
                live-stream's own version (live-stream/show.blade.php) —
                reproduced as the real, if seemingly-unintentional, legacy
                behavior, not "corrected" to be per-channel dynamic.
            --}}
            <section aria-label="بيانات القناة">
                <img src="/images/channels/{{ $channelModel->id }}.png" alt="{{ $channelModel->title }}">
                <img src="/images/beams/1.png" alt="مجال التغطية">
            </section>

            <section aria-label="الفتاوى المضافة على القناة">
                <ul>
                    @foreach ($generalQuestions as $question)
                        <li>
                            <a href="/fatawa-all-{{ $question->id }}.htm">{{ $question->question_text }}</a>
                            @if ($question->topic)
                                <span>{{ $question->topic->topic_name }}</span>
                            @endif
                            @if ($question->author)
                                <span>{{ $question->author->prename }} {{ $question->author->name }}</span>
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
