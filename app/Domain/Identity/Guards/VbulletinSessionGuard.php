<?php

namespace App\Domain\Identity\Guards;

use App\Domain\Identity\Models\VbUser;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

/**
 * Public-site authentication (ADR-0011, Blueprint v1.0 §9). Reads the same
 * three cookies the legacy application already sets and validates them
 * directly against vBulletin's own `session`/`user` tables — Laravel never
 * maintains a synced copy of vBulletin's users.
 *
 * Faithfully reproduces the legacy check_login()/get_session()/get_cookie()
 * logic in w2a_config.php:
 *  - `bb_sessionhash` cookie: looked up in `session`, valid only if the
 *    stored `idhash` matches md5(User-Agent + first-3-octets-of-IP) and the
 *    session has been active within the last 900 seconds (15 minutes).
 *  - `bb_userid` + `bb_password` cookie pair: valid only if
 *    md5(user.password . cookie_salt) matches the `bb_password` cookie.
 */
class VbulletinSessionGuard implements Guard
{
    use GuardHelpers;

    /** Legacy session table's inactivity window, in seconds (w2a_config.php get_session()). */
    private const SESSION_TTL_SECONDS = 900;

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
        $sessionHash = $this->request->cookie('bb_sessionhash');

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

        if ($session->idhash !== $this->expectedIdHash()) {
            return null;
        }

        if ((time() - (int) $session->lastactivity) >= self::SESSION_TTL_SECONDS) {
            return null;
        }

        return VbUser::on('vbulletin')->find($session->userid);
    }

    private function viaCredentialCookie(): ?VbUser
    {
        $userId = $this->request->cookie('bb_userid');
        $passwordCookie = $this->request->cookie('bb_password');

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

    private function expectedIdHash(): string
    {
        $ip = implode('.', array_slice(explode('.', (string) $this->request->ip()), 0, 3));

        return md5($this->request->userAgent().$ip);
    }
}
