{{-- The CD card grid — cds-main.htm and cds-group-{id}.htm (OLD w2acd_cont.htm). --}}
            <div class="w2a-cd-grid">
                @forelse ($items as $item)
                    @php
                        $listPhoto = $item->firstThumbnailFilename()
                            ? '/images/cds_image2/' . $item->firstThumbnailFilename()
                            : '/images/way2_cddefault.png';
                        $listThumb = \App\Domain\Content\Support\MediaUrl::thumbnail('h=260&w=260&src=' . $listPhoto);
                    @endphp
                    <article class="w2a-cd-card">
                        <a href="/cds-item-{{ $item->id }}.htm" class="w2a-cd-card__link">
                            <span class="w2a-cd-card__media">
                                <img src="{{ $listThumb }}" alt="غلاف إسطوانة {{ $item->title }}" width="260"
                                    height="260" loading="lazy" decoding="async">
                                <span class="w2a-cd-card__play" aria-hidden="true"><i class="fa fa-play"></i></span>
                            </span>
                            <span class="w2a-cd-card__content">
                                <strong>{{ $item->title }}</strong>
                                <span>عرض محتويات الإسطوانة <i class="fa fa-angle-right" aria-hidden="true"></i></span>
                            </span>
                        </a>
                    </article>
                @empty
                    <div class="w2a-empty-state w2a-cd-grid__empty">
                        <i class="fa fa-folder-open-o" aria-hidden="true"></i>
                        <strong>لا توجد إسطوانات متاحة حالياً.</strong>
                    </div>
                @endforelse
            </div>
