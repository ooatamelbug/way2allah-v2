<?php

namespace App\Domain\Identity\Support;

use Illuminate\Http\Request;

/**
 * vBulletin forum-session IP binding, reproduced from the legacy source (D1, owner-approved Option C, 2026-11-09;
 * reference: the local vBulletin 5.5.5 / SIMPLE_VERSION 562 copy under `7amlat/admincp/forumConfig/core/vb/`).
 *
 * vBulletin accepts a stored session only when BOTH IP checks pass (session.php:274-353):
 *  1. `idhash` === md5(User-Agent . ipSubstring(ALT_IP)) — session/web.php:34-38, session.php:299;
 *  2. ipSubstring(session.host) == ipSubstring(IPADDRESS) — session.php:280, :347 (host stored as IPADDRESS, :770).
 *
 * ALT_IP / IPADDRESS come from the request constructor (request.php:47-100), never from Laravel's `request()->ip()`
 * (Laravel trusted proxies must not change forum binding):
 *  - webip = REMOTE_ADDR (fetchIp, 268-288);
 *  - altip = CF-Connecting-IP if public, else the first public X-Forwarded-For entry, else REMOTE_ADDR
 *    (fetchAltIp, 295-350; "public" = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE, utility/ip.php:57-60);
 *  - when REMOTE_ADDR is in the proxy list (exact, or a `*` prefix wildcard, utility/ip.php:121-144), altip becomes the
 *    configured proxy header's value if it is a valid IP (request.php:57-74), and the two are swapped: ALT_IP =
 *    webip, IPADDRESS = altip (83-92); otherwise ALT_IP = altip, IPADDRESS = webip;
 *  - both are trimmed; IPv6 is canonicalised with inet_ntop(inet_pton()) (request.php:76-77, utility/ip.php:91-108);
 *  - ipSubstring keeps the first `4 - ipcheck` dot-separated parts (utility/ip.php:159-165, session.php:746-751);
 *  - a missing User-Agent is the literal `vBulletin` (request.php:105-108).
 *
 * Deliberate, safety-only deviations (owner decisions OD2 / ipcheck=4): `proxyiplist = all` — and a bare `*`, which
 * matches every address the same way — is refused; `ipcheck = 4` (no IP binding at all) is refused unless explicitly
 * approved; malformed settings are refused. A refused configuration never weakens validation: the guard then
 * accepts no forum session cookie. The host comparison is strict (`===`) where vBulletin uses PHP's loose `==`, so
 * a numeric-looking prefix such as `1.10` never equals `1.1` here — stricter, never weaker.
 */
final class VbulletinSessionBinding
{
    private const USER_AGENT_FALLBACK = 'vBulletin';

    private const MAX_COOKIE_TIMEOUT = 31_536_000;

    /** @param list<string> $proxyList */
    private function __construct(
        private readonly int $ipcheck,
        private readonly int $cookieTimeout,
        private readonly array $proxyList,
        private readonly string $proxyHeader,
    ) {}

    /** @throws VbulletinSessionBindingConfigurationException */
    public static function fromConfig(): self
    {
        $ipcheck = self::integerSetting(config('services.vbulletin.ipcheck', 1), 'VBULLETIN_IPCHECK', 0, 4);

        if ($ipcheck === 4 && filter_var(config('services.vbulletin.ipcheck_allow_unbound', false), FILTER_VALIDATE_BOOLEAN) !== true) {
            throw new VbulletinSessionBindingConfigurationException(
                'VBULLETIN_IPCHECK=4 disables forum session IP binding entirely; it requires the explicit approval VBULLETIN_IPCHECK_ALLOW_UNBOUND=true.'
            );
        }

        return new self(
            $ipcheck,
            self::integerSetting(config('services.vbulletin.cookie_timeout', 900), 'VBULLETIN_COOKIE_TIMEOUT', 1, self::MAX_COOKIE_TIMEOUT),
            self::proxyList(config('services.vbulletin.proxy_ip_list', '')),
            self::proxyHeader(config('services.vbulletin.proxy_ip_header', '')),
        );
    }

    public function cookieTimeout(): int
    {
        return $this->cookieTimeout;
    }

    /** @return array{ipAddress: string, altIp: string} vBulletin's IPADDRESS and ALT_IP for this request */
    public function addresses(Request $request): array
    {
        $server = $request->server;
        $webip = self::serverString($server->get('REMOTE_ADDR'));
        $altip = $this->fetchAltIp($server->get('HTTP_CF_CONNECTING_IP'), $server->get('HTTP_X_FORWARDED_FOR'), $webip);

        $proxy = false;
        if ($this->proxyList !== [] && $this->inProxyList($webip)) {
            $proxy = true;
            if ($this->proxyHeader !== '') {
                $headerIp = $server->get($this->proxyHeader);
                if (is_string($headerIp) && filter_var($headerIp, FILTER_VALIDATE_IP) !== false) {
                    $altip = $headerIp;
                }
            }
        }

        $webip = self::normalize($webip);
        $altip = self::normalize($altip);

        return $proxy
            ? ['ipAddress' => $altip, 'altIp' => $webip]
            : ['ipAddress' => $webip, 'altIp' => $altip];
    }

