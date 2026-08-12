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
    <nav aria-label="breadcrumb">{{ $locationModel->title }}</nav>

    <div class="row service-box">
        <div class="col-md-12">
            <h3>{{ $locationModel->title }}</h3>
            <div class="row">
                <div class="col-md-4 text-center">
                    <img src="{{ $locationModel->photoUrl() }}" width="250" alt="{{ $locationModel->title }}">
                </div>
                <div class="col-md-8">
                    <ul class="menu">
                        <li>الاسم: {{ $locationModel->title }}</li>
                        @if ($locationModel->type == 1)
                            <li>الهاتف: </li>
                            <li>العنوان: {{ $position->formatted_address ?? '' }}</li>
                            <li>المدينة: {{ $position->administrative_area_level_2 ?? '' }}</li>
                            <li>المحافظة: {{ $position->administrative_area_level_1 ?? '' }}</li>
                            <li>الدولة: {{ $position->country ?? '' }}</li>
                        @endif
                        @if (! empty($locationModel->website))
                            <li>الموقع الإلكتروني: <a href="{{ $locationModel->website }}" target="_blank" rel="noopener">{{ $locationModel->website }}</a></li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="row service-box margin-bottom-40">
        <div class="col-lg-9 col-md-8 col-sm-7 nopadding">
            <h4>قائمة الدعاة</h4>
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
                            <td><a href="/location-{{ $locationModel->id }}-author-{{ $author->id }}.htm">{{ trim($author->prename.' '.$author->name) }}</a></td>
                            <td>{{ $author->count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
