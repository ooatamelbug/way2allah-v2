{{--
    Repair Batch 1 (decision-log #52). Full re-read of `telawah/more.php`
    (76 lines) and its 2 helper functions (`telawah/functions.php`'s
    `most_downloaded_list()`). Restored: the `.portlet.box.blue` results
    wrapper (icon `fa-plus`, real caption "أحدث التلاوات المضافة بالموقع"
    — a DIFFERENT string from the `<title>` tag's "أحدث المواد بقسم
    التلاوات", not reconciled into one, matching the same distinct-title-
    vs-caption pattern already established elsewhere in this codebase),
    the real `<table id="sample_5">` per-row structure (title + author
    link + hit count, no numbered column — `more.php` never has one,
    unlike `fatwa-today.php`/`auther_profile.php`'s tables), and the
    sidebar's real `most_downloaded_list()` portlet (icon
    `fa-cloud-download`, `.downloaded_list`/`.telawah-group-recits`
    classes, `w2a_open_div()`'s real `color="blue top_side"` — an extra
    class beyond plain "blue"; `most_downloaded_list()`'s own
    `if ($TotalList>0)` wraps the WHOLE portlet including its wrapper —
    an empty result renders no box at all, not an empty box).

    No breadcrumb: `more.php` never calls `page_bar()`/`title()`/
    `breadcrumb()` — confirmed by the full source read, same as
    `fatwa-today.php`/`auther_profile.php`.

    No empty-state message: `more.php` has no `count==0` fallback either
    — a real, confirmed absence, not an oversight.

    `most_recent_list()` (`telawah/functions.php:330-...`, "جديد
    التلاوات") is a real function in the same file, but `more.php` never
    calls it — only `most_downloaded_list()` is on this page. Not added.
--}}
@extends('layouts.app')

@section('title', 'أحدث المواد بقسم التلاوات')

@push('styles')
    <link rel="stylesheet" href="/fatawa/css/new-style.css">
    <link href="https://fonts.googleapis.com/css?family=Cairo|Reem+Kufi" rel="stylesheet">
@endpush

@section('content')
    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"> <i class="fa fa-plus"></i>أحدث التلاوات المضافة بالموقع
                        </div>
                    </div>
                    <div class="portlet-body series-overflow series-overflow-auto">
                        <div class="portlet-body">
                            <table class="table table-striped table-hover" id="sample_5">
                                <tbody>
                                    @foreach ($telawahs as $telawah)
                                        <tr>
                                            <td class="">
                                                <div class="row">
                                                    <div class="col-lg-12">
                                                        <h5>
                                                            <a href="/recite-item-{{ $telawah->id }}.htm">{{ $telawah->title }}</a>
                                                        </h5>
                                                        <div class="row page-header color_00a">
                                                            <div class="col-sm-6 col-xs-12">
                                                                <span class="text-blue">
                                                                    <a href="/recite-group-{{ $telawah->auth_id }}.htm">{{ $telawah->group_title }}</a>
                                                                </span>
                                                            </div>
                                                            <div class="col-sm-6 col-xs-12">
                                                                الزيارات:
                                                                <span class="text-blue">
                                                                    {{ $telawah->hits }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xs-12 col-sm-12 col-md-3 telawah-item-sidebar nopadding">
            {{-- most_downloaded_list()'s own `if ($TotalList>0)` wraps the
                 ENTIRE portlet, including its wrapper — an empty result
                 means no box at all, not an empty box. --}}
            @if ($mostDownloaded->isNotEmpty())
                <div id="" class="col-md-12 col-sm-12">
                    <div class="portlet box blue top_side">
                        <div class="portlet-title">
                            <div class="caption">
                                <i class="fa fa-cloud-download"></i>
                                الأكثر تحميلا
                            </div>
                        </div>
                        <div class="portlet-body ">
                            <ul class="downloaded_list">
                                @foreach ($mostDownloaded as $item)
                                    <li class="list-group-item telawah-group-recits">
                                        <a href="/recite-item-{{ $item->id }}.htm"> <i class="fa fa-volume-up"></i> {{ $item->title }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
