<?php

namespace App\Domain\Admin\Support;

/**
 * OLD Admin Migration Phase 2 — prepares an audit entry's structured
 * metadata for display.
 *
 * Two jobs, and deliberately no more:
 *
 * 1. **Redact by key name.** Nothing Phase 1 records today is secret —
 *    mirror links are public download URLs — but the audit trail is
 *    general, and a value must not be shown merely because some future
 *    caller put it in metadata. Keys are matched whole and
 *    case-insensitively, so `link` is never mistaken for `api_key`.
 * 2. **Stay structured.** The result is a nested array for the view to
 *    render with escaped `{{ }}` output. This class never builds HTML, so
 *    there is no string it could fail to escape.
 */
final class AuditMetadataPresenter
{
    public const REDACTED = '[محجوب]';

    private const SENSITIVE_KEYS = [
        'password', 'pwd', 'passwd', 'pass',
        'token', 'remember_token', 'api_token', 'access_token',
        'secret', 'api_key', 'apikey', 'api',
        'cookie', 'session', 'hash',
    ];

    /**
     * @param  array<mixed>|null  $metadata
     * @return array<mixed>
     */
    public static function present(?array $metadata): array
    {
        if ($metadata === null) {
            return [];
        }

        $out = [];

        foreach ($metadata as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                $out[$key] = self::REDACTED;

                continue;
            }

            $out[$key] = is_array($value) ? self::present($value) : $value;
        }

        return $out;
    }

    /** Scalars rendered as text; the view escapes the result. */
    public static function scalar(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            default => (string) $value,
        };
    }
}