    public function userAgent(Request $request): string
    {
        $userAgent = $request->server->get('HTTP_USER_AGENT');

        return $userAgent === null ? self::USER_AGENT_FALLBACK : self::serverString($userAgent);
    }

    /** md5(User-Agent . ipSubstring(ALT_IP)) — vBulletin's web session idhash. */
    public function idHash(Request $request): string
    {
        return md5($this->userAgent($request).$this->ipSubstring($this->addresses($request)['altIp']));
    }

    /** The stored `session.host` is in the same network prefix as this request's IPADDRESS. */
    public function hostMatches(string $storedHost, Request $request): bool
    {
        return $this->ipSubstring($storedHost) === $this->ipSubstring($this->addresses($request)['ipAddress']);
    }

    public function ipSubstring(string $ip): string
    {
        return implode('.', array_slice(explode('.', $ip), 0, 4 - $this->ipcheck));
    }

    private function fetchAltIp(mixed $cfConnectingIp, mixed $forwardedFor, string $webip): string
    {
        if (is_string($cfConnectingIp) && self::isPublic($cfConnectingIp)) {
            return $cfConnectingIp;
        }

        if (is_string($forwardedFor)) {
            foreach (explode(',', $forwardedFor) as $candidate) {
                $candidate = trim($candidate);
                if (self::isPublic($candidate)) {
                    return $candidate;
                }
            }
        }

        return $webip;
    }

    private function inProxyList(string $ip): bool
    {
        foreach ($this->proxyList as $entry) {
            if (str_ends_with($entry, '*')
                ? strncasecmp($ip, $entry, strlen($entry) - 1) === 0
                : $ip === $entry) {
                return true;
            }
        }

        return false;
    }

    private static function isPublic(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    private static function normalize(string $ip): string
    {
        $trimmed = trim($ip);
        if (filter_var($trimmed, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return $trimmed;
        }

        $packed = inet_pton($trimmed);

        return $packed === false ? $trimmed : (string) inet_ntop($packed);
    }

    private static function serverString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private static function integerSetting(mixed $value, string $name, int $min, int $max): int
    {
        if (is_int($value)) {
            $int = $value;
        } elseif (is_string($value) && preg_match('/^\d{1,9}$/', trim($value)) === 1) {
            $int = (int) trim($value);
        } else {
            throw new VbulletinSessionBindingConfigurationException("{$name} must be a whole number between {$min} and {$max}.");
        }

        if ($int < $min || $int > $max) {
            throw new VbulletinSessionBindingConfigurationException("{$name} must be a whole number between {$min} and {$max}.");
        }

        return $int;
    }

    /** @return list<string> */
    private static function proxyList(mixed $value): array
    {
        if ($value === null || $value === '' || $value === false) {
            return [];
        }
        if (! is_string($value)) {
            throw new VbulletinSessionBindingConfigurationException('VBULLETIN_PROXY_IP_LIST must be a comma-separated list of IP addresses or prefix wildcards.');
        }
        if (trim($value) === '') {
            return [];
        }

        $entries = array_map('trim', explode(',', $value));
        foreach ($entries as $entry) {
            if (strcasecmp($entry, 'all') === 0 || $entry === '*') {
                throw new VbulletinSessionBindingConfigurationException(
                    'VBULLETIN_PROXY_IP_LIST may not trust every address ("all" or "*"): any client could then choose its own forum session IP.'
                );
            }
            $valid = str_ends_with($entry, '*')
                ? preg_match('/^[0-9A-Fa-f:.]+\*$/', $entry) === 1
                : filter_var($entry, FILTER_VALIDATE_IP) !== false;
            if (! $valid) {
                throw new VbulletinSessionBindingConfigurationException('VBULLETIN_PROXY_IP_LIST entries must be IP addresses or prefix wildcards ending in "*"; an entry is malformed or empty.');
            }
        }

        return $entries;
    }

    private static function proxyHeader(mixed $value): string
    {
        if ($value === null || $value === '' || $value === false) {
            return '';
        }
        if (! is_string($value) || preg_match('/^[A-Z][A-Z0-9_]*$/', $value) !== 1) {
            throw new VbulletinSessionBindingConfigurationException('VBULLETIN_PROXY_IP_HEADER must be a server variable name such as HTTP_X_FORWARDED_FOR.');
        }

        return $value;
    }
}
