@props(['items', 'module', 'parentId'])

<div class="w2a-qualities-list">
    @foreach ($items as $mirror)
        @php
            $isKhotab = $module === 'khotab';
            $isAudio = $isKhotab ? (int) $mirror->vedio === 0 : $mirror->isAudioLike();
            $title = $isKhotab ? $mirror->comment : $mirror->title;
            $routePrefix = $isKhotab ? 'khotab-mirror' : 'var-mirror';
            $playerType = $isKhotab ? 'khotab_mirror' : 'anasheed_mirror';
            $path = parse_url((string) $mirror->link, PHP_URL_PATH) ?: (string) $mirror->link;
            $format = strtoupper((string) pathinfo($path, PATHINFO_EXTENSION));
            $format = $format !== '' ? $format : ($isAudio ? 'MP3' : 'MP4');
        @endphp
        <article class="w2a-quality-card">
            <div class="w2a-quality-meta">
                <span class="w2a-quality-num">{{ $loop->iteration }}</span>
                <div class="w2a-quality-title-wrap">
                    <a class="w2a-quality-title" href="/{{ $routePrefix }}-{{ $parentId }}-{{ $mirror->id }}.htm" download>{{ $title }}</a>
                    <div class="w2a-quality-badges">
                        <span class="w2a-quality-badge-fmt">{{ $format }}</span>
                        <span><i class="fa fa-database" aria-hidden="true"></i> {{ \App\Domain\Content\Support\LegacyFileSizeFormatter::format((int) ($mirror->linksize ?? 0)) }}</span>
                        <span><i class="fa fa-download" aria-hidden="true"></i> {{ number_format((int) $mirror->hits) }} تنزيل</span>
                    </div>
                </div>
            </div>
            <div class="w2a-quality-actions">
                <button type="button" class="w2a-quality-play-btn" onclick="w2a_play({{ $mirror->id }},'{{ $playerType }}')">
                    <i class="fa {{ $isAudio ? 'fa-headphones' : 'fa-play-circle' }}" aria-hidden="true"></i> {{ $isAudio ? 'استماع' : 'مشاهدة' }}
                </button>
                <a href="/{{ $routePrefix }}-{{ $parentId }}-{{ $mirror->id }}.htm" download class="w2a-quality-down-btn">
                    <i class="fa fa-download" aria-hidden="true"></i> تحميل
                </a>
            </div>
        </article>
    @endforeach
</div>
