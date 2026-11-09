<?php

namespace App\Domain\Admin\Actions\Advfiles;

/**
 * Phase 5 — payload values for audit metadata. `code` is raw HTML by design,
 * so it is never copied into the audit row: a SHA-256 and a length identify
 * the version. `audio_url` is recorded verbatim unless it looks like markup
 * (AdminAuditRecorder refuses markup), in which case a marker is recorded.
 */
final class AdvfileAuditValue
{
    /** @return array{sha256: string, length: int} */
    public static function code(?string $code): array
    {
        $code = (string) $code;

        return ['sha256' => hash('sha256', $code), 'length' => mb_strlen($code)];
    }

    /** @return string|array{redacted: string, length: int} */
    public static function text(?string $text): string|array
    {
        $text = (string) $text;

        return preg_match('/<[a-z!\/][^>]*>/i', $text) === 1
            ? ['redacted' => 'markup', 'length' => mb_strlen($text)]
            : $text;
    }
}
