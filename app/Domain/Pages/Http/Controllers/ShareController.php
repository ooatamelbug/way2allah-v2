<?php

namespace App\Domain\Pages\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * Replaces `help/share.php` (Roadmap task 6.3, gated on Business
 * Confirmation #2 — RESOLVED: active/required, temporary placeholder
 * banner assets approved). Legacy source has zero interactivity, zero
 * database interaction, and zero request-input handling (confirmed by
 * full-file re-read, Task 6.3 investigation §6) — a pure static page, same
 * shape as `PrivacyController`/`AboutController`.
 *
 * The ~25 banner images stay referenced at their exact legacy (confirmed
 * broken — no `/w2a/` directory exists anywhere in this codebase) URLs,
 * per explicit authorization: no new placeholder/asset pipeline is built,
 * the approved "placeholder" is simply keeping the same broken reference
 * legacy already has.
 */
class ShareController
{
    /**
     * `help/share.php`'s ~25 banner images, grouped by size exactly as the
     * legacy markup groups them, each `file` appended to the same
     * hardcoded `https://way2allah.com/w2a/` base the legacy file uses.
     * Width/height copied verbatim, including 2 confirmed legacy
     * irregularities kept as-is rather than "corrected": the second
     * 336x280 banner (`336-280-1.gif`) has no width/height attributes at
     * all in the legacy markup (`null` here), and `468-60-9.gif` is
     * `height="62"`, not `60` like its 9 siblings.
     */
    private const BANNER_GROUPS = [
        'مقاس 300*600' => [
            ['file' => '300-600-1.gif', 'width' => 300, 'height' => 600],
        ],
        'مقاس 336*280' => [
            ['file' => '336-280-2.gif', 'width' => 330, 'height' => 280],
            ['file' => '336-280-1.gif', 'width' => null, 'height' => null],
        ],
        'مقاس 300*250' => [
            ['file' => '300-250-1.gif', 'width' => 300, 'height' => 250],
        ],
        'مقاس 250*250' => [
            ['file' => '250-250-1.gif', 'width' => 250, 'height' => 250],
        ],
        'مقاس 468*60' => [
            ['file' => '468-60-1.gif', 'width' => 468, 'height' => 60],
            ['file' => '468-60-2.gif', 'width' => 468, 'height' => 60],
            ['file' => '468-60-3.gif', 'width' => 468, 'height' => 60],
            ['file' => '468-60-4.gif', 'width' => 468, 'height' => 60],
            ['file' => '468-60-5.gif', 'width' => 468, 'height' => 60],
            ['file' => '468-60-6.gif', 'width' => 468, 'height' => 60],
            ['file' => '468-60-7.gif', 'width' => 468, 'height' => 60],
            ['file' => '468-60-8.gif', 'width' => 468, 'height' => 60],
            ['file' => '468-60-9.gif', 'width' => 468, 'height' => 62],
            ['file' => '468-60-10.gif', 'width' => 468, 'height' => 60],
        ],
        'مقاس 110*110' => [
            ['file' => '110-110-1.gif', 'width' => 110, 'height' => 110],
            ['file' => '110-110-2.gif', 'width' => 110, 'height' => 110],
            ['file' => '110-110-3.gif', 'width' => 110, 'height' => 110],
        ],
        'مقاس 150*60' => [
            ['file' => '150-60-1.gif', 'width' => 150, 'height' => 60],
            ['file' => '150-60-2.gif', 'width' => 150, 'height' => 60],
            ['file' => '150-60-3.gif', 'width' => 150, 'height' => 60],
            ['file' => '150-60-4.gif', 'width' => 150, 'height' => 60],
        ],
        'مقاس 120*60' => [
            ['file' => '120-60-1.gif', 'width' => 120, 'height' => 60],
            ['file' => '120-60-2.gif', 'width' => 120, 'height' => 60],
            ['file' => '120-60-3.gif', 'width' => 120, 'height' => 60],
        ],
    ];

    public function __invoke(): View
    {
        return view('pages.share', [
            'bannerBaseUrl' => 'https://way2allah.com/w2a/',
            'bannerGroups' => self::BANNER_GROUPS,
        ]);
    }
}
