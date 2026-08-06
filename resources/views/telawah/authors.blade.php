@extends('layouts.app')

@section('title', 'قائمة القراء بقسم التلاوات')

@section('content')
    <section aria-label="قائمة القراء">
        <ul>
            @foreach ($groups as $group)
                <li>
                    <a href="/recite-group-{{ $group->id }}.htm">{{ $group->title }}</a>
                    — {{ $group->telawah }} تلاوة
                </li>
            @endforeach
        </ul>
    </section>
@endsection
