@props(['items', 'module', 'parentId', 'parentVedio' => null])

<div class="w2a-qualities-list">
    @foreach ($items as $mirror)
        @php
            $isKhotab = $module === 'khotab';
            // B2 / F05-2: the same rule as the Khotab mirror player (an audio-flagged `.mp4` of a video item is video).
            $isAudio = $isKhotab
                ? ! \App\Domain\Content\Services\MediaPlayerService::khotabMirrorPlaysAsVideo($mirror->vedio, $parentVedio, (string) $mirror->link)
                : $mirror->isAudioLike();
            $title = $isKhotab ? $mirror->comment : $mirror->title;
            $routePrefix = $isKhotab ? 'khotab-mirror' : 'var-mirror';
            $playerType = $isKhotab ? 'khotab_mirror' : 'anasheed_mirror';
            $path = parse_url((string) $mirror->link, PHP_URL_PATH) ?: (string) $mirror->link;
            $format = strtoupper((string) pathinfo($path, PATHINFO_EXTENSION));
            $format = $format !== '' ? $format : ($isAudio ? 'MP3' : 'MP4');
            // B2 / F07-A: a Variety mirror whose link the /var-mirror route cannot serve gets no download link; F07-B2: nor
            // does a hidden mirror (the route refuses it). Khotab unchanged.
            $downloadable = $isKhotab || ((int) ($mirror->hidden ?? 0) === 0 && \App\Domain\Content\Support\VarietyMediaLink::isDownloadable($mirror->link));
            // Khotab feedback (2026-11-18): a mirror in a format browsers cannot play (RM / RMVB / WMV …, the player's own
            // BrowserPlaybackFormat list) offers no «مشاهدة» button — it would only open the "unsupported format" notice.
            // Download only; Khotab mirrors are always downloadable. Variety cards are unchanged.
            $downloadOnly = $isKhotab && \App\Domain\Content\Support\BrowserPlaybackFormat::isUnsupported((string) $mirror->link);
        @endphp
        <article class="w2a-quality-card">
            <div class="w2a-quality-meta">
                <span class="w2a-quality-num">{{ $loop->iteration }}</span>
                <div class="w2a-quality-title-wrap">
                    @if ($downloadable)
                        <a class="w2a-quality-title" href="/{{ $routePrefix }}-{{ $parentId }}-{{ $mirror->id }}.htm" download>{{ $title }}</a>
                    @else
                        <span class="w2a-quality-title">{{ $title }}</span>
                    @endif
                    <div class="w2a-quality-badges">
                        <span class="w2a-quality-badge-fmt">{{ $format }}</span>
                        <span><i class="fa fa-database" aria-hidden="true"></i> {{ \App\Domain\Content\Support\LegacyFileSizeFormatter::format((int) ($mirror->linksize ?? 0)) }}</span>
                        <span><i class="fa fa-download" aria-hidden="true"></i> {{ number_format((int) $mirror->hits) }} تنزيل</span>
                    </div>
                </div>
            </div>
            <div class="w2a-quality-actions">
                @if ($downloadOnly)
                    <span class="w2a-quality-download-only" title="هذه الصيغة لا تعمل داخل المتصفح؛ حمّل الملف وشغّله ببرنامج يدعمها" data-download-only>
                        <i class="fa fa-info-circle" aria-hidden="true"></i> يتطلب التحميل للتشغيل
                    </span>
                @else
                    <button type="button" class="w2a-quality-play-btn" onclick="w2a_play({{ $mirror->id }},'{{ $playerType }}')">
                        <i class="fa {{ $isAudio ? 'fa-headphones' : 'fa-play-circle' }}" aria-hidden="true"></i> {{ $isAudio ? 'استماع' : 'مشاهدة' }}
                    </button>
                @endif
                @if ($downloadable)
                    <a href="/{{ $routePrefix }}-{{ $parentId }}-{{ $mirror->id }}.htm" download class="w2a-quality-down-btn">
                        <i class="fa fa-download" aria-hidden="true"></i> تحميل
                    </a>
                @endif
            </div>
            {{-- Contextual administration (OLD Mirror(): edit / delete beside each mirror). --}}
            @contextualAdmin
                <x-admin.contextual-controls variant="inline" :controls="$isKhotab
                    ? \App\Domain\Admin\Contextual\Controls\KhotabItemControls::forMirror($mirror)
                    : \App\Domain\Admin\Contextual\Controls\AnasheedItemControls::forMirror($mirror)" />
            @endcontextualAdmin
        </article>
    @endforeach
</div>
