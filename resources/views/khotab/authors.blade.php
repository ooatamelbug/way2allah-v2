@extends('layouts.app')

@section('title', $sectionTitle)

{{--
    Visual parity audit (khotab-video.htm, 2026-08-18): breadcrumb,
    page-title, portlet wrapper, alphabetical grouping, A-Z jump nav, and
    per-author count reproduced from authors.php (source read in full).
    `<title>` (above) and this `<h3>` are legacy's own two DIFFERENT
    strings (authors.php:7 vs. authors.php:35's `title('قائمة الدعاة ب' .
    $title)`) — not reconciled into one, since legacy itself never did.
    The home breadcrumb link uses "/" (Laravel's own root route), matching
    the same siteurl->"/" adaptation already established in
    layouts/app.blade.php and khotab/item.blade.php, not literal
    "index.php". Both breadcrumb segments use `href=""` deliberately —
    authors.php:9,15,20,25,34 sets `'url'=>''` (not omitted), and
    breadcrumb_items() (functions.php:516-538) renders an `<a>` whenever
    the `url` key is set at all, regardless of its value — legacy's own
    behavior, not a Laravel omission.
    The malformed `<i class=\fa fa-gift\"></i>` icon `title()`
    (functions.php:541-543) itself emits ahead of the `<h3>` is a legacy
    authoring bug (a stray `\f` form-feed escape breaks the class
    attribute, so no real icon ever renders) — deliberately not
    reproduced, per the standing "don't reproduce legacy bugs" rule.
--}}
@section('content')
<div class="page-bar">
    <ul class="page-breadcrumb">
        <li><i class="fa fa-home"></i><a href="/">الرئيسية</a><i class="fa fa-angle-right"></i></li>
        <li><a href="">{{ $breadcrumbLabel }}</a><i class="fa fa-angle-right"></i></li>
        <li><a href="">قائمة الدعاة</a><i class=""></i></li>
    </ul>
</div>
<h3 class="page-title">قائمة الدعاة ب{{ $sectionTitle }}</h3>

<div class="row service-box margin-bottom-40">
    <div class="col-md-12 col-sm-12">
        <div class="portlet box blue">
            <div class="portlet-title">
                <div class="caption">
                    <i class="fa fa-child"></i>
                    قائمة الدعاة
                </div>
            </div>
            <div class="portlet-body">
                <div class="w2a-preachers-wrap">
                    <div class="w2a-preachers-toolbar">
                        <div class="w2a-preachers-search-wrap">
                            <i class="fa fa-search w2a-preachers-search-icon" aria-hidden="true"></i>
                            <label class="sr-only" for="w2a_author_search_input">ابحث عن اسم الداعية</label>
                            <input type="search" id="w2a_author_search_input" class="w2a-preachers-search-input" placeholder="ابحث عن اسم الداعية..." autocomplete="off">
                            <button type="button" id="w2a_author_search_clear" class="w2a-preachers-search-clear" hidden aria-label="مسح البحث"><i class="fa fa-times" aria-hidden="true"></i></button>
                        </div>
                        <div class="w2a-tree-badge">
                            <i class="fa fa-user-circle" aria-hidden="true"></i> {{ $groupedAuthors->flatten(1)->count() }} داعية
                        </div>
                    </div>

                    @if($groupedAuthors->isNotEmpty())
                        <nav class="w2a-alphabet-nav" aria-label="الانتقال حسب الحرف">
                            @foreach($groupedAuthors as $letter => $authors)
                                <a href="#w2a_letter_{{ md5($letter) }}" class="w2a-alphabet-link">{{ $letter }}</a>
                            @endforeach
                        </nav>
                    @endif

                    <div class="w2a-authors-container">
                        @forelse($groupedAuthors as $letter => $authors)
                            <section class="w2a-letter-section" id="w2a_letter_{{ md5($letter) }}" data-letter="{{ $letter }}" tabindex="-1">
                                <div class="w2a-letter-header">
                                    <span class="w2a-letter-badge">{{ $letter }}</span>
                                    <h3 class="w2a-letter-title">حرف {{ $letter }}</h3>
                                </div>
                                <div class="w2a-preachers-grid">
                                    @foreach($authors as $author)
                                        <x-content.preacher-card
                                            :author="$author"
                                            href="/khotab-{{ $op }}-{{ $author->id }}.htm"
                                            :count="$author->{$countColumn}"
                                            :count-label="$countLabel"
                                        />
                                    @endforeach
                                </div>
                            </section>
                        @empty
                            <div class="w2a-empty-state" role="status">لا يوجد دعاة متاحون في هذا القسم حاليًا.</div>
                        @endforelse
                        <p id="w2a_author_result_status" class="sr-only" aria-live="polite"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
