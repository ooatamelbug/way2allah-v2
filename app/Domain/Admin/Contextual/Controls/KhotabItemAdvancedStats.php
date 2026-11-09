<?php

namespace App\Domain\Admin\Contextual\Controls;

use App\Domain\Admin\Actions\Khotab\ArchiveOrgMetadata;
use App\Domain\Content\Support\LegacyShortDateFormatter;
use Illuminate\Support\Facades\DB;

/**
 * «إحصائيات متطورة» — OLD `AdvancedStats()` (`modules/Khotab/index.php:893`)
 * and `advanced_stats.htm`: the read-only block OLD showed every
 * administrator on the item page. Reproduced value by value, with OLD's
 * wording:
 *
 * - backup report: `down` (0 waiting / 1 done / other failed, with `trial`),
 *   the backup size (`linksize`, CoolSize) and the CD (`cd`);
 * - link report: only once downloaded — the link works when `online` equals
 *   `linksize`, the check date (`checktime`, CoolShortDate) and the size at
 *   the check (`online`, CoolSize); and visitors' broken-link reports
 *   (`broken`, with the `nuke_brokenlinks` count for `divid = 1`);
 * - item information from `nuke_islamic_advanced`: duration (`adur`, ms →
 *   `i:s`), copyright and comment;
 * - audio properties when `astr` is set, video properties when `vstr` is set.
 */
final class KhotabItemAdvancedStats
{
    private const PENDING = 'يجب إنزال الملف أولاً';

    /** @return array<string, mixed> */
    public static function for(int $itemId): array
    {
        $main = DB::connection('main');
        $item = $main->table('nuke_islamic_khotab')->where('id', $itemId)
            ->first(['id', 'down', 'trial', 'online', 'linksize', 'checktime', 'cd', 'broken']);
        $advanced = $main->table('nuke_islamic_advanced')->where('id', $itemId)->first();

        if ($item === null) {
            return [];
        }

        $trial = (int) $item->trial;
        $stats = ['online' => self::PENDING, 'checktime' => self::PENDING, 'checksize' => self::PENDING];
        $down = (int) $item->down;
        if ($down === 0) {
            $stats['down'] = "بإنتظار التنزيل ({$trial} محاولة)";
        } elseif ($down === 1) {
            $stats['down'] = 'تم تنزيل الملف';
            $stats['checktime'] = LegacyShortDateFormatter::format((int) $item->checktime);
            $stats['checksize'] = ArchiveOrgMetadata::coolSize((float) $item->online);
            $stats['online'] = (int) $item->online === (int) $item->linksize ? 'تعمل' : 'لا تعمل';
        } else {
            $stats['down'] = "لم يتم ({$trial} محاولة)";
        }

        $stats['linksize'] = ArchiveOrgMetadata::coolSize((float) $item->linksize);
        $stats['cd'] = (string) $item->cd === '' ? 'لم يتم نسخ الملف حتى الآن' : (string) $item->cd;

        if ((string) $item->broken === '0') {
            $stats['broken'] = 'لم يتم الإبلاغ';
        } else {
            $reports = $main->table('nuke_brokenlinks')->where('khid', $itemId)->where('divid', 1)->value('count');
            $stats['broken'] = 'تم الإبلاغ ('.$reports.' مرة)';
        }

        $field = fn (string $name): string => (string) ($advanced->{$name} ?? '');
        $adur = (int) $field('adur');

        return $stats + [
            'adur' => $adur > 0 ? date('i:s', mktime(0, 0, (int) floor($adur / 1000), 0, 0, 0)) : 'غير معروف',
            'cright' => $field('cright') === '' ? 'لا يوجد حقوق نسخ' : $field('cright'),
            'perf' => $field('perf') === '' ? 'لا يوجد تعليق' : $field('perf'),
            'audio' => $field('astr') === '' ? null : ['srate' => $field('srate'), 'ares' => $field('ares'), 'abit' => $field('abit'), 'alist' => $field('alist')],
            'video' => $field('vstr') === '' ? null : ['frate' => $field('frate'), 'vres' => $field('vres'), 'vbit' => $field('vbit'), 'width' => $field('width'), 'height' => $field('height'), 'vlist' => $field('vlist')],
        ];
    }
}
