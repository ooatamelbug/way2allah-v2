{{--
    Shared groups/series/items listing markup for channels/show.blade.php
    and channels/author.blade.php (pre-Wave-4 decision #5 — small, local
    Blade partial extraction, no behavioral change). $showAuthorLinks
    controls the one real difference between the two call sites: show.blade.php
    (channel.php, unfiltered) links each row to its author; author.blade.php
    (author.php, already filtered to one author) doesn't repeat that link.

    Batch 1 (category-1.htm/channel-1.htm parity): channels/functions.php's
    ListGroup() (line 3), ListSeries() (line 88), ListKhotab() (line 176) —
    all three genuinely active `id="tabelgrp"`/`id="tabelser"`/`id="tabelkht"`
    tables, verified against both source and live production HTML for
    channel-1.htm. channels/author.php (author.blade.php's legacy source)
    calls the identical three functions — same real table markup applies
    there too — but does NOT call Plugins('datatables') (confirmed: zero
    matches), so DataTables CSS/JS is pushed from channels/show.blade.php
    only, not from this shared partial.
--}}
@php($showAuthorLinks = $showAuthorLinks ?? false)

<div class="col-md-12 col-sm-12">
    <div class="portlet box blue">
        <div class="portlet-title">
            <div class="caption"><i class="fa fa-child"></i> قائمة المجموعات</div>
        </div>
        <div class="portlet-body series-overflow">
            <table class="table table-striped table-hover" id="tabelgrp">
                <tbody>
                    @foreach ($groups as $group)
                        <tr><td class="">
                            <div class="row"><div class="col-lg-12">
                                <h5>
                                    <div class="row">
                                        <div class="col-sm-12 col-lg-6">
                                            <a href="/khotab-group-{{ $group->id }}.htm">{{ $group->title }}</a>
                                        </div>
                                        @if ($showAuthorLinks)
                                            <div class="col-sm-12 col-lg-6">
                                                الداعية:
                                                <a href="/khotab-video-{{ $group->author_id }}.htm">{!! str_replace(' ', '&nbsp;', e($group->author)) !!}</a>
                                            </div>
                                        @endif
                                    </div>
                                </h5>
                                <div class="row page-header color_00a">
                                    {{--
                                        channels/functions.php's ListGroup() SQL (line 7-15) never
                                        selects `time`/`lastupdate` — it assigns tinydate($item->time)
                                        on an undefined property, which legacy's suppressed-errors
                                        config turns into tinydate(null) = date('Y-m-d', 0) =
                                        "1970-01-01". Confirmed live on production (every group row
                                        shows this exact date for both fields) — reproduced as
                                        observed, not "fixed" to a real date that was never there.
                                    --}}
                                    <div class="col-sm-3 col-xs-6 text-blue">
                                        <span><i class="fa fa-calendar"></i> {{ date('Y-m-d', 0) }}</span>
                                    </div>
                                    <div class="col-sm-3 col-xs-6 text-blue">
                                        <span><i class="fa fa-refresh"></i> {{ date('Y-m-d', 0) }}</span>
                                    </div>
                                    <div class="col-sm-3 col-xs-6 text-blue">
                                        <span><i class="fa fa-play-circle-o"></i> المواد: {{ $group->count }}</span>
                                    </div>
                                </div>
                            </div></div>
                        </td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="col-md-12 col-sm-12">
    <div class="portlet box blue">
        <div class="portlet-title">
            <div class="caption"><i class="fa fa-child"></i> قائمة السلاسل</div>
        </div>
        <div class="portlet-body series-overflow">
            <table class="table table-striped table-hover" id="tabelser">
                <tbody>
                    @foreach ($series as $item)
                        <tr><td class="">
                            <div class="row"><div class="col-lg-12">
                                <h5>
                                    <div class="row">
                                        <div class="col-sm-12 col-lg-6">
                                            <a href="/khotab-series-{{ $item->id }}.htm">{{ $item->title }}</a>
                                        </div>
                                        @if ($showAuthorLinks)
                                            <div class="col-sm-12 col-lg-6">
                                                الداعية:
                                                <a href="/channel-{{ $item->channel_id }}-{{ $item->author_id }}.htm">{{ $item->author }}</a>
                                            </div>
                                        @endif
                                    </div>
                                </h5>
                                <div class="row page-header color_00a">
                                    <div class="col-md-3 col-xs-6 text-blue">
                                        <span><i class="fa fa-calendar"></i> {{ date('Y-m-d', $item->time) }}</span>
                                    </div>
                                    <div class="col-md-3 col-xs-6 text-blue">
                                        <span><i class="fa fa-refresh"></i> {{ date('Y-m-d', $item->lastupdate) }}</span>
                                    </div>
                                    <div class="col-md-3 col-xs-6 text-blue">
                                        <span><i class="fa fa-play-circle-o"></i> المواد: {{ $item->count }}</span>
                                    </div>
                                </div>
                            </div></div>
                        </td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

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
                                        @if ($showAuthorLinks)
                                            <div class="col-sm-12 col-lg-6">
                                                الداعية:
                                                <a href="/channel-{{ $channelModel->id }}-{{ $item->author_id }}.htm">{{ $item->author }}</a>
                                            </div>
                                        @endif
                                    </div>
                                </h5>
                                <div class="row page-header color_00a">
                                    <div class="col-md-3 col-xs-6 text-blue">
                                        <span><i class="fa fa-calendar"></i> {{ date('Y-m-d', $item->time) }}</span>
                                    </div>
                                    @php($duration = \App\Domain\Content\Support\LegacyDurationFormatter::format((int) ($item->adur ?? 0)))
                                    <div class="col-md-3 col-xs-6 text-blue">
                                        <span><i class="fa fa-clock-o"></i> {{ $duration }}</span>
                                    </div>
                                    <div class="col-md-3 col-xs-6 text-blue">
                                        <span><i class="fa fa-commenting-o"></i> التعليقات: {{ $item->comments }}</span>
                                    </div>
                                    <div class="col-md-3 col-xs-6 text-blue">
                                        <span><i class="fa fa-eye"></i> مشاهدات: {{ number_format($item->hits) }}</span>
                                    </div>
                                </div>
                            </div></div>
                        </td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
