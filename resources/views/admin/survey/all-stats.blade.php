@extends('layouts.admin')

{{-- admincp.md §5's confirmed fix: every question's tally is aggregated across all
     respondents (SurveyController::summarizeQuestion()), not the last
     loop-leftover respondent legacy's all_stats.php used for question
     types 1/2/4/6. --}}

@section('title', 'الإحصائيات الكاملة: '.$survey->title)

@section('content')
    {{--
        CONFIRMED_PAGE_MARKUP_GAP, fixed (AdminCP Final Page-Level
        Visual-Parity Verification, 2026-08-22): legacy `all_stats.php`
        wraps each question in its own "portlet light bordered" (a
        different, unboxed portlet variant from the box-purple pages) —
        this page had no portlet wrapping at all.
    --}}
    @foreach ($summaries as $entry)
        <x-admin-portlet :title="$entry['question']->title" light icon="icon-edit">
            @php $options = $entry['question']->optionsArray(); @endphp
            <ul>
                @forelse ($entry['summary'] as $value => $count)
                    <li>{{ $options[$value - 1] ?? $value }}: {{ $count }}</li>
                @empty
                    <li>لا توجد إجابات</li>
                @endforelse
            </ul>
        </x-admin-portlet>
    @endforeach
@endsection
