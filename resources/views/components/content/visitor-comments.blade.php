@props(['comments'])

<div class="w2a-comments-wrap">
    <div class="w2a-comments-header-bar">
        <span class="w2a-comments-count-pill">
            <i class="fa fa-comments" aria-hidden="true"></i>
            إجمالي التعليقات: {{ number_format($comments->total()) }} تعليق
        </span>
    </div>

    <div class="w2a-comments-list">
        @foreach ($comments as $comment)
            @php
                $isModerator = (int) $comment->uid === 0
                    && (trim((string) $comment->uname) === '' || $comment->uname === 'مشرف التعليقات');
                $author = $isModerator ? 'مشرف التعليقات' : trim((string) $comment->uname);
                $flagCode = $isModerator ? 'way2allah' : trim((string) $comment->code);
                $hasFlag = $flagCode !== '' && is_file(public_path('images/flags/'.$flagCode.'.png'));
            @endphp

            <article class="w2a-comment-card">
                <header class="w2a-comment-header">
                    <div class="w2a-comment-user-info">
                        <span class="w2a-comment-avatar-wrap" aria-hidden="true">
                            @if ($hasFlag)
                                <img
                                    src="/images/flags/{{ $flagCode }}.png"
                                    alt=""
                                    class="w2a-comment-flag"
                                    width="28"
                                    height="20"
                                    loading="lazy"
                                >
                            @else
                                <span class="w2a-comment-avatar-fallback"><i class="fa fa-user"></i></span>
                            @endif
                        </span>
                        <span class="w2a-comment-author">
                            {{ $author !== '' ? $author : 'زائر' }}
                            @if ($isModerator)
                                <span class="w2a-comment-mod-badge"><i class="fa fa-check-circle" aria-hidden="true"></i> مشرف</span>
                            @endif
                        </span>
                    </div>
                    <time class="w2a-comment-date" datetime="{{ date('c', (int) $comment->mytime) }}">
                        <i class="fa fa-clock-o" aria-hidden="true"></i>
                        {{ \App\Domain\Content\Support\LegacyShortDateFormatter::format((int) $comment->mytime) }}
                    </time>
                </header>
                <p class="w2a-comment-body">{!! nl2br(e((string) $comment->comment)) !!}</p>
                <span class="w2a-comment-footer-icon" aria-hidden="true"><i class="fa fa-quote-right"></i></span>
            </article>
        @endforeach
    </div>

    {{ $comments->links() }}
</div>
