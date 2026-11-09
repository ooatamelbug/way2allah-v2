<?php

namespace App\Http\Middleware;

use App\Domain\Identity\Support\VbulletinCookieNames;
use Illuminate\Cookie\Middleware\EncryptCookies as BaseEncryptCookies;

/**
 * Laravel's cookie encryption, with exactly the three vBulletin auth cookies left
 * as they are (N1 / R14-A, 2026-11-08).
 *
 * The forum sets `{prefix}sessionhash`, `{prefix}userid` and `{prefix}password`
 * itself, unencrypted; the stock middleware failed to decrypt them and nulled
 * them, so VbulletinSessionGuard never saw a forum cookie over HTTP. Only those
 * names (VbulletinCookieNames, the guard's own source) are exempted, resolved at
 * request time so a cached configuration is honoured. Every other cookie —
 * Laravel's session and XSRF cookies included — is still encrypted and decrypted
 * as before, and unrelated unencrypted cookies are still discarded. The exemption
 * makes the cookies readable, not trusted: the guard validates them against the
 * forum database.
 */
class EncryptCookies extends BaseEncryptCookies
{
    public function isDisabled($name)
    {
        return parent::isDisabled($name) || in_array($name, VbulletinCookieNames::all(), true);
    }
}
