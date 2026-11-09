<?php

namespace App\Domain\Admin\Actions\Cds;

/** Phase 6 — free text for audit metadata; markup-looking values become a marker (AdminAuditRecorder refuses markup). */
final class CdAuditText
{
    /** @return string|array{redacted: string, length: int} */
    public static function of(?string $text): string|array
    {
        $text = (string) $text;

        return preg_match('/<[a-z!\/][^>]*>/i', $text) === 1 ? ['redacted' => 'markup', 'length' => mb_strlen($text)] : $text;
    }

    /**
     * @param  list<array{title: string, url: string}>  $pairs
     * @return list<array{title: mixed, url: mixed}>
     */
    public static function links(array $pairs): array
    {
        return array_map(fn ($p) => ['title' => self::of($p['title']), 'url' => self::of($p['url'])], $pairs);
    }
}
