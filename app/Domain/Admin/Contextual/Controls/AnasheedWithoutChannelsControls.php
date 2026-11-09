<?php

namespace App\Domain\Admin\Contextual\Controls;

use App\Domain\Admin\Contextual\ContextualControl;

/**
 * The per-row control of `anasheed_without_channels.htm` (OLD
 * `anasheed_without_channels()`, `modules/Anasheed/index.php:375`).
 */
final class AnasheedWithoutChannelsControls
{
    /**
     * «تعديل» — OLD `AnasheedEditAnasheed&redirct_to=…`, the item page's T4
     * form; OLD returned to `redirct_to` after a successful save, so the
     * return is the list page the row is on.
     */
    public static function edit(int $itemId, string $listPage): ContextualControl
    {
        return new ContextualControl('تعديل', 'fa-pencil-square-o', 'admin.anasheed.items.edit', ['anasheedItem' => $itemId],
            returnTo: $listPage);
    }
}
