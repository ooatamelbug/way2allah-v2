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
@if($slides->isNotEmpty())
    @push('styles')
        <link href="/assets/global/plugins/fancybox/source/jquery.fancybox.css" rel="stylesheet">
        <link href="/assets/global/plugins/carousel-owl-carousel/owl-carousel/owl.carousel-rtl.css" rel="stylesheet">
        <link href="/assets/global/plugins/slider-revolution-slider/rs-plugin/css/settings.css" rel="stylesheet">
    @endpush

    @section('slider')
        <!-- BEGIN SLIDER -->
        <div class="page-slider">
            <div class="fullwidthbanner-container revolution-slider">
                <div class="fullwidthabnner">
                    <ul id="revolutionul">
                        @foreach($slides as $slide)
                            <li data-transition="fade" data-slotamount="8" data-masterspeed="700" data-delay="9400" data-thumb="/{{ $slide->image }}">
                                <a href="{{ $slide->url }}">
                                    <img src="/{{ $slide->image }}" alt="{{ $slide->title }}" width="100%">
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    <div class="tp-bannertimer tp-bottom"></div>
                </div>
            </div>
        </div>
        <!-- END SLIDER -->
    @endsection

    @push('scripts')
        <!-- BEGIN RevolutionSlider -->
        <script src="/assets/global/plugins/slider-revolution-slider/rs-plugin/js/jquery.themepunch.plugins.min.js" type="text/javascript"></script>
        <script src="/assets/global/plugins/slider-revolution-slider/rs-plugin/js/jquery.themepunch.revolution.min.js" type="text/javascript"></script>
        <script src="/assets/global/plugins/slider-revolution-slider/rs-plugin/js/jquery.themepunch.tools.min.js" type="text/javascript"></script>
        <script src="/assets/frontend/pages/scripts/revo-slider-init.js" type="text/javascript"></script>
        <!-- END RevolutionSlider -->

        {{--
            Batch S (homepage slider investigation): index.php's own
            initialization call, confirmed present live right after the 4
            RevolutionSlider script tags above but never ported —
            revo-slider-init.js only *defines* RevosliderInit.initRevoSlider(),
            it never calls itself. Without this call, jQuery('.fullwidthabnner')
            .revolution({...}) never runs, so the slider's <li> slides stay in
            plain document flow (stacked vertically) instead of being
            initialized into a carousel. This is the entire fix — same
            @push('scripts') stack, so it still runs after jquery.min.js.
        --}}
        <script type="text/javascript">
            jQuery(document).ready(function() {
                RevosliderInit.initRevoSlider();
            });
        </script>
    @endpush
@endif

