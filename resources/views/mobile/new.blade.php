@extends('layouts.app')

@section('title', 'الجوال الدعوي - مواد جديدة مضافه')

@section('content')
    <x-page-chrome heading="مواد جديدة مضافه" :breadcrumb="[['title' => 'قسم الموبايل', 'url' => '/mobiles.htm'], ['title' => 'مواد جديدة مضافه', 'url' => '']]" />
    <div class="container-fluid">
        @include('mobile._menu')
        <x-content.premium-panel title="مواد جديدة مضافه" icon="fa-flash">
            <p>مجموع المواد الجديدة: آخر أسبوع {{ $week }} · آخر 30 يوم {{ $month }}</p>
            <p>عرض: <a href="/mobile-shownew-7.htm">أسبوع</a> | <a href="/mobile-shownew-14.htm">أسبوعين</a> | <a href="/mobile-shownew-30.htm">30 يوم</a></p>
            <ul>
                @foreach ($days as $day)
                    <li>@if ($day['count'] > 0)<a href="/mobile-seldate-{{ $day['timestamp'] }}.htm">{{ $day['date'] }}</a>@else{{ $day['date'] }}@endif ({{ $day['count'] }})</li>
                @endforeach
            </ul>
        </x-content.premium-panel>
    </div>
@endsection
