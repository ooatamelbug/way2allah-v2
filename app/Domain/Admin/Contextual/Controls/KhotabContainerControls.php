<?php

namespace App\Domain\Admin\Contextual\Controls;

use App\Domain\Admin\Actions\Categories\CategoryList;
use App\Domain\Admin\Contextual\ContextualControl;
use App\Domain\Content\Models\Category;

/**
 * Khotab group and series pages — OLD `ListCats()` (`modules/Khotab/index.php`
 * :404/:407) and Gen 2 `breadcrumb()` (`functions.php:485-496`): beside each
 * of the container's own categories, «اضف للكل» and «امسح من الكل» —
 * Phase 3's bulk operations. `cat_fastcopy` stays out (Phase 3 §21.1).
 */
final class KhotabContainerControls
{
    /**
     * @param  'group'|'series'  $type
     * @return list<array{id: int, title: string|null, controls: list<ContextualControl>}>
     */
    public static function categories(string $type, int $containerId, ?string $rawCategories): array
    {
        $ids = array_map('intval', CategoryList::parse($rawCategories));
        $titles = Category::query()->whereKey($ids)->pluck('title', 'id');
        $parameter = [$type => $containerId];
        $scope = $type === 'series' ? 'كل مواد السلسلة' : 'مواد المجموعة التي ليست ضمن سلسلة';

        $rows = [];
        foreach ($ids as $categoryId) {
            $rows[] = [
                'id' => $categoryId,
                'title' => $titles->has($categoryId) ? (string) $titles[$categoryId] : null,
                'controls' => [
                    ContextualControl::action('اضف للكل', 'fa-plus-circle', "admin.categories.khotab.{$type}.add-all", $parameter, 'POST',
                        ['category_id' => $categoryId], "إضافة التصنيف #{$categoryId} إلى {$scope}؟"),
                    ContextualControl::action('امسح من الكل', 'fa-minus-circle', "admin.categories.khotab.{$type}.remove-all", $parameter, 'POST',
                        ['category_id' => $categoryId], "حذف التصنيف #{$categoryId} من {$scope}؟"),
                ],
            ];
        }

        return $rows;
    }
}
