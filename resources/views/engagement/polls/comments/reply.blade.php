@extends('layouts.app')

@section('title', 'ارسال تعليق على استفتاء')

{{-- OLD reply() / replyPreview(): what is being answered (the parent comment,
     or «تعليق مباشر على الإستفتاء...»), then the form — name (the member, or
     the anonymous name), subject (default "Re: …"), comment (HTML not
     allowed), «ارسال الموضوع باسم زائر» when anonymous posting is on, and the
     two buttons «استعراض» / «موافق!». --}}
@section('content')
    <x-page-chrome heading="ارسال تعليق على استفتاء" :breadcrumb="[
        ['title' => 'الإستفتاءات', 'url' => '/surveys.htm'],
        ['title' => $poll->pollTitle, 'url' => '/survey-comments-'.$poll->pollID.'.htm'],
        ['title' => 'ارسال تعليق', 'url' => ''],
    ]" />
    <x-content.premium-panel :title="isset($preview) ? 'عرض تعليق على الإستفتاء' : 'ارسال تعليق على استفتاء'" icon="fa-pencil">
        @if (! $canPost)
            <div class="alert alert-info text-center">التعليق غير مسموح للضيوف, الرجاء التسجيل في المنتدى ثم تسجيل الدخول.</div>
        @else
            @if (isset($preview))
                <div style="border:1px dashed #94a3b8;border-radius:8px;padding:10px;margin-bottom:12px">
                    <b>{{ strip_tags($preview['subject']) }}</b><br>
                    <span class="text-muted">بواسطة {{ $member && ! ($xanonpost ?? false) ? $member->username : $settings['anonymous'] }}</span><br><br>
                    {!! nl2br(e($preview['comment']), false) !!}
                </div>
            @else
                <div class="text-center" style="margin-bottom:12px">
                    <b>{{ $parent ? (trim((string) $parent->subject) !== '' ? $parent->subject : '[لا يوجد موضوع]') : $poll->pollTitle }}</b><br>
                    @if ($parent)
                        <div style="margin-top:8px">{!! app(\App\Domain\Content\Support\SafeLegacyHtmlRenderer::class)->render((string) $parent->comment) !!}</div>
                    @else
                        <i>تعليق مباشر على الإستفتاء...</i>
                    @endif
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger"><ul class="list-unstyled" style="margin:0">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
            @endif

            <form method="post" action="{{ route('engagement.polls.comments.submit', $poll) }}">
                @csrf
                <p><b>اسمك:</b> {{ $member ? $member->username : $settings['anonymous'] }}</p>
                <label><b>الموضوع:</b><br>
                    <input type="text" name="subject" maxlength="60" size="50" class="form-control" value="{{ old('subject', $preview['subject'] ?? $defaultSubject) }}"></label>
                <label style="display:block;margin-top:8px"><b>تعليق:</b><br>
                    <textarea name="comment" cols="70" rows="10" class="form-control">{{ old('comment', $preview['comment'] ?? '') }}</textarea></label>
                <p class="text-muted">كود HTML غير مسموح.</p>
                @if ($member && $settings['anonpost'])
                    <label><input type="checkbox" name="xanonpost" value="1" @checked(old('xanonpost', $xanonpost ?? false))> ارسال الموضوع باسم زائر</label><br>
                @endif
                <input type="hidden" name="pid" value="{{ $parent ? (int) $parent->tid : 0 }}">
                <input type="hidden" name="mode" value="{{ $prefs['mode'] }}">
                <input type="hidden" name="order" value="{{ $prefs['order'] }}">
                <input type="hidden" name="thold" value="{{ $prefs['thold'] }}">
                <button type="submit" name="op" value="preview" class="btn btn-default">استعراض</button>
                <button type="submit" name="op" value="ok" class="btn btn-primary">موافق!</button>
            </form>
        @endif
    </x-content.premium-panel>
@endsection
