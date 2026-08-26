# `/admincp/` Login + Dashboard Completion — Status Record

**Date:** 2026-08-22. **Status: implemented, closes the sole confirmed operational gap from the prior admin-migration audit.** Full reasoning, alternatives, and evidence: `docs/decision-log.md` entry #22. This document is the durable "current status" record other documentation should point to; the decision-log entry is the durable "why."

## History (preserved, not rewritten)

1. **Wave 5** (see this project's earlier decision-log entries and `routes/admin.php`'s own comments) migrated 11 admin feature areas — surveys, permission editor, SoundCloud, YouTube, locations, questionnaire, broadcasting's working half, chat's working half, upload-team tracking, link-quality/repair tooling, and staff management — each fully permission-gated and tested. Building a login page or dashboard was **explicitly excluded** from that wave's 10 tasks, recorded directly in `resources/views/layouts/admin.blade.php`'s own comment at the time: *"No dashboard/logout nav here — building a real login/logout flow is outside this wave's 10 tasks... nothing in the Roadmap asks for a new UI around it this round."*
2. **`admincp/` Full Migration & Route-Family Reconciliation Audit** (2026-08-22, same day, prior task in this series) independently investigated the symptom `GET /admincp/` → 404. It found: the admin route family was otherwise complete and internally consistent (zero broken links, zero missing authorization middleware across all 51 registered routes); the 404 traced directly to the absence of any route for the bare `/admincp/` root; and that absence was the *documented, deliberate* consequence of the Wave 5 exclusion above, not an implementation oversight. The audit explicitly declined to build a login/dashboard without owner sign-off, classifying the admin migration `ADMIN_MIGRATION_PARTIALLY_COMPLETE` with the login/dashboard item marked `BUSINESS_DECISION_REQUIRED`.
3. **Owner decision** (2026-08-22): approved implementation, explicitly superseding the Wave 5 exclusion for this one capability only. Recorded in `docs/decision-log.md` entry #22.
4. **This completion** (2026-08-22): implemented per the owner's decision.

## Current State (supersedes the audit's `PARTIALLY_COMPLETE` classification for this item)

- `GET /admincp/` (and `/admincp`, no trailing slash) — 200, real login page (anonymous) or real dashboard (authenticated), reusing the existing `AdminGuard` architecture unchanged.
- `POST /admincp/login` — real credential verification via `AdminGuard::attempt()`, no new password-verification logic.
- `POST /admincp/logout` — real session invalidation, POST-only.
- Dashboard content: a permission-filtered list of the 9 already-migrated feature areas that have a real, linkable index route (`AdminDashboardModules`, derived directly from `routes/admin.php`'s own registrations — not the permission editor or broadcasting, see decision-log #22 for why those two are excluded, deliberately, not by oversight).
- The previously-flagged navigation orphan (`admin.link-quality.khotab.large-files`) now has a real in-view link.
- All 51 pre-existing feature routes, their controllers, their authorization middleware, and their business logic are **unchanged**.

## What Remains Genuinely Open

- No known admin capability gap remains. **Update (2026-08-22):** `Broadcasting`'s missing dashboard-linkable index route (originally flagged here) was investigated and closed the same day — see `docs/decision-log.md` entry #23. Legacy `broadcasting/index.php`'s `op=editstream` branch was found to be real, functional, source-reconstructable behavior (not dead code, merely un-linked from legacy's own sidebar nav), so `BroadcastingController::index()` was built and `admin.broadcasting.index` now has a real dashboard entry. `op=addstream` (permanently dead in legacy) and `delete_stream.php` (dead on its first line) remain deliberately unported.
- See the audit's own capability inventory (§4-§5 of its report) for the full accounting of what legacy `admincp/` offered and what was/wasn't ported, all of which otherwise stands unchanged by this completion.

## Superseded Statements

Any earlier statement (in this project's own reports, this session's chat history, or code comments) that:
- `/admincp/` intentionally remains 404, or
- login/dashboard UI is out of scope, or
- the admin migration is `PARTIALLY_COMPLETE` solely because of this gap,

is **superseded by this record** as of 2026-08-22. The underlying historical facts (Wave 5's original exclusion, the audit's confirmation of it) remain true and are preserved above, not deleted — only the *current* status has changed.

**Further update (2026-08-22, later the same day):** this document's own scope is the *functional* login/dashboard/Broadcasting closure — it does not cover AdminCP's separate visual/page-level parity work, which continued after this document was written (shared shell reconstruction, page-level portlet parity, Permissions profile/stats, Locations map, Staff phone/Facebook) and is tracked in `docs/reviews/admincp-visual-parity-reconstruction.md` and `docs/decision-log.md` entries #24-28. The final, current-as-of-today authoritative status for AdminCP as a whole is recorded in `docs/decision-log.md`'s "AdminCP Closure — Final Authoritative State" note (immediately after entry #28): `ADMIN_MIGRATION_COMPLETE — FUNCTIONAL_AND_VISUAL_PARITY_RECONCILED`. This document's own findings remain accurate and are not superseded in substance — only cross-referenced forward so a reader landing here first knows a fuller closure exists.
