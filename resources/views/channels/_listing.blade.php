{{--
    Shared groups/series/items listing markup for channels/show.blade.php
    and channels/author.blade.php (pre-Wave-4 decision #5 — small, local
    Blade partial extraction, no behavioral change). $showAuthorLinks
    controls the one real difference between the two call sites: show.blade.php
    (channel.php, unfiltered) links each item row to its author;
    author.blade.php (already filtered to one author) does not repeat that
    link, and its item title uses the full row instead (owner decision,
    2026-11-08 — Gen 2's shared ListKhotab() did print «الداعية:» there too;
    the earlier "author.php doesn't repeat that link" wording was inaccurate).

    Gen 2 channels/functions.php renders each section only when it has rows
    (ListGroup() :19, ListSeries() :107, ListKhotab() :195, `num_rows > 0`);
    reproduced here (2026-11-08). Group / series cards link their author as
    Gen 2 did — group → khotab-video-{author}.htm (:48), series →
    channel-{channel}-{author}.htm (:137) — through the grid's opt-in
    secondaryHref (no nested anchors).

    Groups and series use the shared collection-card component. The material
    listing remains a table, so channels/show.blade.php still loads DataTables;
    author.blade.php follows the legacy page and does not load that plugin.
--}}
@php
    $groupAuthorHref = fn ($group) => ((int) ($group->author_id ?? 0)) > 0
        ? route('khotab.authors.show', ['op' => 'video', 'author' => (int) $group->author_id], false)
        : null;
    $seriesAuthorHref = fn ($series) => ((int) ($series->author_id ?? 0)) > 0 && ((int) ($series->channel_id ?? 0)) > 0
        ? route('channels.show-author', ['channel' => (int) $series->channel_id, 'author' => (int) $series->author_id], false)
        : null;
@endphp
@if ($groups->isNotEmpty())
<div class="col-md-12 col-sm-12">
    <div class="portlet box blue">
        <div class="portlet-title">
            <div class="caption"><i class="fa fa-folder" aria-hidden="true"></i> قائمة المجموعات</div>
        </div>
        <div class="portlet-body">
            <x-content.media-collection-grid :items="$groups" type="group" secondary="author" :secondary-href="$groupAuthorHref" />
        </div>
    </div>
</div>
@endif

@if ($series->isNotEmpty())
<div class="col-md-12 col-sm-12">
    <div class="portlet box blue">
        <div class="portlet-title">
            <div class="caption"><i class="fa fa-list-ol" aria-hidden="true"></i> قائمة السلاسل</div>
        </div>
        <div class="portlet-body">
            <x-content.media-collection-grid :items="$series" secondary="author" :secondary-href="$seriesAuthorHref" />
        </div>
    </div>
</div>
@endif

@if ($items->isNotEmpty())
<div class="col-md-12 col-sm-12">
    <div class="portlet box blue">
        <div class="portlet-title">
            <div class="caption"><i class="fa fa-child"></i> قائمة المواد</div>
        </div>
        <div class="portlet-body series-overflow series-overflow-auto">
            <table class="table table-striped table-hover" id="tabelkht">
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td class="">
                                <div class="row">
                                    <div class="col-lg-12">
                                        <h5>
                                            <div class="row">
                                                <div class="{{ $showAuthorLinks ? 'col-sm-12 col-lg-6' : 'col-sm-12 col-lg-12' }}">
                                                    <a
                                                        href="/khotab-item-{{ $item->id }}.htm">{{ $item->title }}</a>
                                                </div>
                                                @if ($showAuthorLinks)
                                                    <div class="col-sm-12 col-lg-6">
                                                        الداعية:
                                                        <a
                                                            href="/channel-{{ $channelModel->id }}-{{ $item->author_id }}.htm">{{ $item->author }}</a>
                                                    </div>
                                                @endif
                                            </div>
                                        </h5>
                                        <div class="row page-header color_00a">
                                            <div class="col-md-3 col-xs-6 text-blue">
                                                <span><i class="fa fa-calendar"></i>
                                                    {{ date('Y-m-d', $item->time) }}</span>
                                            </div>
                                            @php($duration = \App\Domain\Content\Support\LegacyDurationFormatter::format((int) ($item->adur ?? 0)))
                                            <div class="col-md-3 col-xs-6 text-blue">
                                                <span><i class="fa fa-clock-o"></i> {{ $duration }}</span>
                                            </div>
                                            <div class="col-md-3 col-xs-6 text-blue">
                                                <span><i class="fa fa-commenting-o"></i> التعليقات:
                                                    {{ $item->comments }}</span>
                                            </div>
                                            <div class="col-md-3 col-xs-6 text-blue">
                                                <span><i class="fa fa-eye"></i> مشاهدات:
                                                    {{ number_format($item->hits) }}</span>
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
@endif
