# Wave 5 Permission Model — Ratification Proposal

**Date:** 2026-08-07. Responds to `wave-5-verification-review.md` Findings 1-3, per your decision: **keep page-level permission enforcement, reclassify it as a deliberate architectural hardening rather than a port of legacy behavior, and bring every affected document and route into agreement with that.**

**Status: proposal only. Nothing below has been applied yet.** No code, route, or documentation file has been changed. This lays out the exact edits for your approval before anything is written.

---

## 1. Decision-log correction (Finding 1)

This project's standing convention is "visible correction, never silent rewrite" — decision-log entries are an append-only chronological record, so entry #9 is not edited in place. Instead: a new entry (#10) records the corrected decision, and a one-line addendum on #9 itself points forward to it.

### 1a. New entry — `docs/decision-log.md` #10

```markdown
## 10. Wave 5 per-permission route enforcement ratified as a deliberate hardening — corrects entry #9's "mechanical translation" framing

- **Decision:** The `admin.permission:{module}.{key}` middleware enforcement added on every Wave 5 route is **kept as-is**, and is reclassified: it is a deliberate security hardening introduced during migration, not a reproduction of an existing legacy access-control mechanism. Legacy `admincp/` never enforced `nuke_authors.permissions` at the page or action level anywhere — confirmed by an exhaustive grep of every Wave-5-relevant feature directory (`wave-5-verification-review.md` Finding 1): the only code that reads the permissions array is `sidebar.php` (nav-link *visibility* only, at the whole-module granularity) and the permission-editor itself (`edit_author.php`, which writes another admin's stored grid — it does not check the acting admin's own access). Every legacy feature page sits behind nothing but `main.php`'s single "any logged-in admin" gate.
- **Reason:** Blueprint §16's security-remediation direction and ADR-0010's "can be replaced" latitude for confirmed-weak legacy mechanisms both support closing this gap. Real per-admin permission data already exists in `nuke_authors.permissions` and is worth making load-bearing rather than cosmetic — but that is a new decision, made now, with its own justification, not an inherited requirement.
- **Alternatives considered:** Revert to nav-visibility-only parity with legacy (rejected — would mean building real Spatie permission plumbing, a real permission-editor UI, and then deliberately not enforcing any of it at the point that matters, which has no defensible rationale once named explicitly); leave the stricter behavior in place but undocumented (rejected — this is exactly the silent-assumption problem the verification review exists to catch).
- **Trade-offs:** Every Wave 5 route is now strictly more restrictive than its legacy counterpart ever was. Accepted consequence, not an oversight: an admin who previously could reach any admincp page by URL, permissions blob notwithstanding, now genuinely cannot without the matching grant. `AdminPermissionSeeder`'s existing 27 permissions (10 modules) need real, correct assignment to every admin who currently expects unrestricted access, since the previous de facto behavior (any login = any page) no longer holds anywhere in the Laravel admin.
- **Impact:** No code changes required by this decision alone — see §2/§3 below for the two follow-on fixes it does require for internal consistency. `docs/decision-log.md` #9 gets a forward-pointing addendum (not rewritten). `00-master-migration-blueprint.md` §9 and §16 get a clarifying line (see §1c below).
- **Confidence:** High — this is a direct decision you made after reviewing the evidence, not an inference.
```

### 1b. Addendum on the existing entry — `docs/decision-log.md` #9

Appended directly beneath #9's existing `**Confidence:**` line, not replacing it:

```markdown
- **Correction (2026-08-07, see entry #10):** the "Confidence: High — direct, mechanical translation of already-real, already-confirmed-functional legacy authorization data" claim above is wrong on one specific point, caught during Wave 5 verification review: `sidebar.php`'s check is real, but it only ever gated nav-link *visibility*, never page-level access — no legacy admincp page enforces the permissions array at all. The permission *data model* (namespaced `{module}.{key}` rows) is still a faithful translation of the `menu.php` authorization arrays; only the *enforcement* built on top of it in Wave 5 is new. Entry #10 ratifies that enforcement as an intentional hardening rather than retracting it.
```

### 1c. Blueprint clarification — `00-master-migration-blueprint.md`

**§9 (Authentication & Authorization)**, append one line after the existing "Authorization: Spatie Laravel-Permission..." bullet:

```markdown
- Admin per-permission route enforcement (Wave 5) is a deliberate hardening beyond legacy's own behavior — legacy's `nuke_authors.permissions` blob only ever controlled admin-panel nav-link visibility, never page-level access (decision-log #10).
```

**§16 (Security Remediation table)**, new row appended (numbered 12, after the existing 11):

```markdown
| 12 | `admincp/`'s permissions blob never enforced page-level access, only nav visibility | **Fixed in Wave 5** | Laravel's `admin.permission:{module}.{key}` middleware enforces real per-route authorization; decision-log #10 |
```

**Revision history**: a third 2026-08-07 entry, per the Blueprint's own "no silent edit" governance rule (every post-freeze amendment needs a dated entry) — exact wording to match the existing two 2026-08-06 entries' style once you approve.

---

## 2. Finding 2 fix — minimal route change so every grantable permission is reachable through the UI

