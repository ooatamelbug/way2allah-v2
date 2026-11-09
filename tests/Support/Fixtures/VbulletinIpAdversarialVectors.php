<?php

namespace Tests\Support\Fixtures;

/**
 * D1 closure follow-up (2026-11-09): ADVERSARIAL golden vectors — generated exactly like VbulletinIpGoldenVectors, by
 * EXECUTING the legacy vBulletin request constructor (request.php:47-100, fetchIp 268-288, fetchAltIp 295-350) and
 * `vB_Utility_Ip` from the local vBulletin copy, one fresh PHP process per case, under PHP 7.4 (real traits) and
 * PHP 8.3 — identical. `idhash` / `host_substr` follow session/web.php:37 and session.php:746-751 (vBulletin's own
 * ipSubstring). Covers the acceptance review's missing vectors (proxied fallbacks, F1, ipcheck=2 numeric prefixes,
 * IPv6 filtering, ports, CGNAT, REMOTE_ADDR edge cases). Inputs and results only — no vBulletin code. Regenerate,
 * never edit by hand.
 *
 * @return array<string, array{server: array<string, string>, proxy_ip_list: string, proxy_ip_header: string, ipcheck: int, expected: array{IPADDRESS: string, ALT_IP: string, userAgent: string, idhash: string, host_substr: string}}>
 */
