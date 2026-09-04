@props(['groups', 'subgroups' => false])

<div class="w2a-reciters-wrap">
    <div class="w2a-reciters-toolbar">
        <div class="w2a-reciters-search-wrap">
            <i class="fa fa-search w2a-reciters-search-icon" aria-hidden="true"></i>
            <label class="sr-only" for="w2a_reciter_search_input">{{ $subgroups ? 'ابحث باسم القسم الفرعي' : 'ابحث باسم القارئ' }}</label>
            <input type="search" id="w2a_reciter_search_input" class="w2a-reciters-search-input" placeholder="{{ $subgroups ? 'ابحث باسم القسم الفرعي...' : 'ابحث باسم القارئ...' }}" autocomplete="off">
            <button type="button" id="w2a_reciter_search_clear" class="w2a-reciters-search-clear" hidden aria-label="مسح البحث"><i class="fa fa-times" aria-hidden="true"></i></button>
        </div>
        <div class="w2a-tree-badge">
            <i class="fa fa-users" aria-hidden="true"></i> {{ $groups->count() }} {{ $subgroups ? 'قسم فرعي' : 'قارئ' }}
        </div>
    </div>

    <div class="w2a-reciters-grid">
        @foreach($groups as $group)
            @php($comment = empty($group->des) ? 'تلاوات قرآنية خاشعة ومجودة' : $group->des)
            <a href="/recite-group-{{ $group->id }}.htm" class="w2a-reciter-card" data-title="{{ $group->title }}">
                <span class="w2a-reciter-card-top">
                    <span class="w2a-reciter-avatar-wrap">
                        <img src="/images/telawah.gif" alt="{{ $group->title }}" class="w2a-reciter-avatar" width="120" height="120" loading="lazy" decoding="async">
                    </span>
                    <span class="w2a-reciter-info">
                        <span class="w2a-reciter-name">{{ $group->title }}</span>
                        @if((int) $group->telawah > 0)
                            <span class="w2a-reciter-count-badge">{{ (int) $group->telawah }} تلاوة</span>
                        @endif
                    </span>
                </span>
                <span class="w2a-reciter-meta">
                    <span class="w2a-reciter-meta-item"><i class="fa fa-sitemap" aria-hidden="true"></i> <span>{{ (int) $group->child }} قسم فرعي</span></span>
                    <span class="w2a-reciter-meta-item"><i class="fa fa-eye" aria-hidden="true"></i> <span>{{ number_format((int) $group->hits) }} زيارة</span></span>
                </span>
                <span class="w2a-reciter-comment">{{ \App\Domain\Content\Support\LegacyTextTruncator::words($comment, 90) }}</span>
                <span class="w2a-reciter-cta"><span>استماع للتلاوات</span><i class="fa fa-angle-right" aria-hidden="true"></i></span>
            </a>
        @endforeach
    </div>
    <p id="w2a_reciter_result_status" class="sr-only" aria-live="polite"></p>
</div>
