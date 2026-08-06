@extends('layouts.app')

@section('title', $poll->pollTitle)

@section('content')
    <form method="post" action="{{ route('engagement.polls.vote', $poll) }}">
        @csrf
        <p>{{ $poll->pollTitle }}</p>
        @foreach ($options as $option)
            @if ($option->optionText)
                <label><input type="radio" name="voteID" value="{{ $option->voteID }}"> {{ $option->optionText }}</label>
            @endif
        @endforeach
        <button type="submit">تصويت</button>
    </form>
    <p>
        <a href="{{ route('engagement.polls.results', $poll) }}">نتائج</a> |
        <a href="{{ route('engagement.polls.index') }}">تصويتات</a>
    </p>
    <p>أصوات {{ $poll->totalVotes() }}</p>
@endsection
