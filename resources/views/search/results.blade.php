{{--
    advanced-search/index.php equivalent. Combined mawad+series (+topics
    for fatawa) response, per the approved deviation from legacy's
    two-request ajax_mawad/ajax_series split (SearchController's own
    docblock). gallery/cds are not offered as department options —
    confirmed dead code in legacy.

    G-05 (Migration Gap Register): full result-display restoration —
    result links, author/channel metadata, hit/item counts, varieties
    thumbnails, title_sub()-equivalent highlighting (LegacySearchRendering
    ::highlight()), Graphic()-equivalent "new" badges (::newBadge()), the
    3 distinct legacy validation messages, and legacy's real (reachable)
    no-results behavior — see SearchController's own docblock for the
    date-range/ordering/channel-filter fixes and what remains deferred
    (15s anti-repeat cookie, author/channel autocomplete — both out of
    scope this pass).
--}}
@extends('layouts.app')

@section('title', 'البحث المتقدم')

@php use App\Domain\Content\Support\LegacySearchRendering; @endphp

@section('content')
    <form method="post" action="/search.htm">
        @csrf
        <div class="form-group">
            <label for="kh_title">اسم السلسلة أو المادة</label>
            <input type="text" name="kh_title" id="kh_title" value="{{ $title }}">
        </div>
        <div class="form-group">
            <label for="kh_dept">القسم</label>
            <select name="kh_dept" id="kh_dept">
                <option value="">إختر</option>
                @foreach ($departments as $value => $label)
                    <option value="{{ $value }}" @selected($department === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label for="kh_author_name">الشيخ</label>
            <input type="text" name="kh_author_name" id="kh_author_name" value="{{ $authorId ?: '' }}">
        </div>
        <div class="form-group">
            <label for="kh_channel">القناة</label>
            <input type="text" name="kh_channel" id="kh_channel" value="{{ $channelId ?: '' }}">
        </div>
        <div class="form-group">
            <label for="kh_from">من</label>
            <input type="text" name="kh_from" id="kh_from" value="{{ $from }}">
            <label for="kh_to">إلى</label>
            <input type="text" name="kh_to" id="kh_to" value="{{ $to }}">
        </div>
        <button type="submit" name="kh_search">بحث</button>
    </form>

    @if (! $valid)
        <p>{{ $errorMessage }}</p>
    @else
        @if ($noResultsAtAll)
            <p>عفوا ، لا يوجد نتائج تطابق شروط البحث. من فضلك حاول تغيير شروط البحث المستخدمة</p>
        @endif

        <section aria-label="نتائج البحث">
            {{-- `Listmawad()` only ever calls `Listmawad_view()` when it has real results — no section renders at all otherwise (see SearchController's own docblock on why the old per-section "no items" messages aren't reproduced). --}}
            @if ($mawad !== null && ! $mawadEmpty)
                <section aria-label="نتائج البحث - المواد">
                    <h2>قائمة المواد</h2>
                    <ul>
                        @if ($resultKind === 'media')
                            @foreach ($mawad as $item)
                                <li>
                                    <a href="/khotab-item-{{ $item->id }}.htm">{!! LegacySearchRendering::highlight($item->title, $title) !!}</a>
                                    {!! LegacySearchRendering::newBadge($item->time) !!}
                                    <div>الداعية: <a href="/khotab-video-{{ $item->author }}.htm">{{ $item->prename }} {{ $item->name }}</a></div>
                                    @if ($item->channel_id > 0)
                                        <div>القناة: <a href="/channel-{{ $item->channel_id }}-{{ $item->author }}.htm"><img src="/images/channels/{{ $item->channel_id }}.png" width="24" height="24" alt=""></a></div>
                                    @endif
                                    <div>الزيارات: {{ $item->hits }}</div>
                                </li>
                            @endforeach
                        @elseif ($resultKind === 'varieties')
                            @foreach ($mawad as $item)
                                @php
                                    $thumbUrl = ((int) $item->frame) === 1
                                        ? \App\Domain\Content\Support\MediaUrl::asset('anasheed/frame/'.intdiv((int) $item->id, 1000).'/'.$item->id.'.jpg')
                                        : '/images/tvnoise.gif';
                                @endphp
                                <li>
                                    <a href="/var-item-{{ $item->id }}.htm">
                                        <img src="{{ $thumbUrl }}" alt="{{ $item->title }}">
                                        {!! LegacySearchRendering::highlight($item->title, $title) !!}
                                    </a>
                                </li>
                            @endforeach
                        @elseif ($resultKind === 'fatawa')
                            @foreach ($mawad as $item)
                                @php $linkId = str_replace('|', '', (string) $item->general_question_id); @endphp
                                <li>
                                    <a href="/fatawa-all-{{ $linkId }}.htm">{!! LegacySearchRendering::highlight($item->question_text, $title) !!}</a>
                                    <div>{{ $item->date_of_fatwa }}</div>
                                    <div>{{ $item->prename }}: <a href="/auther-questions-{{ $item->auther_id }}.htm">{{ $item->name }}</a></div>
                                </li>
                            @endforeach
                        @endif
                    </ul>
                    {{ $mawad->links() }}
                </section>
            @endif

            @if ($series !== null && ! $seriesEmpty)
                <section aria-label="نتائج البحث - السلاسل">
                    <h2>قائمة السلاسل</h2>
                    <ul>
                        @if ($resultKind === 'media')
                            @foreach ($series as $item)
                                <li>
                                    <a href="/khotab-series-{{ $item->id }}.htm">{!! LegacySearchRendering::highlight($item->title, $title) !!}</a>
                                    <div>الداعية: <a href="/khotab-video-{{ $item->author_id }}.htm">{{ $item->prename }} {{ $item->name }}</a></div>
                                    @if ($item->channel_id > 0)
                                        <div>القناة: <a href="/channel-{{ $item->channel_id }}-{{ $item->author_id }}.htm"><img src="/images/channels/{{ $item->channel_id }}.png" width="24" height="24" alt=""></a></div>
                                    @endif
                                    <div>المواد: {{ $item->count }}</div>
                                </li>
                            @endforeach
                        @elseif ($resultKind === 'varieties')
                            @foreach ($series as $item)
                                @php
                                    $thumbUrl = ((int) $item->icon) === 1
                                        ? \App\Domain\Content\Support\MediaUrl::asset('anasheed/icons/'.intdiv((int) $item->id, 1000).'/'.$item->id.'.jpg')
                                        : '/images/pix001.gif';
                                    $comment = $item->des !== null && $item->des !== '' ? $item->des : 'بدون تعليق';
                                @endphp
                                <li>
                                    <a href="/var-group-{{ $item->id }}.htm">
                                        <img src="{{ $thumbUrl }}" alt="{{ $item->title }}">
                                        {!! LegacySearchRendering::highlight($item->title, $title) !!}
                                    </a>
                                    <div>الأقسام الفرعية: {{ $item->child }}</div>
                                    <div>المقاطع: {{ $item->anasheed }}</div>
                                    <div>الزيارات: {{ $item->hits }}</div>
                                    <div>التعليق: {{ $comment }}</div>
                                </li>
                            @endforeach
                        @elseif ($resultKind === 'fatawa')
                            @foreach ($series as $item)
                                <li>
                                    <a href="/fatawa-all-{{ $item->id }}.htm">{!! LegacySearchRendering::highlight($item->question_text, $title) !!}</a>
                                    <div>عدد الفتاوى: {{ $listing->countFatawaForQuestion($item->id) }}</div>
                                    <div>المشاهدات: {{ $item->num_view }}</div>
                                </li>
                            @endforeach
                        @endif
                    </ul>
                    {{ $series->links() }}
                </section>
            @endif

            @if ($topics !== null && ! $topicsEmpty)
                <section aria-label="نتائج البحث - الموضوعات">
                    <h2>قائمة الموضوعات</h2>
                    <ul>
                        @foreach ($topics as $item)
                            <li>
                                <a href="/fatawa-group-{{ $item->id }}-{{ $item->parent_id }}.htm">{!! LegacySearchRendering::highlight($item->topic_name, $title) !!}</a>
                                <div>عدد الأسئلة: {{ $listing->countGeneralQuestionsForTopic($item->id) }}</div>
                                <div>{{ $item->db_insertion_date ? date('Y-m-d', (int) $item->db_insertion_date) : '' }}</div>
                            </li>
                        @endforeach
                    </ul>
                    {{ $topics->links() }}
                </section>
            @endif
        </section>
    @endif
@endsection
