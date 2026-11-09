@extends('layouts.app')

{{--
    chat_room/alhedaya_room.php, generalized from its own hardcoded
    location=10 to any location (Wave C — "Public Locations & Da'wah
    Registration Surfaces"). No generic-location legacy file survives, so
    this is a moderate-confidence generalization of that one real file's
    structure, not a proven byte-for-byte port — see LocationController's
    own docblock. Letter-indexed author navigation (alhedaya_room.php's
    own `<a name="$X">`/jump-links) is simplified to a plain table, same
    simplification precedent as chat-room/author.blade.php.
--}}

@php
    $position = $locationModel->googlemap ? @unserialize($locationModel->googlemap) : null;
@endphp

@section('title', $locationModel->title)

@section('content')
    {{-- Shared title()/breadcrumb() chrome: the location name once as the breadcrumb (plain text, as before), no
         separate page heading — the details panel below carries the name, as Gen 2's panel caption did. --}}
    <x-page-chrome :breadcrumb="[['title' => (string) $locationModel->title]]" />

    {{-- No sidebar (owner decision 2026-11-07): the details and author list use the full content width. --}}
    <div class="row service-box margin-bottom-40">
        <div class="col-xs-12 nopadding flex flex-column gap-5">
            <x-content.premium-panel :title="(string) $locationModel->title" icon="fa-map-marker">
                <div class="row">
                    <div class="col-sm-4 text-center">
                        <img class="img-responsive center-block" src="{{ $locationModel->photoUrl() }}" width="250" alt="{{ $locationModel->title }}">
                    </div>
                    <div class="col-sm-8">
                        <ul class="menu">
                            <li>الاسم: {{ $locationModel->title }}</li>
                            @if ($locationModel->type == 1)
                                <li>الهاتف: </li>
                                <li>العنوان: {{ $position->formatted_address ?? '' }}</li>
                                <li>المدينة: {{ $position->administrative_area_level_2 ?? '' }}</li>
                                <li>المحافظة: {{ $position->administrative_area_level_1 ?? '' }}</li>
                                <li>الدولة: {{ $position->country ?? '' }}</li>
                            @endif
                            @if (!empty($locationModel->website))
                                <li>الموقع الإلكتروني: <a href="{{ $locationModel->website }}" target="_blank"
                                        rel="noopener">{{ $locationModel->website }}</a></li>
                            @endif
                        </ul>
                    </div>
                </div>
            </x-content.premium-panel>

            <x-content.premium-panel title="قائمة الدعاة" icon="fa-users">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>م</th>
                                <th>الاسم</th>
                                <th>عدد المواد</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($authors as $index => $author)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td><a
                                            href="/location-{{ $locationModel->id }}-author-{{ $author->id }}.htm">{{ trim($author->prename . ' ' . $author->name) }}</a>
                                    </td>
                                    <td>{{ $author->count }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-content.premium-panel>
        </div>
    </div>
@endsection
