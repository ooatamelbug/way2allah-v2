<?php

namespace App\Domain\Content\Support;

final class SearchDepartments
{
    /** `functions.php:1187-1198`'s `w2a_search_depts_arr()`, minus `gallery`/`cds` (confirmed dead, not offered). */
    public const OPTIONS = [
        'video' => 'المرئيات',
        'audio' => 'الصوتيات',
        'dumped_files' => 'المواد المفرغة',
        'anasheed' => 'الأناشيد',
        'sections' => 'المقاطع المؤثرة',
        'cartoon' => 'الكارتون',
        'documentary' => 'الوثائقيات',
        'video_sections' => 'مقاطع المرئية',
        'fatawa' => 'الفتاوى المرئية',
    ];
}
