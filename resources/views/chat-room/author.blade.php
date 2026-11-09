@extends('layouts.app')

{{-- Replaces chat_room/author.php. --}}

{{-- Location 10 (chat room): title/breadcrumb unchanged. Another (existing — the controller 404s otherwise) location:
     its own name, linking to location-{id}.htm. --}}
@php
    $isChatRoom = empty($locationModel);
    $authorName = $authorModel->prename.' '.$authorModel->name;
    $crumbTitle = $isChatRoom ? 'غرفة الهداية' : (string) $locationModel->title;
    $crumbUrl = $isChatRoom ? '/chat_room.htm' : '/location-'.$locationModel->id.'.htm';
    $authorUrl = $isChatRoom ? '/chat_author_'.$authorModel->id.'.htm' : '/location-'.$locationId.'-author-'.$authorModel->id.'.htm';
@endphp
@if ($isChatRoom)
@section('title', $authorModel->prename.' '.$authorModel->name.' - غرفة الهداية الدعوية')
@else
@section('title', $authorModel->prename.' '.$authorModel->name.' - '.$locationModel->title)
@endif

@section('content')
    {{-- Gen 2 title('غرفة الهداية - {author}') + breadcrumb(غرفة الهداية → chat_room.htm, {author}); another location
         names itself and links to its own page. Shared title()/breadcrumb() chrome. --}}
    <x-page-chrome :heading="$crumbTitle.' - '.$authorName" :breadcrumb="[
        ['title' => $crumbTitle, 'url' => $crumbUrl],
        ['title' => $authorName],
    ]" />

    <div class="row service-box margin-bottom-40 w2a-media-layout">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding flex flex-column gap-5">
            {{-- Gen 2 ListGroup()/ListSeries() render their block only when there are rows; items always render. --}}
            @if ($groups->isNotEmpty())
                <x-content.premium-panel title="قائمة المجموعات" icon="fa-folder">
                    <ul>
                        @foreach ($groups as $group)
                            <li><a href="/khotab-group-{{ $group->id }}.htm">{{ trim($group->title) }}</a> ({{ $group->count }})</li>
                        @endforeach
                    </ul>
                </x-content.premium-panel>
            @endif

            @if ($series->isNotEmpty())
                <x-content.premium-panel title="قائمة السلاسل" icon="fa-list-ol">
                    <x-content.chat-series-list :items="$series" />
                </x-content.premium-panel>
            @endif

            <x-content.premium-panel title="قائمة المواد" icon="fa-play-circle">
                <x-content.chat-item-list :items="$items" />
            </x-content.premium-panel>
        </div>

        <aside class="col-lg-3 col-md-4 col-sm-5 nopadding flex flex-column gap-5" aria-label="الشريط الجانبي">
            {{-- decision-log #114: the chat room (location 10) keeps chat_author_{id}; another location links to its own author page. --}}
            <section class="w2a-refresh-panel w2a-author-profile-card" id="author-info">
                <a href="{{ $authorUrl }}"><img class="w2a-author-avatar" src="{{ $authorModel->displayImageUrl() }}" alt="{{ $authorName }}"></a>
                <h3 class="w2a-author-name">{{ $authorName }}</h3>
            </section>

            <x-content.premium-panel title="أكثر الدروس مشاهدة" icon="fa-eye">
                <x-content.chat-lesson-list :items="$mostViewed" />
            </x-content.premium-panel>

            <x-content.premium-panel title="أجدد التسجيلات" icon="fa-clock-o">
                <x-content.chat-lesson-list :items="$mostRecent" />
            </x-content.premium-panel>
        </aside>
    </div>
@endsection
