@extends('layouts.admin')

@section('title', 'حذف التعليقات: '.$poll->pollTitle)

@section('content')
    {{-- OLD Admin Migration Phase 4 — OLD RemovePollComment (super-admin only).
         Removing a comment removes its whole reply thread, as in OLD. --}}
    <x-admin-portlet :title="'التعليقات — #'.$poll->pollID.' '.$poll->pollTitle" color="blue" icon="fa fa-comments">
        @include('admin.mirrors._errors')
        <p class="text-muted">عداد التعليقات: {{ (int) $poll->comments }} &nbsp;·&nbsp; التعليقات الموجودة: {{ $comments->count() }}</p>
        <table class="table table-striped table-light">
            <thead><tr><th>#</th><th>رد على</th><th>الاسم</th><th>العنوان</th><th>التعليق</th><th>التاريخ</th><th></th></tr></thead>
            <tbody>
                @forelse ($comments as $comment)
                    <tr>
                        <td>{{ $comment->tid }}</td>
                        <td>{{ (int) $comment->pid > 0 ? '#'.$comment->pid : '' }}</td>
                        <td>{{ $comment->name }}</td>
                        <td>{{ $comment->subject }}</td>
                        <td style="max-width:420px;word-break:break-word">{{ \Illuminate\Support\Str::limit((string) $comment->comment, 300) }}</td>
                        <td dir="ltr">{{ $comment->date }}</td>
                        <td>
                            <form method="post" action="{{ route('admin.polls.comments.destroy', $comment) }}"
                                  onsubmit="return confirm('هل أنت متأكد من حذف جميع التعليقات المختارة والردود عليها؟')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm red"><i class="fa fa-trash-o"></i> حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">لا توجد تعليقات.</td></tr>
                @endforelse
            </tbody>
        </table>
        <a href="{{ route('admin.polls.edit', $poll) }}" class="btn default">رجوع</a>
    </x-admin-portlet>
@endsection
