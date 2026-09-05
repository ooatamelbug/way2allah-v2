@extends('layouts.app')

@section('title', 'الطريق إلى الله - طريقك نحو معرفة الله')

{{--
    G-02 (Migration Gap Register) — replaces `index.php` + `new_content.php`.
    Full trace: "Homepage Migration — Implementation Blueprint". Section
    numbers below match that document's Section-by-Section Migration
    Matrix (§1-§17) and `new_content.php`'s own comment markers
    (BEGIN/END SERVICE BOX, BEGIN/END BLOCKQUOTE BLOCK, BEGIN CLIENTS).

    NOT reproduced (confirmed dead in `new_content.php`, not this task's
    scope): the group=158/98 duplicate `listvars()` calls, `w2a_index_files()`
    ("رسائل دعوية"), the BEGIN STEPS banner montage, `list_latest_cds()`
    ("جديد الإسطوانات"), RECENT WORKS, and TABS/TESTIMONIALS.
--}}

@php use App\Domain\Content\Support\LegacyTextTruncator; @endphp

{{--
    G-13-06 (media/visual parity phase): `slider.php`, included from
    `header.php:532-535` only when `$display_slider` is set — confirmed
    (exhaustive grep) that `index.php:6` is the ONLY place that assignment
    is live; every other file's copy of the same line is commented out.
    Query, image path, click-through `url`, and `title`/alt reproduced
    exactly from `slider.php:3,18-22` — `image` already stores the full
    `media/7amlat/slide_*.jpg` path, not a bare filename.
--}}
@if ($slides->isNotEmpty())
    @section('slider')
        <div class="page-slider container" aria-roledescription="carousel" aria-label="أبرز محتوى الطريق إلى الله">
            <div class="w2a-hero-slider-wrap">
                <div class="w2a-hero-slider-track">
                    @foreach ($slides as $slide)
                        <div class="w2a-hero-slide {{ $loop->first ? 'active' : '' }}" data-slide-index="{{ $loop->index }}"
                            aria-hidden="{{ $loop->first ? 'false' : 'true' }}">
                            <a href="{{ $slide->url }}" class="w2a-hero-slide-link">
                                <img src="{{ \App\Domain\Content\Support\MediaUrl::asset($slide->image) }}"
                                    alt="{{ $slide->title }}" decoding="async"
                                    @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif>
                                <div class="w2a-hero-overlay">
                                    <div class="w2a-hero-caption">
                                        <h2 class="w2a-hero-title">{{ $slide->title }}</h2>
                                        <span class="w2a-hero-cta">عرض المزيد <i class="fa fa-arrow-left"
                                                aria-hidden="true"></i></span>
                                    </div>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
                @if ($slides->count() > 1)
                    <button type="button" class="w2a-hero-arrow w2a-hero-prev" aria-label="الشريحة السابقة"
                        title="السابق"><i class="fa fa-chevron-right" aria-hidden="true"></i></button>
                    <button type="button" class="w2a-hero-arrow w2a-hero-next" aria-label="الشريحة التالية"
                        title="التالي"><i class="fa fa-chevron-left" aria-hidden="true"></i></button>
                    <div class="w2a-hero-dots" aria-label="اختيار الشريحة">
                        @foreach ($slides as $slide)
                            <button type="button" class="w2a-hero-dot {{ $loop->first ? 'active' : '' }}"
                                data-dot-index="{{ $loop->index }}" aria-label="الشريحة {{ $loop->iteration }}"
                                aria-current="{{ $loop->first ? 'true' : 'false' }}"></button>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endsection
@endif

