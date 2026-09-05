@props(['channel'])

<a href="/channel-{{ $channel->id }}.htm" class="w2a-channel-card" data-title="{{ $channel->title }}"
    data-freq="{{ $channel->freq }}">
    <span class="w2a-channel-logo-wrap">
        <img src="/images/channels/{{ $channel->id }}.png" alt="شعار قناة {{ $channel->title }}" class="w2a-channel-logo"
            width="160" height="90" loading="lazy" decoding="async">
    </span>
    <span class="w2a-channel-body">
        <span class="w2a-channel-header">
            <span class="w2a-channel-title">{{ $channel->title }}</span>
            @if (!empty($channel->khotab))
                <span class="w2a-channel-count-badge">{{ $channel->khotab }} مادة</span>
            @endif
        </span>

        <span class="w2a-channel-specs">
            <span class="w2a-channel-spec-item">
                <span class="w2a-channel-spec-label"><i class="fa fa-rss" aria-hidden="true"></i> التردد:</span>
                <span class="w2a-channel-spec-val">{{ $channel->freq ?: '---' }}</span>
            </span>
            <span class="w2a-channel-spec-item">
                <span class="w2a-channel-spec-label"><i class="fa fa-arrows-v" aria-hidden="true"></i> الاستقطاب:</span>
                <span class="w2a-channel-spec-val">{{ $channel->polar ?: 'عمودي (V)' }}</span>
            </span>
            <span class="w2a-channel-spec-item">
                <span class="w2a-channel-spec-label"><i class="fa fa-tachometer" aria-hidden="true"></i> معدل
                    الترميز:</span>
                <span class="w2a-channel-spec-val">{{ $channel->srate ?: '27500' }}</span>
            </span>
            <span class="w2a-channel-spec-item">
                <span class="w2a-channel-spec-label"><i class="fa fa-shield" aria-hidden="true"></i> معامل
                    التصويب:</span>
                <span class="w2a-channel-spec-val">{{ $channel->fec ?: '5/6' }}</span>
            </span>
        </span>

        <span class="w2a-channel-cta">
            <span>مشاهدة برامج القناة</span>
            <i class="fa fa-angle-right" aria-hidden="true"></i>
        </span>
    </span>
</a>
