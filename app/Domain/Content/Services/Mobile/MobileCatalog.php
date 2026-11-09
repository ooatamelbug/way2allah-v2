<?php

namespace App\Domain\Content\Services\Mobile;

use App\Domain\Content\Models\Mobile\MobileCategory;
use App\Domain\Content\Models\Mobile\MobileMaterial;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * OLD Admin Migration Phase 7 — the public read side of the Mobile section,
 * recovered from OLD `modules/Mobile/index.php`: main page (`index`),
 * category page (`viewdownload`), new materials (`NewDownloads`,
 * `NewDownloadsDate`), top rated, most popular, search.
 */
final class MobileCatalog
{
    /** OLD convertorderbyin(): the eight accepted sort keys. */
    public const ORDERS = [
        'titleA' => ['title', 'asc'], 'titleD' => ['title', 'desc'],
        'dateA' => ['date', 'asc'], 'dateD' => ['date', 'desc'],
        'hitsA' => ['hits', 'asc'], 'hitsD' => ['hits', 'desc'],
        'ratingA' => ['downloadratingsummary', 'asc'], 'ratingD' => ['downloadratingsummary', 'desc'],
    ];

    public const ORDER_LABELS = [
        'titleA' => 'العنوان (أ الى ي)', 'titleD' => 'العنوان (ي الى أ)',
        'dateA' => 'التاريخ (الأقدم أولاً)', 'dateD' => 'التاريخ (الأحدث أولاً)',
        'hitsA' => 'الشهرة (الأقل الى الأكثر زيارة)', 'hitsD' => 'الشهرة (الأكثر الى الأقل زيارة)',
        'ratingA' => 'التقييم (من الأدنى الى الأعلى تقييم)', 'ratingD' => 'التقييم (من الأعلى الى الأدنى تقييم)',
    ];

    public static function validOrder(?string $order): bool
    {
        return $order === null || isset(self::ORDERS[$order]);
    }

    /** OLD index(): top-level categories by title, each with up to three subcategories. */
    public static function mainCategories(): Collection
    {
        return MobileCategory::query()->where('parentid', 0)->orderBy('title')->get()
            ->each(fn (MobileCategory $c) => $c->setRelation('firstChildren', $c->children()->limit(3)->get()));
    }

    /** OLD viewdownload(): the materials of one category, sorted and paged. */
    public static function categoryMaterials(int $cid, string $order, int $page, int $perPage): LengthAwarePaginator
    {
        [$column, $direction] = self::ORDERS[$order];

        return MobileMaterial::query()->where('cid', $cid)->orderBy($column, $direction)->orderBy('lid')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /** OLD search(): title or description contains the query. */
    public static function search(string $query, string $order, int $page, int $perPage): LengthAwarePaginator
    {
        [$column, $direction] = self::ORDERS[$order];
        $like = '%'.addcslashes($query, '%_\\').'%';

        return MobileMaterial::query()->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('description', 'like', $like))
            ->orderBy($column, $direction)->orderBy('lid')->paginate($perPage, ['*'], 'page', $page);
    }

    /** OLD search(): the categories whose title matches, shown above the results. */
    public static function searchCategories(string $query): Collection
    {
        return MobileCategory::query()->where('title', 'like', '%'.addcslashes($query, '%_\\').'%')->orderByDesc('title')->get();
    }

    /** OLD TopRated(): rated, at least `$downloadvotemin` votes, best first, at most 25. */
    public static function topRated(): Collection
    {
        return MobileMaterial::query()->where('downloadratingsummary', '!=', 0)
            ->where('totalvotes', '>=', (int) config('mobile_downloads.top_rated_min_votes'))
            ->orderByDesc('downloadratingsummary')->orderBy('lid')->limit((int) config('mobile_downloads.top_rated'))->get();
    }

    /** OLD MostPopular(): most downloaded first, at most 25. */
    public static function mostPopular(): Collection
    {
        return MobileMaterial::query()->orderByDesc('hits')->orderBy('lid')->limit((int) config('mobile_downloads.most_popular'))->get();
    }

    /**
     * OLD NewDownloads(): for each of the last $days days, how many materials
     * were added; plus the totals for the last week and month.
     *
     * @return array{days: list<array{date: string, timestamp: int, count: int}>, week: int, month: int}
     */
    public static function newByDays(int $days): array
    {
        $count = fn (int $daysBack) => MobileMaterial::query()->whereDate('date', date('Y-m-d', time() - 86400 * $daysBack))->count();
        $list = [];
        for ($i = 0; $i < $days; $i++) {
            $list[] = ['date' => date('Y-m-d', time() - 86400 * $i), 'timestamp' => time() - 86400 * $i, 'count' => $count($i)];
        }
        $since = fn (int $d) => MobileMaterial::query()->where('date', '>=', date('Y-m-d 00:00:00', time() - 86400 * ($d - 1)))->count();

        return ['days' => $list, 'week' => $since(7), 'month' => $since(30)];
    }

    /** OLD NewDownloadsDate(): the materials added on one day, by title. */
    public static function addedOn(int $timestamp): Collection
    {
        return MobileMaterial::query()->whereDate('date', date('Y-m-d', $timestamp))->orderBy('title')->get();
    }

    /** OLD newdownloadgraphic(): "new" badge — 'today' (≤1 day), '3days', 'week', or null. */
    public static function newBadge(?string $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }
        $days = (int) floor((strtotime(date('Y-m-d')) - strtotime(substr($date, 0, 10))) / 86400);

        return match (true) {
            $days < 0 => null,
            $days <= 1 => 'today',
            $days <= 3 => '3days',
            $days <= 7 => 'week',
            default => null,
        };
    }
}
