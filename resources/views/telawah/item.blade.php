@extends('layouts.app')

@section('title', $telawahItem->title)

@section('content')
    <div class="row service-box margin-bottom-40 sh-w2a-block">
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
