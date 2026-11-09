@extends('layouts.app')

@section('title', 'تعليقات: '.$poll->pollTitle)

{{-- OLD DisplayTopic(): the poll's comments under `pid` (0 = the whole poll),
     in OLD's order, threshold and display mode. Comments are rendered through
     SafeLegacyHtmlRenderer — OLD echoed them raw. --}}
@section('content')
    <x-page-chrome :heading="'تعليقات: '.$poll->pollTitle" :breadcrumb="[
        ['title' => 'الإستفتاءات', 'url' => '/surveys.htm'],
        ['title' => $poll->pollTitle, 'url' => '/survey-results-'.$poll->pollID.'.htm'],
        ['title' => 'التعليقات', 'url' => ''],
    ]" />
    @if (session('poll_comment_posted'))
        <div class="alert alert-success">تم إرسال تعليقك</div>
    @endif
    <x-content.premium-panel title="التعليقات" icon="fa-comments">
        @include('engagement.polls.comments._navbar')
        @if ($pid > 0)
            <p><a href="{{ \App\Domain\Engagement\Services\PollCommentUrls::thread((int) $poll->pollID) }}">كل تعليقات الإستفتاء</a></p>
        @endif
        @forelse ($comments as $node)
            @include('engagement.polls.comments._comment', ['comment' => $node['comment'], 'showParent' => true])
            @include('engagement.polls.comments._children', ['nodes' => $node['children']])
        @empty
            <p class="text-center">لا توجد تعليقات.</p>
        @endforelse
    </x-content.premium-panel>
@endsection
