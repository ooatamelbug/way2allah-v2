<?php

namespace App\Domain\Admin\Contextual\Controls;

use App\Domain\Admin\Contextual\ContextualControl;

/**
 * The per-row control of `anasheed_without_authors.htm` (OLD
 * `anasheed_without_authors()`, `modules/Anasheed/index.php:333`).
 */
final class AnasheedWithoutAuthorsControls
{
    /**
     * «تعديل» — OLD `AnasheedEditAnasheed&redirct_to=…`, the same handler as
     * the item page's T4, so it opens the existing T4 form. OLD carried
     * `redirct_to` through the form and returned to it after a successful
     * save, so the return is the list page the row is on.
     */
    public static function edit(int $itemId, string $listPage): ContextualControl
    {
        return new ContextualControl('تعديل', 'fa-pencil-square-o', 'admin.anasheed.items.edit', ['anasheedItem' => $itemId],
            returnTo: $listPage);
    }
}
