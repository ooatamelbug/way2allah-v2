<?php

namespace App\Domain\Admin\Support;

use App\Domain\Admin\Models\AdminUser;

/**
 * OLD Admin Migration Phase 0 — the extension point for record-scoped
 * administration authorization.
 *
 * `EnsureAdminHasPermission` answers "may this admin use this feature at
 * all", which is route-shaped and sufficient for every permission seeded
 * before Phase 0. Mirror Management (Phase 1) is the first unit where the
 * answer can also depend on *which record* is being acted on, and Laravel
 * had no layer for that — so this is the smallest thing that gives Phase 1
 * somewhere to put such a rule.
 *
 * It is deliberately NOT a policy framework. There is one interface, one
 * resolver (`AdminRecordAuthorizer`), and **zero registered
 * implementations**.
 *
 * **No Mirror constraint will be registered.** Decision D1b' is closed
 * (owner, 2026-10-12): OLD's Khotab audio-vs-video moderator division is
 * **not** reproduced. Mirror authorization is flat — `khotab.addmirror`,
 * `khotab.editmirror`, `khotab.delmirror` and the three `anasheed.*`
 * equivalents — with no `audio`/`vedio` record constraint, no `AvAdmin()`
 * recreation and no runtime `nuke_*_mod` dependency. If the business later
 * wants media-type-separated moderation, it arrives as a new Laravel
 * authorization feature, explicitly chosen, rather than inherited
 * implicitly from OLD.
 *
 * This extension point therefore stays available but unused. That is not
 * dead code waiting on a decision — the decision is made; it is the seam
 * a *future* record-scoped rule would use, and it already carries Phase 1
 * at zero cost because `AdminRecordAuthorizer` with an empty list is
 * exactly super-admin + permission.
 *
 * Any future implementation must not read `nuke_islamic_mod`,
 * `nuke_anasheed_mod` or any other legacy moderator grant table at
 * runtime. Those stores are historical evidence only; Laravel/Spatie is
 * the sole runtime authority.
 */
interface AdminRecordConstraint
{
    /**
     * Does this constraint have an opinion about this permission/record pair?
     * Returning false means "not my business" — never "denied".
     */
    public function appliesTo(string $permission, object $record): bool;

    /**
     * Only consulted when appliesTo() is true. Returning false denies.
     */
    public function permits(AdminUser $admin, string $permission, object $record): bool;
}
