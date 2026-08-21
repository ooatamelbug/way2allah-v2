@extends('layouts.app')

{{--
    Shared Page Chrome Parity Audit: telawah/authors.php's real document
    title is `$title` alone ("قسم التلاوات") — the visible heading uses a
    DIFFERENT string, `title('قائمة القراء ' . $title)`. Confirmed
    genuinely different against fresh production; this view previously
    reused the heading text for the document title.
--}}
@section('title', 'قسم التلاوات')

@section('content')
    {{--
        recite.htm parity: telawah/authors.php -> list_telawat_groups(0)
        (telawah/functions.php:130-203) — ONE outer portlet ("قائمة القراء",
        fa-users icon, parent_id=0) wrapping a flat `.row.telawat_authors_list`
        of `.telawah-author` cards, NOT one portlet per reader. Verified
        against fresh live HTML: exactly 1 portlet-title, 100 `.telawah-author`
        cards inside it. The previous version here wrapped each group in its
        own nested portlet (101 portlet-titles rendered) — a real,
        structurally different markup, not a counting artifact.

        Image: `images/telawah.gif` (functions.php:164) — a single hardcoded
        placeholder, identical for every reader/group, not a DB field or
        per-item lookup (G-13-07, unchanged).

        Description truncation: functions.php:172's `substrwords($comment, 90)`
        — reused via the existing LegacyTextTruncator::words() helper
        (already built for this exact function, home_functions.php's
        callers), not re-derived. The untruncated comment is also the
        card's `title=""` tooltip attribute (functions.php:161).
    --}}
    {{--
        Shared Page Chrome Parity Audit: telawah/authors.php:8,13 —
        `$breadcrumb[] = ['title'=>'التلاوات','url'=>'']` (isset() is true
        even for an empty string, so it's a real `<a href="">`, confirmed
        against fresh production), then `['title'=>'قائمة القراء']` (no
        `url` key at all — plain text, current item).
    --}}
    <x-page-chrome
        heading="قائمة القراء بقسم التلاوات"
        :breadcrumb="[
            ['title' => 'التلاوات', 'url' => ''],
            ['title' => 'قائمة القراء'],
        ]"
    />

    <div class="row service-box margin-bottom-40">
        <div class="col-md-12 col-sm-12">
            <div class="portlet box blue">
                <div class="portlet-title">
                    <div class="caption"><i class="fa fa-users"></i> قائمة القراء</div>
                </div>
                <div class="portlet-body">
                    <div class="row telawat_authors_list">
                        @foreach ($groups as $group)
                            @php($comment = empty($group->des) ? 'بدون تعليق' : $group->des)
                            <div class="col-xs-12 col-sm-6 col-md-4 telawah-author">
                                <div class="thumbnail">
                                    <div class="row">
                                        <div class="col-xs-12 telawa-author-name text-center">
                                            <a href="/recite-group-{{ $group->id }}.htm"><span>{{ $group->title }}</span></a>
                                        </div>
                                    </div>
                                    <div class="row margin-top-10">
                                        <div class="col-xs-12 mar" title="{{ $comment }}">
                                            <div class="telawa-thumb">
                                                <a href="/recite-group-{{ $group->id }}.htm" class="bg_group">
                                                    <img src="/images/telawah.gif" alt="{{ $group->title }}">
                                                </a>
                                            </div>
                                            <div class="telawa-details">
                                                <div class="list-group">
                                                    <div class="list-group-item telawah-group-subcats"> الأقسام الفرعية : <span>{{ (int) $group->child }}</span> قسم </div>
                                                    <div class="list-group-item telawah-group-recits"> التلاوات : <span>{{ (int) $group->telawah }}</span> تلاوة </div>
                                                    <div class="list-group-item telawah-group-visits"> الزيارات : <span>{{ (int) $group->hits }}</span> زيارة </div>
                                                    <div class="list-group-item telawah-group-comment"> التعليق : {{ \App\Domain\Content\Support\LegacyTextTruncator::words($comment, 90) }} </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
