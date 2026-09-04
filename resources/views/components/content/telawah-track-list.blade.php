@props(['items'])

@if ($items->isNotEmpty())
    <div class="w2a-qualities-list">
        @foreach ($items as $item)
            <article class="w2a-quality-card">
                <div class="w2a-quality-meta">
                    <span class="w2a-quality-num" aria-hidden="true">{{ $loop->iteration }}</span>
                    <div class="w2a-quality-title-wrap">
                        <a class="w2a-quality-title" href="/recite-item-{{ $item->id }}.htm">{{ $item->title }}</a>
                        <div class="w2a-quality-badges">
                            <span class="w2a-quality-badge-fmt">MP3</span>
                            <span><i class="fa fa-book" aria-hidden="true"></i> تلاوة قرآنية</span>
                        </div>
                    </div>
                </div>
                <div class="w2a-quality-actions">
                    <button type="button" class="w2a-quality-play-btn" onclick="w2a_play({{ $item->id }}, 'telawat')">
                        <i class="fa fa-headphones" aria-hidden="true"></i> استماع
                    </button>
                    <a href="/recite-download-{{ $item->id }}.htm" class="w2a-quality-down-btn">
                        <i class="fa fa-download" aria-hidden="true"></i> تحميل
                    </a>
                </div>
            </article>
        @endforeach
    </div>
@else
    <div class="w2a-tree-empty" role="status">
        <i class="fa fa-info-circle" aria-hidden="true"></i>
        <h5>عفوا ، لا يوجد تلاوات مضافة في هذا القسم</h5>
    </div>
@endif
