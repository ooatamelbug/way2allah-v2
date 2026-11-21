<?php

namespace App\Domain\Admin\Support;

/**
 * F01 / B6a — the fixed vocabulary of OLD's advertising module
 * (`old-project/modules/ads/admin/index.php:37-51`), reproduced verbatim so
 * stored `nuke_ads` values keep their meaning:
 *
 * - `position` 1–12 → OLD `$positions_array` labels (with sizes);
 * - `percentage` → OLD `$percentage_array` (shown in reports only; OLD's own
 *   add/edit forms had the field commented out);
 * - `type` 1 = image ad («إعلان مصور»), 0 = raw HTML/JS code («جوجل أدسنس»);
 * - `path_type` 1 = uploaded file, 2 = URL (OLD's select values);
 * - `ads_show_type` `period` (startdate/enddate) or `numview` (required_num_view).
 *
 * Administration only. Nothing here renders an advertisement publicly (B6c).
 */
final class LegacyAds
{
    public const PERMISSION = 'ads.manageads';

    public const TYPE_CODE = 0;

    public const TYPE_IMAGE = 1;

    public const PATH_UPLOAD = 1;

    public const PATH_URL = 2;

    /** @var array<int, string> */
    public const POSITIONS = [
        1 => 'يسار الصفحة الرئيسية - 120 * 600',
        2 => 'وسط الصفحة الرئيسية - 120 * 240',
        3 => 'يمين الصفحة الرئيسية - 120 * 240',
        4 => 'تفاصيل المادة علوي - 728 * 90',
        5 => 'تفاصيل المادة جانبي - 250 * 250',
        6 => 'دروس ومحاضرات صوتية - 250 * 250',
        7 => 'صفحة الشيخ علوى - 728 * 90',
        8 => 'صفحة الشيخ جانبى - 250 * 250',
        9 => 'صفحة المنوعات علوى - 728 * 90',
        10 => 'صفحة القناة علوى - 728 * 90',
        11 => 'عرض الديالوج سفلى - 468 * 60',
        12 => 'بداية تحميل الصفحة - 500 * 700',
    ];

    /** @var array<int, string> */
    public const PERCENTAGES = [100 => 'كامل', 50 => 'نصف', 33 => 'ثلث', 25 => 'ربع'];

    public static function positionLabel(mixed $position): string
    {
        return self::POSITIONS[(int) $position] ?? 'غير محدد';
    }

    public static function typeLabel(mixed $type): string
    {
        return (int) $type === self::TYPE_CODE ? 'جوجل أدسنس' : 'إعلان مصور';
    }

    /**
     * OLD `w2a_ads_main()` status (`index.php:177-211`): «ساري» / «لم يبدأ بعد» / «منتهي».
     * Period ads compare Y-m-d strings against today, exactly as OLD; an ad
     * whose dates match none of OLD's branches gets no status, as OLD.
     */
    public static function status(object $ad, ?string $today = null): string
    {
        $today ??= now()->format('Y-m-d');

        if ((string) $ad->ads_show_type === 'period') {
            $start = (string) $ad->startdate;
            $end = (string) $ad->enddate;

            if ($today === $start || $today === $end || ($today > $start && $today < $end)) {
                return 'ساري';
            }
            if ($today < $start && $today < $end) {
                return 'لم يبدأ بعد';
            }
            if ($today > $start && $today > $end) {
                return 'منتهي';
            }

            return '';
        }

        if ((int) $ad->num_view === -1) {
            return 'لم يبدأ بعد';
        }

        return (int) $ad->num_view > (int) $ad->required_num_view ? 'منتهي' : 'ساري';
    }

    /** A value safe to use as an <img src>/<a href> in admin previews: http(s) or root-relative only. */
    public static function safeUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            return null;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        return preg_match('#^https?://#i', $url) === 1 ? $url : null;
    }
}
