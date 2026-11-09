<?php

namespace App\Domain\Content\Services\Mobile;

use Illuminate\Support\Facades\DB;

/**
 * OLD Admin Migration Phase 7 — small shared rules of the Mobile section.
 */
final class MobileSite
{
    /** PHP-Nuke's `$anonymous` (nuke_config.anonymous, "زائر") — the name anonymous votes carry. */
    public static function anonymousName(): string
    {
        $name = (string) DB::connection('main')->table('nuke_config')->value('anonymous');

        return $name !== '' ? $name : 'Anonymous';
    }

    /**
     * Whether a stored download URL can actually be served. OLD redirected to
     * any stored URL (after rewriting knoz4arab.com to a host that does not
     * resolve). Here a URL is offered only if it is http(s) and its host is not
     * one production verification showed to be gone (plan §26.6) — an
     * unavailable download is reported, never redirected.
     */
    public static function downloadAvailable(string $url): bool
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) {
            return false;
        }

        return ! in_array(strtolower($parts['host']), array_map('strtolower', (array) config('mobile_downloads.unavailable_hosts')), true);
    }

    /**
     * Stored descriptions are legacy HTML (291 of 341 contain markup — <br>,
     * <strong>, <img>). OLD echoed them raw; here line breaks are kept and
     * every tag is removed, so the text is shown safely (escaped by Blade).
     */
    public static function plainText(?string $html): string
    {
        $text = preg_replace('#<\s*br\s*/?\s*>|</\s*(p|div|li)\s*>#i', "\n", (string) $html);
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    }
}
