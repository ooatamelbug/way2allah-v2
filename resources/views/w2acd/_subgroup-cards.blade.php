{{-- The W2ACD group cards — cds-group-{id}.htm (its sub-groups) and cds-main.htm (the top-level groups, visitor
     parity W3). OLD ListGroup() + group_head.htm / group_cd_content.htm (css/style.css .var_group):
     the title on its own line, then the icon (89×88) on the right with four stacked
     lines beside it — sub-groups, CDs, visits, comment. The CD count is the live
     count (OLD printed `$Group[anasheed]`, a column this table lacks, so it was blank). --}}
            <x-content.premium-panel title="قائمة الأقسام الفرعية" icon="fa-sitemap">
                <div class="w2a-subgroups-grid" data-cd-subgroups>
                    @foreach ($subGroups as $sub)
                        <a href="/cds-group-{{ $sub->id }}.htm" class="w2a-subgroup-card w2a-cd-subgroup" data-cd-subgroup="{{ $sub->id }}">
                            <span class="w2a-cd-subgroup-title">{{ $sub->title }}</span>
                            <span class="w2a-cd-subgroup-body">
                                <span class="w2a-cd-subgroup-icon"><img src="{{ $sub->icon_url }}" alt="{{ $sub->title }}" width="89" height="88" loading="lazy" decoding="async"></span>
                                <span class="w2a-cd-subgroup-lines" data-cd-subgroup-lines>
                                    <span class="w2a-cd-subgroup-line is-children">الأقسام الفرعية : {{ (int) $sub->children_count }} قسم</span>
                                    <span class="w2a-cd-subgroup-line is-cds">الإسطوانات : {{ (int) $sub->items_count }} إسطوانة</span>
                                    <span class="w2a-cd-subgroup-line is-hits">الزيارات : {{ number_format((int) $sub->hits) }} زيارة</span>
                                    <span class="w2a-cd-subgroup-line is-comment">التعليق : {{ $sub->des !== '' && $sub->des !== null ? $sub->des : 'بدون تعليق' }}</span>
                                </span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </x-content.premium-panel>
