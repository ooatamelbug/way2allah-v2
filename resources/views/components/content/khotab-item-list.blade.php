@props([
    'items',
    'video' => true,
    'showAuthor' => false,
    'showDate' => true,
    'showComments' => true,
    'emptyMessage' => 'لا توجد مواد مطابقة بقاعدة بيانات الموقع لهذا التاريخ',
])

@if($items->isNotEmpty())
    <div class="w2a-items-list-wrap">
        @foreach($items as $item)
            @php
                $duration = \App\Domain\Content\Support\LegacyDurationFormatter::format((int) ($item->adur ?? 0));
                $authorId = (int) ($item->author ?? $item->author_id ?? 0);
            @endphp
            <article class="w2a-item-card-row">
                <div class="w2a-item-icon-badge" aria-hidden="true">
                    <i class="fa {{ $video ? 'fa-video-camera' : 'fa-headphones' }}"></i>
                </div>
                <div class="w2a-item-card-content">
                    <div class="w2a-item-card-header">
                        <a href="/khotab-item-{{ $item->id }}.htm" class="w2a-item-card-title">{{ trim($item->title) }}</a>
                        @if($showAuthor && $authorId > 0 && !empty($item->name))
                            <a href="/khotab-{{ $video ? 'video' : 'audio' }}-{{ $authorId }}.htm" class="w2a-item-card-author">
                                <i class="fa fa-user-circle" aria-hidden="true"></i>
                                <span>{{ $item->name }}</span>
                            </a>
                        @endif
                    </div>
                    <div class="w2a-item-card-meta">
                        @if($showDate)
                            <span class="w2a-meta-pill"><i class="fa fa-calendar" aria-hidden="true"></i> {{ !empty($item->time) ? date('Y-m-d', $item->time) : '' }}</span>
                        @endif
                        @if($showComments && !empty($item->comments))
                            <span class="w2a-meta-pill"><i class="fa fa-commenting-o" aria-hidden="true"></i> {{ $item->comments }} تعليق</span>
                        @endif
                        <span class="w2a-meta-pill"><i class="fa fa-eye" aria-hidden="true"></i> {{ number_format($item->hits) }} مشاهدة</span>
                        @if(!empty($item->channel_id))
                            <a href="/channel-{{ $item->channel_id }}.htm" class="w2a-meta-pill w2a-meta-channel">
                                <i class="fa fa-television" aria-hidden="true"></i>
                                <span>{{ $item->channel ?: 'القناة الفضائية' }}</span>
                            </a>
                        @endif
                        @if($duration !== '00:00:00')
                            <span class="w2a-meta-pill w2a-meta-duration"><i class="fa fa-clock-o" aria-hidden="true"></i> {{ $duration }}</span>
                        @endif
                    </div>
                </div>
                <a href="/khotab-item-{{ $item->id }}.htm" class="w2a-item-action-btn">
                    <span>استماع / مشاهدة</span>
                    <i class="fa fa-angle-right" aria-hidden="true"></i>
                </a>
            </article>
        @endforeach
    </div>
@else
    <div class="w2a-tree-empty" role="status">
        <i class="fa fa-info-circle" aria-hidden="true"></i>
        <h5>{{ $emptyMessage }}</h5>
    </div>
@endif
