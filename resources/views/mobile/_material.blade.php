{{-- Phase 7 — one material row (OLD's listing entry). --}}
@php($badge = \App\Domain\Content\Services\Mobile\MobileCatalog::newBadge($material->date))
<div class="w2a-mobile-material" style="border-bottom:1px solid #eee;padding:8px 0">
    <strong><a href="/mobileview-details-{{ $material->lid }}.htm">{{ $material->title }}</a></strong>
    @if ($badge === 'today') <span class="label label-success">جديد اليوم</span>
    @elseif ($badge === '3days') <span class="label label-info">جديد خلال 3 أيام</span>
    @elseif ($badge === 'week') <span class="label label-default">جديد هذا الأسبوع</span>
    @endif
    <div class="text-muted" style="font-size:12px">
        {{ $material->date ? substr($material->date, 0, 10) : '' }}
        · تحميل: {{ (int) $material->hits }}
        @if ((int) $material->totalvotes > 0) · التقييم: {{ (int) $material->downloadratingsummary }} ({{ (int) $material->totalvotes }} صوت) @endif
        @if ((int) $material->totalcomments > 0) · <a href="/mobileview-comments-{{ $material->lid }}.htm">تعليقات ({{ (int) $material->totalcomments }})</a> @endif
    </div>
    {{-- Contextual administration (OLD DownloadsModDownload edit icon beside each material). --}}
    @contextualAdmin
        <x-admin.contextual-controls variant="inline" :controls="[\App\Domain\Admin\Contextual\Controls\PublicPageControls::mobileMaterial($material)]" />
    @endcontextualAdmin
    @if (trim($material->description) !== '')
        <div style="white-space:pre-line">{{ \Illuminate\Support\Str::limit(\App\Domain\Content\Services\Mobile\MobileSite::plainText($material->description), 300) }}</div>
    @endif
</div>
