<?php

namespace App\Domain\Admin\Contextual\Controls;

use App\Domain\Admin\Contextual\ContextualControl;
use App\Domain\Content\Models\KhotabGroup;

/**
 * OLD public admin parity — the Khotab group page (owner, 2026-10-21).
 *
 * `panel()` is OLD's «لوحة تحكم المجموعة» (`group_cp.htm`), in OLD's order
 * and with OLD's labels. Each control opens the Laravel counterpart of the
 * OLD admin op's page, which sends the admin back to the group page
 * itself, as OLD did — so these links carry no return_to.
 *
 * `forSeries()` / `forItem()` are OLD's icons beside each series
 * (`KhotabUpSeries`) and each item (`KhotabUpKhotab`) of the page: a
 * CSRF-protected POST each, never a GET.
 */
final class KhotabGroupControls
{
    /** @return list<ContextualControl> */
    public static function panel(KhotabGroup $group): array
    {
        $id = ['group' => $group->id];

        return [
            ContextualControl::link('إعادة ترتيب', 'fa-refresh', 'admin.khotab.groups.rearrange', $id, returns: false),
            ContextualControl::link('التصنيف السريع', 'fa-sitemap', 'admin.khotab.groups.fastcopy', $id, returns: false),
            ContextualControl::link('تعديل المجموعة', 'fa-pencil-square-o', 'admin.khotab.groups.edit', $id, returns: false),
            ContextualControl::link('إضافة مادة', 'fa-plus', 'admin.khotab.groups.items.create', $id, returns: false),
            ContextualControl::link('إضافة سلسلة', 'fa-plus', 'admin.khotab.groups.series.create', $id, returns: false),
            ContextualControl::link('حذف المجموعة', 'fa-trash', 'admin.khotab.groups.delete', $id, returns: false),
            ContextualControl::link('مواد لم تصنف', 'fa-list', 'admin.khotab.groups.items', $id, returns: false),
        ];
    }

    /** OLD's icon beside a series: take it out of the group. */
    public static function forSeries(int $seriesId): ContextualControl
    {
        return ContextualControl::action('إخراج السلسلة من المجموعة', 'fa-sign-out', 'admin.khotab.series.detach', ['series' => $seriesId], 'POST');
    }

    /** OLD's icon beside an item: take it out of the group (or series). */
    public static function forItem(int $itemId): ContextualControl
    {
        return ContextualControl::action('إخراج المادة من المجموعة', 'fa-sign-out', 'admin.khotab.items.detach', ['item' => $itemId], 'POST');
    }
}
