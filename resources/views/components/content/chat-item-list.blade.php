@props([
    'items',
    'related' => false,
    'emptyMessage' => 'لا توجد مواد مطابقة بقاعدة بيانات الموقع',
])

@if ($items->isNotEmpty())
    <div class="w2a-items-list-wrap">
        @foreach ($items as $item)
            <article class="w2a-item-card-row">
                <span class="w2a-item-icon-badge" aria-hidden="true"><i class="fa {{ $related ? 'fa-link' : 'fa-volume-up' }}"></i></span>
                <div class="w2a-item-card-content">
                    <div class="w2a-item-card-header">
                        <a href="/chat_lesson_{{ $item->id }}.htm" class="w2a-item-card-title">{{ trim((string) $item->title) }}</a>
                        @if ($related && ! empty($item->author_id) && ! empty($item->name))
                            <a href="/chat_author_{{ $item->author_id }}.htm" class="w2a-item-card-author">
                                <i class="fa fa-user-circle" aria-hidden="true"></i>
                                <span>{{ trim(($item->prename ?? '').' '.$item->name) }}</span>
                            </a>
                        @endif
                    </div>
                    <div class="w2a-item-card-meta">
                        @if (! $related && ! empty($item->time))
                            <span class="w2a-meta-pill"><i class="fa fa-calendar" aria-hidden="true"></i> {{ \App\Domain\Content\Support\LegacyShortDateFormatter::format((int) $item->time) }}</span>
                        @endif
                        @if (isset($item->downcount))
                            <span class="w2a-meta-pill"><i class="fa fa-download" aria-hidden="true"></i> تحميلات: {{ number_format((int) $item->downcount) }}</span>
                        @endif
                        @if (isset($item->hits))
                            <span class="w2a-meta-pill"><i class="fa fa-eye" aria-hidden="true"></i> مشاهدات: {{ number_format((int) $item->hits) }}</span>
                        @endif
                    </div>
                </div>
                <a href="/chat_lesson_{{ $item->id }}.htm" class="w2a-item-action-btn">
                    <span>{{ $related ? 'عرض المادة' : 'استماع / مشاهدة' }}</span>
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
