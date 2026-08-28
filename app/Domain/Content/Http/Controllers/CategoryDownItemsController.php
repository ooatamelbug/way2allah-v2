<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Models\Series;
use App\Domain\Content\Services\ContentListingService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

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
 *
 * **`.grx` Encoding Repair (decision-log #53) — `BUSINESS_REPAIR_LOW_RISK`,
 * explicitly NOT recovered/exact legacy parity.** Legacy's own real
 * `DownItems()` (`functions.php:744`) runs the exact same bare
 * `iconv('utf-8', 'windows-1256', $Content)` with no failure handling at
 * all — when a title contains a Unicode character windows-1256 can't
 * represent, `iconv()` returns `false`, and legacy's `echo false;`
 * silently produces a fully EMPTY download body (still `200`, still the
 * real headers) — not a crash, but an unusable, silently-broken file.
 * This project's own warning-to-exception escalation turned that same
 * condition into an uncontrolled `500` instead (a real, disclosed
 * divergence — see the Sitewide Internal 404 Audit / decision-log #52's
 * investigation for the full trace: ~14% of a random 150-series sample
 * failed, driven by 2 confirmed real, legitimate Unicode classes:
 * Arabic-Indic digits and the `ﷺ` religious honorific ligature,
 * U+FDFA — plus co-occurring U+200F marks).
 *
 * **Owner decision: do not reproduce legacy's silent-empty-file defect
 * either.** `normalizeForWindows1256Export()` below narrowly normalizes
 * only proven-unsupported character classes — applied to both the series
 * title (`$folder`) and each item's own title, since both feed the same
 * `iconv()` call. `iconv()` itself stays strict (no `//IGNORE`/`//TRANSLIT`
 * — deliberately, so a genuinely new, not-yet-identified unsupported
 * character surfaces as a real, loggable failure instead of silently
 * vanishing). The output contract (Windows-1256 body, all headers,
 * filename convention, item formatting/ordering) is otherwise completely
 * unchanged — this is not a UTF-8 migration and not a new file format.
 *
 * **Follow-up (decision-log #54) — Quranic Unicode compatibility, owner
 * explicitly approved.** A 3-series real-data trace (12578/6329/11140)
 * found 6 more unsupported codepoints in titles quoting Quranic verses
 * verbatim with full tashkeel/Uthmani annotation; a broader 150-series
 * simulation found 6 further codepoints of the same kind. All 12 are
 * handled below via 3 bounded steps, in this exact order, none
 * overlapping in the characters they touch: (1) recompose decomposed
 * ALEF+hamza/maddah sequences into the precomposed letter Windows-1256
 * already supports; (2) substitute exactly 2 distinct letters (ALEF
 * WASLA, FARSI YEH) proven — by direct corpus-internal spelling-frequency
 * comparison, not assumption — to be used interchangeably with their
 * plain counterparts in this dataset; (3) remove the remaining Quranic
 * recitation/annotation marks, which carry no letter-identity function
 * and whose removal was verified (real-data before/after examples) to
 * leave every base letter and word boundary intact. This remains an
 * explicit allowlist of exact codepoints, not a Unicode-range or
 * -category rule — any character outside this list still reaches the
 * unchanged defensive `iconv()` failure path below.
 */
class CategoryDownItemsController
{
    /**
     * Arabic-Indic digits → ASCII digits — the numeric value is
     * unchanged, and the result is safely representable in Windows-1256
     * (verified directly, not assumed).
     */
    private const ARABIC_INDIC_DIGITS = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    /**
     * Distinct Arabic letters with a corpus-proven-safe legacy Windows-1256
     * approximation. Owner-approved, exactly these 2 — not a general
     * transliteration table. Each was verified by direct corpus-internal
     * spelling-frequency comparison (e.g. plain ي outnumbers ی 314-to-2 in
     * the sampled data, both minority occurrences confined to
     * Quranic-verse-quoting titles) before being added.
     */
    private const LETTER_SUBSTITUTIONS = [
        "\u{0671}" => "\u{0627}", // ALEF WASLA (ٱ) -> ALEF (ا)
        "\u{06CC}" => "\u{064A}", // FARSI YEH (ی) -> YEH (ي)
    ];

    /**
     * Quranic recitation/annotation marks with no letter-identity
     * function, unrepresentable in Windows-1256, owner-approved for
     * removal — an explicit codepoint allowlist, deliberately not a
     * Unicode range or general-category rule. U+0653 here covers only
     * standalone occurrences; a ا+U+0653 sequence is recomposed to آ
     * before this step runs, so it never reaches this list.
     */
    private const REMOVABLE_MARKS = [
        "\u{0670}", // ARABIC LETTER SUPERSCRIPT ALEF (dagger alif)
        "\u{06E1}", // ARABIC SMALL HIGH DOTLESS HEAD OF KHAH
        "\u{0653}", // ARABIC MADDAH ABOVE (standalone)
        "\u{06DA}", // ARABIC SMALL HIGH JEEM
        "\u{06E6}", // ARABIC SMALL YEH
        "\u{06E5}", // ARABIC SMALL WAW
        "\u{06E4}", // ARABIC SMALL HIGH MADDA
        "\u{08F1}", // ARABIC OPEN DAMMATAN
    ];

    public function show(ContentListingService $listing, int $series, ?int $category = null): Response
    {
        $seriesModel = Series::findOrFail($series);

        $items = $listing->khotabLinksForSeriesDownload($series, $category);

        $content = '';
        $folder = $seriesModel->title !== null ? $this->normalizeForWindows1256Export($seriesModel->title).'\\' : '';

        foreach ($items as $item) {
            $extension = strtolower((string) pathinfo((string) $item->link, PATHINFO_EXTENSION));
            $title = $this->normalizeForWindows1256Export((string) $item->title);
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

        $body = @iconv('utf-8', 'windows-1256', $content);

        if ($body === false) {
            // Should not happen after normalizeForWindows1256Export()
            // above — every currently-known unsupported character class
            // is normalized before this point. Reaching here means a
            // genuinely new, not-yet-identified Unicode character exists
            // in this series' data. Deliberately NOT silently discarded
            // (no //IGNORE) and NOT silently served as an empty 200 body
            // (legacy's own real, undesirable behavior for this exact
            // condition, explicitly rejected by the owner) — logged with
            // enough context to narrow the failure to the offending item,
            // then a controlled 500 with no exception detail in the
            // response body, matching this project's existing convention
            // (DeploymentInstallerController's own Log::error pattern).
            $failingItem = null;
            foreach ($items as $item) {
                $itemBody = @iconv('utf-8', 'windows-1256', $this->normalizeForWindows1256Export((string) $item->title));
                if ($itemBody === false) {
                    $failingItem = $item->id ?? null;
                    break;
                }
            }

            Log::error('khotab-series .grx export: iconv to windows-1256 failed after normalization', [
                'series_id' => $series,
                'category_id' => $category,
                'item_id' => $failingItem,
            ]);

            abort(500);
        }

        return response($body, 200, [
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

    /**
     * `.grx` Encoding Repair (decision-log #53, extended by #54) —
     * narrowly scoped to this export's own Windows-1256 contract, not a
     * general-purpose text sanitizer. Normalizes ONLY character classes
     * proven, by direct investigation, to be unrepresentable in
     * Windows-1256. Any other character is passed through unchanged — a
     * genuinely new unsupported character is meant to surface as a real,
     * loggable `iconv()` failure in `show()` above, not be silently
     * swallowed here.
     *
     * Order is deliberate and each step's character set is disjoint from
     * the others, so there is no overlap that could make the order
     * matter:
     *   1. Recompose decomposed ALEF+hamza/maddah sequences into the
     *      precomposed letter Windows-1256 already supports.
     *   2. Substitute the 2 owner-approved distinct letters.
     *   3. Remove the owner-approved Quranic annotation marks, plus the
     *      pre-existing U+200F removal.
     *   4. Arabic-Indic digits -> ASCII (numeric value unchanged).
     *   5. Expand the `ﷺ` ligature to its own plain-letter phrase.
     */
    private function normalizeForWindows1256Export(string $text): string
    {
        $text = preg_replace('/\x{0627}\x{0654}/u', "\u{0623}", $text) ?? $text; // ا + ٔ -> أ
        $text = preg_replace('/\x{0627}\x{0655}/u', "\u{0625}", $text) ?? $text; // ا + ٕ -> إ
        $text = preg_replace('/\x{0627}\x{0653}/u', "\u{0622}", $text) ?? $text; // ا + ٓ -> آ

        $text = strtr($text, self::LETTER_SUBSTITUTIONS);

        $text = str_replace(self::REMOVABLE_MARKS, '', $text);
        $text = str_replace("\u{200F}", '', $text);

        $text = strtr($text, self::ARABIC_INDIC_DIGITS);

        $text = str_replace("\u{FDFA}", 'صلى الله عليه وسلم', $text);

        return $text;
    }
}
