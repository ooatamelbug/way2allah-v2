<?php

namespace App\Domain\Content\Ads;

use App\Domain\Content\Support\KhotabBanner;
use App\Support\Http\SafeExternalUrl;
use Illuminate\Support\Facades\DB;
use Random\Randomizer;

/**
 * F01 / B6c-1 — OLD's home banner strip (`old-project/index.php:345-361`): two forum banners from one random
 * `nuke_banners` row with `cat = 'montada'` (`get_random_two_montada_banners()`), around one random Khotab banner
 * from the admin-selected set `nuke_banners_ads` (`get_random_three_banners()`, B6b), linking to the item.
 * Gen 2 commented the strip out (`7amlat/new_content.php:210`).
 *
 * Owner rules (B6b decisions 3–4): selection rows are never modified, and a member is drawn only if its item
 * **exists, is publicly visible (`hidden = 0`) and still has a valid banner** (`banner = 1` and the GIF exists —
 * {@see KhotabBanner}). OLD drew any row, rendering a broken image for missing items or files. Among eligible
 * rows the pick is uniform over rows, as OLD's `ORDER BY RAND() LIMIT 1`.
 *
 * Forum banners: rendered only when both the image and the link pass {@see SafeExternalUrl} (OLD's http→https
 * image rewrite applied first). Read-only; returns null — and runs **no query** — while advertising is off.
 */
final class HomeBannerStripService
{
    public function __construct(private readonly Randomizer $randomizer) {}

    /**
     * @return array{forum: array<1|2, array{href: string, src: string, title: string}>, item: array{id: int, title: string, src: string, width: int, height: int}|null}|null
     */
    public function present(): ?array
    {
        if (! AdsSwitch::renderingEnabled()) {
            return null;
        }

        $item = $this->pickItem();
        $forum = $this->forumBanners();
        if ($item === null && $forum === []) {
            return null;
        }

        return ['forum' => $forum, 'item' => $item];
    }

    /** @return array{id: int, title: string, src: string, width: int, height: int}|null */
    public function pickItem(): ?array
    {
        $rows = DB::connection('main')->table('nuke_banners_ads as b')
            ->join('nuke_islamic_khotab as k', 'k.id', '=', 'b.media_id')
            ->where('k.banner', 1)->where('k.hidden', 0)
            ->orderBy('b.id')
            ->get(['b.id', 'k.id as item_id', 'k.title', 'k.banner', 'k.hidden'])
            ->all();

        foreach ($this->randomizer->shuffleArray($rows) as $row) {
            $banner = KhotabBanner::for((int) $row->item_id, (int) $row->banner, (int) $row->hidden);
            if ($banner !== null) {
                return ['id' => (int) $row->item_id, 'title' => (string) $row->title, 'src' => $banner['url'],
                    'width' => $banner['width'], 'height' => $banner['height']];
            }
        }

        return null;
    }

    /** @return array<1|2, array{href: string, src: string, title: string}> keyed by OLD's slot (1 left, 2 right) */
    public function forumBanners(): array
    {
        $rows = DB::connection('main')->table('nuke_banners')->where('cat', 'montada')->orderBy('id')->get()->all();
        if ($rows === []) {
            return [];
        }

        $row = $rows[$this->randomizer->getInt(0, count($rows) - 1)];
        $banners = [];
        foreach ([1, 2] as $n) {
            $src = SafeExternalUrl::href(str_replace('http://', 'https://', (string) ($row->{'image'.$n} ?? '')));
            $href = SafeExternalUrl::href(trim((string) ($row->{'url'.$n} ?? '')));
            if ($src !== null && $href !== null) {
                $banners[$n] = ['href' => $href, 'src' => $src, 'title' => (string) ($row->{'title'.$n} ?? '')];
            }
        }

        return $banners;
    }
}
