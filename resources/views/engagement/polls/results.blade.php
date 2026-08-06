@extends('layouts.app')

{{-- surveys.md §5's confirmed fix: shows the real poll title (already fetched, $poll->pollTitle),
     not legacy's permanently-empty $poll->holdtitle reference. CSS-based
     percentage bar, not the legacy stacked-GIF-image technique
     (surveys.md §5/§10's own recommendation). --}}

@section('title', $poll->pollTitle)

@section('content')
    <h1>{{ $poll->pollTitle }}</h1>
    @foreach ($options as $option)
        @if ($option->optionText)
            @php $percent = $totalVotes > 0 ? round(100 * $option->optionCount / $totalVotes) : 0; @endphp
            <div>
                {{ $option->optionText }}
                <div style="background:#eee; width:200px;">
                    <div style="background:#4a90d9; width:{{ $percent }}%;">&nbsp;</div>
                </div>
                {{ $percent }}% ({{ $option->optionCount }})
            </div>
        @endif
    @endforeach

    <p>مجموع الأصوات: {{ $totalVotes }}</p>
    <p>
        <a href="{{ route('engagement.polls.show', $poll) }}">صالة التصويتات</a> |
        <a href="{{ route('engagement.polls.index') }}">تصويتات أخرى</a>
    </p>

    @if ($latestFive->isNotEmpty())
        <section aria-label="اخر 5 استفتاءات">
            <h2>اخر 5 استفتاءات في الطريق إلى الله</h2>
            <ul>
                @foreach ($latestFive as $recent)
                    <li><a href="{{ route('engagement.polls.results', $recent) }}">{{ $recent->pollTitle }}</a> ({{ $recent->voters }} صوت)</li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
