<?php

namespace App\Domain\Admin\Actions\Cds;

/**
 * Phase 6 — a release's download links, stored as two positionally paired
 * comma lists on `nuke_w2acd_w2acd`: `link` (URLs) and `cd` (their titles).
 * This is the live, canonical representation (3E.1 §0.4.1); the normalized
 * `nuke_w2acd_mirror` table is never used.
 *
 * Writer recovered from OLD `cdsConfirmInsert` / `cdsConfirmEdit`
 * (`modules/w2acd/admin/index.php:315-563`): `implode(",", $link_url)`,
 * `implode(",", $link_title)`. Reader: `W2acdItem::mirrorLinks()` (explode,
 * pair by position).
 *
 * Corrected: OLD dropped empty URLs and empty titles independently, so one
 * missing title shifted every later title onto the wrong link. Here a link is
 * a pair — both parts or neither — so positions always stay aligned, and
 * neither part may contain the comma delimiter (validated by the request).
 */
final class CdLinkList
{
    /**
     * @param  list<array{title: string, url: string}>  $pairs
     * @return array{link: string, cd: string}
     */
    public static function format(array $pairs): array
    {
        return [
            'link' => implode(',', array_column($pairs, 'url')),
            'cd' => implode(',', array_column($pairs, 'title')),
        ];
    }

    /** @return list<array{title: string, url: string}> */
    public static function parse(?string $link, ?string $cd): array
    {
        if ((string) $link === '') {
            return [];
        }
        $titles = explode(',', (string) $cd);

        return array_map(
            fn (string $url, int $i) => ['title' => $titles[$i] ?? '', 'url' => $url],
            explode(',', (string) $link),
            array_keys(explode(',', (string) $link)),
        );
    }
}
