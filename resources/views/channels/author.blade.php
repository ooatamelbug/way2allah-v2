@extends('layouts.app')

{{--
    channels/author.php — channel-{id}-{author}.htm.

    Layout (owner-approved, 2026-11-08): Gen 2's two-column page —
    `row service-box` with the listing in the main column and the profile /
    recommendation / IF-012 boxes in the sidebar (author.php:41-98) — using the
    same column classes as channels/show.blade.php. Sections without rows are
    omitted, as Gen 2 (see _listing.blade.php).

    Heading / breadcrumb / title: author.php:12, :30-39 — `$title = 'مرئيات '
    . prename . ' ' . name . ' - قناة ' . channel title`, used as the document
    title and, when the author exists, the page heading with a three-level
    breadcrumb; otherwise the heading is 'قناة {title}' with two levels.

    Sidebar: «الملف الشخصي» (profile-userpic / img-responsive, author.php:58-72),
    «اخترنا لك هذه المادة» (randomitems(), author.php:75-82, restored
    2026-11-08 through the shared featured-items card), then IF-012's
    «الأكثر تحميلا» / «جديد المواد» boxes — kept, and deliberately empty, as
    legacy never populates them on this page (author.php:84-98).
--}}

@php
    $authorName = trim(($authorRow->prename ?? '') . ' ' . ($authorRow->name ?? ''));
    $hasAuthor = (int) ($authorRow->id ?? 0) > 0;
    $channelLabel = 'قناة ' . $channelModel->title;
@endphp

@section('title', 'مرئيات ' . ($authorRow->prename ?? '') . ' ' . ($authorRow->name ?? '') . ' - ' . $channelLabel)

@section('content')
    @if ($hasAuthor)
        <x-page-chrome :heading="'مرئيات ' . ($authorRow->prename ?? '') . ' ' . ($authorRow->name ?? '') . ' - ' . $channelLabel" :breadcrumb="[
            ['title' => 'القنوات الفضائية', 'url' => '/channels.htm'],
            ['title' => $channelLabel, 'url' => '/channel-' . $channelModel->id . '.htm'],
            ['title' => $authorName, 'url' => ''],
        ]" />
    @else
        <x-page-chrome :heading="$channelLabel" :breadcrumb="[
            ['title' => 'القنوات الفضائية', 'url' => '/channels.htm'],
            ['title' => $channelLabel, 'url' => ''],
        ]" />
    @endif

    <div class="row service-box margin-bottom-40 w2a-channel-author">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding flex flex-column gap-5">
            @include('channels._listing', ['showAuthorLinks' => false])
        </div>

        <aside class="col-lg-3 col-md-4 col-sm-5 nopadding flex flex-column gap-5" aria-label="الشريط الجانبي">
            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-child"></i> الملف الشخصي</div>
                    </div>
                    <div class="portlet-body">
                        {{-- media/authors/sq/{id}.png — a third, non-bucketed media
                             convention (IF-013), distinct from MediaPathResolver's
                             floor(id/1000) scheme; no fallback, as Gen 2. --}}
                        <div class="profile-userpic">
                            <img src="{{ \App\Domain\Content\Support\MediaUrl::asset('authors/sq/'.$author.'.png') }}" class="img-responsive" alt="">
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-child"></i> اخترنا لك هذه المادة</div>
                    </div>
                    <div class="portlet-body">
                        <x-content.featured-items :items="$randomFeatured" />
                    </div>
                </div>
            </div>

            {{-- IF-012: present but empty, as Gen 2 author.php:84-98. --}}
            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-child"></i> الأكثر تحميلا</div>
                    </div>
                    <div class="portlet-body"></div>
                </div>
            </div>

            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-child"></i> جديد المواد</div>
                    </div>
                    <div class="portlet-body"></div>
                </div>
            </div>
        </aside>
    </div>
@endsection
