<?php

namespace App\Domain\Admin\Contextual\Controls;

use App\Domain\Admin\Contextual\ContextualControl;

/**
 * Public promotional files — OLD `ShowAdFile()` (`modules/advfiles/index.php:463-466`)
 * and the library's admin item row (`templates/advfiles/admin_khotab_cont.htm`):
 * «تعديل الملف» and «مسح الملف» for the item's promotional file, now Phase 5's
 * edit and unpublish routes. The promotional file shares the item's id.
 *
 * Unpublishing removes the file page itself, so it returns to the item page.
 */
final class PromotionalFileControls
{
    /**
     * @param  string|null  $returnTo  where a successful edit returns; null = this page
     * @return list<ContextualControl>
     */
    public static function forItem(int $itemId, ?string $returnTo = null): array
    {
        $itemPage = "/khotab-item-{$itemId}.htm";

        return [
            new ContextualControl('تعديل الملف', 'fa-pencil', 'admin.advfiles.edit', ['khotabItem' => $itemId], returnTo: $returnTo),
            ContextualControl::action('مسح الملف', 'fa-trash', 'admin.advfiles.unpublish', ['khotabItem' => $itemId], 'DELETE', [],
                "إلغاء نشر الملف الدعائي للمادة #{$itemId}؟", returnTo: $itemPage),
        ];
    }
}
