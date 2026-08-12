<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Models\Series;
use App\Domain\Content\Services\ContentListingService;
use Illuminate\Http\Response;

/**
 * Replaces `categories/downitems.php` (`khotab-series-{id}.grx` /
 * `khotab-series-{id}-{cat}.grx`, `.htaccess:226-227`) — a GetRight
 * download-manager playlist for one series' khotab items, optionally
 * filtered to one category.
 *
 * `$_GET['id']`/`$_GET['cat']` map directly to `{series}`/`{category}`
 * (URL segment order confirmed against `.htaccess:226`: `id=$1&cat=$2`).
 * The listing query is genuinely different from `khotabItemsByCategory()`
 * — no `vedio`/`group_id` filter, conditional category join — see
 * `ContentListingService::khotabLinksForSeriesDownload()`'s own docblock.
 *
 * `DownItems()` (`functions.php:713-747`) is reproduced exactly:
 * - one `"URL: {link}\r\nFile: C:\Way2Allah\{folder}\{title}.{ext}.GetRight\r\n\r\n"`
 *   block per item, `$folder` = the series title with a literal trailing
 *   `\`.
 * - title sanitization: `functions.php:723-732` replaces `\`->`-`,
 *   `\/`(the 2-char sequence)->`-`, `/`->`-`, then `*`/`?`/`<`/`>`/`|`/`"`
 *   all -> ` `, then `:`->`_`. The `\/`->`-` step is a confirmed no-op as
 *   written (the preceding `\`->`-` step already removes every backslash
 *   in the string, so no `\/` sequence can ever survive to match it) —
 *   omitted here rather than reproducing dead code, not a shortcut.
 * - **output is encoded windows-1256, NOT UTF-8** (`iconv('utf-8',
 *   'windows-1256', $Content)`) — GetRight is a Windows program of this
 *   era; sending UTF-8 would render Arabic titles as mojibake in the
 *   downloaded file. This is the single most important behavior to not
 *   silently "fix" to UTF-8.
 * - `Content-Type: application/force-download`, `Content-Disposition:
 *   attachment; filename="Way2Allah-{name}.grx"` — `$name` is the series
 *   title itself (`downitems.php:16`'s 3rd arg to `DownItems()`), not a
 *   generic filename.
 *
 * `if (is_array($items))` else `header('location: '.$siteurl)` —
 * `get_results()` always returns an array (empty or not) for a
 * successful query, so this legacy branch is effectively unreachable
 * (no query-failure path exists to trigger the redirect) — not
 * reproduced as a real branch; `Series::findOrFail()` below provides the
 * equivalent "nonexistent series" guard via a 404 instead, a deliberate,
 * minimal difference (technical-correctness call, not a behavior
 * invention) rather than a literal unreachable-branch port.
 */
class CategoryDownItemsController
{
    public function show(ContentListingService $listing, int $series, ?int $category = null): Response
    {
        $seriesModel = Series::findOrFail($series);

        $items = $listing->khotabLinksForSeriesDownload($series, $category);

        $content = '';
        $folder = $seriesModel->title !== null ? $seriesModel->title.'\\' : '';

        foreach ($items as $item) {
            $extension = strtolower((string) pathinfo((string) $item->link, PATHINFO_EXTENSION));
            $title = (string) $item->title;
            $title = str_replace('\\', '-', $title);
            $title = str_replace('/', '-', $title);
            $title = str_replace('*', ' ', $title);
            $title = str_replace('?', ' ', $title);
            $title = str_replace('<', ' ', $title);
            $title = str_replace('>', ' ', $title);
            $title = str_replace('|', ' ', $title);
            $title = str_replace('"', ' ', $title);
            $title = str_replace(':', '_', $title);

            $content .= "URL: {$item->link}\r\nFile: C:\\Way2Allah\\{$folder}{$title}.{$extension}.GetRight\r\n\r\n";
        }

        $body = iconv('utf-8', 'windows-1256', $content);

        return response($body === false ? '' : $body, 200, [
            'Pragma' => 'public',
            'Expires' => '0',
            // Legacy sends 2 separate Cache-Control header lines
            // (header("Cache-Control: ...", false) — the 2nd call
            // deliberately does not replace the 1st). Combined into one
            // comma-joined value here, which HTTP treats as equivalent —
            // not chasing literal header-line duplication for this.
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0, private',
            'Content-Type' => 'application/force-download',
            'Content-Disposition' => 'attachment; filename="Way2Allah-'.$seriesModel->title.'.grx"',
            'Content-Transfer-Encoding' => 'binary',
        ]);
    }
}
