@props([
    'title',
    'icon' => 'fa-star',
    'items' => [],
    'meta' => 'downloads',
    'type' => 'fatawa',
    'emptyText' => 'لا توجد مواد حالياً',
])

<section class="w2a-refresh-panel w2a-sidebar-ranking-panel">
    <header class="w2a-refresh-panel__header">
        <span class="w2a-refresh-panel__icon" aria-hidden="true">
            <i class="fa {{ $icon }}"></i>
        </span>
        <div class="w2a-refresh-panel__heading">
            <h2>{{ $title }}</h2>
        </div>
    </header>
    <div class="w2a-refresh-panel__body">
        @if (count($items) > 0)
            <ul class="w2a-ranking-list" role="list">
                @foreach ($items as $item)
                    @php
                        $titleText = trim($item->question_text ?? ($item->title ?? ''));
                        $rank = $loop->iteration;

                        // Resolve target URL cleanly based on content domain
                        if ($type === 'fatawa') {
                            $gId = isset($item->general_question_id)
                                ? str_replace('|', '', (string) $item->general_question_id)
                                : null;
                            $targetUrl = $gId
                                ? "/fatawa-all-{$gId}.htm#{$item->id}"
                                : (isset($item->id)
                                    ? "/fatawa-{$item->id}.htm"
                                    : '#');
                        } elseif ($type === 'khotab') {
                            $targetUrl = isset($item->id) ? "/khotab-item-{$item->id}.htm" : $item->url ?? '#';
                        } elseif ($type === 'telawah') {
                            $targetUrl = isset($item->id) ? "/recite-item-{$item->id}.htm" : $item->url ?? '#';
                        } elseif ($type === 'var') {
                            $targetUrl = isset($item->id) ? "/var-item-{$item->id}.htm" : $item->url ?? '#';
                        } elseif ($type === 'cds') {
                            $targetUrl = isset($item->id) ? "/cds-item-{$item->id}.htm" : $item->url ?? '#';
                        } else {
                            $targetUrl = $item->url ?? (isset($item->id) ? "/item-{$item->id}.htm" : '#');
                        }

                        $thumb =
                            method_exists($item, 'firstThumbnailFilename') && $item->firstThumbnailFilename()
                                ? '/images/cds_image2/' . $item->firstThumbnailFilename()
                                : (!empty($item->thumbnail)
                                    ? '/images/cds_image2/' .
                                        (is_string($item->thumbnail) && str_contains($item->thumbnail, ',')
                                            ? explode(',', $item->thumbnail)[0]
                                            : $item->thumbnail)
                                    : null);

                        $downloads = $item->num_download ?? ($item->downcount ?? ($item->hits ?? null));
                        $views = $item->num_view ?? ($item->views ?? null);
                        $dateVal = $item->mytime ?? ($item->time ?? null);
                        $authorName = $item->author->name ?? ($item->sheikh ?? null);
                    @endphp
                    <li class="w2a-ranking-item">
                        <a href="{{ $targetUrl }}" class="w2a-ranking-item__link" title="{{ $titleText }}">
                            <span class="w2a-ranking-badge w2a-ranking-badge--{{ $rank <= 3 ? $rank : 'default' }}"
                                aria-label="المرتبة {{ $rank }}">
                                {{ $rank }}
                            </span>
                            @if ($thumb)
                                <img src="{{ $thumb }}"
                                    class="w2a-ranking-thumb {{ $type === 'cds' ? 'w2a-ranking-thumb--cd' : '' }}"
                                    alt="{{ $titleText }}" width="48" height="48" loading="lazy"
                                    decoding="async">
                            @endif
                            <div class="w2a-ranking-item__content">
                                <span class="w2a-ranking-item__title">{{ $titleText }}</span>
                                <div class="w2a-ranking-item__meta">
                                    @if ($authorName)
                                        <span class="w2a-ranking-meta-tag w2a-ranking-meta-tag--author">
                                            <i class="fa fa-user" aria-hidden="true"></i> {{ $authorName }}
                                        </span>
                                    @endif

                                    @if ($meta === 'downloads' && $downloads !== null)
                                        <span class="w2a-ranking-meta-tag">
                                            <i class="fa fa-download" aria-hidden="true"></i>
                                            @if ($type === 'cds')
                                                مرات التحميل : {{ $downloads }} مرة
                                            @else
                                                {{ number_format((int) $downloads) }} تحميل
                                            @endif
                                        </span>
                                    @elseif ($meta === 'views' && $views !== null)
                                        <span class="w2a-ranking-meta-tag">
                                            <i class="fa fa-eye" aria-hidden="true"></i>
                                            {{ number_format((int) $views) }} مشاهدة
                                        </span>
                                    @elseif ($meta === 'recent' && $dateVal)
                                        <span class="w2a-ranking-meta-tag">
                                            <i class="fa fa-clock-o" aria-hidden="true"></i>
                                            @if ($type === 'cds')
                                                بتاريخ : {{ \App\Domain\Content\Support\LegacyShortDateFormatter::format((int) $dateVal) }}
                                            @else
                                                {{ \App\Domain\Content\Support\LegacyShortDateFormatter::format((int) $dateVal) }}
                                            @endif
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="w2a-ranking-empty">{{ $emptyText }}</p>
        @endif
    </div>
</section>