@section('content')

    {{-- BEGIN SERVICE BOX (sections 1-2: static text cards + blockquote CTA) --}}
    <div class="row service-box top-section-card">
        <div class="col-md-4 col-sm-4">
            <div class="service-box-card">
                <div class="service-box-heading"> <em><i class="fa fa-book blue"></i></em> <span>معاني الآيات</span> </div>
                <p> وَقَالُوا اتَّخَذَ اللَّهُ وَلَدًا ۗ سُبْحَانَهُ ۖ بَل لَّهُ مَا فِي السَّمَاوَاتِ وَالْأَرْضِ ۖ كُلٌّ
                    لَّهُ قَانِتُونَ [البقرة : 116]
                    { وَقَالُوا } أي: اليهود والنصارى والمشركون, وكل من قال ذلك: { اتَّخَذَ اللَّهُ وَلَدًا } فنسبوه إلى ما
                    لا يليق بجلاله, وأساءوا كل الإساءة, وظلموا أنفسهم.</p>
            </div>
        </div>
        <div class="col-md-4 col-sm-4">
            <div class="service-box-card">
                <div class="service-box-heading"> <em><i class="fa fa-group blue"></i></em> <span>حديث شريف</span> </div>
                <p>قال رسول الله صلى الله عليه وسلم: "لا تباغضوا ولا تحاسدوا ولا تدابروا وكونوا عباد الله إخوانًا ولا يحل
                    لمسلم أن يهجر أخاه فوق ثلاثة أيام"</p>
            </div>
        </div>
        <div class="col-md-4 col-sm-4">
            <div class="service-box-card">
                <div class="service-box-heading"> <em><i class="fa fa-comments blue"></i></em> <span>قول مأثور</span> </div>
                <p>كيف أضحك والأقصى أسير!!!<br>
                    قول ل الناصر صلاح الدين و لم يرى يضحك بعدها إلا حينما حرر القدس وبيت المقدس فى يوم في 11 رجب سنة 583 هـ،
                    وقال : الآن أضحك.</p>
            </div>
        </div>
    </div>
    {{-- END SERVICE BOX --}}

    {{-- BEGIN BLOCKQUOTE BLOCK --}}
    <div class="row quote-v1 margin-bottom-30">
        <div class="col-md-9"> <span>موقع الطريق إلى الله - طريقك نحو معرفة الله</span> </div>
        <div class="col-md-3 text-right"> <a class="btn-transparent"
                href="https://docs.google.com/forms/d/e/1FAIpQLSey90EU6LJY9pTm6qsRSgDOVZPeSNmgz8vrh4jwRVdTnNRGIQ/viewform?usp=sf_link"
                target="_blank"><i class="fa fa-rocket margin-right-10"></i>انضم إلينا الآن </a> </div>
    </div>
    {{-- END BLOCKQUOTE BLOCK --}}

    <div class="row service-box w2a-equal-height-row">
        {{-- Section 3: أحدث المرئيات على مدار الساعة --}}
        <x-home.section-card title="أحدث المرئيات على مدار الساعة" icon="fa-video-camera" color="blue home" width="4"
            width-sm="6">
            <ul class="vars">
                @foreach ($videos as $item)
                    <li>
                        <a href="/khotab-item-{{ $item->id }}.htm" title="{{ $item->title }}" class="tt">
                            <img src="{{ $item->thumb }}" title="{{ $item->title }}" alt="{{ $item->title }}"
                                width="72" height="50">
                            <div class="w2a-var-copy">
                                <span>{{ LegacyTextTruncator::words((string) $item->title, 90) }}</span><br>
                                <small>{{ $item->prename }} {{ $item->name }}</small>
                                @if (isset($item->timeLabel))
                                    <span class="var_time">{{ $item->timeLabel }}</span>
                                @endif
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="mooore"><a href="/khotab-video_news.htm" class="mo">المزيد ..</a></div>
        </x-home.section-card>

        {{-- Section 4: برامج حصرية لشبكة الطريق إلى الله --}}
        <x-home.section-card title="برامج حصرية لشبكة الطريق إلى الله" icon="fa-spinner" color="blue home" width="4"
            width-sm="6">
            <ul class="vars">
                @foreach ($cat487 as $item)
                    @php
                        $logo = match ((int) $item->id) {
                            613 => '/images/logos/Salon.gif',
                            612 => '/images/logos/wasabeko.gif',
                            611 => '/images/logos/LwAreftmoh.gif',
                            610 => '/images/logos/AlamtanyAya.gif',
                            609 => '/images/logos/KadaShbab.gif',
                            603 => '/images/logos/AbgadiaAsaria.gif',
                            601 => '/images/logos/RamdanKarab.gif',
                            592 => '/images/logos/ayatTotla.gif',
                            562 => '/images/logos/AnRab.gif',
                            618 => '/images/logos/RamdanKarab6.gif',
                            default => '/images/tvnoise.gif',
                        };
                    @endphp
                    <li>
                        <a href="/category-{{ $item->id }}.htm" title="{{ $item->title }}" class="tt">
                            <img src="{{ $logo }}" title="{{ $item->title }}" alt="{{ $item->title }}"
                                width="72" height="50">
                            <span>{{ $item->title }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="mooore"><a href="/category-487.htm" class="mo">المزيد ..</a></div>
        </x-home.section-card>

        {{-- Section 5: أحدث الفتاوى المرئية --}}
        <x-home.section-card title="أحدث الفتاوى المرئية" icon="fa-play-circle" color="blue home" width="4"
            width-sm="6">
            <ul class="vars">
                @foreach ($fatawas as $item)
                    <li>
                        <a href="/fatawa-all-{{ $item->linkId }}.htm" title="{{ $item->question_text }}"
                            class="tt">
                            <img src="/images/tvnoise.gif" title="{{ $item->question_text }}"
                                alt="{{ $item->question_text }}" width="72" height="50">
                            <div class="w2a-var-copy">
                                <span>{{ LegacyTextTruncator::chars((string) $item->question_text, 110, '..') }}</span><br>
                                <small>{{ $item->prename }} {{ $item->name }}</small>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="mooore"><a href="/more-fatawa.htm" class="mo">المزيد ..</a></div>
        </x-home.section-card>

        {{-- Section 6: مقاطع حصرية (parent=158) --}}
        <x-home.section-card title="مقاطع حصرية" icon="fa-cut" color="blue home" width="4" width-sm="6">
            <ul class="vars">
                @forelse ($exclusive158 as $item)
                    <li>
                        <a href="/var-item-{{ $item->id }}.htm" title="{{ $item->title }}">
                            <img src="{{ $item->thumb }}" alt="{{ $item->title }}" width="72" height="50">
                            <span>{{ LegacyTextTruncator::words((string) $item->title, 90) }}
                                <br />
                                <small>{{ $item->group_title }}</small>
                            </span>
                        </a>
                    </li>
                @empty
                    <li>لا يوجد مواد حالياَ</li>
                @endforelse
            </ul>
            <div class="mooore"><a href="/exclusive-news.htm" class="mo">المزيد ..</a></div>
        </x-home.section-card>

        {{-- Section 7: مقاطع Youtube --}}
        <x-home.section-card title="مقاطع Youtube" icon="fa-youtube" color="blue home" width="4" width-sm="6">
            @if ($youtube['empty'])
                لا توجد مقاطع مضافة بعد
            @else
                <iframe width="100%" height="210"
                    src="https://www.youtube.com/embed/{{ $youtube['id'] }}?rel=0&amp;showinfo=0" frameborder="0"
                    allow="autoplay; encrypted-media" allowfullscreen></iframe>
            @endif
        </x-home.section-card>

        {{-- Section 8: مقاطع SoundCloud --}}
        <x-home.section-card title="مقاطع SoundCloud" icon="fa-soundcloud" color="blue home" width="4"
            width-sm="6">
            @if ($soundcloud['empty'])
                لا توجد مقاطع مضافة بعد
            @else
                <iframe width="100%" height="210" scrolling="no" frameborder="no"
                    src="https://w.soundcloud.com/player/?url=https%3A//api.soundcloud.com/tracks/{{ $soundcloud['id'] }}&amp;auto_play=false&amp;hide_related=false&amp;show_comments=true&amp;show_user=true&amp;show_reposts=false&amp;visual=true"></iframe>
            @endif
        </x-home.section-card>
    </div>

    <div class="row service-box w2a-equal-height-row">
        {{-- Section 9: جديد التلاوات --}}
        <x-home.section-card title="جديد التلاوات" icon="fa-quran" color="blue home" width="4" width-sm="6">
            <ul class="homecss">
                @forelse ($telawahs as $item)
                    <x-home.media-link href="/recite-item-{{ $item->id }}.htm" :title="$item->title" :subtitle="$item->group_title"
                        icon="fa-book" />
                @empty
                    <li class="w2a-empty-state">لا توجد تلاوات مضافة حاليًا</li>
                @endforelse
            </ul>
            <div class="mooore"><a href="/recite-news.htm" class="mo">المزيد ..</a></div>
        </x-home.section-card>

        {{-- Section 10: جديد الصوتيات --}}
        <x-home.section-card title="جديد الصوتيات" icon="fa-volume-up" color="blue home" width="4" width-sm="6">
            <ul class="homecss">
                @forelse ($audios as $item)
                    <x-home.media-link href="/khotab-item-{{ $item->id }}.htm" :title="$item->title" :subtitle="trim($item->prename . ' ' . $item->name)"
                        icon="fa-volume-up" />
                @empty
                    <li class="w2a-empty-state">لا توجد صوتيات مضافة حاليًا</li>
                @endforelse
            </ul>
            <div class="mooore" style="margin-top:-1px;"><a href="/khotab-audio_news.htm" class="mo">المزيد ..</a>
            </div>
        </x-home.section-card>

        {{-- جديد الأفلام الوثائقية (parent=12) --}}
        <x-home.section-card title="جديد الأفلام الوثائقية" icon="fa-film" color="blue home" width="4"
            width-sm="6">
            <ul class="vars">
                @forelse ($documentary12 as $item)
                    <li>
                        <a href="/var-item-{{ $item->id }}.htm" title="{{ $item->title }}">
                            <img src="{{ $item->thumb }}" alt="{{ $item->title }}" width="72" height="50">
                            <span>{{ LegacyTextTruncator::words((string) $item->title, 90) }}
                                <br />
                                <small>{{ $item->group_title }}</small>
                            </span>
                        </a>
                    </li>
                @empty
                    <li>لا يوجد مواد حالياَ</li>
                @endforelse
            </ul>
            <div class="mooore"><a href="/documentary-news.htm" class="mo">المزيد ..</a></div>
        </x-home.section-card>
    </div>

    <div class="row service-box w2a-equal-height-row">
        {{-- جديد الكارتون (parent=57) --}}
        <x-home.section-card title="جديد الكارتون" icon="fa-child" color="blue home" width="4" width-sm="6">
            <ul class="vars">
                @forelse ($cartoon57 as $item)
                    <li>
                        <a href="/var-item-{{ $item->id }}.htm" title="{{ $item->title }}">
                            <img src="{{ $item->thumb }}" alt="{{ $item->title }}" width="72" height="50">
                            <span>{{ LegacyTextTruncator::words((string) $item->title, 90) }}
                                <br />
                                <small>{{ $item->group_title }}</small>
                            </span>
                        </a>
                    </li>
                @empty
                    <li>لا يوجد مواد حالياَ</li>
                @endforelse
            </ul>
            <div class="mooore"><a href="/cartoon-news.htm" class="mo">المزيد ..</a></div>
        </x-home.section-card>

        {{-- أحدث المواد المفرغة --}}
        <x-home.section-card title="أحدث المواد المفرغة" icon="fa-book" color="blue home" width="4"
            width-sm="6">
            <ul class="vars">
                @foreach ($dumpFiles as $item)
                    <li>
                        <a href="/khotab-item-{{ $item->id }}.htm" title="{{ $item->title }}" class="tt">
                            <img src="{{ $item->thumb }}" title="{{ $item->title }}" alt="{{ $item->title }}"
                                width="72" height="50">
                            <div>
                                <span>{{ LegacyTextTruncator::words((string) $item->title, 65) }}</span><br>
                                <small>{{ $item->prename }} {{ $item->name }}</small>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="mooore"><a href="/dumped-lectures.htm" class="mo">المزيد ..</a></div>
        </x-home.section-card>

        {{-- التصويت --}}
        <x-home.section-card title="التصويت" icon="fa-check-square-o" color="blue home w2a_polls" body-class="nopadding"
            width="4" width-sm="6">
            @if ($pollData)
                <form action="/survey-vote-{{ $pollData['poll']->pollID }}.htm" method="post" class="w2a-poll-form">
                    <input type="hidden" name="pollID" value="{{ $pollData['poll']->pollID }}" />
                    <p id="title" class="w2a-poll-title"><a
                            href="/survey-results-{{ $pollData['poll']->pollID }}.htm">{{ $pollData['poll']->pollTitle }}</a>
                    </p>
                    <ul class="homecss w2a-poll-options">
                        @foreach ($pollData['options'] as $option)
                            <li class="w2a-poll-item">
                                <label for="vot{{ $option->voteID }}" class="w2a-poll-label">
                                    <input type="radio" name="voteID" id="vot{{ $option->voteID }}"
                                        value="{{ $option->voteID }}" class="w2a-poll-radio">
                                    <span>{{ $option->optionText }}</span>
                                </label>
                            </li>
                        @endforeach
                    </ul>
                    <div class="w2a-poll-action">
                        <button class="w2a_submit w2a-poll-submit-btn" type="submit">تصــويت</button>
                    </div>
                    <div class="w2a-poll-meta">
                        <span><i class="fa fa-users" aria-hidden="true"></i> المشاركين :
                            {{ $pollData['totalVotes'] }}</span>
                        <span><i class="fa fa-comments" aria-hidden="true"></i> التعليقات:
                            {{ $pollData['commentsDisplay'] }}</span>
                    </div>
                </form>
            @endif
        </x-home.section-card>
    </div>

    {{-- BEGIN NOW WATCHING --}}
    <div class="row w2a-now-watching">
        <div class="col-md-3 w2a-now-watching-header">
            <h2>أكثر المواد مشاهدة</h2>
            <p>قائمة بأكثر المواد تفاعلاً ومشاهدة في الوقت الحالي على موقع الطريق إلى الله</p>
            <div class="w2a-rail-nav">
                <button type="button" class="w2a-rail-btn w2a-rail-prev" aria-label="المواد السابقة" title="السابق"><i
                        class="fa fa-chevron-right" aria-hidden="true"></i></button>
                <button type="button" class="w2a-rail-btn w2a-rail-next" aria-label="المواد التالية" title="التالي"><i
                        class="fa fa-chevron-left" aria-hidden="true"></i></button>
            </div>
        </div>
        <div class="col-md-9 w2a-now-watching-body">
            <div class="w2a-now-watching-rail" tabindex="0" aria-label="أكثر المواد مشاهدة">
                @forelse ($trending as $item)
                    @php $title = str_replace('"', "''", (string) $item->title); @endphp
                    <article class="w2a-watching-card">
                        <a href="/var-item-{{ $item->id }}.htm" class="w2a-watching-thumb-link">
                            <div class="w2a-watching-thumb-wrap">
                                <img src="{{ $item->thumb }}" alt="{{ $title }}" width="280" height="180"
                                    loading="lazy" decoding="async">
                                <span class="w2a-watching-play-badge" aria-hidden="true"><i
                                        class="fa fa-play"></i></span>
                            </div>
                            <div class="w2a-watching-info">
                                <h4 class="w2a-watching-title">{{ $title }}</h4>
                            </div>
                        </a>
                    </article>
                @empty
                    <p class="w2a-empty-state">لا توجد مواد متاحة حاليًا</p>
                @endforelse
            </div>
        </div>
    </div>
    {{-- END NOW WATCHING --}}

@endsection
