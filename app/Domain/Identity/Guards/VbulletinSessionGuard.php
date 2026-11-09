<?php

namespace App\Domain\Identity\Guards;

use App\Domain\Identity\Models\VbUser;
use App\Domain\Identity\Support\VbulletinCookieNames;
use App\Domain\Identity\Support\VbulletinSessionBinding;
use App\Domain\Identity\Support\VbulletinSessionBindingConfigurationException;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Public-site authentication (ADR-0011, Blueprint v1.0 §9). Reads the same
 * three cookies the legacy application already sets and validates them
 * directly against vBulletin's own `session`/`user` tables — Laravel never
 * maintains a synced copy of vBulletin's users.
 *
 * Faithfully reproduces the legacy check_login()/get_session()/get_cookie()
 * logic in w2a_config.php:
 *  - `bb_sessionhash` cookie: looked up in `session`, valid only if the
 *    session is younger than the forum's `cookietimeout` (default 900 s), its
 *    `idhash` matches vBulletin's md5(User-Agent . ipSubstring(ALT_IP)), AND its
 *    `host` matches the request's IPADDRESS under the same ipSubstring rule —
 *    vBulletin's two IP checks (D1, 2026-11-09, decision-log #138), computed by
 *    VbulletinSessionBinding from the raw server values, never `request()->ip()`.
 *    A malformed or unsafe binding configuration accepts no session cookie (logged).
 *  - `bb_userid` + `bb_password` cookie pair: valid only if
 *    md5(user.password . cookie_salt) matches the `bb_password` cookie.
 *
 * Cookie names (N1 / R14-A, 2026-11-08): `{prefix}sessionhash` / `{prefix}userid` /
 * `{prefix}password` from VbulletinCookieNames (`services.vbulletin.cookie_prefix`,
 * default `bb_`, the names above) — the same source as the EncryptCookies
 * exemption that lets these unencrypted forum cookies reach this guard. Only the
 * configured prefix is read. Read-only: the forum's `session` row is never touched
 * (no `lastactivity` refresh). vB5's token-based remember-me `password` cookie is
 * not supported — it never matches the md5 check, so it fails closed.
 */
class VbulletinSessionGuard implements Guard
{
    use GuardHelpers;

    /** Validated once per request; false = the configuration was refused (fail closed). */
    private VbulletinSessionBinding|false|null $binding = null;

    public function __construct(private readonly Request $request) {}

    public function user(): ?VbUser
    {
        // See AdminGuard::user()'s identical fix (decision #3) — instanceof
        // narrows GuardHelpers' ?Authenticatable $user back to ?VbUser.
        if ($this->user instanceof VbUser) {
            return $this->user;
        }

        $vbUser = $this->viaSessionHashCookie() ?? $this->viaCredentialCookie();
        $this->user = $vbUser;

        return $vbUser;
    }

    public function validate(array $credentials = []): bool
    {
        return false; // This guard never validates raw credentials — only the two legacy cookie forms.
    }

    private function viaSessionHashCookie(): ?VbUser
    {
        $name = VbulletinCookieNames::sessionHash();
        $sessionHash = $name === null ? null : $this->request->cookie($name);

        if (! is_string($sessionHash) || $sessionHash === '') {
            return null;
        }

        $session = DB::connection('vbulletin')
            ->table('session')
            ->where('sessionhash', $sessionHash)
            ->first();

        if ($session === null || (int) $session->userid <= 0) {
            return null;
        }

        $binding = $this->binding();
        if ($binding === null) {
            return null;
        }

        // 1. Not expired (vBulletin: lastactivity > now - cookietimeout).
        if ((time() - (int) $session->lastactivity) >= $binding->cookieTimeout()) {
            return null;
        }

        // 2. idhash from ALT_IP (vBulletin session/web.php:37, session.php:299).
        if (! hash_equals($binding->idHash($this->request), (string) $session->idhash)) {
            return null;
        }

        // 3. session.host against IPADDRESS (vBulletin session.php:347).
        if (! $binding->hostMatches((string) ($session->host ?? ''), $this->request)) {
            return null;
        }

        return VbUser::on('vbulletin')->find($session->userid);
    }

    private function viaCredentialCookie(): ?VbUser
    {
        $userIdName = VbulletinCookieNames::userId();
        $passwordName = VbulletinCookieNames::password();
        $userId = $userIdName === null ? null : $this->request->cookie($userIdName);
        $passwordCookie = $passwordName === null ? null : $this->request->cookie($passwordName);

        if (! is_string($userId) || $userId === '' || ! is_string($passwordCookie) || $passwordCookie === '') {
            return null;
        }

        if (! ctype_digit($userId)) {
            return null;
        }

        $vbUser = VbUser::on('vbulletin')->find((int) $userId);

        if ($vbUser === null) {
            return null;
        }

        $cookieSalt = config('services.vbulletin.cookie_salt');

        if (! is_string($cookieSalt) || $cookieSalt === '') {
            // Misconfiguration (missing VBULLETIN_COOKIE_SALT) must never silently
            // accept every cookie — fail closed, not open.
            return null;
        }

        $expected = md5($vbUser->password.$cookieSalt);

        return hash_equals($expected, $passwordCookie) ? $vbUser : null;
    }

    private function binding(): ?VbulletinSessionBinding
    {
        if ($this->binding === null) {
            try {
                $this->binding = VbulletinSessionBinding::fromConfig();
            } catch (VbulletinSessionBindingConfigurationException $e) {
                $this->binding = false;
                Log::error('forum-auth: session binding configuration refused — forum session cookies are not accepted', ['reason' => $e->getMessage()]);
            }
        }

        return $this->binding === false ? null : $this->binding;
    }
}
