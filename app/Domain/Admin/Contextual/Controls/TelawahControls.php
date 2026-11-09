<?php

namespace App\Domain\Admin\Contextual\Controls;

use App\Domain\Admin\Contextual\ContextualControl;
use App\Domain\Content\Models\TelawahGroup;
use App\Domain\Content\Models\TelawahItem;

/**
 * OLD public admin parity — the Telawah pages (owner, 2026-10-29), from OLD
 * `modules/Telawah/index.php` (production-hash-verified), in OLD's order and
 * with OLD's labels. Each control opens the Laravel counterpart of the OLD
 * admin op's page, which sends the admin where OLD did — so these links carry
 * no return_to.
 *
 * Not offered, as in production OLD: Main's «إضافة تلاوة» (`group_id` = 0 —
 * «القسم غير موجود»); the group page's «نقل القسم» (no handler) and its three
 * HTML-commented controls; the item page's «اضافة وصلة بديلة» (disabled by
 * OLD itself) and «اضافة الى قائمة الشير» (owner-retired).
 */
final class TelawahControls
{
    /** `recite.htm` — OLD `Group(0)`'s «لوحة التحكم» (`group_main_cp.htm`). @return list<ContextualControl> */
    public static function main(): array
    {
        return [
            ContextualControl::link('إضافة قارىء', 'fa-user-plus', 'admin.telawah.main.groups.create', returns: false),
        ];
    }

    /** `recite-group-{id}.htm` — OLD `Group(N)`'s «لوحة تحكم الاقسام الفرعية» (`author_cp.htm`). @return list<ContextualControl> */
    public static function group(TelawahGroup $group): array
    {
        $id = ['telawahGroup' => $group->id];

        return [
            ContextualControl::link('تعديل القسم', 'fa-pencil-square-o', 'admin.telawah.groups.edit', $id, returns: false),
            ContextualControl::link('إضافة تلاوة', 'fa-plus', 'admin.telawah.groups.items.create', $id, returns: false),
            ContextualControl::link('إضافة قسم فرعى', 'fa-sitemap', 'admin.telawah.groups.subgroups.create', $id, returns: false),
            ContextualControl::link('حذف القسم', 'fa-trash', 'admin.telawah.groups.delete', $id, returns: false),
        ];
    }

    /**
     * `recite-item-{id}.htm` — OLD `AdvancedStats()`'s «لوحة تحكم المشرفين»:
     * «تعديل», «مسح», «نقل إلى قسم شيخ أخر», then its status block — the
     * existing link-status page (PublicPageControls::telawahItem).
     *
     * @return list<ContextualControl>
     */
    public static function item(TelawahItem $item): array
    {
        $id = ['telawahItem' => $item->id];

        return [
            ContextualControl::link('تعديل', 'fa-pencil-square-o', 'admin.telawah.items.edit', $id, returns: false),
            ContextualControl::link('مسح', 'fa-trash', 'admin.telawah.items.delete', $id, returns: false),
            ContextualControl::link('نقل إلى قسم شيخ أخر', 'fa-arrows', 'admin.telawah.items.move', $id, returns: false),
            PublicPageControls::telawahItem($item),
        ];
    }
}
