@props([
    'item',
    'module',
    'date',
    'size',
    'downloadUrl',
    'pdfUrl' => null,
    'pdfCount' => null,
    'notes' => null,
])

@php
    $isVideo = (bool) $item->vedio;
    $playLabel = $isVideo ? 'مشاهدة المادة' : 'استماع المادة';
@endphp

<div class="w2a-item-details-card">
    <div class="w2a-item-header">
        <span class="w2a-item-header-icon" aria-hidden="true">
            <i class="fa {{ $isVideo ? 'fa-video-camera' : 'fa-headphones' }}"></i>
        </span>
        <h2 class="w2a-item-header-title">{{ $item->title }}</h2>
    </div>

    @if (! empty($item->description))
        <div class="w2a-item-desc-box">{{ $item->description }}</div>
    @endif

    <div class="w2a-item-meta-grid">
        <div class="w2a-meta-pill">
            <span class="w2a-meta-icon"><i class="fa fa-calendar" aria-hidden="true"></i></span>
            <span class="w2a-meta-info"><span class="w2a-meta-label">تاريخ التحميل</span><span class="w2a-meta-value">{{ $date }}</span></span>
        </div>
        <div class="w2a-meta-pill">
            <span class="w2a-meta-icon"><i class="fa fa-hdd-o" aria-hidden="true"></i></span>
            <span class="w2a-meta-info"><span class="w2a-meta-label">حجم المادة</span><span class="w2a-meta-value">{{ $size }}</span></span>
        </div>
        <div class="w2a-meta-pill">
            <span class="w2a-meta-icon"><i class="fa fa-eye" aria-hidden="true"></i></span>
            <span class="w2a-meta-info"><span class="w2a-meta-label">عدد الزيارات</span><span class="w2a-meta-value">{{ number_format((int) $item->hits) }} زيارة</span></span>
        </div>
        <div class="w2a-meta-pill">
            <span class="w2a-meta-icon"><i class="fa fa-download" aria-hidden="true"></i></span>
            <span class="w2a-meta-info"><span class="w2a-meta-label">عدد الحفظ</span><span class="w2a-meta-value">{{ number_format((int) $item->downcount) }} مرة</span></span>
        </div>
        @if ($pdfCount !== null && (int) $pdfCount !== 0)
            <div class="w2a-meta-pill">
                <span class="w2a-meta-icon"><i class="fa fa-file-pdf-o" aria-hidden="true"></i></span>
                <span class="w2a-meta-info"><span class="w2a-meta-label">تحميل التفريغ</span><span class="w2a-meta-value">{{ number_format((int) $pdfCount) }} زيارة</span></span>
            </div>
        @endif
        @if ($notes !== null && $notes !== '' && $notes !== 0 && $notes !== '0')
            <div class="w2a-meta-pill">
                <span class="w2a-meta-icon"><i class="fa fa-comment-o" aria-hidden="true"></i></span>
                <span class="w2a-meta-info"><span class="w2a-meta-label">تعليق الدرس</span><span class="w2a-meta-value">{{ is_numeric($notes) ? number_format((float) $notes).' مرة' : $notes }}</span></span>
            </div>
        @endif
    </div>

    <div class="w2a-item-actions-grid">
        <button type="button" onclick="w2a_play({{ $item->id }},'{{ $module }}')" class="w2a-action-btn w2a-action-play">
            <i class="fa {{ $isVideo ? 'fa-play-circle' : 'fa-headphones' }}" aria-hidden="true"></i><span>{{ $playLabel }}</span>
        </button>
        <a href="{{ $downloadUrl }}" target="_blank" rel="noopener" class="w2a-action-btn w2a-action-download">
            <i class="fa fa-floppy-o" aria-hidden="true"></i><span>حفظ المادة</span>
        </a>
        @if ($pdfUrl && $pdfCount !== null && (int) $pdfCount !== 0)
            <a href="{{ $pdfUrl }}" target="_blank" rel="noopener" class="w2a-action-btn w2a-action-pdf">
                <i class="fa fa-file-pdf-o" aria-hidden="true"></i><span>ملف تفريغ</span>
            </a>
        @endif
        <button type="button" data-toggle="modal" data-target="#commentsModal" class="w2a-action-btn w2a-action-comment send-comment-btn">
            <i class="fa fa-commenting" aria-hidden="true"></i><span>اضف تعليقك</span>
        </button>
        <button type="button" data-toggle="modal" data-target="#sendFriendModal" class="w2a-action-btn w2a-action-share send-friend-btn">
            <i class="fa fa-paper-plane" aria-hidden="true"></i><span>أرسل لصديق</span>
        </button>
    </div>
</div>
