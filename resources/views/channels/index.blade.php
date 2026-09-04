@extends('layouts.app')

@section('title', 'قائمة القنوات الفضائية')

@section('content')
    <x-page-chrome heading="قائمة القنوات الفضائية" :breadcrumb="[['title' => 'القنوات الفضائية', 'url' => '']]" />

    <div class="row service-box margin-bottom-40">
        <x-home.section-card title="قائمة القنوات الفضائية" icon="fa-tv" width="12">
            <div class="w2a-channels-wrap">
                <div class="w2a-channels-toolbar">
                    <div class="w2a-channels-search-wrap">
                        <i class="fa fa-search w2a-channels-search-icon" aria-hidden="true"></i>
                        <label class="sr-only" for="w2a_channel_search_input">ابحث باسم القناة أو التردد</label>
                        <input type="search" id="w2a_channel_search_input" class="w2a-channels-search-input" placeholder="ابحث باسم القناة أو التردد..." autocomplete="off">
                        <button type="button" id="w2a_channel_search_clear" class="w2a-channels-search-clear" hidden aria-label="مسح البحث"><i class="fa fa-times" aria-hidden="true"></i></button>
                    </div>
                    <div class="w2a-tree-badge"><i class="fa fa-television" aria-hidden="true"></i> {{ $channels->count() }} قناة فضائية</div>
                </div>

                <div class="w2a-channels-grid">
                    @forelse($channels as $channel)
                        <x-content.channel-card :channel="$channel" />
                    @empty
                        <div class="w2a-empty-state" role="status">لا توجد قنوات فضائية متاحة حاليًا.</div>
                    @endforelse
                </div>
                <p id="w2a_channel_result_status" class="sr-only" aria-live="polite"></p>
            </div>
        </x-home.section-card>
    </div>
@endsection
