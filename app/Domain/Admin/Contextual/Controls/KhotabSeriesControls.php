<?php

namespace App\Domain\Admin\Contextual\Controls;

use App\Domain\Admin\Contextual\ContextualControl;
use App\Domain\Content\Models\Series;

/**
 * OLD public admin parity — the Khotab series page (owner, 2026-10-21).
 *
 * `panel()` is OLD's «لوحة تحكم السلسلة» (`series_cp.htm`), in OLD's order
 * and with OLD's labels. Each control opens the Laravel counterpart of the
 * OLD admin op's page, which sends the admin back to where OLD did — so these
 * links carry no return_to.
 *
 * `forItem()` is OLD's icon beside each item of the page (`KhotabUpKhotab`,
 * whose series branch takes the item out of the series): a CSRF-protected
 * POST, never a GET.
 */
final class KhotabSeriesControls
{
    /** @return list<ContextualControl> */
    public static function panel(Series $series): array
    {
        $id = ['series' => $series->id];

        return [
            ContextualControl::link('إعادة ترتيب', 'fa-refresh', 'admin.khotab.series.rearrange', $id, returns: false),
            ContextualControl::link('التصنيف السريع', 'fa-sitemap', 'admin.khotab.series.fastcopy', $id, returns: false),
            ContextualControl::link('تعديل السلسلة', 'fa-pencil-square-o', 'admin.khotab.series.edit', $id, returns: false),
            ContextualControl::link('إضافة مادة', 'fa-plus', 'admin.khotab.series.items.create', $id, returns: false),
            ContextualControl::link('حذف السلسلة', 'fa-trash', 'admin.khotab.series.delete', $id, returns: false),
            ContextualControl::link('مواد لم تصنف', 'fa-list', 'admin.khotab.series.items', $id, returns: false),
        ];
    }

    /** OLD's icon beside an item: take it out of the series. */
    public static function forItem(int $itemId): ContextualControl
    {
        return ContextualControl::action('إخراج المادة من السلسلة', 'fa-sign-out', 'admin.khotab.items.detach', ['item' => $itemId], 'POST');
    }
}
