{{--
    Shared groups/series/items listing markup for channels/show.blade.php
    and channels/author.blade.php (pre-Wave-4 decision #5 — small, local
    Blade partial extraction, no behavioral change). $showAuthorLinks
    controls the one real difference between the two call sites: show.blade.php
    (channel.php, unfiltered) links each row to its author; author.blade.php
    (author.php, already filtered to one author) doesn't repeat that link.

    Groups and series use the shared collection-card component. The material
    listing remains a table, so channels/show.blade.php still loads DataTables;
    author.blade.php follows the legacy page and does not load that plugin.
--}}
<div class="col-md-12 col-sm-12">
    <div class="portlet box blue">
        <div class="portlet-title">
            <div class="caption"><i class="fa fa-folder" aria-hidden="true"></i> قائمة المجموعات</div>
        </div>
        <div class="portlet-body">
            <x-content.media-collection-grid :items="$groups" type="group" secondary="author" />
        </div>
    </div>
</div>

<div class="col-md-12 col-sm-12">
    <div class="portlet box blue">
        <div class="portlet-title">
            <div class="caption"><i class="fa fa-list-ol" aria-hidden="true"></i> قائمة السلاسل</div>
        </div>
        <div class="portlet-body">
            <x-content.media-collection-grid :items="$series" secondary="author" />
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
                        <tr>
                            <td class="">
                                <div class="row">
                                    <div class="col-lg-12">
                                        <h5>
                                            <div class="row">
                                                <div class="col-sm-12 col-lg-6">
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
