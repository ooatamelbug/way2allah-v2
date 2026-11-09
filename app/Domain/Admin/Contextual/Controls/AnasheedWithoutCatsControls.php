<?php

namespace App\Domain\Admin\Contextual\Controls;

use App\Domain\Admin\Contextual\ContextualControl;

/**
 * The per-row control of `anasheed_without_cats.htm` (OLD
 * `anasheed_without_cats()`, `modules/Anasheed/index.php:291`).
 */
final class AnasheedWithoutCatsControls
{
    /**
     * «إضافة تصنيف» — OLD `cat_add_to_anasheed`, the same handler as the item
     * page's T7, so it opens the existing T7 picker. OLD always redirected to
     * `var-item-{id}.htm` after an add (its `redirct_to` was never read), so
     * the successful add returns to the item page, not to this list.
     */
    public static function addCategory(int $itemId): ContextualControl
    {
        return new ContextualControl('إضافة تصنيف', 'fa-sitemap', 'admin.categories.anasheed.item', ['anasheedItem' => $itemId],
            returnTo: "/var-item-{$itemId}.htm");
    }
}
