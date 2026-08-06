@extends('layouts.admin')

@section('title', $survey->title.' - '.($answer->isGuest() ? "زائر ({$answer->id})" : "عضو #{$answer->user_id}"))

@section('content')
    @php $responses = $answer->answersArray(); @endphp
    <dl>
        @foreach ($questions as $question)
            <dt>{{ $question->title }}</dt>
            <dd>
                @php $value = $responses[$question->id] ?? null; @endphp
                @if (is_array($value))
                    {{ implode(', ', array_map(fn ($v) => $question->optionsArray()[$v - 1] ?? $v, $value)) }}
                @elseif (in_array((int) $question->question_type, [1, 6], true))
                    {{ $question->optionsArray()[$value - 1] ?? $value }}
                @else
                    {{ $value }}
                @endif
            </dd>
        @endforeach
    </dl>
@endsection
