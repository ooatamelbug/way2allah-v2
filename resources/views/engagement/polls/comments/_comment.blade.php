{{-- One full comment, as OLD DisplayTopic()/DisplayKids() printed it:
     subject, (التقييم: score), بواسطة name في date, the body (rendered safely,
     cut at commentlimit with «أقرأ باقي التعليق...»), then the actions. --}}
@php
    $c = $comment;
    $body = (string) $c->comment;
    $cut = mb_strlen($body) > $settings['limit'];
    $html = app(\App\Domain\Content\Support\SafeLegacyHtmlRenderer::class)->render($cut ? mb_substr($body, 0, $settings['limit']) : $body);
@endphp
<div class="w2a-poll-comment" id="{{ (int) $c->tid }}" style="border:1px solid #e2e8f0;border-radius:8px;padding:10px 12px;margin:10px 0;background:#fff">
    <div>
        <b>{{ trim((string) $c->subject) !== '' ? $c->subject : '[لا يوجد موضوع]' }}</b>
        <span class="text-muted">(التقييم: {{ (int) $c->score }})</span><br>
        <span class="text-muted">بواسطة {{ trim((string) $c->name) !== '' ? $c->name : $settings['anonymous'] }} في {{ $c->date ? substr((string) $c->date, 0, 16) : '' }}</span>
    </div>
    <div style="margin-top:8px">{!! $html !!}
        @if ($cut)
            <br><br><b><a href="{{ \App\Domain\Engagement\Services\PollCommentUrls::single((int) $poll->pollID, (int) $c->tid, $prefs) }}">أقرأ باقي التعليق...</a></b>
        @endif
    </div>
    <div style="margin-top:6px;font-size:13px">
        [
        @if ($canPost)
            <a href="{{ \App\Domain\Engagement\Services\PollCommentUrls::reply((int) $poll->pollID, (int) $c->tid, $prefs) }}">رد على هذا</a>
        @endif
        @if (! empty($showParent) && (int) $c->pid > 0)
            | <a href="{{ \App\Domain\Engagement\Services\PollCommentUrls::showReply((int) $poll->pollID, (int) $c->pid, (int) \App\Domain\Engagement\Models\PollComment::query()->where('tid', $c->pid)->value('pid'), $prefs) }}">الأصل</a>
        @endif
        ]
        {{-- Contextual administration (OLD «مسح» → Phase 4 thread delete, super-admin). --}}
        @contextualAdmin
            <x-admin.contextual-controls variant="inline" :controls="[\App\Domain\Admin\Contextual\Controls\PublicPageControls::pollComment($c)]" />
        @endcontextualAdmin
    </div>
</div>
