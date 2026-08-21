@extends('layouts.app')

@section('title', $categoryModel->title)

{{--
    Batch 1 (category-1.htm/channel-1.htm parity): categories/category.php:44-45
    (`register_script('scripts/khotab_tables.js'); Plugins('datatables');`) —
    confirmed unconditional, not inside any `if`. Scoped to this page only via
    @push, matching this page's own real asset registration rather than making
    DataTables globally active in the shared layout.
--}}
@push('styles')
    <link href="/assets/global/plugins/datatables/datatables.min.css" rel="stylesheet" type="text/css"/>
    <link href="/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap-rtl.css" rel="stylesheet" type="text/css"/>
@endpush

@push('scripts')
    <script src="/assets/global/plugins/datatables/datatables.min.js" type="text/javascript"></script>
    <script src="/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js" type="text/javascript"></script>
    <script src="/scripts/khotab_tables.js" type="text/javascript"></script>
@endpush

@section('content')
    {{-- Shared Page Chrome Parity Audit: replaces the previous bare <nav><a> list (missing Home, the "التصنيفات الموضوعية" root label, and the real page-breadcrumb DOM) with the shared chrome. --}}
    <x-page-chrome :heading="$categoryModel->title" :breadcrumb="$breadcrumbTrail" />

    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
            {{-- G-13-12 (media/visual parity phase): categories/functions.php's
                 own ListSeries()/ListKhotab() (lines ~154, ~397 — distinct
                 functions from khotab module's same-named ones) — the same
                 conditional 24x24 images/channels/{id}.png convention. --}}
            <section aria-label="قائمة السلاسل">
                <ul>
                    @foreach ($series as $item)
                        <li>
                            <a href="/khotab-series-{{ $item->id }}.htm">{{ $item->title }}</a>
                            @if(!empty($item->channel_id))
                                <a href="/channel-{{ $item->channel_id }}.htm"><img width="24" height="24" src="/images/channels/{{ $item->channel_id }}.png" alt=""></a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>

            {{--
                Batch 1 (category-1.htm/channel-1.htm parity): categories/functions.php's
                ListKhotab() (line 318) — genuinely active `id="tabelkht"` table,
                verified against BOTH source and live production HTML (unlike
                this same file's ListSeries(), whose table markup is HTML-commented
                out in source and in production — that section is deliberately
                left untouched this batch). `$ob->mode` is referenced in the
                legacy conditionals below but never defined anywhere in
                category.php's call chain (confirmed: zero matches for `$ob`),
                so every `$ob->mode != 'x'` comparison is `null != 'x'` — always
                true — meaning date/comments/duration/channel-badge all render
                unconditionally here, reproduced as such rather than guessing
                a $ob source that doesn't exist.
            --}}
            <div class="col-md-12 col-sm-12">
                <div class="portlet box blue">
                    <div class="portlet-title">
                        <div class="caption"><i class="fa fa-child"></i> قائمة المواد</div>
                    </div>
                    <div class="portlet-body series-overflow series-overflow-auto">
                        <table class="table table-striped table-hover" id="tabelkht">
                            <tbody>
                                @foreach ($items as $item)
                                    <tr><td class="">
                                        <div class="row"><div class="col-lg-12">
                                            <h5>
                                                <div class="row">
                                                    <div class="col-sm-12 col-lg-6">
                                                        <a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a>
                                                    </div>
                                                    @if(!empty($item->name))
                                                        <div class="col-sm-12 col-lg-6">
                                                            {{ $item->prename }} :
                                                            <a href="/khotab-video-{{ $item->authID }}.htm">{!! str_replace(' ', '&nbsp;', e($item->name)) !!}</a>
                                                        </div>
                                                    @endif
                                                </div>
                                            </h5>
                                            <div class="row page-header color_00a">
                                                <div class="col-md-3 col-xs-6 text-blue">
                                                    <span><i class="fa fa-calendar"></i> {{ date('Y-m-d', $item->time) }}</span>
                                                </div>
                                                <div class="col-md-3 col-xs-6 text-blue">
                                                    <span><i class="fa fa-commenting-o"></i> التعليقات: {{ $item->comments }}</span>
                                                </div>
                                                <div class="col-md-3 col-xs-6 text-blue">
                                                    <span><i class="fa fa-eye"></i> مشاهدات: {{ number_format($item->hits) }}</span>
                                                </div>
                                                @if(!empty($item->channel_id))
                                                    <div class="col-md-3 col-xs-6 text-blue">
                                                        <span><i class="fa fa-television"></i> القناة:
                                                            <a href="/channel-{{ $item->channel_id }}.htm"><img width="24" height="24" src="/images/channels/{{ $item->channel_id }}.png" alt=""></a>
                                                        </span>
                                                    </div>
                                                @endif
                                                @php($duration = \App\Domain\Content\Support\LegacyDurationFormatter::format((int) ($item->adur ?? 0)))
                                                @if($duration !== '00:00:00')
                                                    <div class="col-md-3 col-xs-6 text-blue">
                                                        <span><i class="fa fa-clock-o"></i> {{ $duration }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                        </div></div>
                                    </td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @if(!empty($categoryModel->description))
                <section aria-label="وصف التصنيف">{{ $categoryModel->description }}</section>
            @endif
        </div>

        <aside class="col-lg-3 col-md-4 col-sm-5 nopadding" aria-label="الشريط الجانبي">
            <h3>اخترنا لك هذه المادة</h3>
            <ul>
                @foreach ($randomFeatured as $item)
                    <li><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                @endforeach
            </ul>

            <h3>الأكثر تحميلا</h3>
            <ul>
                @foreach ($mostDownloaded as $item)
                    <li><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                @endforeach
            </ul>

            <h3>جديد المواد</h3>
            <ul>
                @foreach ($mostRecent as $item)
                    <li><a href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
                @endforeach
            </ul>
        </aside>
    </div>
@endsection
