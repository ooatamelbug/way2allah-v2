@extends('layouts.app')

@section('title', $telawahItem->title)

@section('content')
    {{-- Contextual administration (OLD Telawah AdvancedStats(): «لوحة تحكم المشرفين» — edit, delete, move, and the admin-only link status). --}}
    @contextualAdmin
        <x-admin.contextual-controls title="لوحة تحكم المشرفين" :controls="\App\Domain\Admin\Contextual\Controls\TelawahControls::item($telawahItem)" />
    @endcontextualAdmin
    <div class="row service-box margin-bottom-40 w2a-media-layout sh-w2a-block">
        <div class="col-xs-12 col-sm-12 col-md-9 telawah-item-content nopadding">
            <section class="portlet box blue" aria-label="تفاصيل المادة">
                <div class="portlet-title">
                    <div class="caption"><i class="fa fa-file-audio-o"></i> تفاصيل المادة</div>
                </div>
                <div class="portlet-body">
                    <x-content.media-details-card
                        :item="$telawahItem"
                        module="telawat"
                        :date="$telawahItem->mytime ? \App\Domain\Content\Support\LegacyShortDateFormatter::format((int) $telawahItem->mytime) : ''"
                        :size="\App\Domain\Content\Support\LegacyFileSizeFormatter::format((int) ($telawahItem->linksize ?? 0))"
                        :download-url="'/recite-download-'.$telawahItem->id.'.htm'"
                        :is-video="false"
                        badge="تلاوة نادرة"
                        :show-comment-action="false"
                        :show-share-action="false"
                    />
                </div>
            </section>

            <x-content.media-player-panel />

            {{-- OLD Telawah Mirror() (modules/Telawah/index.php:345-392, mirror_*.htm): «قائمة الروابط
                 البديلة للمادة» — description linked to the mirror, a player for playable audio (OLD's
                 JavaScript player; its video "play" pointed at a Khotab route and never worked), the file
                 type and size. Links are http(s) only. Admins get edit / delete beside each mirror. --}}
            @php($telawahMirrors = $telawahItem->mirrors()->get())
            @if ($telawahMirrors->isNotEmpty())
                @php($safeHtml = app(\App\Domain\Content\Support\SafeLegacyHtmlRenderer::class))
                <section class="portlet box blue" aria-label="قائمة الروابط البديلة للمادة">
                    <div class="portlet-title"><div class="caption"><i class="fa fa-link"></i> قائمة الروابط البديلة للمادة</div></div>
                    <div class="portlet-body">
                        <table class="table table-striped">
                            <thead><tr><th>م</th><th>الوصف</th><th>سماع</th><th>النوع</th><th>الحجم</th></tr></thead>
                            <tbody>
                                @foreach ($telawahMirrors as $mirror)
                                    @php($mirrorHref = $safeHtml->httpUrl($mirror->link))
                                    @php($mirrorLabel = trim((string) $mirror->comment) !== '' ? $mirror->comment : 'وصلة #'.$mirror->id)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>
                                            @if ($mirrorHref !== null)
                                                <a href="{{ $mirrorHref }}" target="_blank" rel="nofollow noopener noreferrer">{{ $mirrorLabel }}</a>
                                            @else
                                                {{ $mirrorLabel }}
                                            @endif
                                            @contextualAdmin
                                                <x-admin.contextual-controls variant="inline" :controls="\App\Domain\Admin\Contextual\Controls\PublicPageControls::telawahMirror($mirror)" />
                                            @endcontextualAdmin
                                        </td>
                                        <td>{!! $safeHtml->player($mirror->link) ?? '' !!}</td>
                                        <td>{{ strtolower(pathinfo((string) parse_url((string) $mirror->link, PHP_URL_PATH), PATHINFO_EXTENSION)) }}</td>
                                        <td>{{ \App\Domain\Content\Support\LegacyFileSizeFormatter::format((int) ($mirror->size ?? 0)) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        </div>

        <aside class="col-xs-12 col-sm-12 col-md-3 telawah-item-sidebar nopadding" aria-label="الشريط الجانبي">
            <div class="portlet box blue top_side">
                <div class="portlet-title"><div class="caption"><i class="fa fa-cloud-download"></i> الأكثر تحميلا</div></div>
                <div class="portlet-body"><x-content.telawah-sidebar-list :items="$mostDownloaded" meta="downloads" /></div>
            </div>

            <div class="portlet box blue top_side">
                <div class="portlet-title"><div class="caption"><i class="fa fa-flash"></i> احدث المواد</div></div>
                <div class="portlet-body"><x-content.telawah-sidebar-list :items="$mostRecent" meta="date" /></div>
            </div>
        </aside>
    </div>

    <x-content.media-player-script />
@endsection
