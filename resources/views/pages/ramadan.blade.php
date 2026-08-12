@extends('layouts.app')

{{--
    pages/ramadan.php + ramadan1442.php + ramadan-archive.php, consolidated
    into one parameterized view (Roadmap task 6.3). Card chrome follows
    google-form.blade.php's existing translation of legacy's
    w2a_open_div()/w2a_close_div() wrapper — not a pixel-perfect port of
    the original CSS. `images/channels/{id}.png` keeps the exact legacy
    relative path (confirmed missing from this codebase, pages.md §5
    addendum) — no placeholder asset was created, per explicit
    authorization. Empty years render nothing at all, matching legacy's
    own `if ($TotalList > 0)` guard around each section.
--}}

@section('title', 'برامج رمضان 1447 هـ')

@section('content')
    @foreach ($seriesByYear as $year => $series)
        @if ($series->isNotEmpty())
            <div class="row service-box margin-bottom-40 sh-w2a-block">
                <div class="col-xs-12 col-sm-12 col-md-12 telawah-item-content nopadding">
                    <div class="portlet-body series-overflow series-overflow-auto">
                        <h4>
                            برامج رمضان {{ $year }} هـ
                            @if (in_array($year, $yearsWithCounter, true))
                                <small>(عدد الزيارات: {{ $counters[$year] ?? 0 }})</small>
                            @endif
                        </h4>
                        <table class="table table-striped table-hover">
                            <tbody>
                                @foreach ($series as $item)
                                    <tr>
                                        <td>
                                            <div class="row">
                                                <div class="col-sm-12 col-lg-6">
                                                    اسم البرنامج:
                                                    <a href="{{ route('khotab.series.show', $item->id) }}">{{ trim((string) $item->title) }}</a>
                                                </div>
                                                <div class="col-sm-12 col-lg-6">
                                                    الداعية:
                                                    <a href="{{ route('khotab.authors.show', ['op' => 'video', 'author' => $item->author_id]) }}">{{ trim($item->prename.' '.$item->name) }}</a>
                                                </div>
                                            </div>
                                            <div class="row page-header color_00a">
                                                <div class="col-md-3 col-xs-6 text-blue">
                                                    <i class="fa fa-calendar"></i>
                                                    {{ $item->lastupdate ? date('Y-m-d', $item->lastupdate) : ($item->time ? date('Y-m-d', $item->time) : '') }}
                                                </div>
                                                <div class="col-md-3 col-xs-6 text-blue">
                                                    <i class="fa fa-television"></i>
                                                    القناة:
                                                    {{-- channel_id is nullable in the underlying schema; legacy's raw
                                                         string-interpolated href degrades to a malformed-but-harmless
                                                         link rather than crashing — route() requires a real value, so
                                                         it falls back to 0 here to preserve that "never fails" shape
                                                         rather than a new 500 error legacy never had. --}}
                                                    <a href="{{ route('channels.show-author', ['channel' => $item->channel_id ?? 0, 'author' => $item->author_id]) }}">
                                                        <img width="24" height="24" border="0" src="/images/channels/{{ $item->channel_id }}.png" alt="">
                                                    </a>
                                                </div>
                                                <div class="col-md-3 col-xs-6 text-blue">
                                                    <i class="fa fa-play-circle-o"></i>
                                                    المواد: {{ $item->count }}
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
    @endforeach
@endsection
