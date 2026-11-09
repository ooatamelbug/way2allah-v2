<?php

namespace App\Domain\Content\Services\SendToFriend;

/**
 * Input limits shared by the three send-to-friend endpoints (#106, owner-approved
 * 2026-11-08): names at most 100 characters, email addresses at most 254. Existing
 * per-module rules (required fields, Fatwa's 2-character minimum, the email format
 * checks) stay as they were; these limits are added on top.
 */
final class SendToFriendInput
{
    public const NAME_MAX = 100;

    public const EMAIL_MAX = 254;

    /**
     * Khotab / Anasheed's legacy single combined check (both names present, both
     * emails FILTER_VALIDATE_EMAIL), plus the shared length limits. False maps to
     * the legacy response code '2'.
     */
    public static function legacyFieldsValid(string $yourName, string $yourEmail, string $friendName, string $friendEmail): bool
    {
        if ($yourName === '' || $friendName === '') {
            return false;
        }

        if (mb_strlen($yourName) > self::NAME_MAX || mb_strlen($friendName) > self::NAME_MAX) {
            return false;
        }

        if (strlen($yourEmail) > self::EMAIL_MAX || strlen($friendEmail) > self::EMAIL_MAX) {
            return false;
        }

        return filter_var($yourEmail, FILTER_VALIDATE_EMAIL) !== false
            && filter_var($friendEmail, FILTER_VALIDATE_EMAIL) !== false;
    }
}
