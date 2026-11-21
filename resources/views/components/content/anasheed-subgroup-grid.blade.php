@props(['groups', 'itemCounts' => null])

{{-- OLD ListGroup() / group_cont.htm (visitor parity A9, 2026-11-04): the group icon (media/anasheed/icons when
     icon = 1, else images/pix001.gif), the sub-group / item / visit counts — zero included — and «التعليق» (des, or
     «بدون تعليق» when empty). F08 / B4 (owner-approved 2026-11-15): the item count is the publicly visible count
     (`$itemCounts`, hidden = 0) when the page supplies it; the stored `anasheed` counter is only a fallback. --}}
<div class="w2a-subgroups-grid">
    @foreach($groups as $group)
        <a href="/var-group-{{ $group->id }}.htm" class="w2a-subgroup-card">
            <span class="w2a-subgroup-card-top">
                <span class="w2a-subgroup-icon-wrap w2a-subgroup-icon-wrap--image">
                    <img src="{{ $group->thumbUrl() }}" alt="{{ $group->title }}" width="75" height="100" loading="lazy" decoding="async">
                </span>
                <span class="w2a-subgroup-info">
                    <span class="w2a-subgroup-title">{{ $group->title }}</span>
                    <span class="w2a-subgroup-count"><i class="fa fa-film" aria-hidden="true"></i> {{ (int) ($itemCounts !== null ? ($itemCounts[(int) $group->id] ?? 0) : $group->anasheed) }} مقطع</span>
                </span>
            </span>
            <span class="w2a-reciter-meta">
                <span class="w2a-reciter-meta-item"><i class="fa fa-sitemap" aria-hidden="true"></i> <span>{{ (int) $group->child }} قسم فرعي</span></span>
                <span class="w2a-reciter-meta-item"><i class="fa fa-eye" aria-hidden="true"></i> <span>{{ number_format((int) $group->hits) }} زيارة</span></span>
            </span>
            <span class="w2a-subgroup-notes"><strong>التعليق :</strong> {{ trim((string) $group->des) !== '' ? $group->des : 'بدون تعليق' }}</span>
        </a>
    @endforeach
</div>
