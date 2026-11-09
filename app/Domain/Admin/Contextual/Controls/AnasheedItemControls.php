<?php

namespace App\Domain\Admin\Contextual\Controls;

use App\Domain\Admin\Actions\Categories\CategoryList;
use App\Domain\Admin\Contextual\ContextualControl;
use App\Domain\Content\Models\AnasheedItem;
use App\Domain\Content\Models\AnasheedMirror;
use App\Domain\Content\Models\Category;

/**
 * Anasheed item page (`var-item-{id}.htm`) — OLD `Detailes()`'s
 * «لوحة تحكم المادة» (`item_cp.htm`), `Mirror()`, `Tree()` and `Comments()`
 * (`modules/Anasheed/index.php`, production working copy).
 *
 * `toolbar()` is OLD's working toolbar in OLD's order (owner, 2026-10-23):
 * «اضافة صورة للمادة», «تعديل المادة», «إضافة وصلة» (Phase 1), «حذف المادة»,
 * «إضافة تصنيف» (Phase 3). Not rendered, as in production OLD: «تعديل / حذف
 * صورة للمادة» (always commented out in OLD — their guard reads a `banner`
 * column production lacks) and «رفع / تعديل / حذف البانر» (each handler dies
 * with «قيد التطوير») — dead; the share queue — owner-retired. OLD's
 * `AdvancedStats()` link-status block was never called from `Detailes()` and
 * is owner-retired too.
 */
final class AnasheedItemControls
{
    /** @return list<ContextualControl> */
    public static function toolbar(AnasheedItem $item): array
    {
        $id = ['anasheedItem' => $item->id];

        return [
            // item_cp.htm «اضافة صورة للمادة» — the item frame.
            ContextualControl::link('اضافة صورة للمادة', 'fa-picture-o', 'admin.anasheed.items.frame', $id, returns: false),
            // item_cp.htm «تعديل المادة».
            ContextualControl::link('تعديل المادة', 'fa-pencil-square-o', 'admin.anasheed.items.edit', $id, returns: false),
            // item_cp.htm «إضافة وصلة» — Phase 1.
            ContextualControl::link('إضافة وصلة', 'fa-link', 'admin.mirrors.anasheed.show', $id, gateRoute: 'admin.mirrors.anasheed.store'),
            // item_cp.htm «حذف المادة».
            ContextualControl::link('حذف المادة', 'fa-trash', 'admin.anasheed.items.delete', $id, returns: false),
            // item_cp.htm «إضافة تصنيف» — Phase 3.
            ContextualControl::link('إضافة تصنيف', 'fa-sitemap', 'admin.categories.anasheed.item', $id),
        ];
    }

    /** OLD `comment_admin.htm` «إضافة تعليق إداري» — decides whether the form shows. */
    public static function moderatorComment(AnasheedItem $item): ContextualControl
    {
        return ContextualControl::action('إضافة التعليق', 'fa-comment', 'admin.anasheed.items.comments.store', ['anasheedItem' => $item->id], 'POST');
    }

    /** «(تعديل التعليق)» beside each published comment — OLD `AnasheedWaitingList&id=`. */
    public static function forComment(int $commentId): ContextualControl
    {
        return ContextualControl::link('(تعديل التعليق)', 'fa-pencil', 'admin.anasheed.comments.edit', ['anasheedComment' => $commentId], returns: false);
    }

    /** `Mirror()`:525 — edit / delete beside each mirror. @return list<ContextualControl> */
    public static function forMirror(AnasheedMirror $mirror): array
    {
        return [
            ContextualControl::link('تعديل الوصلة', 'fa-pencil', 'admin.mirrors.anasheed.edit', ['anasheedMirror' => $mirror->id]),
            ContextualControl::action('حذف الوصلة', 'fa-trash', 'admin.mirrors.anasheed.destroy', ['anasheedMirror' => $mirror->id], 'DELETE', [], "حذف الوصلة #{$mirror->id}؟"),
        ];
    }

    /** `Tree()`:396 — the delete icon beside each of the item's categories. */
    public static function removeCategory(AnasheedItem $item, int $categoryId): ContextualControl
    {
        return ContextualControl::action('حذف التصنيف', 'fa-minus-circle', 'admin.categories.anasheed.item.remove', ['anasheedItem' => $item->id], 'POST',
            ['category_id' => $categoryId], "حذف التصنيف #{$categoryId} من النشيد؟");
    }

    /** The item's `cat_id` list (the one Phase 3 edits), id ⇒ title. @return array<int, string|null> */
    public static function assignedCategories(AnasheedItem $item): array
    {
        $ids = array_map('intval', CategoryList::parse($item->getAttribute('cat_id')));
        $titles = Category::query()->whereKey($ids)->pluck('title', 'id');

        $assigned = [];
        foreach ($ids as $categoryId) {
            $assigned[$categoryId] = $titles->has($categoryId) ? (string) $titles[$categoryId] : null;
        }

        return $assigned;
    }
}
