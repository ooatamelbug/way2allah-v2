@props(['series', 'category'])

<div class="w2a-series-download-panel">
    <p class="w2a-series-download-intro">اختر المواد التي تريد إضافتها إلى قائمة التنزيل.</p>

    <div class="w2a-series-download-actions">
        <a class="w2a-series-download-action" href="/khotab-series-{{ $series->id }}-{{ $category->id }}.grx">
            <span class="w2a-series-download-icon" aria-hidden="true"><i class="fa fa-folder-open-o"></i></span>
            <span class="w2a-series-download-copy">
                <strong>تحميل مواد التصنيف</strong>
                <small>المواد الموجودة في هذا التصنيف فقط</small>
            </span>
            <i class="fa fa-chevron-left w2a-series-download-arrow" aria-hidden="true"></i>
        </a>

        <a class="w2a-series-download-action w2a-series-download-action--primary" href="/khotab-series-{{ $series->id }}.grx">
            <span class="w2a-series-download-icon" aria-hidden="true"><i class="fa fa-download"></i></span>
            <span class="w2a-series-download-copy">
                <strong>تحميل السلسلة بالكامل</strong>
                <small>كل مواد السلسلة في قائمة واحدة</small>
            </span>
            <i class="fa fa-chevron-left w2a-series-download-arrow" aria-hidden="true"></i>
        </a>
    </div>

    <div class="w2a-series-download-note" role="note">
        <span class="w2a-series-download-note-icon" aria-hidden="true"><i class="fa fa-info-circle"></i></span>
        <div class="w2a-series-download-note-copy">
            <strong>قبل بدء التنزيل</strong>
            <p>تعمل قوائم التنزيل المجمعة بواسطة برنامج GetRight.</p>
            <a href="http://download.getright.com/getright-download.exe" target="_blank" rel="noopener noreferrer">
                تنزيل برنامج GetRight
                <i class="fa fa-external-link" aria-hidden="true"></i>
            </a>
        </div>
    </div>
</div>
