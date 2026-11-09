<?php

namespace App\Domain\Admin\Actions\Polls;

/**
 * Phase 4 — poll text for audit metadata. Poll titles and option texts are
 * free text that OLD stored unfiltered, and AdminAuditRecorder refuses any
 * metadata string that looks like markup. Recording such a value verbatim
 * would make the recorder throw and roll back a legitimate mutation, so a
 * markup-looking value is replaced by a marker with its length instead.
 * No real poll text contains markup today; this only keeps legacy data from
 * ever blocking administration.
 */
final class PollAuditText
{
    /** @return string|array{redacted: string, length: int} */
    public static function of(?string $text): string|array
    {
        $text = (string) $text;

        return preg_match('/<[a-z!\/][^>]*>/i', $text) === 1
            ? ['redacted' => 'markup', 'length' => mb_strlen($text)]
            : $text;
    }
}