final class VbulletinIpAdversarialVectors
{
    public static function all(): array
    {
        return [
            'proxied: multi-value configured header falls back to alt-IP rules' => [
                'server' => [
                    'REMOTE_ADDR' => '10.9.8.7',
                    'HTTP_USER_AGENT' => 'AdversarialUA/1.0',
                    'HTTP_X_FORWARDED_FOR' => '203.0.113.50, 198.51.100.9',
                ],
                'proxy_ip_list' => '10.9.8.7',
                'proxy_ip_header' => 'HTTP_X_FORWARDED_FOR',
                'ipcheck' => 1,
                'expected' => [
                    'IPADDRESS' => '203.0.113.50',
                    'ALT_IP' => '10.9.8.7',
                    'userAgent' => 'AdversarialUA/1.0',
                    'idhash' => 'cdd73b9c69267d12f51c76a32ab4af8e',
                    'host_substr' => '203.0.113',
                ],
            ],
            'proxied: missing configured header falls back to CF-Connecting-IP' => [
                'server' => [
                    'REMOTE_ADDR' => '10.9.8.7',
                    'HTTP_USER_AGENT' => 'AdversarialUA/1.0',
                    'HTTP_CF_CONNECTING_IP' => '198.51.100.77',
                ],
                'proxy_ip_list' => '10.9.8.7',
                'proxy_ip_header' => 'HTTP_X_CLIENT_IP',
                'ipcheck' => 1,
                'expected' => [
                    'IPADDRESS' => '198.51.100.77',
                    'ALT_IP' => '10.9.8.7',
                    'userAgent' => 'AdversarialUA/1.0',
                    'idhash' => 'cdd73b9c69267d12f51c76a32ab4af8e',
                    'host_substr' => '198.51.100',
                ],
            ],
            'proxied: invalid configured header falls back to X-Forwarded-For' => [
                'server' => [
                    'REMOTE_ADDR' => '10.9.8.7',
                    'HTTP_USER_AGENT' => 'AdversarialUA/1.0',
                    'HTTP_X_CLIENT_IP' => 'not-an-ip',
                    'HTTP_X_FORWARDED_FOR' => '198.51.100.88',
                ],
                'proxy_ip_list' => '10.9.8.7',
                'proxy_ip_header' => 'HTTP_X_CLIENT_IP',
                'ipcheck' => 1,
                'expected' => [
                    'IPADDRESS' => '198.51.100.88',
                    'ALT_IP' => '10.9.8.7',
                    'userAgent' => 'AdversarialUA/1.0',
                    'idhash' => 'cdd73b9c69267d12f51c76a32ab4af8e',
                    'host_substr' => '198.51.100',
                ],
            ],
            'proxied: victim session through a proxy that sets the header' => [
                'server' => [
                    'REMOTE_ADDR' => '10.9.8.7',
                    'HTTP_USER_AGENT' => 'AdversarialUA/1.0',
                    'HTTP_X_CLIENT_IP' => '198.51.100.9',
                ],
                'proxy_ip_list' => '10.9.8.7',
                'proxy_ip_header' => 'HTTP_X_CLIENT_IP',
                'ipcheck' => 1,
                'expected' => [
                    'IPADDRESS' => '198.51.100.9',
                    'ALT_IP' => '10.9.8.7',
                    'userAgent' => 'AdversarialUA/1.0',
                    'idhash' => 'cdd73b9c69267d12f51c76a32ab4af8e',
                    'host_substr' => '198.51.100',
                ],
            ],
            'proxied: attacker through an unsafe proxy (header absent, client CF-Connecting-IP passed through)' => [
                'server' => [
                    'REMOTE_ADDR' => '10.9.8.7',
                    'HTTP_USER_AGENT' => 'AdversarialUA/1.0',
                    'HTTP_CF_CONNECTING_IP' => '198.51.100.9',
                ],
                'proxy_ip_list' => '10.9.8.7',
                'proxy_ip_header' => 'HTTP_X_CLIENT_IP',
                'ipcheck' => 1,
                'expected' => [
                    'IPADDRESS' => '198.51.100.9',
                    'ALT_IP' => '10.9.8.7',
                    'userAgent' => 'AdversarialUA/1.0',
                    'idhash' => 'cdd73b9c69267d12f51c76a32ab4af8e',
                    'host_substr' => '198.51.100',
                ],
            ],
            'ipcheck 2: session created at 1.10.5.5 behind public X-Forwarded-For' => [
                'server' => [
                    'REMOTE_ADDR' => '1.10.5.5',
                    'HTTP_USER_AGENT' => 'AdversarialUA/1.0',
                    'HTTP_X_FORWARDED_FOR' => '8.8.8.8',
                ],
                'proxy_ip_list' => '',
                'proxy_ip_header' => '',
                'ipcheck' => 2,
                'expected' => [
                    'IPADDRESS' => '1.10.5.5',
                    'ALT_IP' => '8.8.8.8',
                    'userAgent' => 'AdversarialUA/1.0',
                    'idhash' => '14d1de57da70ae159162aead74d42ed6',
                    'host_substr' => '1.10',
                ],
            ],
            'ipcheck 2: later request from 1.1.7.7 behind the same X-Forwarded-For' => [
                'server' => [
                    'REMOTE_ADDR' => '1.1.7.7',
                    'HTTP_USER_AGENT' => 'AdversarialUA/1.0',
                    'HTTP_X_FORWARDED_FOR' => '8.8.8.8',
                ],
                'proxy_ip_list' => '',
                'proxy_ip_header' => '',
                'ipcheck' => 2,
                'expected' => [
                    'IPADDRESS' => '1.1.7.7',
                    'ALT_IP' => '8.8.8.8',
                    'userAgent' => 'AdversarialUA/1.0',
                    'idhash' => '14d1de57da70ae159162aead74d42ed6',
                    'host_substr' => '1.1',
                ],
            ],
            'IPv6 CF-Connecting-IP (canonicalised)' => [
                'server' => [
                    'REMOTE_ADDR' => '203.0.113.42',
                    'HTTP_USER_AGENT' => 'AdversarialUA/1.0',
                    'HTTP_CF_CONNECTING_IP' => '2606:4700:0:0:0:0:0:ABCD',
                ],
                'proxy_ip_list' => '',
                'proxy_ip_header' => '',
                'ipcheck' => 1,
                'expected' => [
                    'IPADDRESS' => '203.0.113.42',
                    'ALT_IP' => '2606:4700::abcd',
                    'userAgent' => 'AdversarialUA/1.0',
                    'idhash' => '93e05b3cc02faf9fc0029578e3de5243',
                    'host_substr' => '203.0.113',
                ],
            ],
            'uppercase IPv6 in X-Forwarded-For (canonicalised)' => [
                'server' => [
                    'REMOTE_ADDR' => '203.0.113.42',
                    'HTTP_USER_AGENT' => 'AdversarialUA/1.0',
                    'HTTP_X_FORWARDED_FOR' => '2606:4700:0:0:0:0:0:ABCD',
                ],
                'proxy_ip_list' => '',
                'proxy_ip_header' => '',
                'ipcheck' => 1,
                'expected' => [
                    'IPADDRESS' => '203.0.113.42',
                    'ALT_IP' => '2606:4700::abcd',
                    'userAgent' => 'AdversarialUA/1.0',
                    'idhash' => '93e05b3cc02faf9fc0029578e3de5243',
                    'host_substr' => '203.0.113',
                ],
            ],
            'IPv6 ULA and link-local skipped in X-Forwarded-For' => [
                'server' => [
                    'REMOTE_ADDR' => '203.0.113.42',
                    'HTTP_USER_AGENT' => 'AdversarialUA/1.0',
                    'HTTP_X_FORWARDED_FOR' => 'fd00::1, fe80::1, 2606:4700::1',
                ],
                'proxy_ip_list' => '',
                'proxy_ip_header' => '',
                'ipcheck' => 1,
                'expected' => [
                    'IPADDRESS' => '203.0.113.42',
                    'ALT_IP' => '2606:4700::1',
                    'userAgent' => 'AdversarialUA/1.0',
                    'idhash' => 'b84ca6b1de291f1d003edbd10611e534',
                    'host_substr' => '203.0.113',
                ],
            ],
            'IPv6 ULA / link-local only: falls back to REMOTE_ADDR' => [
                'server' => [
                    'REMOTE_ADDR' => '203.0.113.42',
                    'HTTP_USER_AGENT' => 'AdversarialUA/1.0',
                    'HTTP_X_FORWARDED_FOR' => 'fd12:3456::1, fe80::abcd',
                ],
                'proxy_ip_list' => '',
                'proxy_ip_header' => '',
                'ipcheck' => 1,
                'expected' => [
                    'IPADDRESS' => '203.0.113.42',
                    'ALT_IP' => '203.0.113.42',
                    'userAgent' => 'AdversarialUA/1.0',
                    'idhash' => 'b218309be9f2ffc560d056ed975909c3',
                    'host_substr' => '203.0.113',
                ],
            ],
            'ULA CF-Connecting-IP ignored' => [
                'server' => [
                    'REMOTE_ADDR' => '203.0.113.42',
                    'HTTP_USER_AGENT' => 'AdversarialUA/1.0',
                    'HTTP_CF_CONNECTING_IP' => 'fd00::1',
                    'HTTP_X_FORWARDED_FOR' => '8.8.4.4',
                ],
                'proxy_ip_list' => '',
                'proxy_ip_header' => '',
                'ipcheck' => 1,
                'expected' => [
                    'IPADDRESS' => '203.0.113.42',
                    'ALT_IP' => '8.8.4.4',
                    'userAgent' => 'AdversarialUA/1.0',
                    'idhash' => '0be3a23c58a8d53b6c6dfe62c5588774',
                    'host_substr' => '203.0.113',
                ],
            ],
            'IPv4 with port in X-Forwarded-For is not an IP' => [
                'server' => [
                    'REMOTE_ADDR' => '203.0.113.42',
                    'HTTP_USER_AGENT' => 'AdversarialUA/1.0',
                    'HTTP_X_FORWARDED_FOR' => '198.51.100.7:4321',
                ],
                'proxy_ip_list' => '',
                'proxy_ip_header' => '',
                'ipcheck' => 1,
                'expected' => [
                    'IPADDRESS' => '203.0.113.42',
                    'ALT_IP' => '203.0.113.42',
                    'userAgent' => 'AdversarialUA/1.0',
                    'idhash' => 'b218309be9f2ffc560d056ed975909c3',
                    'host_substr' => '203.0.113',
                ],
            ],
            'IPv4 with port skipped, next public entry used' => [
                'server' => [
                    'REMOTE_ADDR' => '203.0.113.42',
                    'HTTP_USER_AGENT' => 'AdversarialUA/1.0',
                    'HTTP_X_FORWARDED_FOR' => '198.51.100.7:4321, 8.8.8.8',
                ],
                'proxy_ip_list' => '',
                'proxy_ip_header' => '',
                'ipcheck' => 1,
                'expected' => [
                    'IPADDRESS' => '203.0.113.42',
                    'ALT_IP' => '8.8.8.8',
                    'userAgent' => 'AdversarialUA/1.0',
                    'idhash' => 'a880219bd599efb21442597563832194',
                    'host_substr' => '203.0.113',
                ],
            ],
            'CGNAT 100.64/10 in X-Forwarded-For counts as public' => [
                'server' => [
                    'REMOTE_ADDR' => '203.0.113.42',
                    'HTTP_USER_AGENT' => 'AdversarialUA/1.0',
                    'HTTP_X_FORWARDED_FOR' => '100.64.1.2',
                ],
                'proxy_ip_list' => '',
                'proxy_ip_header' => '',
                'ipcheck' => 1,
                'expected' => [
                    'IPADDRESS' => '203.0.113.42',
                    'ALT_IP' => '100.64.1.2',
                    'userAgent' => 'AdversarialUA/1.0',
                    'idhash' => 'c65e6a411529febc2e3d49b675435021',
                    'host_substr' => '203.0.113',
                ],
            ],
            'missing REMOTE_ADDR' => [
                'server' => [
                    'HTTP_USER_AGENT' => 'AdversarialUA/1.0',
                ],
                'proxy_ip_list' => '',
                'proxy_ip_header' => '',
                'ipcheck' => 1,
                'expected' => [
                    'IPADDRESS' => '',
                    'ALT_IP' => '',
                    'userAgent' => 'AdversarialUA/1.0',
                    'idhash' => '846972c027dabf7f5cb69b8e4d2f2a2b',
                    'host_substr' => '',
                ],
            ],
            'whitespace around REMOTE_ADDR is trimmed' => [
                'server' => [
                    'REMOTE_ADDR' => ' 203.0.113.42 ',
                    'HTTP_USER_AGENT' => 'AdversarialUA/1.0',
                ],
                'proxy_ip_list' => '',
                'proxy_ip_header' => '',
                'ipcheck' => 1,
                'expected' => [
                    'IPADDRESS' => '203.0.113.42',
                    'ALT_IP' => '203.0.113.42',
                    'userAgent' => 'AdversarialUA/1.0',
                    'idhash' => 'b218309be9f2ffc560d056ed975909c3',
                    'host_substr' => '203.0.113',
                ],
            ],
            'whitespace REMOTE_ADDR does not match an exact proxy list entry' => [
                'server' => [
                    'REMOTE_ADDR' => ' 10.9.8.7 ',
                    'HTTP_USER_AGENT' => 'AdversarialUA/1.0',
                    'HTTP_X_CLIENT_IP' => '198.51.100.9',
                ],
                'proxy_ip_list' => '10.9.8.7',
                'proxy_ip_header' => 'HTTP_X_CLIENT_IP',
                'ipcheck' => 1,
                'expected' => [
                    'IPADDRESS' => '10.9.8.7',
                    'ALT_IP' => '10.9.8.7',
                    'userAgent' => 'AdversarialUA/1.0',
                    'idhash' => 'cdd73b9c69267d12f51c76a32ab4af8e',
                    'host_substr' => '10.9.8',
                ],
            ],
        ];
    }
}
