<?php

namespace App\Domain\Admin\Contextual\Controls;

use App\Domain\Admin\Contextual\ContextualControl;
use App\Domain\Content\Models\AnasheedGroup;

/**
 * OLD public admin parity — the Anasheed group page (owner, 2026-10-23).
 *
 * `panel()` is OLD's «لوحة تحكم قسم المنوعات» (`group_cp.htm`, rendered by
 * OLD `modules/Anasheed/index.php` `Group()`), in OLD's order and with OLD's
 * labels. Each control opens the Laravel counterpart of the OLD admin op's
 * page, which sends the admin where OLD did — so these links carry no
 * return_to.
 *
 * OLD's sixth control, «ترتيب القسم» (`Order_anasheed_in_group`), is not
 * here: its page selects `nuke_anasheed_anasheed.banner`, a column production
 * does not have, so in OLD it never rendered its form (owner, 2026-10-23:
 * dead, not revived).
 */
final class AnasheedGroupControls
{
    /** @return list<ContextualControl> */
    public static function panel(AnasheedGroup $group): array
    {
        $id = ['anasheedGroup' => $group->id];

        return [
            ContextualControl::link('اضافة صورة للقسم', 'fa-picture-o', 'admin.anasheed.groups.icon', $id, returns: false),
            ContextualControl::link('تعديل القسم', 'fa-pencil-square-o', 'admin.anasheed.groups.edit', $id, returns: false),
            ContextualControl::link('إضافة مادة', 'fa-plus', 'admin.anasheed.groups.items.create', $id, returns: false),
            ContextualControl::link('إضافة قسم فرعي', 'fa-sitemap', 'admin.anasheed.groups.subgroups.create', $id, returns: false),
            ContextualControl::link('حذف القسم', 'fa-trash', 'admin.anasheed.groups.delete', $id, returns: false),
        ];
    }
}
