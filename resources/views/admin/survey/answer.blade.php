@extends('layouts.admin')

@php $respondentLabel = $answer->isGuest() ? "زائر ({$answer->id})" : ($username ?? "عضو #{$answer->user_id}"); @endphp
@section('title', $survey->title.' - '.$respondentLabel)

@section('content')
    {{--
        Portlet caption reconstructs legacy `survey/answer.php`'s own dynamic
        caption ("{survey} - {respondent}"), now resolving the real forum
        username the same way `stats.php`/`stats.blade.php` does instead of a
        bare "عضو #id" (`answer.php:34-35`'s own `LEFT JOIN users`). Legacy
        additionally links the name to the member's forum profile from
        inside the caption bar itself (`answer.php:54`) — not reproduced
        here since `<x-admin-portlet>`'s `:title` is escaped plain text, not
        HTML; the smallest safe fix is the correct text, not an unescaped
        title just to recover one clickable link.
    --}}
    <x-admin-portlet :title="$survey->title.' - '.$respondentLabel">
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
    </x-admin-portlet>
@endsection
