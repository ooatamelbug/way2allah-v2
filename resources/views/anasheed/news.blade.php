@extends('layouts.app')

@section('title', 'المواد المثبتة بقسم: ' . $group->title)

@section('content')
    <section aria-label="المواد المثبتة">
        <h2>المواد المثبتة بقسم: {{ $group->title }}</h2>
        <ul>
            @foreach ($pinnedItems as $item)
                <li><a href="/var-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
            @endforeach
        </ul>
    </section>

    <section aria-label="المواد الجديدة">
        <h2>المواد الجديدة بقسم: {{ $group->title }}</h2>
        <ul>
            @foreach ($newestItems as $item)
                <li><a href="/var-item-{{ $item->id }}.htm">{{ $item->title }}</a></li>
            @endforeach
        </ul>
    </section>
@endsection
