@props(['groups'])

<div class="w2a-subgroups-grid">
    @foreach($groups as $group)
        <a href="/var-group-{{ $group->id }}.htm" class="w2a-subgroup-card">
            <span class="w2a-subgroup-card-top">
                <span class="w2a-subgroup-icon-wrap"><i class="fa fa-folder-open" aria-hidden="true"></i></span>
                <span class="w2a-subgroup-info">
                    <span class="w2a-subgroup-title">{{ $group->title }}</span>
                    @if((int) $group->anasheed > 0)
                        <span class="w2a-subgroup-count"><i class="fa fa-film" aria-hidden="true"></i> {{ (int) $group->anasheed }} مقطع</span>
                    @endif
                </span>
            </span>
            <span class="w2a-reciter-meta">
                <span class="w2a-reciter-meta-item"><i class="fa fa-sitemap" aria-hidden="true"></i> <span>{{ (int) $group->child }} قسم فرعي</span></span>
                <span class="w2a-reciter-meta-item"><i class="fa fa-eye" aria-hidden="true"></i> <span>{{ number_format((int) $group->hits) }} زيارة</span></span>
            </span>
        </a>
    @endforeach
</div>