@section('content')

    {{-- BEGIN SERVICE BOX (sections 1-2: static text cards + blockquote CTA) --}}
    <div class="row service-box margin-bottom-40">
        <div class="col-md-4 col-sm-4">
            <div class="service-box-heading"> <em><i class="fa fa-book blue"></i></em> <span>معاني الآيات</span> </div>
            <p> وَقَالُوا اتَّخَذَ اللَّهُ وَلَدًا ۗ سُبْحَانَهُ ۖ بَل لَّهُ مَا فِي السَّمَاوَاتِ وَالْأَرْضِ ۖ كُلٌّ لَّهُ قَانِتُونَ [البقرة : 116]
                { وَقَالُوا } أي: اليهود والنصارى والمشركون, وكل من قال ذلك: { اتَّخَذَ اللَّهُ وَلَدًا } فنسبوه إلى ما لا يليق بجلاله, وأساءوا كل الإساءة, وظلموا أنفسهم.</p>
        </div>
        <div class="col-md-4 col-sm-4">
            <div class="service-box-heading"> <em><i class="fa fa-group blue"></i></em> <span>حديث شريف</span> </div>
            <p>قال رسول الله صلى الله عليه وسلم: "لا تباغضوا ولا تحاسدوا ولا تدابروا وكونوا عباد الله إخوانًا ولا يحل لمسلم أن يهجر أخاه فوق ثلاثة أيام"</p>
        </div>
        <div class="col-md-4 col-sm-4">
            <div class="service-box-heading"> <em><i class="fa fa-comments blue"></i></em> <span>قول مأثور</span> </div>
            <p>كيف أضحك والأقصى أسير!!!<br />
                قول ل الناصر صلاح الدين و لم يرى يضحك بعدها إلا حينما حرر القدس وبيت المقدس فى يوم في 11 رجب سنة 583 هـ، وقال : الآن أضحك.</p>
        </div>
    </div>
    {{-- END SERVICE BOX --}}

    {{-- BEGIN BLOCKQUOTE BLOCK --}}
    <div class="row quote-v1 margin-bottom-30">
        <div class="col-md-9"> <span>موقع الطريق إلى الله - طريقك نحو معرفة الله</span> </div>
        <div class="col-md-3 text-right"> <a class="btn-transparent" href="https://docs.google.com/forms/d/e/1FAIpQLSey90EU6LJY9pTm6qsRSgDOVZPeSNmgz8vrh4jwRVdTnNRGIQ/viewform?usp=sf_link" target="_blank"><i class="fa fa-rocket margin-right-10"></i>انضم إلينا الآن </a> </div>
    </div>
    {{-- END BLOCKQUOTE BLOCK --}}

    <div class="row service-box">
        {{-- Section 3: أحدث المرئيات على مدار الساعة --}}
        <x-home.section-card title="أحدث المرئيات على مدار الساعة" icon="fa-video-camera" color="blue home" width="4" width-sm="6">
            <ul class="vars">
                @foreach ($videos as $item)
                    <li>
                        <a href="/khotab-item-{{ $item->id }}.htm" title="{{ $item->title }}" class="tt">
                            <img src="{{ $item->thumb }}" title="{{ $item->title }}" alt="{{ $item->title }}" width="72" height="50">
                            <span>{{ LegacyTextTruncator::words((string) $item->title, 90) }}</span><br>
                            <small>{{ $item->prename }} {{ $item->name }}</small>
                            @if (isset($item->timeLabel))
                                <span class="var_time">{{ $item->timeLabel }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="mooore"><a href="/khotab-video_news.htm" class="mo">المزيد ..</a></div>
        </x-home.section-card>

        {{-- Section 4: برامج حصرية لشبكة الطريق إلى الله --}}
        <x-home.section-card title="برامج حصرية لشبكة الطريق إلى الله" icon="fa-spinner" color="blue home" width="4" width-sm="6">
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
                            <img src="{{ $logo }}" title="{{ $item->title }}" alt="{{ $item->title }}" width="72" height="50">
                            <span>{{ $item->title }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="mooore"><a href="/category-487.htm" class="mo">المزيد ..</a></div>
        </x-home.section-card>

        {{-- Section 5: أحدث الفتاوى المرئية --}}
        <x-home.section-card title="أحدث الفتاوى المرئية" icon="fa-play-circle" color="blue home" width="4" width-sm="6">
            <ul class="vars">
                @foreach ($fatawas as $item)
                    <li>
                        <a href="/fatawa-all-{{ $item->linkId }}.htm" title="{{ $item->question_text }}" class="tt">
                            <img src="/images/tvnoise.gif" title="{{ $item->question_text }}" alt="{{ $item->question_text }}" width="72" height="50">
                            <span>{{ LegacyTextTruncator::chars((string) $item->question_text, 110, '..') }}</span><br>
                            <small>{{ $item->prename }} {{ $item->name }}</small>
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
                <iframe width="100%" height="210" src="https://www.youtube.com/embed/{{ $youtube['id'] }}?rel=0&amp;showinfo=0" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>
            @endif
        </x-home.section-card>

        {{-- Section 8: مقاطع SoundCloud --}}
        <x-home.section-card title="مقاطع SoundCloud" icon="fa-soundcloud" color="blue home" width="4" width-sm="6">
            @if ($soundcloud['empty'])
                لا توجد مقاطع مضافة بعد
            @else
                <iframe width="100%" height="210" scrolling="no" frameborder="no" src="https://w.soundcloud.com/player/?url=https%3A//api.soundcloud.com/tracks/{{ $soundcloud['id'] }}&amp;auto_play=false&amp;hide_related=false&amp;show_comments=true&amp;show_user=true&amp;show_reposts=false&amp;visual=true"></iframe>
            @endif
        </x-home.section-card>
    </div>

    <div class="row service-box">
        {{-- Section 9: جديد التلاوات --}}
        <x-home.section-card title="جديد التلاوات" icon="fa-quran" color="blue home" width="3" width-sm="6">
            @foreach ($telawahs as $item)
                <ul class="homecss">
                    <li><a href="/recite-item-{{ $item->id }}.htm" class="tt2">
                            <span class="top_video">{{ LegacyTextTruncator::chars((string) $item->title, 60, ' ...') }}</span>
                            <span class="tooltip2">
                                <span class="top2"></span>
                                @if (strlen((string) $item->title) > 60)
                                    <span class="middle2">{{ $item->title }}</span>
                                @endif
                                <span class="middle2">{{ $item->group_title }}</span>
                                <span class="bottom2"></span>
                            </span>
                        </a>
                    </li>
                </ul>
            @endforeach
            <div class="mooore"><a href="/recite-news.htm" class="mo">المزيد ..</a></div>
        </x-home.section-card>

        {{-- Section 10: جديد الصوتيات --}}
        <x-home.section-card title="جديد الصوتيات" icon="fa-volume-up" color="blue home" width="3" width-sm="6">
            @foreach ($audios as $item)
                <ul class="homecss">
                    <li><a href="/khotab-item-{{ $item->id }}.htm" class="tt2">
                            <span class="top_video">{{ LegacyTextTruncator::mixedMultibyte((string) $item->title, 40, 25) }}</span>
                            <span class="tooltip2">
                                <span class="top2"></span>
                                @if (strlen((string) $item->title) > 60)
                                    <span class="middle2">{{ $item->title }}</span>
                                @endif
                                <span class="middle2">{{ $item->title }} - {{ $item->prename }} {{ $item->name }}</span>
                                <span class="bottom2"></span>
                            </span>
                        </a>
                    </li>
                </ul>
            @endforeach
            <div class="mooore" style="margin-top:-1px;"><a href="/khotab-audio_news.htm" class="mo">المزيد ..</a></div>
        </x-home.section-card>

        {{-- Section 11: التصميمات الدعوية --}}
        <x-home.section-card title="التصميمات الدعوية" icon="fa-pencil" color="blue home" body-class="nopadding" width="3" width-sm="6">
            <div class="list_carousel2">
                <ul id="pics">
                    @foreach ($album['images'] as $image)
                        <li><a href="/gallery-{{ $album['album_id'] }}.htm"><img border="0" src="{{ $image->thumb }}"></a></li>
                    @endforeach
                </ul>
                <div class="clearfix"></div>
            </div>
        </x-home.section-card>

        {{-- Section 12: إعلان (position 3) --}}
        <x-home.section-card title="إعلان" icon="fa-location-arrow" color="blue home" body-class="text-center nopadding" width="3" width-sm="6">
            {!! $ad3 !!}
        </x-home.section-card>
    </div>

    <div class="row service-box">
        {{-- Section 13: جديد الأفلام الوثائقية (parent=12) --}}
        <x-home.section-card title="جديد الأفلام الوثائقية" icon="fa-film" color="blue home" width="3" width-sm="6">
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

        {{-- Section 14: جديد الكارتون (parent=57) --}}
        <x-home.section-card title="جديد الكارتون" icon="fa-child" color="blue home" width="3" width-sm="6">
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

        {{-- Section 15: أحدث المواد المفرغة --}}
        <x-home.section-card title="أحدث المواد المفرغة" icon="fa-book" color="blue home" width="3" width-sm="6">
            <ul class="vars">
                @foreach ($dumpFiles as $item)
                    <li>
                        <a href="/khotab-item-{{ $item->id }}.htm" title="{{ $item->title }}" class="tt">
                            <img src="{{ $item->thumb }}" title="{{ $item->title }}" alt="{{ $item->title }}" width="72" height="50">
                            <span>{{ LegacyTextTruncator::words((string) $item->title, 65) }}</span><br>
                            <small>{{ $item->prename }} {{ $item->name }}</small>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="mooore"><a href="/dumped-lectures.htm" class="mo">المزيد ..</a></div>
        </x-home.section-card>

        {{-- Section 16: التصويت --}}
        <x-home.section-card title="التصويت" icon="fa-check-square-o" color="blue home w2a_polls" body-class="nopadding" width="3" width-sm="6">
            @if ($pollData)
                <form action="/survey-vote-{{ $pollData['poll']->pollID }}.htm" method="post">
                    <input type="hidden" name="pollID" value="{{ $pollData['poll']->pollID }}" />
                    <p id="title"><a href="/survey-results-{{ $pollData['poll']->pollID }}.htm">{{ $pollData['poll']->pollTitle }}</a></p>
                    <ul class="homecss" style="margin-top:0px;">
                        @foreach ($pollData['options'] as $option)
                            <li><a style="background-image:none; padding-right:0;padding-top:0px;"><input type="radio" name="voteID" id="vot{{ $option->voteID }}" value="{{ $option->voteID }}" />&nbsp;&nbsp;<label for="vot{{ $option->voteID }}" class="mylabel">{{ $option->optionText }}</label></a></li>
                        @endforeach
                    </ul>
                    <input class="w2a_submit" value="تصــويت" type="submit">
                    <br />
                    <div><span>عدد المشاركين : {{ $pollData['totalVotes'] }}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;عدد التعليقات: {{ $pollData['commentsDisplay'] }}</span></div>
                </form>
            @endif
        </x-home.section-card>
    </div>

    {{-- BEGIN CLIENTS (Section 17: تشاهدون الآن) --}}
    <div class="row our-clients">
        <div class="col-md-3">
            <h2><a href="#">تشاهدون الآن</a></h2>
            <p>قائمة بأكثر المواد مشاهدة في الوقت الحالي على موقعناً.</p>
        </div>
        <div class="col-md-9">
            <div class="owl-carousel owl-carousel6-brands">
                @foreach ($trending as $item)
                    @php $title = str_replace('"', "''", (string) $item->title); @endphp
                    <div class="client-item">
                        <a href="/var-item-{{ $item->id }}.htm">
                            <img src="{{ $item->thumb }}" class="img-responsive" alt="{{ $title }}" width="100%">
                            <div class="client-item-overlay"></div>
                            <div class="client-item-title">{{ $title }}</div>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    {{-- END CLIENTS --}}

    {{--
        `index.php`'s footer JS registers `carouFredSel` for `#pics` (this
        section's own live carousel) and `#cds` (the dead `list_latest_cds()`
        carousel — not reproduced, matching the Blueprint's §8/§14 decision:
        no `#cds` element exists on this page to init against).

        Visual parity audit finding (2026-08-18): this block was previously
        placed directly in `@section('content')`, which renders inside
        `<div class="main">` — well before `layouts/app.blade.php` loads
        `jquery.min.js` near the end of `<body>`. `$(function () {...})`
        calls `$` immediately (not deferred), so it threw "$ is not defined"
        and silently prevented `#pics` from ever initializing. Moved into
        `@push('scripts')` (already used by this same file for the
        RevolutionSlider scripts above), which `layouts/app.blade.php`
        renders via `@stack('scripts')` right before `</body>`, after
        `jquery.min.js` has loaded — matching legacy's own script order
        (`jquery.min.js` in `<head>`, `jquery.carouFredSel.js` + its init
        near the very end of the page). Script contents, options, and
        selectors are unchanged from before this fix.
    --}}
    @push('scripts')
        <script src="/assets/plugins/jquery.carouFredSel.js"></script>
        <script>
            $(function () {
                jQuery('#pics').carouFredSel({
                    scroll: 1,
                    direction: "up",
                    auto: {pauseDuration: 3000},
                    scroll: {
                        items: 1,
                        effect: "easeOutBounce",
                        pauseOnHover: true
                    }
                });
            });
        </script>
    @endpush
@endsection
