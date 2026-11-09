@extends('layouts.app')

@section('title', trim((string) $comment->subject) !== '' ? $comment->subject : 'تعليق')

{{-- OLD singlecomment(): one comment in full, with «رد على هذا» and «الأصل»
     (the poll). --}}
@section('content')
    <x-page-chrome :heading="'تعليق على: '.$poll->pollTitle" :breadcrumb="[
        ['title' => 'الإستفتاءات', 'url' => '/surveys.htm'],
        ['title' => $poll->pollTitle, 'url' => '/survey-comments-'.$poll->pollID.'.htm'],
        ['title' => 'تعليق', 'url' => ''],
    ]" />
    <x-content.premium-panel title="تعليق" icon="fa-comment">
        @php($settings = $settings)
        @include('engagement.polls.comments._comment', ['comment' => $comment, 'showParent' => false, 'settings' => ['limit' => PHP_INT_MAX] + $settings])
        <p><a href="/survey-{{ $poll->pollID }}.htm">الأصل</a> · <a href="{{ \App\Domain\Engagement\Services\PollCommentUrls::thread((int) $poll->pollID) }}">كل التعليقات</a></p>
    </x-content.premium-panel>
@endsection