No middleware or controller code changes needed. `EnsureAdminHasPermission` already accepts multiple permission names and checks `hasAnyPermission()` (`app/Domain/Admin/Http/Middleware/EnsureAdminHasPermission.php:33-42`) — the questionnaire routes already use this exact pattern (`routes/admin.php:91`, gated on `questionnaire.listallquest,questionnaire.listquest`). Applying the same pattern to `locations` and `chat` is a same-file, same-shape route change, not a new mechanism.

**`locations` (`routes/admin.php:75-86`) — proposed:**

```php
Route::prefix('locations')->name('locations.')->group(function () {
    Route::middleware(['admin.permission:locations.add_location,locations.del_location'])->group(function () {
        Route::get('/', [LocationsController::class, 'index'])->name('index');
        Route::get('/{location}/edit', [LocationsController::class, 'edit'])->name('edit');
    });
    Route::middleware(['admin.permission:locations.add_location'])->group(function () {
        Route::get('/create', [LocationsController::class, 'create'])->name('create');
        Route::post('/', [LocationsController::class, 'store'])->name('store');
        Route::put('/{location}', [LocationsController::class, 'update'])->name('update');
    });
    Route::middleware(['admin.permission:locations.del_location'])->group(function () {
        Route::delete('/{location}', [LocationsController::class, 'destroy'])->name('destroy');
    });
});
```

`index`/`edit` (view-only) open to either permission; `create`/`store`/`update` stay behind `add_location` specifically; `destroy` stays behind `del_location` specifically. A `del_location`-only admin can now reach the list and find something to delete; an `add_location`-only admin's access is completely unchanged.

**`chat` (`routes/admin.php:102-116`) — proposed:**

```php
Route::prefix('chat')->name('chat.')->group(function () {
    Route::middleware(['admin.permission:chat.listrooms,chat.editroom'])->group(function () {
        Route::get('/', [ChatRoomAdminController::class, 'index'])->name('index');
        Route::get('/{room}', [ChatRoomAdminController::class, 'edit'])->name('edit');
    });
    Route::middleware(['admin.permission:chat.editroom'])->group(function () {
        Route::put('/{room}', [ChatRoomAdminController::class, 'update'])->name('update');
        Route::delete('/{room}/owner/{username}', [ChatRoomAdminController::class, 'removeOwner'])->name('owner.destroy');
        Route::delete('/{room}/speaker/{username}', [ChatRoomAdminController::class, 'removeSpeaker'])->name('speaker.destroy');
    });
});
```

`index`/`edit` (view-only) open to either permission; the three mutating actions stay behind `editroom` specifically. An `editroom`-only admin can now reach the edit form they're authorized to submit; a `listrooms`-only admin's access is completely unchanged (view, no mutation — same as today).

The stale `// listrooms gates the directory + view; editroom gates the real (rebuilt, IF-036) edit capability` comment at `routes/admin.php:102-105` gets rewritten as part of this same edit (it's already flagged separately as Finding 9 — folding it in here avoids touching this block twice).

**Tests:** each existing permission-gate test in `SoundcloudYoutubeLocationsTest.php` and `ChatRoomAdminControllerTest.php` needs one new case per controller — "the other single permission can still reach the view route" — plus confirmation the existing "no permission → 403" and "mutation still requires the specific permission" cases still pass unchanged.

---

## 3. Finding 3 — ratify, don't change

No behavior change proposed. `PermissionController`'s existing super-admin-only restriction is already consistent with the entry #10 hardening decision — it just wasn't raised as one at the time it was written; it was self-justified in a route-file comment instead. Proposed fix is purely documentary: entry #10 above (§1a) explicitly covers it, and the existing comment at `routes/admin.php:50-56` gets one additional line added — "Ratified under decision-log #10, not just this comment" — pointing back to the real record instead of being the only justification for the restriction.

---

## 4. Deferred — Findings 4-9

Unchanged from the verification review; not addressed by this proposal. To be handled as their own pass once this permission-model reconciliation is approved and applied:

- Finding 4 — `thumb` not set on new admin accounts.
- Finding 5 — chat-room list missing status icons.
- Finding 6 — 4-site duplicated vBulletin-lookup logic (decision-log #2's revisit trigger).
- Finding 7 — `chat/menu.php`'s internal label contradiction (documentation note only).
- Finding 8 — decision-log #9's stale permission count (25/9 → 27/10) — can be folded into the same edit as the #10 addendum above, since it's touching #9 either way, if you'd like.
- Finding 9 — stale "IF-036" route comment — already folded into §2's `chat` route edit above, since that block is being touched anyway.

---

## Summary of what would change, once approved

| File | Change |
|---|---|
| `docs/decision-log.md` | New entry #10 (full ratification decision); one-line addendum on #9 |
| `00-master-migration-blueprint.md` | One clarifying line in §9; one new row in §16's table; one revision-history entry |
| `routes/admin.php` | `locations` group split 3 ways instead of 2; `chat` group split so view routes accept either permission; stale IF-036 comment rewritten |
| `tests/Feature/Admin/SoundcloudYoutubeLocationsTest.php` | +1 test (single-permission view-reachability) |
| `tests/Feature/Admin/ChatRoomAdminControllerTest.php` | +1 test (single-permission view-reachability) |

No controller, model, or middleware code changes. No new findings. Findings 4-9 untouched, explicitly deferred per your instruction.

Awaiting your approval before implementing any of this.
