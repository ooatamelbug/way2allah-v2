<?php

namespace App\Domain\Identity\Services;

/**
 * Reproduces admincp/index.php's password-format detection (bcrypt / MD5 /
 * SHA1), used only to verify an existing legacy hash on an admin's *next*
 * successful login before AdminGuard rehashes it to bcrypt (ADR-0011).
 *
 * Deliberately does NOT reproduce the legacy code's fourth branch — a
 * plaintext-equality fallback for any value that isn't recognized as
 * bcrypt/MD5/SHA1 (Blueprint v1.0 §16 item 3: "Laravel side never has this
 * fallback at all"). An unrecognized stored-hash shape is always rejected.
 */
class LegacyPasswordVerifier
{
    public function verify(string $plainPassword, string $storedHash): bool
    {
        if ($this->isBcrypt($storedHash)) {
            return password_verify($plainPassword, $storedHash);
        }

        if ($this->isMd5($storedHash)) {
            return hash_equals(strtolower($storedHash), md5($plainPassword));
        }

        if ($this->isSha1($storedHash)) {
            return hash_equals(strtolower($storedHash), sha1($plainPassword));
        }

        // No plaintext fallback — an unrecognized format is always rejected.
        return false;
    }

    public function isBcrypt(string $hash): bool
    {
        return str_starts_with($hash, '$2y$') || str_starts_with($hash, '$2a$');
    }

    public function isMd5(string $hash): bool
    {
        return (bool) preg_match('/^[a-f0-9]{32}$/i', $hash);
    }

    public function isSha1(string $hash): bool
    {
        return (bool) preg_match('/^[a-f0-9]{40}$/i', $hash);
    }
}
