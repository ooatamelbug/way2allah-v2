<?php

namespace App\Domain\Admin\Support;

use App\Domain\Admin\Models\AdminUser;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * OLD Admin Migration Phase 0 — resolves record-scoped administration
 * authorization for migrated admin features.
 *
 * Decision order, deliberately identical to `EnsureAdminHasPermission`'s
 * so the two layers never disagree about an admin's standing:
 *
 *   1. super-admin -> allowed, no further checks (mirrors `radminsuper=1`'s
 *      legacy full-access behavior, and matches the middleware exactly);
 *   2. the Spatie permission must be held on the `admin` guard;
 *   3. every registered `AdminRecordConstraint` that claims the
 *      permission/record pair must permit it.
 *
 * Step 3's list is **empty, by design** — see `AdminRecordConstraint`'s
 * docblock. Decision D1b' is closed: Mirror authorization is flat, so no
 * Mirror constraint is registered now or later. With no constraints this
 * class is exactly step 1 + step 2 applied to a record-aware call site,
 * which is the whole of Phase 1's authorization requirement. The `$record`
 * parameter stays in the signature so a future record-scoped rule — if the
 * business ever asks for one — needs no call-site change.
 *
 * Registered as a singleton by `AdminServiceProvider` so the constraint
 * list has one definition per request.
 */
class AdminRecordAuthorizer
{
    /** @var list<AdminRecordConstraint> */
    private array $constraints;

    /**
     * @param  list<AdminRecordConstraint>  $constraints  Empty in Phase 0 — see the class docblock.
     */
    public function __construct(array $constraints = [])
    {
        $this->constraints = $constraints;
    }

    /**
     * True when $admin may perform $permission, optionally against $record.
     *
     * $record is nullable so a call site that has no record yet (an insert,
     * before the row exists) uses the same entry point as one that does,
     * rather than branching between two authorization mechanisms.
     */
    public function allows(AdminUser $admin, string $permission, ?object $record = null): bool
    {
        if ($admin->hasRole('super-admin')) {
            return true;
        }

        if (! $admin->hasPermissionTo($permission, 'admin')) {
            return false;
        }

        if ($record === null) {
            return true;
        }

        foreach ($this->constraints as $constraint) {
            if ($constraint->appliesTo($permission, $record)
                && ! $constraint->permits($admin, $permission, $record)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Throws rather than returning false, for call sites that should abort.
     * `AuthorizationException` renders as 403, matching
     * `EnsureAdminHasPermission`'s own `abort(403)`.
     *
     * @throws AuthorizationException
     */
    public function authorize(AdminUser $admin, string $permission, ?object $record = null): void
    {
        if (! $this->allows($admin, $permission, $record)) {
            throw new AuthorizationException;
        }
    }

    /** @return list<AdminRecordConstraint> */
    public function constraints(): array
    {
        return $this->constraints;
    }
}
