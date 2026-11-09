<?php

namespace App\Domain\Admin\Contextual\Controls;

use App\Domain\Admin\Contextual\ContextualControl;
use App\Domain\Content\Models\W2acdGroup;

/**
 * W2ACD public admin parity, Phase 2 — the CD group page `cds-group-{id}.htm`.
 *
 * `panel()` is OLD's «لوحة تحكم قسم الاسطوانات» (`group_cp_for_cd.htm`, rendered
 * by OLD `modules/w2acd/index.php` `Group()`), in OLD's order and with OLD's
 * labels: G1 «اضافة صورة للقسم», G2 «تعديل القسم», G4 «إضافة قسم فرعي», G5
 * «حذف القسم». Each opens the Laravel counterpart of the OLD admin op's page,
 * which sends the admin where OLD did — so these links carry no return_to.
 *
 * G3 «إضافة إسطوانة» (OLD `InsertW2ACD(group_id)`) is not here: owner-retired
 * (plan §28.1, reaffirmed 2026-11-01).
 *
 * `mainPanel()` (Phase 3) is the same OLD template on `cds-main` (`Group(0)`,
 * id 0). Only M4 «إضافة قسم فرعي» — a top-level group — worked there: M1
 * (`GroupIcon(0)` «لم يتم العثور»), M2 (`UPDATE … WHERE id = '0'`) and M5
 * (`DELETE … WHERE id = 0`) acted on no row, and M3 is owner-retired.
 */
final class CdGroupControls
{
    /** @return list<ContextualControl> */
    public static function panel(W2acdGroup $group): array
    {
        $id = ['cdGroup' => $group->id];

        return [
            ContextualControl::link('اضافة صورة للقسم', 'fa-picture-o', 'admin.cds.groups.icon', $id, returns: false),
            ContextualControl::link('تعديل القسم', 'fa-pencil-square-o', 'admin.cds.groups.edit', $id, returns: false),
            ContextualControl::link('إضافة قسم فرعي', 'fa-sitemap', 'admin.cds.groups.subgroups.create', $id, returns: false),
            ContextualControl::link('حذف القسم', 'fa-trash', 'admin.cds.groups.delete', $id, returns: false),
        ];
    }

    /** @return list<ContextualControl> */
    public static function mainPanel(): array
    {
        return [
            ContextualControl::link('إضافة قسم فرعي', 'fa-sitemap', 'admin.cds.groups.create', [], returns: false),
        ];
    }
}
