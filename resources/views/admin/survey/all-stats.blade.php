@extends('layouts.admin')

{{-- admincp.md §5's confirmed fix: every question's tally is aggregated across all
     respondents (SurveyController::summarizeQuestion()), not the last
     loop-leftover respondent legacy's all_stats.php used for question
     types 1/2/4/6. --}}

@section('title', 'الإحصائيات الكاملة: '.$survey->title)

@section('content')
    @foreach ($summaries as $entry)
        <section aria-label="{{ $entry['question']->title }}">
            <h2>{{ $entry['question']->title }}</h2>
            @php $options = $entry['question']->optionsArray(); @endphp
            <ul>
                @forelse ($entry['summary'] as $value => $count)
                    <li>{{ $options[$value - 1] ?? $value }}: {{ $count }}</li>
                @empty
                    <li>لا توجد إجابات</li>
                @endforelse
            </ul>
        </section>
    @endforeach
@endsection
