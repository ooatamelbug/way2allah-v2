<?php

namespace App\Domain\Admin\Contextual\Controls;

use App\Domain\Admin\Actions\Categories\CategoryList;
use App\Domain\Admin\Contextual\ContextualControl;
use App\Domain\Admin\Http\Controllers\KhotabMirrorController;
use App\Domain\Content\Models\Category;
use App\Domain\Content\Models\KhotabItem;
use App\Domain\Content\Models\Mirror;

/**
 * Khotab item page (`khotab-item-{id}.htm`) — OLD `AdminPanel()`,
 * `Mirror()`, `Tree()`, `Detailes()`, `AdvancedStats()` and `Comments()`
 * (`modules/Khotab/index.php`), and Gen 2 `adminItemControls()`.
 *
 * `toolbar()` is OLD's «لوحة تحكم المشرفين» in OLD's order, with OLD's labels
 * and conditions (owner rule, 2026-10-21: every working OLD public admin
 * control is preserved). «اضافة للشير» (`KhotabWaitingFB`) stays retired
 * (owner, Section F). The OLD-page links open the Laravel counterpart of the
 * OLD admin op's page, which sends the admin back to the item as OLD did — so
 * they carry no return_to; the controls migrated in earlier phases keep
 * theirs.
 */
final class KhotabItemControls
{
    /** @return list<ContextualControl> */
    public static function toolbar(KhotabItem $item): array
    {
        $id = ['khotabItem' => $item->id];
        $own = ['item' => $item->id];
        $banner = (int) $item->getAttribute('banner');

        $controls = [
            ContextualControl::link('تعديل', 'fa-pencil', 'admin.khotab.items.edit', $own, returns: false),
            ContextualControl::link('نقل', 'fa-arrows', 'admin.khotab.items.move', $own, returns: false),
            ContextualControl::link('مسح', 'fa-trash', 'admin.khotab.items.delete', $own, returns: false),
            // AdminPanel:799 «إضافة تصنيف» — Phase 3.
            ContextualControl::link('إضافة تصنيف', 'fa-sitemap', 'admin.categories.khotab.item', $id),
            // AdminPanel:801 «إضافة وصلة» — Phase 1.
            ContextualControl::link('إضافة وصلة', 'fa-link', 'admin.mirrors.khotab.show', $id, gateRoute: 'admin.mirrors.khotab.store'),
        ];

        // OLD tested `banner == 0`; a requested banner (-1) counts as "has one".
        if ($banner === 0) {
            $controls[] = ContextualControl::link('رفع بانر', 'fa-picture-o', 'admin.khotab.items.banner', $own, returns: false);
        } else {
            $controls[] = ContextualControl::link('تعديل البانر', 'fa-picture-o', 'admin.khotab.items.banner', $own, returns: false);
            $controls[] = ContextualControl::link('حذف البانر', 'fa-times-circle', 'admin.khotab.items.banner.delete', $own, returns: false);
        }

        if ((int) $item->getAttribute('pdf') === 0) {
            $controls[] = ContextualControl::link('رفع التفريغ', 'fa-file-pdf-o', 'admin.khotab.items.pdf', $own, returns: false);
        } else {
            $controls[] = ContextualControl::link('تعديل التفريغ', 'fa-file-pdf-o', 'admin.khotab.items.pdf', $own, returns: false);
            $controls[] = ContextualControl::link('حذف التفريغ', 'fa-times-circle', 'admin.khotab.items.pdf.delete', $own, returns: false);
        }

        // «تنزيل الوورد» is a plain file link, shown beside this panel by the view.
        if ((int) $item->getAttribute('docx') === 0) {
            $controls[] = ContextualControl::link('رفع الوورد', 'fa-file-word-o', 'admin.khotab.items.docx', $own, returns: false);
        } else {
            $controls[] = ContextualControl::link('تعديل الوورد', 'fa-file-word-o', 'admin.khotab.items.docx', $own, returns: false);
            $controls[] = ContextualControl::link('حذف الوورد', 'fa-times-circle', 'admin.khotab.items.docx.delete', $own, returns: false);
        }

        // AdminPanel:848 «طلب رئيسية», offered only while advfile = 0 — Phase 5.
        if ((int) $item->getAttribute('advfile') === 0) {
            $controls[] = ContextualControl::action('طلب رئيسية', 'fa-bullhorn', 'admin.advfiles.request', $id, 'POST');
        }

        if ($banner === 0) {
            $controls[] = ContextualControl::action('طلب بانر', 'fa-paint-brush', 'admin.khotab.items.banner.request', $own, 'POST');
        }

        // «سحب الصور» — OLD get_gif_jpg.php?id=, as an authorized POST (owner, 2026-10-21).
        $controls[] = ContextualControl::action('سحب الصور', 'fa-picture-o', 'admin.khotab.items.images', $own, 'POST');

        // Detailes:1011 — "move the picked mirror here" — Phase 1 re-parent.
        $picked = session(KhotabMirrorController::CLIPBOARD_KEY);
        if ($picked !== null) {
            $mirror = Mirror::query()->find((int) $picked);
            if ($mirror !== null && (int) $mirror->khid !== (int) $item->id) {
                $controls[] = ContextualControl::action(
                    "نقل الوصلة #{$mirror->id} إلى هذه المادة", 'fa-arrows', 'admin.mirrors.khotab.move', ['mirror' => $mirror->id], 'POST',
                    ['target_item_id' => (int) $item->id], "نقل الوصلة #{$mirror->id} إلى هذه المادة؟",
                );
            }
            $controls[] = ContextualControl::action('إلغاء اختيار الوصلة', 'fa-times', 'admin.mirrors.khotab.clipboard.clear', [], 'POST');
        }

        // The item link-health page — Phase 2; kept beside OLD's inline AdvancedStats block (owner, 2026-10-21).
        $controls[] = ContextualControl::link('حالة الوصلات', 'fa-heartbeat', 'admin.link-quality.khotab.item', $id, returns: false);

        return $controls;
    }

