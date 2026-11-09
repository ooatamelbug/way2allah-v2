@extends('layouts.admin')

@section('title', 'تعديل التعليق')

@section('content')
    {{-- OLD WaitingList(id) + addcomment.htm (modules/Khotab/admin/index.php:3048): one comment, its
         text editable, with «نشر التعليـق» (AddComment) and «مسح التعليـق» (DelComment). Each button
         shows only to an admin whose permission reaches its route. --}}
    @php
        $contextual = app(\App\Domain\Admin\Contextual\ContextualAdmin::class);
        $canPublish = $contextual->visible([\App\Domain\Admin\Contextual\ContextualControl::link('نشر', 'fa-check', 'admin.khotab.comments.publish', ['comment' => $comment->id])]) !== [];
        $canHide = $contextual->visible([\App\Domain\Admin\Contextual\ContextualControl::link('مسح', 'fa-times', 'admin.khotab.comments.hide', ['comment' => $comment->id])]) !== [];
    @endphp
    <x-admin-portlet title="(إجمالي عدد التعليقات : 1 تعليق)" color="green" icon="fa fa-comments">
        <div class="comment_block" dir="rtl">
            @if ((string) $comment->code !== '' && is_file(public_path('images/flags/'.$comment->code.'.png')))
                <img class="flag" src="/images/flags/{{ $comment->code }}.png" alt="">
            @endif
            عنوان المادة : <a target="_blank" href="/khotab-item-{{ $comment->khid }}.htm">{{ $item->title ?? '' }}</a><br>
            الاسم : {{ $comment->uname }}<br>
            البريد الإلكتروني : <a href="mailto:{{ $comment->uemail }}">{{ $comment->uemail }}</a><br>
            <hr>
            @if ($canPublish)
                <form method="post" action="{{ route('admin.khotab.comments.publish', ['comment' => $comment->id]) }}">
                    @csrf
                    @method('PUT')
                    @if ($queue !== null)
                        <input type="hidden" name="queue" value="{{ $queue['media'] }}"><input type="hidden" name="page" value="{{ $queue['page'] }}">
                    @endif
                    <p style="text-align:center"><textarea cols="68" rows="10" name="comment" class="form-control">{{ old('comment', $comment->comment) }}</textarea></p>
                    <center><button type="submit" class="btn green">نشر التعليـق</button></center>
                </form>
            @else
                <p style="text-align:center"><textarea cols="68" rows="10" class="form-control" disabled>{{ $comment->comment }}</textarea></p>
            @endif
            @if ($canHide)
                <form method="post" action="{{ route('admin.khotab.comments.hide', ['comment' => $comment->id]) }}" style="margin-top:8px">
                    @csrf
                    @if ($queue !== null)
                        <input type="hidden" name="queue" value="{{ $queue['media'] }}"><input type="hidden" name="page" value="{{ $queue['page'] }}">
                    @endif
                    <center><button type="submit" class="btn red">مسح التعليـق</button></center>
                </form>
            @endif
        </div>
        @if ($queue !== null)
            <center><a href="{{ route('admin.khotab.main.comments', $queue) }}">( الرجوع إلى التعليقات التي تنتظر النشر )</a></center>
        @else
            <center><a href="/khotab-item-{{ $comment->khid }}.htm">( الرجوع إلى صفحة المادة )</a></center>
        @endif
    </x-admin-portlet>
@endsection
