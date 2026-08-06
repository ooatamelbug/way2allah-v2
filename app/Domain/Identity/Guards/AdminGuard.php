<?php

namespace App\Domain\Identity\Guards;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Identity\Services\LegacyPasswordVerifier;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;

/**
 * Admin-panel authentication (ADR-0011, Blueprint v1.0 §9), backed by
 * `nuke_authors` — deliberately not unified with the public-site Guard.
 *
 * New/changed passwords are always stored as bcrypt. An existing legacy hash
 * (MD5 or SHA1, per LegacyPasswordVerifier) is verified once, then
 * transparently rehashed to bcrypt on that successful login — no forced mass
 * password reset, natural convergence to bcrypt over time. This rehash is
 * the one legitimate write this Guard performs against `nuke_authors`.
 */
class AdminGuard implements StatefulGuard
{
    use GuardHelpers;

    private const SESSION_KEY = 'admin_auth_id';

    public function __construct(
        private readonly Request $request,
        private readonly LegacyPasswordVerifier $passwordVerifier,
    ) {}

    public function user(): ?AdminUser
    {
        // `instanceof AdminUser`, not `!== null` — GuardHelpers' $user
        // property is typed ?Authenticatable; the instanceof check narrows
        // it back to AdminUser within this branch so the early return
        // satisfies this method's own ?AdminUser type (decision #3).
        if ($this->user instanceof AdminUser) {
            return $this->user;
        }

        $id = $this->request->session()->get(self::SESSION_KEY);

        $admin = $id === null ? null : AdminUser::on('main')->find($id);
        $this->user = $admin;

        return $admin;
    }

    public function validate(array $credentials = []): bool
    {
        return $this->resolveValidAdmin($credentials) !== null;
    }

    public function attempt(array $credentials = [], $remember = false): bool
    {
        $admin = $this->resolveValidAdmin($credentials);

        if ($admin === null) {
            return false;
        }

        $this->rehashIfNeeded($admin, (string) $credentials['password']);
        $this->login($admin, $remember);

        return true;
    }

    public function once(array $credentials = []): bool
    {
        $admin = $this->resolveValidAdmin($credentials);

        if ($admin === null) {
            return false;
        }

        $this->setUser($admin);

        return true;
    }

    public function login(Authenticatable $user, $remember = false): void
    {
        $this->request->session()->put(self::SESSION_KEY, $user->getAuthIdentifier());
        $this->request->session()->regenerate();
        $this->setUser($user);
    }

    public function loginUsingId($id, $remember = false): AdminUser|false
    {
        $admin = AdminUser::on('main')->find($id);

        if ($admin === null) {
            return false;
        }

        $this->login($admin, $remember);

        return $admin;
    }

    public function onceUsingId($id): AdminUser|false
    {
        $admin = AdminUser::on('main')->find($id);

        if ($admin === null) {
            return false;
        }

        $this->setUser($admin);

        return $admin;
    }

    public function viaRemember(): bool
    {
        // No cookie-based long-lived admin session in the legacy behavior
        // being preserved (§9) — remember-me is a new capability, not part
        // of this Guard's scope.
        return false;
    }

    public function logout(): void
    {
        $this->request->session()->forget(self::SESSION_KEY);
        $this->request->session()->regenerate();
        $this->user = null;
    }

    private function resolveValidAdmin(array $credentials): ?AdminUser
    {
        if (
            ! isset($credentials['aid'], $credentials['password'])
            || ! is_string($credentials['aid'])
            || ! is_string($credentials['password'])
        ) {
            return null;
        }

        $admin = AdminUser::on('main')->where('aid', $credentials['aid'])->first();

        if ($admin === null) {
            return null;
        }

        $stored = $admin->currentStoredPassword();

        if ($stored === '' || ! $this->passwordVerifier->verify($credentials['password'], $stored)) {
            return null;
        }

        return $admin;
    }

    private function rehashIfNeeded(AdminUser $admin, string $plainPassword): void
    {
        if ($this->passwordVerifier->isBcrypt($admin->currentStoredPassword())) {
            return; // Already bcrypt — nothing to converge.
        }

        $bcryptHash = password_hash($plainPassword, PASSWORD_BCRYPT);

        $admin->forceFill(['password' => $bcryptHash])->save();
    }
}
