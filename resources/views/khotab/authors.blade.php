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
                <div class="row">
                    <div class="col-md-12">
                        <div class="abc text-center"></div>
                    </div>
                </div>

                @foreach ($rows as $row)
                    @if ($row->groupLetter !== null)
                        <hr />
                        <center><h1 id="{{ $row->index }}">{{ $row->groupLetter }}</h1></center>
                        <hr />
                    @endif
                    <div class="author">
                        <img class="pull-left" src="{{ $row->author->displayImageUrl() }}" alt="{{ $row->author->prename }} {{ $row->author->name }}">
                        <div class="pull-left">
                            <span class="author-name">
                                <a href="/khotab-{{ $op }}-{{ $row->author->id }}.htm">{{ $row->author->name }}</a>
                            </span>
                            <span class="testimonials-post">{{ $row->author->{$countColumn} }} {{ $countLabel }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

{{--
    authors.php:98-135's inline A-Z jump-nav script, reproduced verbatim
    (same click-handler/smooth-scroll code) — `letterListHtml` is built
    server-side by KhotabAuthorController::groupedAuthorRows() in the same
    single pass as the grouping above, exactly like legacy's own
    `$LetterList` string. `@push('scripts')` (not inline in
    @section('content')) so this renders after jquery.min.js via
    layouts/app.blade.php's `@stack('scripts')` — the homepage's #pics
    carousel broke this exact way by loading too early; not repeating it.
--}}
@push('scripts')
    <script>
        $(document).ready(function() {
            var letterList = '{!! $letterListHtml !!}';
            $('.abc').html(letterList);
            $('.abc a').click(function(event) {
                if (
                    location.pathname.replace(/^\//, '') == this.pathname.replace(/^\//, '')
                    &&
                    location.hostname == this.hostname
                ) {
                    var target = $(this.hash);
                    target = target.length ? target : $('[name=' + this.hash.slice(1) + ']');
                    if (target.length) {
                        event.preventDefault();
                        $('html, body').animate({
                            scrollTop: target.offset().top - 60
                        }, 1000, function() {
                            var target = $(target);
                            target.focus();
                            if (target.is(":focus")) {
                                return false;
                            } else {
                                target.attr('tabindex', '-1');
                                target.focus();
                            };
                        });
                    }
                }
            });
        });
    </script>
@endpush
@endsection
