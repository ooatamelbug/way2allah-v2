{{-- OLD navbar(): "poll title" | N comments, the «أرسل تعليق» button for anyone
     allowed to post (not in nocomments mode), the ownership notice, and the
     guest notice when anonymous posting is off. --}}
<div class="text-center" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px;margin-bottom:10px">
    <div>"{{ $poll->pollTitle }}" | <b>{{ $count }}</b> {{ $count === 1 ? 'تعليق' : 'تعليقات' }}</div>
    @if ($canPost && $prefs['mode'] !== 'nocomments')
        <div style="margin:8px 0"><a class="btn btn-primary btn-sm" href="{{ \App\Domain\Engagement\Services\PollCommentUrls::reply((int) $poll->pollID, 0, $prefs) }}">أرسل تعليق</a></div>
    @endif
    <div class="text-muted" style="font-size:12px">التعليقات مملوكة لأصحابها. نحن غير مسؤلون عن محتواها.</div>
</div>
@if (! $settings['anonpost'] && ($member ?? null) === null)
    <div class="alert alert-info text-center">التعليق غير مسموح للضيوف, الرجاء التسجيل في المنتدى ثم تسجيل الدخول.</div>
@endif
