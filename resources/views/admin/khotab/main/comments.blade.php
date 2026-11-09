@extends('layouts.admin')

@section('title', 'تعليقات تنتظر النشر')

@section('content')
    {{-- OLD WaitingList(0, 1, page) + addcomments.htm (modules/Khotab/admin/index.php:3048): every pending
         comment, 30 a page, each «لم يراجع» / «نشر التعليق» / «مسح التعليق», one «موافق» for the page.
         A choice shows only to an admin whose permission allows it; the edit icon opens the approved
         single-comment page, which comes back here. --}}
    <x-admin-portlet :title="'(إجمالي عدد التعليقات : '.$total.' تعليق)'" color="green" icon="fa fa-comments">
        @include('admin.mirrors._errors')
        {{ $comments->links() }}
        <form method="post" action="{{ route('admin.khotab.main.comments.moderate', ['media' => $media]) }}" dir="rtl">
            @csrf
            <input type="hidden" name="page" value="{{ $comments->currentPage() }}">
            @foreach ($comments as $comment)
                <div class="comment_block" style="border-bottom:1px solid #ddd;padding:8px 0" data-pending-comment="{{ $comment->id }}">
                    @if ((string) $comment->code !== '' && is_file(public_path('images/flags/'.$comment->code.'.png')))
                        <img class="flag" src="/images/flags/{{ $comment->code }}.png" alt="">
                    @endif
                    عنوان المادة : <a target="_blank" href="/khotab-item-{{ $comment->khid }}.htm">{{ $comment->item_title }}</a><br>
                    الاسم : {{ $comment->uname }}<br>
                    البريد الإلكتروني : <a href="mailto:{{ $comment->uemail }}">{{ $comment->uemail }}</a><br>
                    <hr>
                    التعليق : {!! nl2br(e((string) $comment->comment)) !!}
                    <a href="{{ route('admin.khotab.comments.edit', ['comment' => $comment->id, 'queue' => $media, 'page' => $comments->currentPage()]) }}" title="تعديل التعليق"><i class="fa fa-pencil" aria-hidden="true"></i> تعديل التعليق</a>
                    <hr>
                    <label class="comment_not"><input type="radio" value="0" name="Comment[{{ $comment->id }}]" checked> لم يراجع</label>
                    @if ($canPublish)
                        &nbsp;<label class="comment_pub"><input type="radio" value="1" name="Comment[{{ $comment->id }}]"> نشر التعليق</label>
                    @endif
                    @if ($canHide)
                        &nbsp;<label class="comment_del"><input type="radio" value="2" name="Comment[{{ $comment->id }}]"> مسح التعليق</label>
                    @endif
                </div>
            @endforeach
            <br>
            <center><button type="submit" class="btn green">موافق</button></center>
        </form>
        {{ $comments->links() }}
        <p><a href="/khotab-{{ $media }}.htm" class="btn default">رجوع</a></p>
    </x-admin-portlet>
@endsection
