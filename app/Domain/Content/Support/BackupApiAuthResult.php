<?php

namespace App\Domain\Content\Support;

use App\Domain\Admin\Models\AdminUser;

/**
 * Result of `BackupApiAuthenticator::authenticate()` — carries either the
 * authenticated `AdminUser` or the exact raw-text error body `backup.php`
 * itself would have echoed for that failure (see the authenticator's own
 * docblock for the 3 confirmed failure shapes).
 */
final class BackupApiAuthResult
{
    private function __construct(
        public readonly bool $authenticated,
        public readonly ?AdminUser $admin,
        public readonly ?string $errorResponse,
    ) {}

    public static function ok(AdminUser $admin): self
    {
        return new self(true, $admin, null);
    }

    public static function error(string $body): self
    {
        return new self(false, null, $body);
    }
}
