@extends('layouts.admin')

{{-- streamcode is trusted, admin-authored embed HTML/JS (Channel's own
     docblock) — rendered unescaped deliberately, not a template bug. --}}

@section('title', 'تعديل بث قناة: '.$channel->title)

@section('content')
    <form method="post" action="{{ route('admin.broadcasting.update', $channel) }}">
        @csrf
        @method('PUT')
        <textarea name="streamcode" rows="10" cols="80">{{ $channel->streamcode }}</textarea>
        <button type="submit">حفظ كود البث</button>
    </form>

    @if ($channel->streamcode)
        <section aria-label="الكود الحالي">
            {!! $channel->streamcode !!}
        </section>
    @endif
@endsection
