@extends('layouts.app')

@section('title', 'قائمة الدعاة')

@section('content')
    <section aria-label="قائمة الدعاة">
        <ul>
            @foreach ($authors as $author)
                <li>
                    <a href="/khotab-{{ $op }}-{{ $author->id }}.htm">{{ $author->name }}</a>
                </li>
            @endforeach
        </ul>
    </section>
@endsection
