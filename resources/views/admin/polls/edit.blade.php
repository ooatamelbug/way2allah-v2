@extends('layouts.admin')

@section('title', 'تعديل استفتاء: '.$poll->pollTitle)

@section('content')
    {{-- OLD Admin Migration Phase 4 — OLD polledit(): the title and each
         existing choice slot with its vote count. Votes are never edited. --}}
    <x-admin-portlet :title="'تعديل استفتاء: #'.$poll->pollID" color="blue" icon="fa fa-pencil">
        @include('admin.mirrors._errors')
        <p class="text-muted">
            اللغة: {{ $poll->planguage }} &nbsp;·&nbsp; تصويتات: {{ (int) $poll->voters }} &nbsp;·&nbsp; التعليقات: {{ (int) $poll->comments }}
            @if ($canModerateComments)
                &nbsp;·&nbsp; <a href="{{ route('admin.polls.comments', $poll) }}">إدارة التعليقات</a>
            @endif
            &nbsp;·&nbsp; <a href="{{ route('engagement.polls.results', $poll) }}" target="_blank">النتائج</a>
        </p>
        <form method="post" action="{{ route('admin.polls.update', $poll) }}">
            @csrf
            <x-admin.contextual-return />
            @method('PUT')
            <label>عنوان الإستفتاء <input type="text" name="pollTitle" value="{{ old('pollTitle', $poll->pollTitle) }}" maxlength="100" required class="form-control"></label>
            <table class="table table-light">
                @forelse ($slots as $voteId => $rows)
                    <tr>
                        <td>إختيار {{ $voteId }}:</td>
                        <td><input type="text" name="options[{{ $voteId }}]" value="{{ old('options.'.$voteId, $rows->first()->optionText) }}" maxlength="50" class="form-control"></td>
                        <td>{{ (int) $rows->sum('optionCount') }} تصويتات</td>
                    </tr>
                @empty
                    <tr><td colspan="3">لا توجد خيارات لهذا الإستفتاء.</td></tr>
                @endforelse
            </table>
            <button type="submit" class="btn blue">حفظ التغييرات</button>
            <a href="{{ route('admin.polls.index') }}" class="btn default">رجوع</a>
        </form>
    </x-admin-portlet>
@endsection
