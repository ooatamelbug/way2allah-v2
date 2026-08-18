@extends('layouts.app')

{{-- Content structure copied from legacy radio/index.php. The actual audio
     player widget (scripts/w2a_radio.js, css/w2a_radio.css) is a frontend
     asset concern, not ported here — see RadioController's docblock for
     what this page does and does not reproduce. --}}

@section('title', 'راديو الطريق الى الله')

@section('content')
    <div class="row service-box margin-bottom-40 sh-w2a-block" id="w2a_radio">
        <div class="col-xs-12 col-sm-8 col-md-8 col-lg-8 nopadding">
            <section aria-label="قائمة التشغيل الحالية">
                <ul class="playlist">
                    @foreach ($playlist as $item)
                        <li audiourl="{{ $item->audio_url }}" artist="{{ trim($item->prename.' '.$item->author_name) }}" id="li_{{ $item->khid }}_{{ $item->pl_section }}">{{ $item->title }}</li>
                    @endforeach
                </ul>
            </section>
        </div>

        <aside class="col-xs-12 col-sm-4 col-md-4 col-lg-4 nopadding" aria-label="الشريط الجانبي">
            <h3>جديد المواد المرئية</h3>
            <ul>
                @foreach ($newestVideo as $item)
                    <li class="media">
                        <a class="pull-left" href="/khotab-item-{{ $item->id }}.htm"><img class="media-object" src="{{ $item->thumb }}" alt="{{ $item->title }}" style="width: 60px; height: 40px;"></a>
                        <div class="media-body"><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></div>
                    </li>
                @endforeach
            </ul>

            <h3>جديد المواد الصوتية</h3>
            <ul>
                @foreach ($newestAudio as $item)
                    <li class="media">
                        <a class="pull-left" href="/khotab-item-{{ $item->id }}.htm"><img class="media-object" src="{{ $item->thumb }}" alt="{{ $item->title }}" style="width: 60px; height: 40px;"></a>
                        <div class="media-body"><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></div>
                    </li>
                @endforeach
            </ul>
        </aside>
    </div>
@endsection
