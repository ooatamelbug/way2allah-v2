@extends('layouts.admin')

@section('title', 'تعديل التعليق')

@section('content')
    {{-- OLD WaitingList(id) + addcomment.htm (modules/Anasheed/admin/index.php): one comment, its text
         editable, with «نشر التعليق للمادة» (AddComment) and «مسح التعليق للمادة» (DelComment); both
         return to the item page, as OLD's pre-checked «التحويل إلى صفحة المادة» did. --}}
    <x-admin-portlet title="الـــتـــعـــلـــيـــقـــات (1 تعليق)" color="green" icon="fa fa-comments">
        @include('admin.mirrors._errors')
        <div class="comment_block" dir="rtl">
            @if ((string) $comment->code !== '' && is_file(public_path('images/flags/'.$comment->code.'.png')))
                <img class="flag" src="/images/flags/{{ $comment->code }}.png" alt="">
            @endif
            عنوان المادة : <a target="_blank" href="/var-item-{{ $comment->khid }}.htm">{{ $item->title ?? '' }}</a><br>
            الاسم : {{ (int) $comment->uid === 0 && trim((string) $comment->uname) === '' ? 'مشرف التعليقات' : $comment->uname }}<br>
            <hr>
            <form method="post" action="{{ route('admin.anasheed.comments.publish', ['anasheedComment' => $comment->id]) }}">
                @csrf
                @method('PUT')
                <p style="text-align:center"><textarea cols="68" rows="4" name="comment" class="form-control" required>{{ old('comment', $comment->comment) }}</textarea></p>
                <center><button type="submit" class="btn green">نشر التعليق للمادة</button></center>
            </form>
            <form method="post" action="{{ route('admin.anasheed.comments.hide', ['anasheedComment' => $comment->id]) }}" style="margin-top:8px">
                @csrf
                <center><button type="submit" class="btn red">مسح التعليق للمادة</button></center>
            </form>
        </div>
        <center><a href="/var-item-{{ $comment->khid }}.htm">( الرجوع إلى صفحة المادة )</a></center>
    </x-admin-portlet>
@endsection