    /**
     * «إضافة تعليق إداري» — OLD `comment_admin.htm`, which `Comments()`
     * rendered only for an item whose `comments` counter is above 0.
     */
    public static function moderatorComment(KhotabItem $item): ContextualControl
    {
        return ContextualControl::action('إضافة التعليق', 'fa-comment', 'admin.khotab.items.comments.store', ['item' => $item->id], 'POST');
    }

    /** «(تعديل التعليق)» beside each published comment — OLD `KhotabWaitingList&id=`. */
    public static function forComment(int $commentId): ContextualControl
    {
        return ContextualControl::link('(تعديل التعليق)', 'fa-pencil', 'admin.khotab.comments.edit', ['comment' => $commentId], returns: false);
    }

    /**
     * `Mirror()`:1217-1219 — edit / delete beside each mirror; plus the pick
     * half of the re-parent, which OLD kept in its MirrorMove cookie.
     *
     * @return list<ContextualControl>
     */
    public static function forMirror(Mirror $mirror): array
    {
        return [
            ContextualControl::link('تعديل الوصلة', 'fa-pencil', 'admin.mirrors.khotab.edit', ['mirror' => $mirror->id]),
            ContextualControl::action('اختيار للنقل', 'fa-share', 'admin.mirrors.khotab.pick', ['mirror' => $mirror->id], 'POST'),
            ContextualControl::action('حذف الوصلة', 'fa-trash', 'admin.mirrors.khotab.destroy', ['mirror' => $mirror->id], 'DELETE', [], "حذف الوصلة #{$mirror->id}؟"),
        ];
    }

    /** `Tree()`:1152 — the delete icon beside each of the item's categories. */
    public static function removeCategory(KhotabItem $item, int $categoryId): ContextualControl
    {
        return ContextualControl::action('حذف التصنيف', 'fa-minus-circle', 'admin.categories.khotab.item.remove', ['khotabItem' => $item->id], 'POST',
            ['category_id' => $categoryId], "حذف التصنيف #{$categoryId} من المادة؟");
    }

    /**
     * The categories in the item's own `cat` list — the list Phase 3 edits —
     * as id ⇒ title (null for an id with no category row).
     *
     * @return array<int, string|null>
     */
    public static function assignedCategories(KhotabItem $item): array
    {
        $ids = array_map('intval', CategoryList::parse($item->getAttribute('cat')));
        $titles = Category::query()->whereKey($ids)->pluck('title', 'id');

        $assigned = [];
        foreach ($ids as $categoryId) {
            $assigned[$categoryId] = $titles->has($categoryId) ? (string) $titles[$categoryId] : null;
        }

        return $assigned;
    }
}
