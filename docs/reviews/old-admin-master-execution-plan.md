# OLD Administration Migration — Master Execution Plan

**Status:** PLANNING / RECONCILIATION. No implementation, no deployment, no commit, no production queries.

> **Final status (2026-10-15) — supersedes the status line above:** **WHOLE MIGRATION — CLOSED / LOCALLY VERIFIED** ·
> **KNOWN MIGRATION-OWNED FUNCTIONAL GAPS — 0** · **PRODUCTION DEPLOYMENT — NOT STARTED.** **§30 is the final migration
> closure record**; Definition of Done status per item: §7.1. Earlier status statements below are historical.
>
> **Reconciliation note (2026-11-06, decision-log #100, R4):** the 2026-10-15 statement above is historical. After it,
> the owner rule of 2026-10-21 drove module-by-module OLD public admin-control reviews (§31–§53, decision-log #60–#82),
> the closure audit (§54) and visitor-parity work (#85–#99). Current Admin status: local functional closure accepted
> by the owner (#100), one disclosed AdminCP item pending disposition (#41 backup group); remaining work: §54 register;
> retirement checklist: §55. Production deployment NOT STARTED.
>
> **Update (2026-11-06, decision-log #101):** **ADMIN CONTROLS — FUNCTIONALLY COMPLETE LOCALLY — WITH OWNER-APPROVED LOW
> DEFERRALS.** The backup group is dispositioned (Backup Booking Monitor deferred LOW). Admin status: §56.
**Date:** 2026-10-12
**Supersedes for sequencing:** Pass 3C §15 and Pass 3D §10 rankings. Their findings stand; this document replaces their execution order with one plan covering every family.

**Architecture premise, per owner directive — binding on everything below:**

> Laravel's Admin authentication plus the existing Spatie role/permission architecture **is the future and
> sole runtime authorization authority** for migrated administration. The OLD authorization engines —
> `GreenZone()`, `AvAdmin()` and `nuke_*_mod` as a runtime permission store — are **not migrated**.
> Legacy permission data in `nuke_*_mod` and `nuke_authors.permissions` is **evidence of historical
> capability design only**. Legacy grant assignments are **not** imported, unioned, intersected or
> synchronized. Legacy administrator identities are **not** investigated. The Mirror permission
> investigation does not expand further unless an implementation exposes a concrete blocker.

### Plan corrections applied 2026-10-12 (owner directive)

| # | Correction | Effect |
|---|---|---|
| 1 | No legacy permission assignments are migrated from `nuke_*_mod` or `nuke_authors.permissions` | **D1a is withdrawn — it is no longer a blocker or an owner decision.** Permissions are defined fresh in Laravel and assigned through Laravel's existing administration model |
| 2 | `anasheed.addmirror` / `.editmirror` / `.delmirror` accepted; Khotab keeps `khotab.*` | **D2 is decided.** No longer coupled to D1a. No `audio`/`vedio` dimension for Anasheed |
| 3 | Khotab `audio`/`vedio` is preserved as a **business-behavior consideration**, designed in Laravel's authorization architecture — **not** by porting `AvAdmin()` or reading `nuke_islamic_mod` at runtime | **D1b is reframed.** Only one narrow product-policy question remains (§6.1) |
| 4 | Re-parent uses `khotab.editmirror` | **D4 is decided.** No `khotab.movemirror` unless a later explicit requirement asks for it |
| 5 | Phase 0 does **not** "fix OLD's five unauthorized operations" | Phase 0's purpose is restated: **new Laravel endpoints are consistently authorized and audited.** OLD authorization is not modified |
| 6 | Phase 0 stays **minimal** — no speculative framework, no abstractions for hypothetical modules | Scope reduced to the minimum reusable infrastructure the known phases actually require |
| 7 | Legacy-log coexistence is a **bounded Phase 0 implementation-design decision**, not a plan blocker | **D5a is downgraded; D5b is answered** — the authoritative new trail is structured and must not reproduce raw-URL/HTML leakage or the target-attribution defects |
| 8 | Khotab/Telawah Item CRUD stays **EXTERNALLY_OWNED** per the reconciled BC#6 result | §6.3's flagged ambiguity is **closed**, reclassifiable later only if business ownership changes |
| 9 | `JoinMe` / `BackUp` paths remain out of scope | Recorded as **legacy residual risks** and carried into the **OLD retirement checklist** (§7 DoD item 5a) so OLD cannot be retired while required workflows depend on them |

**Net effect: Phase 0 has no remaining owner-decision blockers and is ready to implement.**

---

## 1. Executive status

### 1.1 What is already understood

The OLD administration workstream has completed **eleven** analysis passes, and the discovery phase is **finished**:

| Pass | Established |
|---|---|
| Forensic discovery | Three-generation architecture; the auth bridge; the inline delegation mechanism |
| Business Confirmation #6 reconciliation | #6 said content administration is `EXTERNALLY_OWNED` / out-of-scope — it **never retired** anything. `decision-log.md:521`'s `INTENTIONALLY_RETIRED_CAPABILITY` label remains unsupported. Item CRUD for Khotab/Telawah is **closed as `EXTERNALLY_OWNED`** (correction 8) |
| Scope reset | Boundaries of the administration workstream |
| Pass 2A — Khotab inline | 21 guards, 2 renderers, 45 ops in one public context |
| Pass 3A — Advfiles | 11 operations analysed; under-counted the gen-2 surface (corrected in 3B) |
| Pass 3B — inline dependency map | 120 `$inlineAdminUrl` refs → 76 tokens / 78 ops / 8 workflow units |
| Pass 3C — contextual surface | **151 distinct contextual operations, 17 capability families.** 3B's map covered 41% |
| Pass 3D — ownership reconciliation | Locations family **dissolved**; link "repair" **withdrawn** (never had a handler); mirror family confirmed OLD-exclusive and re-scoped |
| Pass 3E.1 — mirror data contract | **12 CRUD invariants**; scope narrowed to 7 ops / 2 domains on production evidence |
| Pass 3E.2 — auth/audit contract | `AvAdmin()` is live; both permission stores populated; audit trail active (253,499 rows) |
| Pass 3E.3 — decision matrix | 5 decisions framed; 5 engineering defaults, 4 requiring owner approval |

The separate **public-site** migration is already `MIGRATION_COMPLETE` (decision-log #39). This workstream is the administration remainder.

### 1.2 What remains

**We are now primarily in implementation, not discovery.** Of 17 families: 3 are already migrated, 7 fall outside the BC#6 boundary or are dead, and **7 are implementable** — of which **5 are analysed to a level sufficient to begin** and 3 carry business gates.

Only **two** genuinely unresolved investigation items remain, and both are small, named, and attached to a specific phase (§3, Phases 3 and 7). **There is no further broad source archaeology to do.**

**After the 2026-10-12 corrections, Phase 0 has no owner-decision blockers and is ready to implement.** The remaining business gates affect only Phases 5–7 and the Telawah disposition.

### 1.3 The honest shape of the remainder

| | Operations | Note |
|---|---:|---|
| Implementable and unblocked after owner decisions | **~22** | Phases 0–4 |
| Implementable but business-gated | **~41** | Phases 5–7; may shrink to 0 if the owner declines |
| Outside scope (BC#6 / dead / already migrated) | **~88** | §5 |

So the **committed** surface is roughly 22 operations plus one foundation phase. The gated surface is twice that and may not be built at all.

---

## 2. Capability disposition matrix

One classification per family. Items already conclusively classified are **not reopened**; the single genuine document conflict found is noted in §6.

| # | Capability family | Disposition | Phase | Basis |
|---|---|---|---|---|
| 1 | **Mirror & link management** — Khotab + Anasheed CRUD and re-parent | **IMPLEMENT_IN_LARAVEL** | **1** | 3E.1 (12 invariants), 3E.2 (auth/audit), 3E.3 (decisions). No Laravel mirror mutation exists anywhere |
| 1a | Mirror management — **Telawah** | **DEFERRED / BUSINESS_DECISION** | — | 3E.1 §0.4: **1 production row**, no quality/backup columns, no parent counters, no FK index, permission and audit calls commented out. Retire-or-build is a product question |
| 1b | Mirror management — **W2ACD normalized** | **DEAD / DO_NOT_MIGRATE** | — | 3E.1 §0.4.1: table **exactly empty**, counters **never written**, `W2ACDInsertMirror` never exercised in production |
| 2 | **Link-size measurement, quality metric, mismatch triage, size correction** | **ALREADY_MIGRATED** | — | 3D §4: `LinkQualityStatsController`, `LinkSizeChecker`, 3 `Recheck*` jobs, `RecomputeLinkQualityPercentAction`. Migrated from gen-2 AdminCP, not OLD |
| 2a | **`down`/`trial` archive lifecycle** | **ALREADY_MIGRATED** | — | 3D §2.3: `BackupApiController` owns both ends across 5 content tables |
| 2b | **`down = 0` lost-link worklist report** | **IMPLEMENT_IN_LARAVEL** | **2** | 3D §2.5: `BEHAVIOR_MIGRATED / REPORTING_GAP`. Laravel writes the data, surfaces no report |
| 2c | **`KhotabLostedLink` repair form** | **DEAD / DO_NOT_MIGRATE** | — | 3D §2.2: `op=KhotabRepairLostedLink` has **no handler anywhere**, and its `id` field interpolates an undefined variable. There is no OLD behavior to migrate |
| 2d | **Contextual access to recheck / fixSize from the item page** | **IMPLEMENT_IN_LARAVEL** | **2** | 3D §5: `BEHAVIOR_MIGRATED / CONTEXTUAL_UX_GAP`. Pure UX integration over existing actions |
| 3 | **Location entity CRUD** | **ALREADY_MIGRATED** | — | 3D §3.4: `LocationsController`, 6 routes, permission-gated |
| 3a | **"Locations contextual administration"** | **FAMILY DISSOLVED** | — | 3D §3: none of its 6 ops is a Locations capability; they de-duplicate into mirror, Advfiles and category families. **Zero OLD op names a location** |
| 4 | **OLD runtime authorization engines** — `GreenZone()`, **`AvAdmin()`**, `nuke_*_mod` as a permission store | **RETIRED** | — | **Owner directive.** Laravel + Spatie is the sole runtime authority. `nuke_*_mod` and `nuke_authors.permissions` are historical evidence only |
| 4a | **Moderator grant *assignment* mechanism** | **ALREADY_MIGRATED** | — | `AdminStaffController`, `PermissionController`. Assignment is managed in Laravel. **Legacy grant data is not imported** (correction 1) — `SyncLegacyAdminPermissionsAction` is left untouched and is not extended to mirror capabilities |
| 4b | **Content-admin permission vocabulary** (mirror capability names) | **IMPLEMENT_IN_LARAVEL** | **0** | 3E.2 §1.6: no mirror permission exists among Laravel's 29 seeded names. **Decided:** `khotab.{add,edit,del}mirror` + `anasheed.{add,edit,del}mirror`; re-parent reuses `khotab.editmirror` |
| 4c | **Khotab `audio` / `vedio` media distinction** | **IMPLEMENT_IN_LARAVEL** (as business behavior) | **0 / 1** | 3E.2 §1.2 discovered it as live legacy behavior. **Designed in Laravel's authorization architecture — `AvAdmin()` and `nuke_islamic_mod` are NOT preserved as runtime mechanisms** (correction 3). One narrow product question remains: §6.1 |
| 4d | **Moderator action-log view** (`*ModertorsLogs`) | **IMPLEMENT_IN_LARAVEL** | **2** | 3C §10, 3E.2 E4: the log is active (253,499 rows); Laravel has no reader |
| 5 | **Administration audit trail** | **IMPLEMENT_IN_LARAVEL** | **0** | 3E.2 §8: a migration requirement — 87,547 `add_mirror` rows, latest 2026-10-12, and Laravel has no audit facility. **The authoritative trail is a new structured Laravel trail**, not a port of the legacy shape. `nuke_islamic_logs` compatibility writing is a bounded Phase 0 design decision |
| 6 | **Advfiles** (OA-1) | **DEFERRED / BUSINESS_DECISION** → **BUSINESS APPROVED 2026-10-14, IMPLEMENTED (§24)** | **5** | Pass 3A analysed; usage/business uncertainty **retained per owner instruction**. 3D §3.1 added a confirmed second call site |
| 7 | **Category assignment** (OA-3) | **IMPLEMENT_IN_LARAVEL** | **3** | 3D §10 rank 3. Needs re-specification (small prerequisite, §3 Phase 3) |
| 8 | **Item CRUD** — all content domains, **Khotab and Telawah included** | **EXTERNALLY_OWNED** | — | Reconciled BC#6 result, **closed per correction 8**. Reclassifiable later only if business ownership changes |
| 9 | **Content structure** (author / group / series) | **EXTERNALLY_OWNED** | — | BC#6 boundary |
| 10 | **Ordering / weight** | **EXTERNALLY_OWNED** | — | BC#6 boundary — content curation |
| 11 | **Document attachments** (PDF / DOCX) | **EXTERNALLY_OWNED** | — | BC#6 boundary |
| 12 | **Banner lifecycle** (admin half) | **EXTERNALLY_OWNED** | — | BC#6 boundary |
| 13 | **Moderation queue** (`*WaitingList`) | **EXTERNALLY_OWNED** | — | BC#6 boundary |
| 14 | **Counter repair** (`*Count`) | **DEAD / DO_NOT_MIGRATE** | — | Superseded by 3E.1 invariant **I-2**: the relation is the only authoritative count, so a manual repair operation has no purpose. 3D §12 item 9 already rated business impact low |
| 15 | **Fatwa Q&A administration** | **EXTERNALLY_OWNED** | — | BC#6 boundary |
| 16 | **Survey administration** | **PARTIALLY MIGRATED → IMPLEMENT_IN_LARAVEL** | **4** | 3C §5: `SurveyController` exists; category and comment operations are not covered |
| 17 | **W2ACD / CD distribution administration** | **DEFERRED / BUSINESS_DECISION** → **BUSINESS APPROVED 2026-10-14, IMPLEMENTED (§25)** | **6** | 3D §7: no evidence any external system owns it; **not** attributed to BC#6's unnamed system. 3E.1 §0.4.1 added that the comma `link`/`cd` representation is the live one, with **no writer in any codebase** |
| 18 | **Downloads moderation** (`Mobile/`) | **DEFERRED / BUSINESS_DECISION** | **7** | 3D §9: content and administration both OLD-exclusive; gen-2's 24 mobile routes **proven dead**; Laravel zero. Needs a small named discovery prerequisite |
| 19 | `KhotabUpSeries` via Generation 2 | **DEAD / DO_NOT_MIGRATE** | — | 3C §3: `$admin_file` undefined in gen 2, no root `admin.php` — reachable but broken |
| 20 | `khotab/old.php`, `channels/old.php`, `vars/old.php` (32 ops) | **DEAD / DO_NOT_MIGRATE** | — | 3C §7: zero `.htaccess` references. Evidence, not dependency |
| 21 | Generation 2's 24 `mobile*` rewrite rules | **DEAD / DO_NOT_MIGRATE** | — | 3D §9.2: no `new_modules.php`, no `modules/` dir, no downloads-table reference. Copied-forward dead config |
| 22 | `modules/Locations/index.php` | **DEAD / DO_NOT_MIGRATE** | — | 3D §3.6: guards with `eregi()`, removed in PHP 7, no shim anywhere. Conditional on the PHP version, immaterial either way |
| 23 | `MirrorExport` | **DEAD / DO_NOT_MIGRATE** | — | 3E.1 §2.5: maintenance utility, no evidence of current use |
| 24 | `JoinMe` bulk re-parent; `BackUp/admin:1690` mirror insert | **OUT OF SCOPE — legacy residual risk** | — | 3E.2 §4: neither authorization nor mirror-level audit. Phase 1 is **not** expanded to cover them. **Carried into the OLD retirement checklist** (§7 DoD 5a) so OLD cannot be retired while required workflows depend on them (correction 9) |

---

## 3. Implementation phases, in recommended order

**Eight phases.** Each states only what is needed to fix execution order — not feature-level design.

### Phase 0 — Administration Authorization & Audit Foundation

**Purpose, corrected:** ensure that **new Laravel administration endpoints are consistently authorized and
audited.** Phase 0 does **not** fix OLD's unauthorized operations and does **not** modify OLD authorization
in any way — OLD remains legacy behavior until it is retired.

**Deliberately minimal.** Laravel already has admin authentication and Spatie authorization; Phase 0 adds
only the minimum reusable infrastructure the known phases (1–4) actually require. **No speculative
framework, no abstractions for hypothetical future modules.**

- **Capabilities — three, and no more:**
  1. **Define the content-admin permissions** the known phases need, as new Laravel/Spatie permissions in
     the established `{module}.{key}` namespace: `khotab.addmirror`, `khotab.editmirror`,
     `khotab.delmirror`, `anasheed.addmirror`, `anasheed.editmirror`, `anasheed.delmirror`. Assignment is
     managed through Laravel's existing administration model (`AdminStaffController`,
     `PermissionController`). **No legacy grant import, union, intersection or synchronization.**
  2. **A record-scoped authorization check** — the minimum needed to express the Khotab media
     consideration (§6.1) and Phase 3's assignment scoping. Laravel currently has no policy or gate layer;
     this adds the smallest one that serves phases 1–4, not a general framework.
  3. **A structured Laravel audit trail** for administration mutations. Authoritative, structured fields —
     **must not reproduce raw-URL or HTML leakage in a free-text field, and must not reproduce the
     target-attribution defects** found in the legacy log. Writes occur inside the mutation transaction
     (3E.1 invariant **I-10**) and **fail loudly** rather than silently.
- **Why together:** these three are the only shared prerequisites phases 1–4 have in common. Audit cannot
  follow Phase 1 because I-10 puts the audit write inside the mutation transaction, and Laravel has no
  audit facility at all today.
- **Dependencies:** none. This is the root.
- **Analysis sufficient?** **Yes.** 3E.2 §§1.5, 1.6, 8 specify the audit contract; 3E.3 §6 settles the
  authorization defaults. Nothing further to investigate.
- **Unresolved blocker:** **none.** D1a is withdrawn, D2 and D4 are decided, D5b is answered.
- **Bounded implementation-design decision (not a blocker):** whether Laravel *additionally* writes
  compatibility entries into `nuke_islamic_logs` during coexistence. Finalized inside Phase 0's own
  implementation design using evidence already in hand (3E.2 §8.4) — **no further investigation.** Owner
  input is welcome but not required; absent it, engineering decides.
- **Laravel modules affected:** `Support/Permission` and `RoleSeeder` (new permission names);
  `Domain/Admin` (a small record-scoped check, a new audit component); `routes/admin.php`.
- **Test scope:** permission definitions exist and resolve under guard `admin`; super-admin bypass honored;
  record-scoped check allows and denies correctly; audit row shape; audit-inside-transaction rollback;
  loud failure on audit error; **no raw URL or HTML in any audit field.**
- **Production verification:** confirm the new permissions resolve for a known super-admin. **No legacy
  grant reconciliation, no `--dry-run` legacy sync review, no administrator-identity inspection** — all
  withdrawn by correction 1.
- **Closure criteria:** the six mirror permissions exist and are assignable through Laravel's admin UI; the
  record-scoped check is implemented and tested; an administration mutation writes a structured audit row
  inside its transaction, fails loudly on error, and carries no raw URL or HTML. **OLD is untouched.**

### Phase 1 — Mirror Management (Khotab + Anasheed)

- **Capabilities:** the **7 operations** — Khotab insert / edit / delete / re-parent, Anasheed insert / edit / delete.
- **Why together:** 3E.1 §3.4 established the two domains are homogeneous on every axis that matters (same columns, same `idx_khid`, same backup enrolment, same parent-counter contract, same model shape), with three deltas that are per-domain configuration. **One service owns the whole unit.**
- **Dependencies:** **Phase 0** (permissions, per-record scope, audit).
- **Analysis sufficient?** **Yes — this is the most thoroughly analysed unit in the workstream.** 12 CRUD invariants, a complete auth/audit contract, and a decision matrix. **Do not reopen it as an investigation.**
- **Unresolved blocker:** none. One narrow product question — **D1b′** (§6.1), whether the Khotab
  audio/video division of labour should continue to exist as a rule — is needed before the authorization
  design is finalized, **not** before the phase starts.
- **Laravel modules affected:** `Content/Models/Mirror`, `AnasheedMirror`, `KhotabItem`, `AnasheedItem`; a new mirror-administration service; admin routes and views; the Phase 0 audit component.
- **Test scope:** all 7 operations; the 12 invariants, especially **I-2** (derive counts from the relation, synchronize the legacy column), **I-4** (tolerate a missing parent — 4,059 + 480 orphans exist), **I-5** (no UNIQUE, reproduce Khotab's author-scoped insert-only guard, leave Anasheed unguarded), **I-6/I-7** (never touch quality fields or `booking`), **I-8** (bytes vs megabytes), **I-10** (one transaction per operation), **I-11** (two-step re-parent, no serialized-row cookie).
- **Production verification:** one insert, one edit, one delete and one re-parent on a known item, verifying the mirror row, the parent counter, and the audit row; confirm the backup programme is undisturbed (`down`, `trial`, `booking` unchanged except `trial = 0` on edit).
- **Closure criteria:** all 7 operations work from the item page with correct authorization and audit; no behavior change to `BackupApiController` or `LinkQualityStatsController`; OLD's equivalent operations are no longer required for Khotab/Anasheed mirrors.

### Phase 2 — Administration Reporting & Observability

- **Capabilities:** the **`down = 0` lost-link worklist report**; the **moderator action-log view** over `nuke_islamic_logs`; **contextual access** to the existing `recheck*` / `fixSize*` actions from the item page.
- **Why together:** all three are **read surfaces over data Laravel already writes** — the `down` lifecycle (`BackupApiController`), the audit trail (Phase 0), and the link-quality metrics. No new mutation behavior. Small.
- **Dependencies:** **Phase 0** (audit data to read), **Phase 1** (the item-page admin surface to attach contextual actions to).
- **Analysis sufficient?** **Yes** — 3D §2.5, §5 and 3C §10 specify all three. No new investigation.
- **Unresolved blocker:** none.
- **Laravel modules affected:** `Domain/Admin` controllers and views; `LinkQualityStatsController` (adding contextual entry points only — **its behavior is not reopened**).
- **Test scope:** report filters and pagination; log-view scoping and the `hidden` flag; contextual action authorization.
- **Production verification:** the `down = 0` report's count reconciles against a direct aggregate; the log view renders recent real rows.
- **Closure criteria:** an administrator can find unverified links, see who changed a mirror, and trigger a recheck from the item page.
- **Note:** small enough to fold into Phase 1's tail if the team prefers fewer releases. Kept separate here because it has a different risk profile — read-only.

### Phase 3 — Category Assignment (OA-3)

- **Capabilities:** item ↔ category and group/series ↔ category assignment, **including the removal half**, which is OLD-only.
- **Why together:** one data relationship, one UI surface. 3D §5: the *add* half is gen-2-delegated and the *remove* half is OLD-exclusive, so splitting them would ship a one-way door.
- **Dependencies:** **Phase 0**. Benefits from Phase 1's item-page surface.
- **Analysis sufficient?** **Mostly.**
- **Named prerequisite (small):** re-specify the operation set before implementing. 3D §10 rank 3 and §3.1 established two things OA-3's original scope may not cover: a second call site at `modules/Locations/index.php:256-257` carrying a `{$type}` dimension (group / series), and **"add/remove to all"** bulk semantics. **Scope: enumerate the call sites and the `{$type}` values. Not a new archaeology pass.**
- **Laravel modules affected:** `Domain/Content` category models and relations; a new assignment service; admin routes/views.
- **Test scope:** add, remove, the bulk variants, and the `{$type}` dimension; idempotency of repeated assignment.
- **Production verification:** one add and one remove on a known item; confirm the category tree and listings reflect it.
- **Closure criteria:** mis-filed content can be corrected entirely in Laravel; OLD's `cat_del_from_*` is no longer required.

### Phase 4 — Survey Administration Completion

- **Capabilities:** the survey operations `SurveyController` does not cover — category management (`AddCategory`, `EditCategory`, `DelCategory`) and comment moderation (`RemovePollComment`), plus `polledit_select`.
- **Why together:** one existing controller, one permission namespace (`survey.*`, already seeded), one domain.
- **Dependencies:** **Phase 0** for audit consistency. Otherwise independent — **may run in parallel with Phases 1–3.**
- **Analysis sufficient?** **Yes** — 3C §5 identified the uncovered operations; `SurveyController` is the established pattern.
- **Unresolved blocker:** none.
- **Laravel modules affected:** `Domain/Admin/Http/Controllers/SurveyController`, survey models, admin routes/views.
- **Test scope:** category CRUD; comment removal; permission gating.
- **Closure criteria:** survey administration is complete in Laravel; no OLD survey operation is required.

### Phase 5 — Advfiles (OA-1) — **BUSINESS-GATED**

- **Capabilities:** per-item advertised-file attach / replace / delete and the request flow (~4 contextual operations, 11 analysed in Pass 3A).
- **Why together:** one data relationship; Pass 3A already treats it as a unit.
- **Dependencies:** **Phase 0**; Phase 1's item-page surface.
- **Analysis sufficient?** **Yes, technically** — Pass 3A is complete, and 3D §3.1 added a confirmed second call site (`modules/Locations/index.php:486-487`).
- **Unresolved blocker:** **BUSINESS — is the Advfiles capability still wanted?** Retained per explicit owner instruction; existing evidence does not resolve it. *(Resolved 2026-10-14: BUSINESS APPROVED — see §24.)*
- **Laravel modules affected:** a new advfiles domain area; admin routes/views.
- **Closure criteria:** the owner either approves implementation and it ships, or formally retires the capability.

### Phase 6 — W2ACD Administration — **BUSINESS-GATED**

- **Capabilities:** the W2ACD contextual admin surface (~22 operations), **excluding** the normalized mirror CRUD, which is DEAD (§2 row 1b).
- **Why together:** one content domain with one administering system.
- **Dependencies:** **Phase 0**. Independent of Phases 1–5.
- **Analysis sufficient?** **Yes for the data question** — 3E.1 §0.4.1 established the comma `link`/`cd` representation is live (39/39 items, 1–3 links, avg 1.92), the normalized table was never written, and the counters are **not authoritative**.
- **Unresolved blockers — two, both business:** (a) **ownership** — 3D §7 found no evidence any external system administers W2ACD, and it is explicitly **not** attributed to BC#6's unnamed system; (b) **representation** — whether Laravel preserves the comma columns or normalizes them. 3E.1 deliberately declined to call normalization a prerequisite.
- **Closure criteria:** owner confirms W2ACD is live and chooses a representation, then it ships — or it is formally retired. *(Resolved 2026-10-14: BUSINESS APPROVED — see §25.)*

### Phase 7 — Downloads Moderation (`Mobile/`) — **BUSINESS-GATED**

- **Capabilities:** ~15 PHP-Nuke Downloads moderation operations over `nuke_downloads_downloads` / `_categories` / `_votedata`.
- **Why together:** one legacy module, one table group.
- **Dependencies:** **Phase 0**. Fully independent otherwise.
- **Analysis sufficient?** **No — and this is the only family needing real work before it can be ranked honestly.**
- **Named prerequisite (small):** one read-only data check — do `nuke_downloads_*` hold recent rows? 3D §9 proved gen-2's routes are dead and Laravel has zero references, so the remaining question is purely whether the content is still consumed and the workflow still operated. **Scope: one aggregate query plus one owner question. Not a discovery pass.**
- **Unresolved blocker:** **BUSINESS — is this module still in use at all?**
- **Closure criteria:** owner confirms use and it ships, or it is formally retired.

---

## 4. Dependencies between phases

```
                        ┌──────────────────────────────────────────┐
                        │  Phase 0 — Authorization & Audit         │
                        │  Foundation  (ROOT — blocks everything)  │
                        │  ✅ READY — no owner decision blocks it  │
                        └───────────────┬──────────────────────────┘
                                        │
         ┌──────────────┬───────────────┼───────────────┬──────────────┐
         ▼              ▼               ▼               ▼              ▼
  ┌─────────────┐  ┌─────────┐   ┌───────────┐   ┌───────────┐  ┌───────────┐
  │  Phase 1    │  │ Phase 4 │   │  Phase 5  │   │  Phase 6  │  │  Phase 7  │
  │  Mirror     │  │ Survey  │   │  Advfiles │   │  W2ACD    │  │ Downloads │
  │  Management │  │ (par.)  │   │  ⚠ gated  │   │  ⚠ gated  │  │  ⚠ gated  │
  └──────┬──────┘  └─────────┘   └───────────┘   └───────────┘  └───────────┘
         │
         ├──────────────┐
         ▼              ▼
  ┌─────────────┐  ┌──────────────┐
  │  Phase 2    │  │  Phase 3     │
  │  Reporting  │  │  Category    │
  │  & Observ.  │  │  Assignment  │
  └─────────────┘  │  + small     │
                   │  prerequisite│
                   └──────────────┘
```

| Phase | Hard dependency | Soft dependency |
|---|---|---|
| 0 | — | — |
| 1 | 0 | — (D1b′ needed before its auth design is finalized, not before it starts) |
| 2 | 0 (audit data), 1 (item-page surface) | — |
| 3 | 0 | 1 (item-page surface) |
| 4 | 0 (audit consistency only) | — **can run in parallel with 1–3** |
| 5 | 0 | 1 |
| 6 | 0 | — |
| 7 | 0 | — |

**Critical path: 0 → 1 → 2.** Phases 4, 6 and 7 are parallelisable once Phase 0 lands.

---

## 5. Explicitly NOT being implemented

| Item | Why |
|---|---|
| `GreenZone()` as an authorization engine; `nuke_*_mod` as a runtime permission store; multiple competing permission stores | **Owner directive.** Spatie is the future authority. Legacy data is evidence of intent only |
| Item CRUD, content structure, ordering/weight, document attachments, banner lifecycle, moderation queue, Fatwa Q&A | **EXTERNALLY_OWNED** — Business Confirmation #6 boundary |
| Counter repair (`*Count`) | **Superseded** by 3E.1 invariant I-2 — the relation is authoritative, so manual repair has no purpose |
| `KhotabLostedLink`'s repair form | `op=KhotabRepairLostedLink` has **no handler anywhere** and submits an undefined id. No behavior exists to migrate |
| W2ACD normalized mirror CRUD | Table **exactly empty**, counters **never written** — never exercised in production |
| `KhotabUpSeries` via Generation 2 | Reachable but broken (`$admin_file` undefined, no root `admin.php`) |
| `khotab/old.php`, `channels/old.php`, `vars/old.php` (32 ops) | Zero `.htaccess` references — unreachable historical snapshots |
| Generation 2's 24 `mobile*` rewrites | Copied-forward dead configuration — no target exists |
| `modules/Locations/index.php` | Guards with `eregi()`, removed in PHP 7, no shim anywhere |
| `MirrorExport` | Maintenance utility, no evidence of current use |
| Telawah mirror administration | 1 production row and six converging signals of an unfinished feature — **DEFERRED pending a retire-or-build decision**, not implemented |
| Bulk counter repair inside CRUD | 3E.1 invariant I-2 — needs separate justification |
| Orphan cleanup / repair capability | 3D §0.4.2: **robustness is required (I-4), a cleanup feature is not parity** and OLD has none |
| An orphan-cleanup, duplicate-cleanup or counter-repair data migration | No bulk data repair is proposed anywhere in this plan |

**Flagged but out of scope (not inherited silently):** `JoinMe`'s bulk re-parent and `BackUp/admin:1690`'s mirror insert have neither authorization nor mirror-level audit (3E.2 §4). They are not among the 7 operations. If OLD remains reachable, they remain ungoverned — recorded here so the decision to leave them is explicit.

---

## 6. Business decisions still outstanding

### 6.1 The only question touching Phases 0–1

| ID | Narrow product-policy question | When needed |
|---|---|---|
| **D1b′** | OLD divided Khotab mirror administration by media type — audio moderators could not touch video items and vice versa (`AvAdmin()`, 3E.2 §1.2). **Should that division of labour continue to exist as a rule in the new system?** If yes, it is a straightforward technical mapping into Laravel's authorization architecture. If no, Khotab mirror permissions are flat. | **Before Phase 1's authorization design is finalized — not before Phase 0 starts.** Phase 0 defines the six mirror permissions either way; this only decides whether an additional record-scoped dimension applies to Khotab |

That is the **entire** narrow question. Per correction 3, no legacy permission-store investigation is
reopened to answer it — it is a policy choice about whether the business still wants the distinction.

### 6.2 Withdrawn, decided or answered

| ID | Was | Now |
|---|---|---|
| **D1a** — which legacy store is authoritative | Blocking owner decision | **WITHDRAWN** (correction 1). No legacy grants are imported. Permissions are defined in Laravel and assigned through Laravel's admin model |
| **D2** — Anasheed namespace | Blocking, coupled to D1a | **DECIDED** (correction 2): `anasheed.{add,edit,del}mirror`, independent of Khotab, no media dimension |
| **D3** — Anasheed media scope | Engineering default | **DECIDED** (correction 2): not added |
| **D4** — re-parent permission | Engineering default | **DECIDED** (correction 4): reuse `khotab.editmirror`; no `khotab.movemirror` unless a later requirement asks |
| **D1c** — super-admin bypass | Engineering default | Unchanged: preserved |
| **D5a** — audit write target | Blocking owner decision | **DOWNGRADED** (correction 7) to a bounded Phase 0 implementation-design decision, resolved from evidence in hand |
| **D5b** — `extrainfo` content | Blocking security decision | **ANSWERED** (correction 7): the authoritative new trail is structured and must not reproduce raw-URL or HTML leakage |
| **D5c** — audit defect fixes | Engineering default | Unchanged: all fixed, including target attribution and Anasheed granularity |
| **Item CRUD ambiguity** (was §6.3) | Flagged document conflict | **CLOSED** (correction 8): `EXTERNALLY_OWNED` per the reconciled BC#6 result. Reclassifiable later only if business ownership changes |

### 6.3 Gating later phases only — none blocks Phases 0–4

| Phase / item | Question |
|---|---|
| 5 — Advfiles | Is the advertised-files capability still wanted? **Answered 2026-10-14: yes, at parity (§24).** |
| 6 — W2ACD | Is W2ACD live, and who owns its administration? Preserve the comma representation or normalize it? **Answered 2026-10-14: still required; comma representation kept (§25).** |
| 7 — Downloads | Is the `Mobile/` Downloads module still in use at all? (plus one read-only data check) |
| 1a — Telawah mirrors | Retire the capability, or complete it? |

## 7. Definition of Done — the whole OLD Administration migration

OLD administration is **migrated and closed** when all of the following hold:

1. **Every family in §2 has a terminal disposition** — implemented, externally owned, retired, or formally declined by the owner. No family sits in `DEFERRED` without a recorded decision.
2. **Phases 0–4 are CLOSED** (the committed surface).
3. **Phases 5–7 are each either CLOSED or formally RETIRED** by owner decision — recorded in `decision-log.md`, not left implicit.
4. **No Laravel administration surface links to `old.way2allah.com`.**
5a. **The OLD retirement checklist is complete and every residual path is accounted for.** Specifically,
   `JoinMe`'s bulk re-parent and `BackUp/admin:1690`'s mirror insert (3E.2 §4) are confirmed either unused
   or replaced. **OLD cannot be retired while any required workflow still depends on them** — they were
   never in Phase 1's scope, so their disposition must be settled separately and explicitly.
5b. **OLD's Cat admin category writer is retired or restored to the canonical format** (§20.1). While the
   deployed `modules/Cat/admin/index.php` stays live, it strips the leading pipe Laravel OA-3 writes. Not fixed
   in Phase 3 (OLD is not modified); a cutover dependency.
6. **Generation 2's contextual admin toolbars no longer render operations that no longer exist.** 3C §12 identified this as a sequencing hazard independent of migration: gen 2 renders its toolbars from its own local `is_w2a_admin()`, so removing OLD would leave a fully-populated, entirely non-functional admin UI — **worse than an absent one**. Gen-2 rendering must be addressed before OLD's availability, regardless of how much has migrated.
7. **Every *Laravel* administration endpoint is permission-gated and audited.** This is a standard for new
   endpoints; OLD's own gaps are legacy behavior and are not remediated.
8. **An authoritative structured Laravel audit trail exists** for all migrated administration mutations,
   free of raw-URL/HTML leakage and of the legacy target-attribution defects.
9. **The `nuke_*_mod` tables, `GreenZone()` and `AvAdmin()` are confirmed unreferenced** by any live
   system, and the tables are either archived or documented as historical evidence.
10. **Tests pass and PHPStan level 5 is clean** across `app` and `routes`, per project standard.
11. **`PROJECT-HANDOFF.md` and `decision-log.md` record the closure**, including every declined capability
    and its reason.

### 7.1 Definition of Done — status at migration closure (2026-10-15)

Only migration-implementation and closure items are marked closed. Production cutover and deployment items stay
pending until they actually happen.

| # | Item | Status | Basis |
|---|---|---|---|
| 1 | Every family has a terminal disposition | **CLOSED** | §2, §28, §29, §30.5. The last open item, the Telawah mirror disposition, is resolved by §29.1 (edit / delete built; insert disabled by OLD itself) |
| 2 | Phases 0–4 CLOSED | **PENDING — production verification** | Implemented and locally verified. A phase's CLOSED status requires its production verification (§10.6, §13.15, §15.12, §21.8, §23.10), which has not run |
| 3 | Phases 5–7 CLOSED or RETIRED | **PENDING — production verification** | All three were business-approved and are implemented and locally verified (§24–§26); production verification has not run |
| 4 | No Laravel administration surface links to `old.way2allah.com` | **CLOSED** | Verified 2026-10-15: no reference to `old.way2allah.com` or `inlineAdminUrl` in `app`, `routes`, `resources` or `config` outside comments; every contextual control targets a Laravel route (C0 test) |
| 5a | OLD retirement checklist; `JoinMe` / `BackUp` residual paths settled | **PENDING — cutover** | Settled at OLD retirement (closure review §5) |
| 5b | OLD Cat admin writer retired or restored | **PENDING — cutover** | §20.1 coexistence hazard; retire at cutover |
| 6 | Gen 2 contextual toolbars no longer render dead operations | **PENDING — cutover** | Laravel's public pages carry the Laravel contextual surface (§28); Gen 2's own toolbars stop when Gen 2's public pages are replaced or retired |
| 7 | Every Laravel administration endpoint is permission-gated and audited | **CLOSED** (migration endpoints) | Every admin route carries admin middleware, proven equal to the real middleware for all admin routes (C0). Every migrated mutation writes the Phase 0 audit trail in its transaction (Phases 0–7, §29.1) |
| 8 | An authoritative structured Laravel audit trail exists | **CLOSED** | Phase 0 `admin_audit_log`; legacy compatibility writes default-OFF |
| 9 | `nuke_*_mod`, `GreenZone()`, `AvAdmin()` unreferenced by any live system; tables archived or documented | **PENDING — cutover** | The Laravel side is confirmed: no `nuke_*_mod` reference, no runtime use of the legacy permission blob outside the manual bootstrap command and the settled `backup.php` API (decision-log #33). OLD is still live, and archival happens at retirement |
| 10 | Tests pass; PHPStan level 5 clean (`app` + `routes`) | **CLOSED** | Full suite 1897 passed, 1 existing skip; PHPStan 0 errors |
| 11 | `PROJECT-HANDOFF.md` and `decision-log.md` record the closure | **CLOSED** | `PROJECT-HANDOFF.md` §1 (2026-10-15 update); `decision-log.md` #59, including the declined capabilities and why |

**Closed: 1, 4, 7, 8, 10, 11. Pending (cutover or production verification): 2, 3, 5a, 5b, 6, 9.**

**Status refresh (2026-11-06, decision-log #100, R3)** — the table above is the 2026-10-15 record, kept as written.
Current evidence per item:

| # | Status 2026-11-06 | Updated basis |
|---|---|---|
| 1 | **CLOSED** | Plus the module-by-module OLD public admin-control dispositions §31–§53 (#60–#82); one AdminCP item (#41 backup sidebar group) pending owner disposition (#100) |
| 2, 3 | **PENDING — production verification** | Unchanged; nothing deployed |
| 4 | **CLOSED** | Re-verified 2026-11-03 (#83) |
| 5a, 5b, 6, 9 | **PENDING — cutover** | Unchanged; consolidated in §55 |
| 7, 8 | **CLOSED** | Unchanged; contextual controls added since use the same admin middleware and audit trail |
| 10 | **CLOSED** | Latest full suite 2,425 — 2,424 passed, 0 failed, 1 skipped; Architecture 5 / 5; PHPStan 0; Pint PASS (#98) |
| 11 | **CLOSED** | `PROJECT-HANDOFF.md` §1 and `decision-log.md` #59–#100 |

Item 1 update (2026-11-06, decision-log #101): the #41 backup sidebar group is dispositioned — three functions COVERED,
the Backup Booking Monitor OWNER-APPROVED DEFERRED LOW. Admin status: §56.

---

## 8. Progress model

Statuses: **READY** · **NOT STARTED** (blocked only by a predecessor) · **BLOCKED** (named blocker) ·
**IN PROGRESS** · **CLOSED** · **RETIRED**.

| Phase | Name | Status | Blockers | Dependency | Implementation readiness |
|---|---|---|---|---|---|
| **0** | Administration Authorization & Audit Foundation | **IMPLEMENTED / LOCAL VERIFIED / PRODUCTION DEPLOYMENT PENDING** | **None** | — | **Implemented, corrected and locally deployment-verified 2026-10-12 (§§10–12).** 35 focused tests; full suite 1265 passed / 1 skipped; PHPStan 0 errors; Pint clean on all 15 Phase 0 files. **Not CLOSED** — production deployment intentionally postponed; production verification outstanding (§10.6). D1b′ **closed** (§11.3); legacy-log compatibility default-OFF and confirmed inert (§12) |
| **1** | Mirror Management (Khotab + Anasheed) | **IMPLEMENTED / LOCAL VERIFIED / PRODUCTION DEPLOYMENT PENDING** | **None** | Phase 0 | **Implemented and locally verified 2026-10-13 (§13); review accepted.** Anasheed `linksize` question closed (§14.1). Not CLOSED — production verification outstanding (§13.15). Deploys together with Phase 0 |
| **2** | Administration Reporting & Observability | **IMPLEMENTED / LOCAL VERIFIED / PRODUCTION DEPLOYMENT PENDING** | **None** | Phases 0, 1 | **Implemented and locally verified 2026-10-13 (§15); review accepted; item-page integration completed (§16).** Not CLOSED — production verification outstanding (§15.12) |
| **3** | Category Assignment (OA-3) | **IMPLEMENTED / LOCAL VERIFIED / PRODUCTION DEPLOYMENT PENDING** (§20, §21) | None | Phase 0 | Deploy with Phases 0–2 |
| **4** | PHP-Nuke Poll Administration Migration (re-scoped, §23) | **IMPLEMENTED / LOCAL VERIFIED / PRODUCTION DEPLOYMENT PENDING** (§22, §23) | None | Phase 0 | Deploy with Phases 0–3 |
| **5** | Featured / Promotional Content (Advfiles, OA-1) | **IMPLEMENTED / LOCAL VERIFIED / PRODUCTION DEPLOYMENT PENDING** (§24) | None (business approved 2026-10-14) | Phase 0 | Deploy with Phases 0–4 |
| **6** | W2ACD Administration — CD / Album Downloads | **IMPLEMENTED / LOCAL VERIFIED / PRODUCTION DEPLOYMENT PENDING** (§25) | None (business approved 2026-10-14) | Phase 0 | Deploy with Phases 0–5 |
| **7** | Mobile Downloads + Visitor Contributions / Moderation | **IMPLEMENTED / LOCAL VERIFIED / PRODUCTION DEPLOYMENT PENDING** (§26) | None (business approved; Issues 1–2 resolved) | Phase 0 | Deploy with Phases 0–6 |

**Side items, tracked separately**

| Item | Status | Note |
|---|---|---|
| Telawah mirror disposition | **BLOCKED** | Business: retire or complete |
| Gen-2 toolbar decommissioning | **NOT STARTED** | DoD item 6 — a sequencing hazard independent of migration progress |
| `JoinMe` / `BackUp` residual paths | **TRACKED — not in scope** | DoD item 5a. Must be settled before OLD retirement; Phase 1 is not expanded to cover them |
| OLD Cat admin writer vs canonical format | **TRACKED — cutover dependency** | DoD item 5b, §20.1. Retire OLD's category-assignment UI (or restore its canonical writer) at cutover |
| Item CRUD classification | **CLOSED** | `EXTERNALLY_OWNED` per reconciled BC#6 (correction 8) |

**Rollup:** 0 of 8 phases closed · **Phase 0 implemented and READY FOR REVIEW / DEPLOYMENT** ·
3 ready on predecessor closure (1, 2, 4) · 1 nearly ready behind a small engineering prerequisite (3) ·
3 blocked on business gates (5, 6, 7) · **0 requiring further investigation beyond two small named
prerequisites.**

Phase 0 is deliberately **not** marked CLOSED: the master plan requires production verification for it
(§3 Phase 0, §10.6), and that has not run.

> **Superseded 2026-10-15:** the table and rollup above are the 2026-10-12 to 14 progress snapshot. Every phase and the
> contextual surface are now implemented and locally verified, and the Telawah mirror disposition is resolved (§29.1:
> edit / delete built; OLD had disabled insert itself). "CLOSED" in the sense of §7 item 2 still needs production
> verification. Final record: §30; Definition of Done: §7.1.

## 9. Recommended first phase

> **Implement Phase 0 — Administration Authorization & Audit Foundation — first. It is now ready; no owner
> decision blocks it.**

Why Phase 0 rather than Phase 1, despite Mirror Management being the better-analysed and more visibly
valuable unit:

1. **Phase 1 cannot ship without it.** 3E.1's invariant I-10 requires the audit write inside the mutation
   transaction, and Laravel has **no audit facility at all** — so audit is a precondition, not a follow-up.
2. **Every later phase reuses it.** Priority 3 in the owner's own ordering — dependencies needed by later
   administration.
3. **It establishes the standard once.** Defining the permission namespace and the structured audit shape
   before any feature ships means no later phase has to retrofit them. This is about **new Laravel
   endpoints being consistently authorized and audited** — not about remediating OLD, which remains legacy
   behavior and is untouched.
4. **It is small and bounded.** Three components, all specified by completed analysis, with no speculative
   abstraction. It is not a research phase and not a framework.

**Immediately after Phase 0: Phase 1 (Mirror Management)** — highest operational value, no remaining
analysis, and the capability whose absence most visibly decays public content (a dead mirror is a broken
download with no repair path in any system).

**Recommended sequence:** `0 → 1 → 2 → 3`, with **4 in parallel** once 0 lands, and 5/6/7 scheduled only as
their business gates clear.

---

## 10. Phase 0 implementation record (2026-10-12)

Status: **READY FOR REVIEW / DEPLOYMENT.** Not CLOSED — production verification outstanding (§10.6).

> **Superseded 2026-10-15:** Phase 0 was not deployed on its own; deployment waited for the whole-migration closure (§30).
> Production deployment has not started.

### 10.1 Architecture implemented

Three components, no more. Nothing in OLD was modified; no legacy permission assignment was read,
imported, unioned, intersected or synchronized; no administrator identity was inspected.

1. **Six Spatie permissions**, added to the existing `AdminPermissionSeeder::MODULE_KEYS` in the
   established `{module}.{key}` namespace. **Definitions only — nothing grants them.**
2. **`AdminRecordAuthorizer`** + **`AdminRecordConstraint`** — the minimal record-scoped authorization
   extension point, bound as a singleton in `AdminServiceProvider::register()` with a **deliberately empty
   constraint list**. Decision order matches `EnsureAdminHasPermission` exactly (super-admin → permission
   → constraints) so the two layers cannot disagree about an admin's standing.
3. **`AdminAuditEntry` + `AdminAuditRecorder`** — the authoritative structured audit trail, on the **`main`**
   connection.

**Why the audit table is on `main` and not where Spatie lives.** Spatie's tables hold authorization
*configuration* and stay on the default connection per ADR-0011. This table holds *records of content
mutations*, and 3E.1's invariant I-10 requires each mirror operation to write the mirror row and the parent
counter in one transaction — both on `main`. A cross-connection audit write cannot join that transaction,
so an audit row on the default connection could survive a rolled-back mutation: exactly the silent
divergence the legacy log already has. Co-locating with the data it describes is what makes "audit
participates in rollback" achievable, and it is tested (§10.7).

### 10.2 Permissions added

`khotab.addmirror`, `khotab.editmirror`, `khotab.delmirror`, `anasheed.addmirror`, `anasheed.editmirror`,
`anasheed.delmirror`. No `movemirror` (plan correction 4). Khotab's three are `SOURCE_CONFIRMED` keys from
`admincp/khotab/menu.php`'s own `$authorization['khotab']` array; Anasheed's three are minted fresh, since
`admincp/` has no `anasheed` directory — same shape as the existing `telawah.repair` precedent.
`PermissionLabels` gained the six labels plus an `anasheed` module name and icon, with the
not-`SOURCE_CONFIRMED` provenance of the module entry documented in place.

### 10.3 Legacy-defect refusals, structural where possible

| Legacy defect (3E.2 §8.2) | How Phase 0 refuses it |
|---|---|
| Raw URL / HTML in a rendered `extrainfo` | **The table has no free-text description column at all**, so there is nothing to leak into. `assertStructuredMetadata()` additionally rejects markup in metadata string values. A bare URL *is* allowed — it is legitimate before/after data for a link change; a rendered string with tags around it is not |
| Target mis-attribution | `entityType` and `entityId` are both required, and `entityId` is a positive-int-validated parameter. No path can supply a plausible-but-wrong id by default |
| Silent logging failure | No try/catch anywhere in the recorder. A persistence failure propagates and takes the caller's transaction with it |
| Untransacted audit write | `record()` opens no transaction of its own, so it enlists in the caller's |
| IP-source inconsistency / inconsistent escaping | One `ip` parameter; Eloquent parameter binding throughout |

### 10.4 Decision — `nuke_islamic_logs` compatibility

**Resolved from existing evidence only (3E.2 §8.4); no new investigation, no production query.**

**Implemented, isolated, and default-OFF.** `LegacyAuditLogCompatibilityWriter` mirrors an
already-persisted authoritative entry into the legacy table, gated by
`config('admin_audit.legacy_compatibility_writes')`, default `false`.

- **For writing it:** `nuke_islamic_logs` is shared live state, not an OLD-private store — OLD writes it
  (253,499 rows, latest 2026-10-12), Generation 2 maintains its `aid` integrity on rename, and OLD's
  moderator-log views read it. With OLD reachable, both systems can mutate the same mirrors, so a
  Laravel-only trail is incomplete in both directions.
- **For defaulting it off:** Phase 0 ships no mutations, so nothing calls it yet either way. Enabling it
  before Phase 1's row shape is settled would mean new writes into a 253,499-row production table with no
  caller to validate them.
- **Resolution:** build it now behind a flag. Phase 1 enables it with a config change rather than a
  refactor; retiring it when OLD goes away is deleting one file plus one config key. It is explicitly not
  authoritative — it only ever reads from a persisted `AdminAuditEntry`.
- **Accepted cost, stated plainly:** its `extrainfo` is a plain, markup-free summary that interpolates
  metadata **keys only, never values**, so no URL can reach that shared rendered column. OLD's
  moderator-log view will therefore show a less decorated line for Laravel-written rows. That is a
  deliberate display degradation (plan correction 7), not a functional break.

### 10.5 Migrations required for deployment

One: `database/migrations/2026_10_12_100000_create_admin_audit_log_table.php` — creates `admin_audit_log`
on the **`main`** connection. Guarded by a `hasTable()` check, and `down()` drops it. It creates a **new**
table and alters nothing existing, so it carries no risk to legacy data.

> **Update 2026-11-08 (register row N4):** `down()` no longer drops `admin_audit_log` while it holds any row — it throws
> (`NonEmptyTableGuard`), leaving the table and the migration record untouched; an empty or missing table behaves as before.
> The same protection covers `download_rejections`. Production `migrate:rollback` remains prohibited.

### 10.6 Production verification required after deployment

1. **Migration applied:** `admin_audit_log` exists on `main` with its three indexes.
2. **Permissions resolve:** after `php artisan db:seed --class=AdminPermissionSeeder`, the six permission
   rows exist on guard `admin`, and the permission editor renders the `anasheed` block and all six labels.
3. **No grant drift:** confirm no admin gained a mirror permission as a side effect — the expected count of
   holders is **zero** until someone grants one in Laravel.
4. **Compatibility flag off:** `config('admin_audit.legacy_compatibility_writes')` is `false`, and
   `nuke_islamic_logs` has received no new `[laravel]` rows.

No production query beyond these is required, and none is requested now.

### 10.7 Tests added

`tests/Feature/Admin/MirrorPermissionsPhase0Test.php` (14) and
`tests/Feature/Admin/AdminAuditRecorderTest.php` (14 incl. a 3-case dataset) — **28 tests, 117 assertions,
all passing.** Two shared fixtures added to `MainSchema`: `adminAuditLog()` and `nukeIslamicLogs()`.

No existing test was weakened. Two failures during development were in my own new tests and were fixed
properly, not relaxed: the shared `nuke_authors` fixture deliberately omits the `permissions` column, so
the no-legacy-import test declares it locally per the documented `isGuardableColumn` hazard; and a
bare-substring assertion against `SyncLegacyAdminPermissionsAction` false-positived on the word
"mirroring" in its own docblock, so it now asserts on the four capability tokens specifically.

### 10.8 Remaining Phase 0 issues

- **Production verification (§10.6) has not run.** This is why Phase 0 is `READY FOR REVIEW / DEPLOYMENT`
  and not `CLOSED`.
- **Pint reports ~40 pre-existing violations in files Phase 0 never touched** (`routes/admin.php`,
  `ContentListingService.php`, many older tests). Confirmed pre-existing via `git diff --name-only`.
  Deliberately **not** fixed — reformatting 40 unrelated files would bury this phase's diff. All 13 Phase 0
  files pass `pint --test`.
- **Decision D1b′ remains open** (the Khotab audio/video division). The constraint list is empty by design;
  the question is needed before Phase 1's authorization design is finalized, not before Phase 1 starts.

---

## 11. Phase 0 correction pass (2026-10-12)

### 11.1 Migration `hasTable()` guard — REMOVED

**It was not an established convention, and it was actively harmful.** Verified from the repository, not
from production:

- The project's three existing `create`-table migrations — `create_cache_table`, `create_jobs_table`,
  `create_permission_tables` — use **zero** `hasTable()` guards.
- The guard it was modelled on, `LegacyIndexGuard` (E-04/E-05/E-08/E-12), exists for a genuinely different
  situation, stated in its own docblock: those migrations **alter pre-existing legacy tables**, production
  already carried structurally equivalent indexes under other names, and **MySQL permits duplicate indexes
  with a warning rather than an error** — so an unguarded `CREATE INDEX` would have looked like success
  while costing ~20 MB and extra write amplification.
- None of that transfers. `admin_audit_log` is new, nothing outside this programme creates it, and
  `CREATE TABLE` against an existing name is a **hard error, not a warning** — there is no silent-duplication
  hazard to guard against. Migration tracking already prevents ordinary re-execution.

And the guard's real cost: had `admin_audit_log` existed with a *different* shape — a partially-applied
deploy, a hand-created table — it would have returned silently and recorded the migration as applied,
leaving the app running against a schema it does not expect. Failing loudly on that conflict is correct.
`down()` keeps `dropIfExists`, matching `create_jobs_table`.

### 11.2 What removing it exposed — and the real fix

Removing the guard immediately broke all 28 focused tests with
`SQLSTATE[42S01] … Table 'admin_audit_log' already exists (Connection: main, … Database: olddb)`.

That was not a migration defect; it was the guard **masking a real side effect**, exactly as the owner's
reasoning predicted. Root cause, and it is specific to this migration:

- **This is the first migration in the project to create a table on `main`.** Every earlier `main`
  migration only adds an index to a table that already exists.
- `phpunit.xml` overrides `DB_CONNECTION`/`DB_DATABASE` to sqlite `:memory:` but **deliberately does not
  override `DB_MAIN_*`**, so `main` still resolves to a real MySQL database while `RefreshDatabase` runs
  `migrate`. Index migrations tolerate that because adding an index is idempotent; creating a table is not.
- Consequence: the first full-suite run (while the guard was present) **really did create
  `admin_audit_log` in the local dev database `olddb`**, and the guard then hid that on every later run.

**Fix:** the migration now returns early under `app()->runningUnitTests()`, in both `up()` and `down()`.
This is **not the removed guard in disguise** — `runningUnitTests()` is false in production, so a
pre-existing `admin_audit_log` there still fails the migration loudly. Tests build the table from
`MainSchema::adminAuditLog()`, as every other `main` table in this suite already does.

**Drift guard added.** Because the migration body is no longer executed in tests, a new test —
`tests/Feature/Admin/AdminAuditSchemaDriftTest.php` — parses the column names out of the migration file and
requires them to match the fixture's column list exactly, so the fixture cannot silently drift from the
migration it stands in for.

**Local cleanup.** The stray `admin_audit_log` table in the local dev database (`127.0.0.1:3307`, `olddb`)
was verified empty (0 rows) and dropped. It was created by my own earlier test run, not by any deployment.
**No production database was touched or queried at any point.** A subsequent full-suite run confirmed the
table is not recreated.

### 11.3 Decision D1b′ — CLOSED

**Owner decision, 2026-10-12: OLD's Khotab audio/video moderator division is NOT reproduced.**

Phase 1 uses flat Laravel permissions — `khotab.addmirror`, `khotab.editmirror`, `khotab.delmirror`,
`anasheed.addmirror`, `anasheed.editmirror`, `anasheed.delmirror`. No runtime `nuke_*_mod` dependency, no
`AvAdmin()` recreation, no `audio`/`vedio` record constraint. If the business later wants media-type-separated
moderation, it arrives as a **new** Laravel authorization feature, explicitly chosen, rather than inherited
implicitly from OLD.

The record-constraint extension point **remains available with no Mirror constraint registered**. With an
empty list, `AdminRecordAuthorizer` is exactly super-admin + permission — which is the whole of Phase 1's
authorization requirement — and the `$record` parameter stays in the signature so a future rule needs no
call-site change. Documented in `AdminRecordConstraint`, `AdminRecordAuthorizer` and
`AdminServiceProvider::register()`.

**This supersedes Pass 3E.3 §6.1's D1b′ row and §8's "needed before Phase 1's authorization design is
finalized" note. Phase 1 now has no open authorization question.**

### 11.4 Legacy compatibility writes — unchanged, default-OFF

`config('admin_audit.legacy_compatibility_writes')` remains `false`. Not enabled in Phase 0, which has no
content-mutation caller. The `nuke_islamic_logs` coexistence question will be activated — or not — when
Phase 1 provides a real Mirror mutation path. OLD was not changed.

### 11.5 Correction-pass results

| Gate | Result |
|---|---|
| Focused Phase 0 tests | **30 passed**, 128 assertions (28 + 2 new drift tests) |
| Full suite | **1261 tests, 1260 passed, 1 skipped** (the pre-existing skip) |
| PHPStan (level 5, `app` + `routes`) | **0 errors** |
| Pint, Phase 0 files only | **passed** |
| Pre-existing Pint violations elsewhere | **untouched**, as instructed |

Files changed in this correction pass: the migration, `AdminRecordConstraint`, `AdminRecordAuthorizer`,
`AdminServiceProvider`, and the new `AdminAuditSchemaDriftTest`.

---

## 12. Local deployment verification (2026-10-12)

Production deployment is **intentionally postponed**; production was neither modified nor queried at any
point. Everything below ran against the local development environment only.

### 12.1 Safety gate

| | Value |
|---|---|
| `APP_ENV` | `local` |
| Default connection | `sqlite` (migrations ledger: `database/database.sqlite`) |
| Resolved `main` | `mysql` @ `127.0.0.1:3307`, database **`olddb`** |
| Live server identity | `@@hostname` = a local container id · `DATABASE()` = `olddb` · `VERSION()` = **5.7.44** |

`olddb` is not the production database (`w2acp_db2025-2`), and the host is loopback. Gate passed; no
credential or secret was printed at any point.

### 12.2 Migration

`php artisan migrate` — one migration ran, `2026_10_12_100000_create_admin_audit_log_table`, 78.37ms.

- Recorded in the ledger once, batch 3.
- `admin_audit_log` created on `main`; table count 238 → 239.
- Produced schema matches the design exactly: all 10 columns with intended types (`metadata` as a real
  MySQL `json` column — supported since 5.7.8), `PRIMARY KEY (id)`, and all three indexes
  (`idx_audit_entity`, `idx_audit_actor`, `idx_audit_created`), `ENGINE=InnoDB`, `utf8mb4`.
- **No existing legacy table was altered.** Proven by checksumming `SHOW CREATE TABLE` for all 238
  pre-existing tables before and after: the diff is exactly one added line and zero modifications.
- **Second `php artisan migrate` reported `Nothing to migrate.`** — ledger entry count stayed 1, no
  recreation attempted, table untouched.

### 12.3 Seeder

`php artisan db:seed --class=AdminPermissionSeeder` — all six permissions created on guard `admin`
(ids 30–35). `movemirror` count: **0**. Admin permission total 29 → 35. Re-running the seeder left the
total at 35 with 0 duplicate names and grants unchanged — idempotent.

### 12.4 Zero automatic grants or imports

| Check | Result |
|---|---|
| Holders of any of the six permissions | **0** |
| `model_has_permissions` total | 111 → **111** (unchanged) |
| `model_has_roles` total | 5 → **5** (unchanged) |
| Local admins whose `nuke_authors.permissions` blob contains `addmirror` / `editmirror` / `delmirror` | **2 / 2 / 2** |
| …how many of those became a Spatie grant | **0** |

The last two rows are the strongest evidence in this verification: the local database genuinely contains
admins carrying the legacy mirror keys, and **not one of them received a grant.** Plan correction 1 holds
in practice, not only by assertion.

### 12.5 Configuration

`ADMIN_AUDIT_LEGACY_COMPAT_WRITES` is absent from `.env`, `env()` resolves `NULL`,
`config('admin_audit.legacy_compatibility_writes')` is `false`, and
`LegacyAuditLogCompatibilityWriter::enabled()` returns `false`. `nuke_islamic_logs` held **222,853** rows
before and after every step, with **0** rows containing `[laravel]`.

### 12.6 Permission UI

Exercised through the real `admin.permissions.edit` / `.update` routes, not just `PermissionLabels`. A new
test file, `MirrorPermissionUiPhase0Test` (5 tests), verifies: the three Khotab permissions and their real
legacy Arabic labels render; the new `anasheed` module block renders with its own three permissions; **all
six checkboxes render unchecked** for an admin with no grant; a permission can be granted and then removed
through the real update route (`syncPermissions` semantics); and **no query issued while rendering the
editor touches `nuke_islamic_mod`, `nuke_anasheed_mod`, `nuke_telawah_mod` or `nuke_w2acd_mod`** — asserted
by capturing SQL with `DB::listen`.

No real or shared admin account was granted anything. An interactive attempt to drive the route through
`php artisan tinker <file>` hung (that command drops into a REPL rather than exiting), was killed, and its
throwaway admin row was confirmed removed — `model_has_permissions` and `model_has_roles` were back at
baseline before continuing.

### 12.7 Audit behaviour against the real MySQL table

21 checks, all passing, written directly to the table the migration had just created: actor id/aid, action,
module, entity type, entity id, ip, and a unix-integer `created_at`; structured metadata round-tripping
through the MySQL `json` column including a nested before/after pair and a preserved boolean; markup in
metadata refused; a bare URL accepted; a non-positive entity id refused loudly.

**Rollback was then proven with a genuine content mutation**, not an audit-row stand-in: inside one
transaction on `main`, a real row was inserted into `nuke_islamic_mirror` (426,052 rows) and an audit row
recorded for it, both confirmed visible inside the transaction, then a failure was raised — **both
disappeared**, and both tables returned to baseline. A committed-path control confirmed both persist when
the transaction succeeds, after which both rows were deleted.

### 12.8 Cleanup

All verification rows removed. Final reconciliation: `admin_audit_log` **0 rows**; 0 surviving probe rows
in `nuke_islamic_mirror`; 0 surviving throwaway admin rows; 0 holders of the six permissions;
`model_has_permissions` 111; `model_has_roles` 5; `nuke_islamic_logs` 222,853; `nuke_islamic_mirror`
426,052. The only intended, persistent local changes are the new `admin_audit_log` table and the six
permission definitions.

Verification scripts were written to the session scratchpad, outside the project tree, and are not part of
the repository.

### 12.9 Regression gates, after local deployment

| Gate | Result |
|---|---|
| Focused Phase 0 tests (4 files) | **35 passed**, 156 assertions |
| Full suite | **1266 tests, 1265 passed, 1 skipped** (pre-existing) |
| PHPStan (level 5) | **0 errors** |
| Pint, Phase 0 files only (15) | **passed** |
| Pre-existing Pint violations elsewhere | untouched |

**PHPUnit did not mutate the local `main` table.** Verified by checksumming `SHOW CREATE TABLE
admin_audit_log` immediately before and after a full suite run with the table present:
`b63663966aaf6cde047b4fcbafd344cf` both times, 0 rows both times, with `nuke_islamic_mirror` and
`nuke_islamic_logs` row counts unchanged. This confirms the migration's `runningUnitTests()` skip behaves as
designed — the suite neither drops, recreates, nor writes to it.

### 12.10 Expected differences when this is eventually deployed to production

1. **The migrations ledger moves.** Locally the default connection is `sqlite`, so the ledger lives in
   `database/database.sqlite`; in production the default connection is MySQL and the ledger is a table
   there. The mechanism is identical — only the ledger's location differs.
2. **`main` resolves to `w2acp_db2025-2`** rather than `olddb`. The migration is connection-agnostic, so no
   code change is needed, but the deploy must confirm the resolved target before running (same gate as
   §12.1).
3. **MySQL version.** Local is 5.7.44. If production runs a different major version the `json` column type
   still applies (5.7.8+ and all of 8.x), but the production version should be confirmed as ≥ 5.7.8 before
   migrating.
4. **The table does not yet exist in production, and must not.** Per the correction pass, the migration has
   no `hasTable()` guard — if `admin_audit_log` already exists there, the migration **will fail loudly**,
   and that is intended: it would mean something created the table outside this deployment.
5. **`nuke_authors.permissions` content differs.** Locally two admins carry the legacy mirror keys;
   production showed 7/6/6 (Pass 3E.2 E3). **The expected outcome is identical either way — zero grants** —
   because nothing imports them. Production verification should confirm the holder count is 0, not that it
   matches any legacy figure.
6. **Production data volumes are larger** (`nuke_islamic_logs` 253,499 vs 222,853 local;
   `nuke_islamic_mirror` 432,974 vs 426,052). Nothing in Phase 0 scans either table, so no behavioural
   difference is expected.
7. **`php artisan config:clear` is required in production** so `config/admin_audit.php` is picked up if the
   config cache is warm. Locally no config cache was in play.
8. **The `runningUnitTests()` skip is inert in production** — `APP_ENV=production` and no PHPUnit, so the
   migration runs normally. It cannot mask a production schema conflict.

---

## 13. Phase 1 implementation record — Mirror Management (2026-10-13)

Status: **IMPLEMENTED / LOCAL VERIFIED / PRODUCTION DEPLOYMENT PENDING** (review accepted; see §14). Not
CLOSED — production verification outstanding (§13.15). Phase 0 remains **IMPLEMENTED / LOCAL VERIFIED / PRODUCTION DEPLOYMENT PENDING**; the two are
intended to ship together in one controlled window.

Built entirely from the closed contracts (3E.1, 3E.2, corrected plan, Phase 0). No forensic investigation
was reopened, no production query was run, OLD was not modified, no legacy grant was imported. One
contract detail 3E.1 under-specified was read directly from the operation's own source (§13.9).

### 13.1 Architecture

`Controller → FormRequest → Action → Model/DB`, with no generic CRUD layer. Seven explicit actions — one
per operation, per domain — because the two domains' legacy behavior genuinely differs. The only shared
code is `MirrorParentCounter`: the ±1 counter arithmetic and the MB→bytes conversion, written once because
the subtle parts must not diverge.

| Layer | Khotab | Anasheed |
|---|---|---|
| Controller | `KhotabMirrorController` | `AnasheedMirrorController` |
| FormRequests | `StoreKhotabMirrorRequest`, `UpdateKhotabMirrorRequest`, `MoveKhotabMirrorRequest` | `StoreAnasheedMirrorRequest`, `UpdateAnasheedMirrorRequest` |
| Actions | `InsertKhotabMirrorAction`, `UpdateKhotabMirrorAction`, `DeleteKhotabMirrorAction`, `MoveKhotabMirrorAction` | `InsertAnasheedMirrorAction`, `UpdateAnasheedMirrorAction`, `DeleteAnasheedMirrorAction` |
| Models | existing `Mirror`, `KhotabItem` — unchanged | existing `AnasheedMirror`, `AnasheedItem` — unchanged |

These are the **first FormRequests in the codebase**; controllers previously validated inline. Their
`authorize()` returns `true` deliberately — authorization already lives in route middleware and in
`AdminRecordAuthorizer` at the action boundary, and a third check could only drift from those two. Delete
has no FormRequest because it takes no input; route-model binding validates the target.

### 13.2 The seven operations

| # | Operation | Writes | Parent | Audit rows |
|---|---|---|---|---|
| 1 | Khotab insert | `khid, link, comment, linksize (MB→bytes), vedio, backupme, time` | `mirror+1`, `lastmirror=now` | `add_mirror` mirror + item |
| 2 | Khotab edit | `comment, link, vedio, backupme, trial=0` — **not** `linksize` | none | `edit_mirror` mirror, from/to diff |
| 3 | Khotab delete | physical `DELETE` | `mirror-1` if parent exists | `del_mirror` mirror (+ item if parent exists) |
| 4 | Khotab re-parent | `khid` | target `+1`, source `-1` if exists; no `lastmirror` | `move_mirror` mirror |
| 5 | Anasheed insert | `khid, link, title, linksize (MB→bytes), vedio, backupme, time` | `mirror+1`, `lastmirror=now` | `add_mirror` mirror + item |
| 6 | Anasheed edit | `title, link, vedio, backupme, trial=0`, `linksize` (bytes, `down`-gated — §13.9) | none | `edit_mirror` mirror, diff + `size_edit_accepted` |
| 7 | Anasheed delete | physical `DELETE` | `mirror-1` if parent exists | `del_mirror` mirror (+ item if parent exists) |

Edit, delete and re-parent re-read the mirror **under a row lock** (`lockForUpdate`) inside the transaction.
OLD and Laravel can both write these rows during coexistence; the lock makes the audit's "before" values the
ones actually replaced, and makes a concurrent second delete a clean 404 rather than a double decrement.

### 13.3 3E.1 invariants

| Invariant | Preserved by |
|---|---|
| **I-1** scope = 7 ops, 2 domains | Exactly 15 routes; no Anasheed move/pick route exists (tested) |
| **I-2** relation is authoritative; counter is compatibility state | Only atomic `SET mirror = mirror ± 1`; never recomputed. A drifted counter of 7 with 2 real mirrors becomes 8 on insert, not 3 (tested). The item page shows the legacy counter **read-only** beside the real count |
| **I-3** `lastmirror` maintained on insert | Set on both inserts; untouched by edit, delete and re-parent, as in OLD (tested) |
| **I-4** tolerate a missing parent | Edit, delete and re-parent all work on orphans; delete/re-parent skip the decrement; the item-level audit row is skipped. A mirror-id lookup reaches orphans **without listing them** — robustness, not a cleanup feature |
| **I-5** no UNIQUE; no new rejection | No migration adds a constraint. Khotab's author-scoped guard on **insert only**; Anasheed unguarded (duplicate inserts on one item tested) |
| **I-6 / I-7** quality fields and `booking` never written | Verified at SQL level: the INSERT statement contains none of `down, trial, booking, online, percent, checktime, downloader, hits` (tested by capturing the statement). Edit writes only `trial = 0` |
| **I-8** bytes vs MB, and the Anasheed asymmetry | `(int)(1024*1024*(float)$mb)` — truncation, as OLD (1.5 MB → 1572864; tiny values → 0, tested). Khotab edit has no size field; Anasheed edit takes bytes |
| **I-9** `hits` never an admin field | Never written |
| **I-10** one transaction per operation | Mutation + parent counter + authoritative audit in one `main` transaction; rollback proven for all seven (§13.6) |
| **I-11** two-step re-parent, no serialized cookie | Pick stores a mirror id in the Laravel session; place runs from the target item's page. The move endpoint itself takes the target explicitly. No `MirrorMove` cookie is ever set (tested) |
| **I-12** label column differs | `comment` (Khotab) / `title` (Anasheed) |

**Duplicate guard, reproduced exactly (3E.1 §3.5).** Same author scope across all the author's items; inner
join, so orphans are invisible to it; and OLD's own coercion `(int)($item->author ?? 0)`. Production's
`nuke_islamic_khotab.author` **is nullable** — the shared test fixture's comment records `author int(6)
DEFAULT NULL` with 2 NULL rows in production — which closes 3E.1's open nullability question. Consequence,
reproduced and tested: two NULL-author items never match each other, while a NULL-author item *is* compared
against literal-0 authors. Closing that gap would be new rejection the contract does not authorize.

**One deliberate divergence: the decrement floors at zero.** OLD's bare `mirror - 1` would take a 0 counter
negative; on an unsigned column under strict SQL mode that is an error, which would abort the transaction
and make the mirror undeletable. Production has exactly this state (Check D1: 2 Khotab parents with
`mirror = 0` that own mirrors). The floor changes nothing above zero and repairs nothing — it only refuses
to make a wrong value more wrong. Written as `CASE WHEN mirror > 0 …` so the same SQL runs on MySQL and SQLite.

**Size validation ceilings.** Anasheed insert caps at **2047 MB**, because production's
`nuke_anasheed_mirror.linksize` is a signed INT (3E.1 §0.3) and 2048 MB would overflow it — a strict-mode
error that would abort the insert, now a validation message instead. Khotab (BIGINT) has only a 1 TB sanity
bound. Insert requires a non-empty `link`; edit does not, so a pre-existing empty link stays editable.

### 13.4 Authorization

Flat permissions, D1b′ closed: insert → `*.addmirror`, edit → `*.editmirror`, delete → `*.delmirror`,
**Khotab re-parent → `khotab.editmirror`**. Enforced twice and identically: route middleware
`admin.permission:<perm>`, then `AdminRecordAuthorizer::authorize()` inside each action. Viewing an item's
mirrors accepts any of that module's three permissions, so a delete-only admin can still reach something to
delete. No `AvAdmin()`, no `*_mod` read, no audio/video constraint, no `movemirror`, no legacy import.

### 13.5 Transactions

Every action opens one `DB::connection('main')->transaction()` containing the mirror write, the parent
counter update(s) and the authoritative audit write(s). The audit table lives on `main` (Phase 0) precisely
so it can join this transaction. If the authoritative audit write fails, everything rolls back — proven for
all seven operations in tests, and on real MySQL by forcing an audit failure: mirror row, counter **and**
`lastmirror` all reverted.

### 13.6 Audit

The established vocabulary — `add_mirror`, `edit_mirror`, `del_mirror`, `move_mirror` — with module
`khotab`/`anasheed`, the real actor, request IP, and structured metadata. Corrections to legacy, as the
contract required:

- **Target attribution fixed.** Mirror rows carry the mirror's own id. OLD's Khotab insert logged the parent's
  id here. Tested with deliberately divergent ids so a mix-up would be visible.
- **Granularity fixed for Anasheed.** Khotab's dual-row convention (mirror + item) now applies to both
  domains; OLD's Anasheed logged only against the parent.
- **No markup.** URLs appear only as structured values (edit from/to, delete snapshot). Probed on the real
  local data first: **0** of the 426,052 Khotab and 18,807 Anasheed mirror links or labels contain markup, so
  the recorder's markup refusal cannot block any real edit or delete.
- Edit records only fields that actually changed; Anasheed edit also records whether the size edit was
  honoured.

### 13.7 Legacy compatibility log — decision

Still **default-OFF**; not enabled locally or globally. Phase 1 changes *how a failure behaves*, because there
are now real callers:

**Phase 0 left the legacy write unisolated inside the caller's transaction.** That meant an *optional* log, in
a table this project does not own, could roll back a real content change and its authoritative audit row —
inverting the priority. **Phase 1 runs it in a savepoint.** On success it commits atomically with the mutation
as before; on failure only the savepoint rolls back, the mutation and authoritative row still commit, and the
failure goes to `report()` as `LegacyAuditLogWriteFailed`, carrying the authoritative entry id. Not silent, and
not able to compromise the authoritative transaction. A failure of the *authoritative* write still rolls
everything back — unchanged.

Two further changes so a compatibility row is actually useful to OLD: `module` is written in OLD's spelling
(`Khotab`, `Anasheed` — `$module_name` is the capitalised module directory), since OLD's views filter on that
exact string; and `target_id` comes from the corrected authoritative entity id. The `hidden` flag stays `'0'`
for every row — OLD's own per-row values were inconsistent and not worth reproducing.

Verified against the **real** `nuke_islamic_logs` schema on local MySQL inside a deliberately rolled-back
outer transaction: both rows accepted, module `Khotab`, target = mirror id, no URL, no markup.

### 13.8 UI

`/admincp/mirrors/khotab` and `/admincp/mirrors/anasheed`: a lookup page (by item id, or by mirror id — the
latter is how an orphan is reached); an item page listing its mirrors with add, edit, delete and (Khotab)
pick-to-move; an edit page, which for an orphan also offers delete and a direct move form. Controls render
only for the permissions the admin holds. Validation errors, success and error flashes all surface. Sidebar:
**الوصلات البديلة** under المرئيات, and a new **الأناشيد** parent. Functional parity, not OLD's visual design.

### 13.9 Owner question raised — Anasheed edit `linksize` gate *(CLOSED — see §14.1)*

3E.1 recorded only that "Anasheed edit rewrites `linksize`". The operation's own source
(`modules/Anasheed/admin/index.php:272-286`) is more specific, and internally contradictory:

```php
$linksize = intval($linksize);          // bytes — the edit form pre-fills the stored byte value
if ($Mirror[down] != 1) {
    // "a size cannot be edited once the item has been fetched into the site backup"
    $linksize = $Mirror[linksize];       // ...yet this keeps the old size when down != 1
}
```

The comment says sizes are locked **after** backup (`down = 1`); the code locks them **before**. Laravel
follows the **code**, because that is what the 191 production `edit_mirror` operations actually did, and the
edit page shows the size field only for mirrors whose size would be honoured. **Which one was meant is an
owner decision**, and it touches the backup programme: `down = 1` rows are the ones whose `linksize` was set
from a measured size by the backup API, so the code's behavior lets an admin overwrite a measured value.
Changing it later is one line in `UpdateAnasheedMirrorAction::acceptsSizeEdit()`.

### 13.10 Results

| Gate | Result |
|---|---|
| Phase 1 focused tests (4 files) | **111 passed**, 320 assertions |
| Phase 0 focused tests (regression) | **35 passed**, 156 assertions |
| Full suite | **1377 tests, 1376 passed, 1 skipped** (pre-existing) |
| PHPStan (level 5) | **0 errors** |
| Pint, Phase 1 files | **passed** |
| `routes/admin.php` | Edited by Phase 1 but already among the pre-existing `ordered_imports` violations at `HEAD` (`BroadcastingController` before `AdminStaffController`; `UploaderController` before `LocationsController`). **Not reformatted**; Phase 1's two imports were placed in correct order relative to their neighbours |

**Existing tests changed, and why — none weakened.** `AdminAuthControllerTest` asserted exactly 5 grouped
sidebar parents; the new الأناشيد parent makes 6 *by the very `sidebar.php` rule that test protects*. Updated to
6, and strengthened to assert الأناشيد and both new routes by name. `AdminAuditRecorderTest`'s compatibility-row
test now expects module `Khotab`, reflecting the deliberate §13.7 mapping. Shared fixtures gained two real,
production-confirmed columns: `nuke_anasheed_anasheed.lastmirror` and `nuke_anasheed_mirror.time`.

**Errors made during development, fixed properly.** Two audit tests failed on my own test design, not on the
code: on a fresh database the first item and first mirror are both id 1, so a "different ids" precondition
could not hold — fixed with decoy rows; and I had miscounted an expected audit total (a delete writes two rows
when the parent exists). A stray `->skip(fn () => false)` was removed.

### 13.11 Local runtime verification (real local MySQL)

Safety gate: `APP_ENV=local`, `main` = `127.0.0.1:3307/olddb`, MySQL 5.7.44, compat off.

**Phase 0 checksum note.** The `admin_audit_log` checksum differed from the one recorded in §12.9. Diagnosed
before any write: `SHOW CREATE TABLE` includes the volatile `AUTO_INCREMENT=N` counter, which Phase 0's
verification rows had advanced and which MySQL 5.7 does not persist across a restart (server uptime was ~23
minutes). With the counter stripped, the checksum is `a9f9ebb3d7c08f0230cb60a0d547ec61` — **exactly** the
migration's own output in §12.2's before/after diff. Schema unchanged. §12.9's "PHPUnit did not mutate the
table" result still holds, since both of its readings came from one server session; checksums are normalized
from here on.

All seven operations ran through the real actions against real items, then were fully reverted —
**42 checks, all passing**: exact column values, MB→bytes, `down`/`trial` left at their production defaults
(2/0), `booking` untouched, counter and `lastmirror` arithmetic per operation, the duplicate guard rejecting a
real same-author link with nothing written, Khotab edit leaving `linksize` alone, Anasheed's `down = 2` size
edit ignored, 15 authoritative audit rows with the real actor and mirror-id attribution, valid JSON and no
markup, the forced-audit-failure rollback, and compatibility rows against the real legacy schema.

**Performance at production scale:** the duplicate-guard query takes **29.8 ms**, driving from
`idx_khotab_author_listing` (covering) into `idx_mirror_khid`. It never scans the 426k-row mirror table.

**Two honest caveats about the verification script itself.** One PASS line ("duplicate link on same item
accepted") was hard-coded and proved nothing by itself; the real evidence is the following check, where a
second insert of the same link on the same item succeeded and the counter rose by 2. And the script called
`assignRole('super-admin')` on a real admin row while *asserting* idempotence without checking it. Checked
afterwards: that admin is one of the five `radminsuper = 1` accounts and already held the role;
`model_has_roles` is **5**, identical to the Phase 0 baseline. No change resulted — by luck, not by design,
and a re-run script should read rather than assign.

### 13.12 Cleanup

Every verification row removed and every touched value restored exactly — the three items' `mirror` and
`lastmirror`, both mirror tables (426,052 / 18,807), `admin_audit_log` back to 0, `nuke_islamic_logs` at
222,853, `model_has_permissions` 111, `model_has_roles` 5, no probe link surviving. A full suite run with the
table present left it byte-identical (normalized checksum before and after, 0 rows).

### 13.13 Changes beyond Phase 0

**No migration, no seeder change, no config change.** Phase 1 needs none: the six permissions, the audit table
and the compatibility flag all shipped in Phase 0.

### 13.14 Production deployment order — Phase 0 + Phase 1 together

1. **Pre-flight (read-only):** confirm `main` resolves to `w2acp_db2025-2`; MySQL ≥ 5.7.8 (for the `json`
   column); `admin_audit_log` does **not** already exist; `ADMIN_AUDIT_LEGACY_COMPAT_WRITES` unset or `false`.
2. Deploy the code.
3. `php artisan migrate` — creates `admin_audit_log` on `main`. Fails loudly if the table already exists.
4. `php artisan db:seed --class=AdminPermissionSeeder` — six permission definitions. Idempotent.
5. `php artisan config:clear` and `php artisan route:clear` (Phase 1 adds 15 routes; a warm route cache would
   hide them) — or rebuild both caches if production uses them.
6. Grant mirror permissions to the intended admins through the Laravel permission editor. **No legacy grant is
   imported**; until someone is granted, only super-admins can administer mirrors.

### 13.15 Production verification

1. `admin_audit_log` exists with its three indexes; normalized checksum matches
   `a9f9ebb3d7c08f0230cb60a0d547ec61` if production's MySQL renders `SHOW CREATE TABLE` identically.
2. Six permissions on guard `admin`; **zero** holders until granted.
3. A super-admin opens `/admincp/mirrors/khotab` and `/admincp/mirrors/anasheed`; the sidebar shows both.
4. On one agreed Khotab item: insert a mirror, edit it, move it to a second agreed item, delete it. Confirm
   both items' `mirror` and `lastmirror` end where expected and `admin_audit_log` holds 6 rows with the
   correct actor and mirror id. Repeat insert/edit/delete on one Anasheed item (5 rows).
5. `nuke_islamic_logs` received **no** `[laravel]` rows.
6. Confirm OLD still administers the same mirrors normally — coexistence, not replacement.
7. Remove the verification mirrors through the Laravel UI (so the removal is itself audited).

---

## 14. Phase 1 review outcome (2026-10-13)

Phase 1 review **accepted**. No code was changed by this section.

### 14.1 Decision — Anasheed edit `linksize`: current implementation APPROVED

The executable OLD behavior is preserved exactly as Phase 1 implements it:

- the edit form and input use **bytes**;
- the submitted `linksize` is applied only under OLD's executable condition, **`down = 1`**, otherwise the
  stored size is kept.

**The condition is NOT inverted** to match the contradictory legacy comment. Reason: *Behavior First* — the
executable legacy behavior is the parity source unless an explicit business decision overrides it, and a
contradictory comment is insufficient evidence for changing behavior.

If the business later decides that backup-measured sizes must become immutable, that is a **new business-rule
change**, not a Phase 1 parity correction. It would be implemented in
`UpdateAnasheedMirrorAction::acceptsSizeEdit()`.

**Phase 1 has no remaining implementation blocker.**

### 14.2 Verification-process lessons

From the Phase 1 local verification (§13.11). These are safeguards for how verification is performed; they
require no change to production code.

1. **Verification assertions must never contain hard-coded PASS results.** Every reported PASS must be the
   result of a real check against real state.
2. **Verification must never mutate roles or permissions on an existing real or shared admin** on the
   assumption that the state is already present. Read and confirm first, or use an isolated, disposable test
   identity that is removed afterwards.

### 14.3 Current status

| Phase | Status |
|---|---|
| **0** — Administration Authorization & Audit Foundation | **IMPLEMENTED / LOCAL VERIFIED / PRODUCTION DEPLOYMENT PENDING** |
| **1** — Mirror Management (Khotab + Anasheed) | **IMPLEMENTED / LOCAL VERIFIED / PRODUCTION DEPLOYMENT PENDING** |

Both deploy together in one controlled production window (§13.14) and are verified per §13.15. Neither is
marked CLOSED until that production verification is complete. Phase 2 has not started.

---

## 15. Phase 2 implementation record — Administration Reporting & Observability (2026-10-13)

Status: **IMPLEMENTED / LOCAL VERIFIED / PRODUCTION DEPLOYMENT PENDING.** Not CLOSED. Phase 0 and Phase 1
statuses are unchanged and their code was not modified.

### 15.1 Scope implemented — exactly the plan's three surfaces

| Plan surface (§3 Phase 2) | Implemented as | Source of the behavior |
|---|---|---|
| **`down = 0` lost-link worklist report** | `GET /admincp/link-quality/khotab/lost` | The listing half of OLD `KhotabLostedLink` (3D §2.2): `nuke_islamic_khotab WHERE down = 0 ORDER BY id ASC`, 50 per page, OLD's own select list and headline total |
| **Moderator action-log view** | `GET /admincp/audit` — the **authoritative** Laravel trail (`admin_audit_log`); and `GET /admincp/audit/legacy` — a read-only view of `nuke_islamic_logs` | The legacy page reproduces OLD `listlogs()` (`includes/logs.php:103`) exactly. The authoritative page is the Phase 0 trail, which Phase 1 writes |
| **Contextual access to existing recheck / fix-size** | `GET /admincp/link-quality/khotab/items/{item}` — per-item link health | Posts to the **existing, unchanged** `LinkQualityStatsController` routes |

Not built, by design: OLD's lost-link repair form (no handler ever existed — 3D §2.2), OLD's per-row edit-item
link (item CRUD is externally owned, BC#6), and any new analytics, monitoring, background job, or OLD log
migration.

**One narrow source read, of the function this surface replaces.** The plan's own test scope names "log-view
scoping and the `hidden` flag", so OLD `listlogs()` was read to reproduce it rather than guess. It fully
determines the legacy view: super-admin only; one module per view; `hidden = '0'` **always**; optional
filters on moderator, action, target id and type; newest first; 200 per page.

### 15.2 Architecture

`Controller → FormRequest → Model/DB`, read-only, with no generic reporting framework. Queries are written
explicitly in the two controllers, following the existing `LinkQualityStatsController` convention. Two small
support classes isolate the only logic worth testing on its own.

| Kind | Added |
|---|---|
| Controllers | `AdminAuditLogController` (`index`, `legacy`), `KhotabLinkReportController` (`lostLinks`, `item`) |
| FormRequests | `Reports/AdminAuditLogFilterRequest`, `Reports/LegacyAuditLogFilterRequest` |
| Support | `AuditMetadataPresenter` (key-name redaction, structure-preserving), `AuditEntityLinks` (batched entity existence and links) |
| Views | `admin/audit/{index, legacy, _metadata_tree}`, `admin/link-reports/{lost, item}` |
| Routes | 4 GET routes (`admin.audit.index`, `admin.audit.legacy`, `admin.link-quality.khotab.lost`, `admin.link-quality.khotab.item`) |
| Sidebar | **الوصلات التي لا تعمل** under المرئيات; flat **سجل الإدارة** entry |

**No migration, no index, no seeder, no config change. No new permission.**

### 15.3 Authorization

| Page | Gate | Why |
|---|---|---|
| Audit trail, legacy log | `admin.role:super-admin` | Matches OLD: `listlogs()` returned nothing unless `radminsuper === 1`. It is the same role gate as the permission editor. OLD's other two exits from that check — a hard-coded exception for one named account and a `$Pass` bypass parameter — are **not** reproduced |
| Lost-link report, item link health | `admin.permission:khotab.repair` | OLD's screen sat in the Khotab "الصيانة" (maintenance) section and called no `GreenZone()`. `khotab.repair` ("صيانة الوصلات") is the existing Laravel permission for this capability, and gating it is the ratified hardening (decision-log #10) |

**Sidebar without an invented permission.** The audit entry is gated by a *role*, which `AdminDashboardModules`'
permission filter cannot express. That is why the permission editor was left out of the sidebar. The audit
entry uses `permissions => []`: `visibleFor()` then shows it only through its existing super-admin branch,
because `hasAnyPermission([])` is false for everyone else. No key is invented, and the route still enforces the
role itself.

### 15.4 Data sources and queries

- **Authoritative trail:** `admin_audit_log` with the actor eager-loaded. Entity existence is resolved by
  `AuditEntityLinks` with **one `whereIn` per (module, entity type) pair present on the page** — four at most.
  It is never the source for legacy rows, and the legacy table is never its source.
- **Legacy log:** `nuke_islamic_logs`, explicit column list, `module = ? AND hidden = '0'`, optional filters.
  The three modules (`Khotab`, `Anasheed`, `Cat`) are the callers of `listlogs()`. That list is fixed rather
  than queried, which avoids a `DISTINCT` scan over 253k rows, and it was **confirmed on the local copy**:
  exactly these three modules are present.
- **Lost-link report:** `nuke_islamic_khotab` with author, group and series eager-loaded. OLD ran three queries
  per row for these.
- **Item link health:** the item and its mirrors; quality values shown as stored, with no invented labels.

### 15.5 Filtering, pagination and ordering

| Page | Filters (validated) | Pagination | Order |
|---|---|---|---|
| Audit trail | module, action, entity type, entity id, actor id, from/to date | `paginate(50)`, query string kept | `created_at DESC, id DESC`. Stable because `id` is unique, and served by Phase 0's `idx_audit_created` / `idx_audit_actor` |
| Legacy log | module (one of three), aid, action, target id, type | **`cursorPaginate(200)`** on the primary key | `id DESC` |
| Lost links | none (OLD had none) | `paginate(50)` with total (OLD's headline) | `id ASC` |

**Cursor pagination on the legacy log** means a deep page costs the same as the first one: each page is
`id < cursor` on the primary key. OLD fetched every matching row just to count them and then used `OFFSET`.
The trade-off is that the page shows previous/next links instead of page numbers.

### 15.6 Audit rendering and safety

- **Nothing is output raw.** Every value goes through escaped `{{ }}`, and no Phase 2 view contains `{!! !!}`
  (checked). This holds even for values the Phase 0 recorder would refuse: tests store `<script>` and
  `<img onerror>` directly in the table and assert they render escaped.
- **Metadata stays structured** and is rendered as a recursive key/value tree, including nested from/to diffs.
- **Sensitive keys are redacted by whole name**, case-insensitively (`password`, `token`, `api_key`, `secret`,
  `cookie`, …) and at any depth. Nothing Phase 1 records today is secret, but a value must not appear just
  because it is in metadata. Ordinary values such as mirror links stay visible.
- **Legacy `extrainfo`** is OLD's rendered HTML string. Tags are stripped and the remaining text escaped, so it
  is never output raw. The page carries an explicit "legacy — not the authoritative record" banner.
- **Missing references:** if the actor row is gone, the stored `actor_aid` is shown with "(حساب محذوف)". This is
  exactly why Phase 0 stores both. A deleted entity is marked "(محذوف)" with no link. An unknown module/type
  pair is plain text and claims nothing.

### 15.7 Results

| Gate | Result |
|---|---|
| Phase 2 focused (3 files) | **47 passed**, 205 assertions |
| Phase 0 regression | **35 passed**, 156 assertions — identical to the accepted baseline |
| Phase 1 regression | **111 passed**, 320 assertions — identical to the accepted baseline |
| Full suite | **1424 tests, 1423 passed, 1 skipped** (pre-existing) |
| PHPStan (level 5) | **0 errors** |
| Pint, Phase 2 files | **passed**. `routes/admin.php` keeps its pre-existing `ordered_imports` disorder from `HEAD`, untouched; Phase 2's two imports are ordered correctly relative to their neighbours |

**Both N+1 guards were mutation-tested.** Removing the eager loads makes the audit page issue 54 queries instead
of 8, and the lost-link report 95 instead of 5; both tests then fail. One of them had first **passed by
coincidence**: a one-time cached role lookup on the first request and an extra entity query on the second
cancelled each other out. It was rewritten with a warm-up request and identical entity-type mixes, so equal
counts can only mean there are no per-row queries.

**Test-design errors fixed during development, none weakening a test.** The view shows the actor's *current*
`aid` when the actor exists, and the harness defaulted to the logged-in admin's id, so marker aids never
rendered. Fixed by using non-existent actor ids and markers long enough not to occur elsewhere in the page.

### 15.8 Local runtime verification (real local MySQL, real HTTP kernel)

Safety gate: `APP_ENV=local`, `main` = `127.0.0.1:3307/olddb`, sessions on `file` (rendering writes no rows),
compatibility flag off. **All state was read first, only disposable identities were used, and every PASS was
computed — none hard-coded (§14.2).**

The first attempt stopped safely at identity creation. Real `nuke_authors.uid` and `thumb` are `NOT NULL` with no
default, which the test fixture relaxes. The failed `INSERT` wrote nothing (`nuke_authors` stayed at 48), and the
script was corrected to supply both columns.

All checks passed, among them:

- Two disposable admins were created: a super-admin and a `khotab.repair`-only admin. The super-admin produced
  **five genuine audit rows** through the frozen Phase 1 actions on a real item.
- **Audit trail** (67 ms): it shows who changed the mirror, all three actions, the edit's structured from/to, the
  deleted mirror marked, and the live item linked. Filtering returns only the edit. It returns **403** for the
  repair-only admin.
- **Legacy log**: the first page equals **exactly** the 200 newest visible Khotab rows, compared row by row
  against a direct query. Five further cursor pages took 12–17 ms each. The Anasheed and Cat views render.
- **Lost-link report** (274 ms): the headline total reconciles with a direct `COUNT` (**29,370**), and the first
  page equals exactly the 50 lowest-id `down = 0` items.
- **Item link health** (26 ms): the recheck form targets the existing route. The frozen Phase 1 item page still
  renders.
- **Zero writes** on `main` and on the default connection across every report GET.

**Recheck was deliberately not triggered at runtime.** The local queue is `database`, so it would insert a job,
and a worker would then make an outbound request and rewrite a real mirror's quality values. The feature tests
cover that path with `Bus::fake()`.

### 15.9 Open integration decision — the Phase 1 item page *(CLOSED — see §16)*

The plan attaches contextual recheck to **Phase 1's item page**; Phase 1 is frozen. Rather than edit a frozen
file, the contextual actions live on a new Phase 2 **item link-health page**. It is reachable from every
lost-link row and links back to the Phase 1 page. **Nothing in Phase 1 links to it yet.** Completing the plan's
literal closure criterion ("trigger a recheck from the item page") needs one link added to
`resources/views/admin/mirrors/khotab/show.blade.php`, a frozen Phase 1 file. That needs the owner's go-ahead.
The change would be additive and would not alter any Phase 1 behavior.

### 15.10 Performance review items — documented, not acted on

Measured on the local copy; **no index was added** (production indexes on these tables are unconfirmed):

| Observation | Measured | Candidate, for review only |
|---|---|---|
| Lost-link `COUNT(*) WHERE down = 0` is a full scan (`type=ALL`, 289,891 rows; no index on `down`) | 174–430 ms; report total 274 ms | an index on `nuke_islamic_khotab(down)`. OLD paid the same cost on every page |
| `nuke_islamic_logs` has **only a PRIMARY key** locally; a filter matching nothing scans the whole table | 342 ms worst case; a rarer module's first page 207 ms | `(module, hidden, id)`. Only if production also lacks it and the views are used enough to matter |

Both pages are maintenance/super-admin pages and are acceptable as they stand. Any index belongs to a separate,
evidence-based production review, not to this phase.

### 15.11 Cleanup

Everything was restored to the values read at the start: `nuke_authors` 48, `model_has_roles` 5,
`model_has_permissions` 111, `admin_audit_log` 0, `nuke_islamic_mirror` 426,052, `nuke_islamic_logs` 222,853,
`jobs` 0. The touched item's `mirror` and `lastmirror` are back exactly, no probe link remains, and no disposable
identity remains. The `admin_audit_log` normalized checksum is still `a9f9ebb3d7c08f0230cb60a0d547ec61`.
**OLD was not modified, and production was neither queried nor touched.**

### 15.12 Production deployment impact and verification

**Impact:** code only. There are no migrations, seeders or config keys, and no new permission. It adds 4 GET
routes, so the route cache must be cleared or rebuilt. It ships with Phases 0 and 1 in the same window, after
their steps (§13.14).

**Verification after deploy:**
1. A super-admin opens **سجل الإدارة**. After the Phase 1 verification mutations (§13.15), the trail shows them
   with the correct actor, actions and entity links.
2. The legacy view renders recent real rows for Khotab, Anasheed and Cat, and shows no `hidden = 1` rows.
3. A non-super-admin gets **403** on both log pages.
4. The lost-link report total **reconciles against a direct aggregate**:
   `SELECT COUNT(*) FROM nuke_islamic_khotab WHERE down = 0`. This is the plan's own verification item.
5. Open one item's link-health page; confirm the recheck form renders. Trigger a recheck only on an agreed item,
   since it queues a real job.
6. Time the lost-link report and one legacy filter against §15.10, and decide whether the index review is
   warranted.

---

## 16. Phase 2 review outcome and item-page integration (2026-10-13)

Phase 2 review **accepted**. The integration decision in §15.9 was approved and completed.

### 16.1 The integration change

A link from the Phase 1 Khotab item page (`resources/views/admin/mirrors/khotab/show.blade.php`) to the existing
Phase 2 link-health page (`admin.link-quality.khotab.item`). That is the whole change:

- **link only:** no recheck or fix-size logic is duplicated, and nothing is triggered when the page renders
  (tested: zero writes, no job dispatched);
- **no new route**;
- **shown only to admins who may open its target:** the view asks the same `AdminRecordAuthorizer` that backs
  the routes — super-admin, or holder of `khotab.repair` — so the link never leads to a 403 (tested both ways,
  including that the target is in fact refused to a mirrors-only admin).

**Phase 1 remains frozen.** Its view was touched **only as this approved Phase 2 integration point**. No Phase 1
controller, action, FormRequest, route, authorization rule, transaction or audit behavior changed, and the new
tests live in the Phase 2 test file, not in Phase 1's. Phase 1 regression is identical to its accepted baseline
(111 tests, 320 assertions).

This closes the master-plan criterion: **contextual recheck is reachable from the item page.**

### 16.2 Performance review items — remain review-only

Both items in §15.10 stay **documented review items only**: the lost-link `down = 0` count without an index, and
legacy `nuke_islamic_logs` filtering without supporting indexes. No index was added, no pagination was changed to
hide them, no production query was run, and no performance investigation was started. The observed local
timings are accepted for these administration/maintenance surfaces at this stage.

### 16.3 Results

| Gate | Result |
|---|---|
| Affected Phase 2 tests (`KhotabLinkReportPhase2Test`) | **19 passed**, 74 assertions (15 existing + 4 new) |
| Phase 1 regression | **111 passed**, 320 assertions — identical to baseline |
| Pint, changed PHP file | **passed** (the Blade view is not Pint-formatted) |

A full-suite rerun was not required: the change is one view link plus tests, and caused no unexpected failures.

### 16.4 Status

| Phase | Status |
|---|---|
| **0** — Administration Authorization & Audit Foundation | **IMPLEMENTED / LOCAL VERIFIED / PRODUCTION DEPLOYMENT PENDING** |
| **1** — Mirror Management (Khotab + Anasheed) | **IMPLEMENTED / LOCAL VERIFIED / PRODUCTION DEPLOYMENT PENDING** (frozen) |
| **2** — Administration Reporting & Observability | **IMPLEMENTED / LOCAL VERIFIED / PRODUCTION DEPLOYMENT PENDING** |

All three deploy together and are verified per §13.15 and §15.12. None is CLOSED before production verification.
Phase 3 has not started.

---

## 17. Phase 3 prerequisite — Category Assignment (OA-3) source contract (2026-10-13)

Status: **STOPPED before mutation code**, per the Phase 3 stop rule. The narrow prerequisite is complete except
for one item that repository source cannot settle (§17.4). Phases 0–2 are untouched.

### 17.1 Call sites and `{$type}` values

All handlers sit in one OLD dispatcher, `modules/Cat/admin/index.php:1218-1275`.

| Live call site | Operations emitted |
|---|---|
| Gen 1 `modules/Khotab/index.php` (item page; `ListCats()` for group/series pages) | `cat_add_to_item`, `cat_del_from_item`; `cat_add_to_sub{$type}` / `cat_del_from_sub{$type}` (`:404`, `:407`) |
| Gen 1 `modules/index.php` | the same set (`:722`, `:1020-1035`, `:346-347`) |
| Gen 1 `modules/Anasheed/index.php` | `cat_add_to_anasheed` (`:291`), `cat_del_from_anasheed` (`:396`) |
| Gen 2 `khotab/functions.php` | `cat_add_to_item` (`:250`), `cat_fastcopy` (`:104`, `:185`) |
| Gen 2 `anasheed/functions.php` | `cat_add_to_anasheed` (`:144`) |
| Gen 2 `functions.php` `breadcrumb()` | `cat_add_to_sub{$type}` / `cat_del_from_sub{$type}` (`:485-496`) |

Not live: `khotab/old.php`, `channels/old.php` (unreachable snapshots, 3C §7); `modules/Khotab/_index.php`
(underscore backup copy); `modules/Locations/index.php:256-257` (`eregi()`-guarded, 3D §3.6).

**`{$type}` ∈ {`group`, `series`}.** Gen 1 calls `ListCats($Group['id'], 'group', …)` and
`ListCats($Series['id'], 'series', …)`; Gen 2 passes only `'series'` (`khotab/series.php:80`). So the bulk
operations are `cat_add_to_subgroup`, `cat_add_to_subseries`, `cat_del_from_subgroup` and `cat_del_from_subseries`.

### 17.2 Domain / table mapping and mutation semantics

| Operation | Writes | Category counter (`nuke_w2a_cat`) | Then |
|---|---|---|---|
| `cat_add_to_item` | `nuke_islamic_khotab.cat` | `video_count + 1` | repair the item's group and series (§17.3) |
| `cat_del_from_item` | same | `video_count - 1`, floored (`AND video_count > 0`) | same repairs |
| `cat_add_to_anasheed` | `nuke_anasheed_anasheed.cat_id` | `anasheed_count + 1` | — (no cascade) |
| `cat_del_from_anasheed` | same | `anasheed_count - 1`, floored | — |
| `cat_add_to_series` / `cat_del_from_series` | `nuke_islamic_series.cat` | `video_count ± 1` (− floored) | — |
| `cat_add_to_group` / `cat_del_from_group` | `nuke_islamic_groups.cat` | `video_count ± 1` (− floored) | — |
| `cat_add_to_subseries` | every Khotab item `WHERE ser_id = X` (hidden included) lacking the category | `+1` **per item changed** | then `cat_add_to_series` |
| `cat_del_from_subseries` | every item `WHERE ser_id = X` holding it | `-1` per item, floored | then `cat_del_from_series` |
| `cat_add_to_subgroup` | items `WHERE group_id = X AND ser_id = 0` only | `+1` per item | then `cat_add_to_group` |
| `cat_del_from_subgroup` | items `WHERE group_id = X AND ser_id = 0` only | `-1` per item, floored | then **`cat_repair_group`** — not `cat_del_from_group` (an OLD asymmetry) |

**Storage.** Each relationship is a pipe-delimited id list on the content row. Common rules across the
operations:

- **Validation:** the category must exist in `nuke_w2a_cat`, and the item must exist.
- **Duplicates:** adding a category that is already present is a silent no-op — no write, no counter, no log.
  Removing one that is absent is likewise a silent no-op.
- **Bulk input:** the bulk operations take no id list, so there is no "partially invalid input" case.
- **Readers:**
  - Khotab-item and series lists feed `khotab_category_index` / `series_category_index` through **database
    triggers** (`7amlat/db_migration.sql:196-381`). The triggers `TRIM(BOTH '|')` before splitting, so the
    junction tables accept either list format.
  - Groups and Anasheed have no junction table; their readers — OLD, Gen 2 and Laravel's group listing — match
    `LIKE '%|id|%'`, which needs a pipe on **both** sides of every id.

### 17.3 The repair cascade

`cat_repair_series` / `cat_repair_group` recompute the parent's `cat` as **the union of all its items' lists and
overwrite it** (`_islamic_khotab WHERE ser_id = X`, or `group_id = X` — all items in both cases). They run after
every item-level add or remove. Consequence, preserved as OLD behavior: a category assigned **directly** to a
series or group survives only until the next item-level change, unless one of the items also carries it. The
standalone `cat_repair_*` operations have no public call site; they are implemented only as this cascade.

### 17.4 The blocking ambiguity — which OLD version is the parity source

The list-writing code exists in **two versions** in the repository, and they produce different stored data:

| | Committed OLD (`fc633be`, 2025-08-26, the only commit) | Uncommitted working tree (PHP 8.3 modernization) |
|---|---|---|
| Item add | `implode('|', explode('|', $cat)) . "{id}|"` — a byte-preserving append | `implode('|', array_filter(explode('|', $cat), 'strlen')) . '|'` |
| Anasheed add | sets `$add_pref = '|'` to begin a new list with a leading pipe | `array_filter` variant, no prefix |
| Result for `\|3\|17\|` + 5 | `\|3\|17\|5\|` | `3\|17\|5\|` — **leading pipe lost** |
| Fast-copy | `'|' . implode('|', …) . '|'` | — |

The modified file is one of **42 uncommitted files** in `old-project` (+1,035 / −574 lines in this file alone).

The committed format is consistent with every remover (`str_replace('|id|', '|')`), every reader
(`LIKE '%|id|%'`), fast-copy and the triggers. The modernized writer is not. After any add or repair, the
**first** category in a list becomes:

- **unremovable** through OLD's own remover;
- **invisible** to every `LIKE`-based reader (groups in Laravel, item pages in Gen 1 and Gen 2).

Laravel's junction-table pages are unaffected, because the triggers trim.

**Repository source cannot tell which version runs on `old.way2allah.com`.** Earlier `old-project` edits
(Workstream C) were deployed file by file, so the working tree is not proof of what is live.

### 17.5 What would resume Phase 3

1. **Which version of `old-project/modules/Cat/admin/index.php` is deployed on old.way2allah.com** — the committed
   original or the modernized working copy? Owner knowledge, or a checksum of that one file compared with `fc633be`.
2. If it is the **committed original**: Laravel reproduces that format exactly. This is the recommendation in any
   case, because it is the self-consistent contract every reader and remover depends on.
3. If it is the **modernized copy**: an owner decision between reproducing the regression (the Behavior First
   precedent, §14.1) and the original format (the only one with which removal and the `LIKE` readers work).

A secondary scope confirmation can be given in the same answer: `cat_fastcopy` — a live, Gen 2-delegated per-item
category-copy matrix — is not named in the plan's OA-3 text. Include it in this unit or defer it?
`KhotabEditCat` → `EditCat()` edits a category's title and notes; that is category CRUD and is **out of scope**.

---

## 18. Phase 3 paused for an unrelated regression hotfix (2026-10-13)

Phase 3 remains **BLOCKED — prerequisite stop (§17)** and is **paused**. A production regression in the Nuke → OLD
admin authentication bridge — the AdminCP login POST leaving the Nuke origin, so the `Secure` `w2a_user` cookie
landed on HTTP — was fixed as a **separate hotfix**, recorded in `admin-cross-subdomain-auth-regression.md` §14. It
touched only `7amlat/admincp/w2a__login.php` and `7amlat/tests/bridge_checks.php`. No Phase 0–3 code changed.

---

## 19. Phase 3 prerequisite resolved by checksum — classification B (2026-10-13)

### 19.1 Result

| File | SHA-256 | vs production |
|---|---|---|
| Production `public_html/modules/Cat/admin/index.php` (read-only check by owner) | `8642725f6043121e4b22626533e42167efce3dcbf61dcc8cf98b49a6844d47df` | — |
| Local working copy `old-project/modules/Cat/admin/index.php` | `8642725f6043121e4b22626533e42167efce3dcbf61dcc8cf98b49a6844d47df` | **identical** |
| Committed `fc633be` version | `06db7a77b6941bce9c23d17bd3c5a11d98964f8ace6e61bfe42997b16c7566c9` | different (also under CRLF and trailing-newline normalisation) |

**Classification B: production = the current modernized working copy.** The deployed writer is
`implode('|', array_filter(explode('|', $cat), 'strlen')) . '|'`, which **drops the leading pipe**: `|3|17|` + 5 →
`3|17|5|`. §17.4's consequences are therefore **live in production**. After any Cat-admin add or repair, the first
category in that row's list becomes unremovable through OLD's own remover (`str_replace('|id|', '|')`), and
invisible to every `LIKE '%|id|%'` reader — group pages in Laravel and item pages in Gen 1 and Gen 2. Laravel's
junction-table pages are unaffected, because the triggers trim.

Per the stop rule, Phase 3 is **stopped before implementation**. Scope stays as decided: `cat_fastcopy` out,
`KhotabEditCat` out (category CRUD).

### 19.2 The decision required

| Option | Laravel writes | Removal of a first-position category | `LIKE` readers |
|---|---|---|---|
| **1. Preserve the deployed behavior** (Behavior First, as for §14.1) | `3\|17\|5\|` | silent no-op, exactly as OLD today | first category hidden, exactly as OLD today |
| **2. Original canonical format** (committed `fc633be`, consistent with every reader and remover) | `\|3\|17\|5\|` | works | work |

Either way, existing rows are **not** rewritten in bulk; only rows Laravel mutates are affected.

Option 1 means the OA-3 "removal half" — the reason the plan prioritises this unit ("mis-filed content can be corrected
entirely in Laravel") — would not work for first-position categories on rows OLD's modernized writer has touched.
Option 2 breaks parity with what OLD writes today. And while OLD stays live, OLD's next add or repair on the same row
strips the leading pipe again.

---

## 20. Owner decision — canonical category format (2026-10-13)

The owner chose **Option 2** from §19.2. Laravel OA-3 writes the original canonical representation, **`|3|17|5|`**.

| Rule | Behaviour |
|---|---|
| Parse | Both `3\|17\|5\|` (deployed OLD writer) and `\|3\|17\|5\|` (canonical). Empty segments are dropped. |
| Write | Canonical `\|…\|`, **only** on a row a Laravel operation actually changes. An emptied list is stored as `''`. |
| Duplicate add | No-op: no write, no counter change, no audit row, no re-format. |
| Absent remove | No-op, on the same terms. |
| Repair (group/series) | The parent is rewritten only when its **set** of categories changes, so a same-set parent in the old form is left byte-identical. |
| Bulk normalization | **None.** No repair script, no migration, no database-wide pass. |

Owner examples, all covered by tests (§21.6):

| Before | Operation | After |
|---|---|---|
| `3\|17\|5\|` | add 9 | `\|3\|17\|5\|9\|` |
| `3\|17\|5\|` | remove 3 | `\|17\|5\|` |
| `3\|17\|5\|` | add 17 | `3\|17\|5\|` (untouched) |
| `3\|17\|5\|` | remove 99 | `3\|17\|5\|` (untouched) |

The §17 contract is otherwise preserved unchanged: scopes, counters, cascade, OLD's group remove-all asymmetry, and
scope exclusions.

### 20.1 Coexistence hazard — cutover / retirement dependency

While OLD stays live, **the deployed OLD Cat admin writer (`modules/Cat/admin/index.php`, SHA-256 `8642725f…d47df`)
strips the leading pipe again** on its next add or repair of any row. That includes a row Laravel has just written
canonically. Effects while both writers coexist:

- A row can flip back to `3|17|5|` after Laravel normalized it. Laravel still parses it correctly, and its next
  change writes canonical again.
- OLD's own remover still cannot remove a first-position category on such a row. Laravel can.
- `LIKE '%|id|%'` readers miss the first category on any row OLD last wrote.

This is **not fixed in this phase**: OLD is not modified. It is recorded as a dependency on the retirement
checklist. **Retire OLD's Cat admin assignment UI (or restore its canonical writer) at cutover.** Until then,
category assignment should be done in Laravel. Bulk repair of existing rows remains out of scope and would need its
own owner decision.

---

## 21. Phase 3 implementation record — Category Assignment (OA-3) (2026-10-13)

### 21.1 Scope implemented

| Operation (OLD) | Laravel |
|---|---|
| `cat_add_to_item` / `cat_del_from_item` | `KhotabItemCategoryAction::add/remove`, followed by the group and series repair cascade |
| `cat_add_to_anasheed` / `cat_del_from_anasheed` | `AnasheedItemCategoryAction::add/remove`: `cat_id`, `anasheed_count`, no cascade |
| `cat_add_to_subseries` / `cat_del_from_subseries` | `KhotabContainerCategoryAction` `series`: every item `WHERE ser_id = X`, hidden ones included, then the series' own list |
| `cat_add_to_subgroup` | `KhotabContainerCategoryAction` `group`: loose items only (`group_id = X AND ser_id = 0`), then the group's own list |
| `cat_del_from_subgroup` | The same loose scope, then **`cat_repair_group`**, not a direct remove (OLD asymmetry preserved) |
| `cat_repair_series` / `cat_repair_group` | `KhotabParentCategoryRepair`: union of the items' lists, written only on a set change, counters untouched |

The direct series/group add/remove steps are internal only, as in OLD. These are **OUT**: `cat_fastcopy`,
`KhotabEditCat`, and category CRUD.

### 21.2 Architecture

Controller → FormRequest → Action → DB:

- `Http/Controllers/CategoryAssignmentController` — thin;
- `Http/Requests/Categories/CategoryAssignmentRequest` — `category_id` is required, an integer, and must exist in
  `nuke_w2a_cat`;
- `Actions/Categories/*` — `CategoryList` (pure parse/format), `CategoryCounter` (column-guarded, floored
  decrement), the three actions, and the repair.

Each operation is one transaction on `main`, and rows are read under `lockForUpdate`. A bulk operation commits
every item, the container, the counter and every audit row together, or rolls all of them back. OLD ran statement by
statement.

**Junction tables are not written.** `khotab_category_index` and `series_category_index` are maintained by the
existing DB triggers, which `TRIM` both pipes, so they read either form. This was verified at runtime (§21.7).

**Documented divergence from OLD:** a remove deletes *every* occurrence of the id. OLD's
`str_replace('|3|', '|', '|3|3|5|')` left one copy behind.

### 21.3 Authorization

| Permission | Origin | Gates |
|---|---|---|
| `khotab.addcat` (`اضافة تصنيف`) | SOURCE_CONFIRMED — `admincp/khotab/menu.php:93` | every Khotab route: item add/remove, series and group add-all/remove-all, and the pages |
| `anasheed.addcat` (`اضافة تصنيف`) | minted, as for the Anasheed mirror keys | every Anasheed route |

No `delcat` key is minted, because none exists in the legacy source. The permissions are enforced by route
middleware **and** re-checked in each action through `AdminRecordAuthorizer`. They are definitions only: no legacy
grant is imported, and Spatie remains the sole authority.

There are 14 routes under `admincp/categories`, with two sidebar children: «تصنيفات المواد» under المرئيات and
under الأناشيد. No new parent was added.

### 21.4 Audit

Every real change writes one `admin_audit_log` row through the Phase 0 recorder, in the same transaction.

| Change | Actions | Metadata |
|---|---|---|
| Item | `add_cat_item` / `del_cat_item` | `category_id`, `cat_before`, `cat_after` (bulk adds `bulk`, `container_id`) |
| Container | `add_cat_series` / `del_cat_series` / `add_cat_group` | as above, plus `items_changed` |
| Repair | `repair_cat_series` / `repair_cat_group` | `cat_before`, `cat_after` |

No-ops write nothing. Metadata holds no titles and no HTML. Legacy `nuke_islamic_logs` compatibility stays
**default-OFF**.

### 21.5 Integration adjustments to frozen phases

- **Phase 0 `MirrorPermissionsPhase0Test`** — the "own namespace" test filtered `anasheed.%` and asserted exactly the
  three mirror keys. Phase 3 legitimately adds `anasheed.addcat`, so the filter was narrowed to `anasheed.%mirror`.
  The assertion itself is unchanged.
- **Fixture** — `MainSchema::nukeIslamicKhotab` gained the real nullable `cat` column. No existing insert is
  affected.

No Phase 0/1/2 production code was changed. `routes/admin.php`, the seeder, `PermissionLabels` and
`AdminDashboardModules` received additive entries only.

### 21.6 Results

| Gate | Result |
|---|---|
| Phase 3 focused (6 files) | **74 passed**, 265 assertions |
| Phase 0/1/2 regression (11 files) | **197 passed**, 693 assertions |
| Sidebar (`AdminAuthControllerTest`) | 30 passed — parent count unchanged at 6 |
| Full suite | **1,501 passed, 1 skipped** (pre-existing Content skip), 0 failed |
| PHPStan (level 5) | **0 errors** |
| Pint, Phase 3 files only | passed (one import-order fix). `routes/admin.php` has a pre-existing import disorder and was not reformatted. |

**Mutation testing.** Each mutation was applied, the Phase 3 tests were run, and the file was restored. Every
mutation was caught:

| Mutation | Tests failed |
|---|---|
| drop the leading pipe on write (the deployed OLD writer) | 32 |
| remove the no-op guard | 2 |
| widen the group scope past `ser_id = 0` | 2 |
| remove the counter floor | 3 |
| repair rewrites a same-set parent | 3 |
| group remove-all uses a direct remove | 2 |
| remove the action-level authorization | 1 |

**The seven required proofs:**

| # | Proof | Tests |
|---|---|---|
| 1 | Both legacy representations parse | `CategoryListTest` "parses the no-leading-pipe form…" and "parses the canonical…" · `KhotabItemCategoryPhase3Test` "adds to a no-leading-pipe row…" and "adds to a canonical row…" · `AnasheedItemCategoryPhase3Test` "parses both forms…" (dataset) |
| 2 | A mutation writes canonical `\|…\|` | `KhotabItemCategoryPhase3Test` "adds to a no-leading-pipe row and writes the canonical form (owner example 1)" and "removes a middle and a last category from either form" (dataset) · `CategoryListTest` "reproduces the owner examples" |
| 3 | First-position removal from a no-leading-pipe row | `KhotabItemCategoryPhase3Test` "removes the first-position category from a no-leading-pipe row (owner example 2)" · `AnasheedItemCategoryPhase3Test` "removes a first-position category…" |
| 4 | Duplicate add is a no-op | `KhotabItemCategoryPhase3Test` "treats adding an existing category as a no-op… (owner example 3)" — zero write statements, identical snapshot, stored string still `3\|17\|5\|` · Anasheed equivalent · `KhotabContainerCategoryPhase3Test` "is idempotent…" |
| 5 | Absent remove is a no-op | `KhotabItemCategoryPhase3Test` "treats removing an absent category as a no-op… (owner example 4)" · Anasheed equivalent |
| 6 | No unrelated row is normalized | `KhotabItemCategoryPhase3Test` "leaves every unrelated row byte-identical, including legacy-form siblings and a same-set parent" · `KhotabContainerCategoryPhase3Test` "normalizes no row it does not change" · `AnasheedItemCategoryPhase3Test` "runs no cascade and touches no other row" |
| 7 | No bulk normalization | `KhotabItemCategoryPhase3Test` "writes exactly the rows the operation changes — every UPDATE is keyed to one id": with 20 legacy rows present, exactly 2 UPDATEs are issued (the item, then the counter), each `WHERE id = ?`, and the snapshot diff is exactly those two values |

### 21.7 Local runtime verification (real local MySQL, real HTTP)

The safety gate was `APP_ENV=local`, `main` = `127.0.0.1:3307/olddb`, with compatibility off. The run used
`php artisan serve` on `127.0.0.1` with a cookie jar: a real login, CSRF and session handling. Baselines were taken
first. All content and identities were disposable: three categories, a series, a group, three Khotab items (seeded
in both formats), a nasheed, and two admins (`uid = 0`, `thumb = ''`). Every check was computed; there are no
hard-coded PASS lines.

The run seeded the two new permission definitions; they were absent locally beforehand. Like Phase 0's, they are
persistent definitions and are left in place.

**The first run reported 2 failures, both script defects.**

- The outsider POST returned **419**, not 403: login rotates the CSRF token, so the check never reached
  authorization.
- An array-key comparison was wrong.

Teardown restored everything, and the corrected run passed **54/54**:

- **Authorization.** The outsider (admin role, no `addcat`) got 403 on GET and on a CSRF-valid POST, with no write.
- **Writes.** A legacy-form item add was written canonically, `video_count` moved +1, and the trigger-maintained
  `khotab_category_index` matched. The series and group were repaired to the union. The legacy sibling was
  untouched.
- **No-ops.** A duplicate add and an absent remove left rows byte-identical, with no audit row.
- **Anasheed.** A first-position remove on the legacy-form nasheed worked, with `anasheed_count` floored at 0.
- **Bulk scopes.** Series add-all and group remove-all respected their scopes, and the group repair kept the
  category through a series item.
- **Validation.** An unknown category was rejected.
- **Audit and legacy log.** The audit action sequence was exact, and no `nuke_islamic_logs` row was written.
- **Restoration.** All 18 baselines were identical after teardown:
  - MD5 of every `cat`/`cat_id` value across Khotab (292,592 rows), Anasheed, series and groups;
  - MD5 of all `nuke_w2a_cat` counters;
  - the row counts of the content, index, author, audit and Spatie tables;
  - the `admin_audit_log` schema checksum `a9f9ebb3d7c08f0230cb60a0d547ec61`.

  No `p3v*` rows remain. Only the tables' AUTO_INCREMENT counters advanced.

### 21.8 Production deployment impact

Phase 3 deploys with Phases 0–2:

- no migration;
- run `AdminPermissionSeeder`, which adds 2 definitions and grants nothing;
- grant `khotab.addcat` / `anasheed.addcat` through the Laravel permission editor.

Production verification follows §13.15's pattern, with a disposable identity and disposable rows. **§20.1 is a
cutover dependency.**

### 21.9 Status

| Phase | Status |
|---|---|
| **0** — Administration Authorization & Audit Foundation | **IMPLEMENTED / LOCAL VERIFIED / PRODUCTION DEPLOYMENT PENDING** |
| **1** — Mirror Management (Khotab + Anasheed) | **IMPLEMENTED / LOCAL VERIFIED / PRODUCTION DEPLOYMENT PENDING** (frozen) |
| **2** — Administration Reporting & Observability | **IMPLEMENTED / LOCAL VERIFIED / PRODUCTION DEPLOYMENT PENDING** (frozen) |
| **3** — Category Assignment (OA-3) | **IMPLEMENTED / LOCAL VERIFIED / PRODUCTION DEPLOYMENT PENDING** |

Phase 4 has not started.

---

## 22. Phase 4 stopped at Step 1 — the scope premise does not hold (2026-10-13)

> **Resolved 2026-10-14 by business decision — see §23.** This section is kept unchanged as the record of the stop.

Phase 4 was defined (§3) as "the survey operations `SurveyController` does not cover": `AddCategory`,
`EditCategory`, `DelCategory`, `RemovePollComment` and `polledit_select`. It was justified as "one existing
controller, one permission namespace (`survey.*`)". Repository evidence contradicts that premise, so the phase
stopped before implementation. Nothing was changed.

### 22.1 Two different "survey" systems

| | Laravel `Admin\SurveyController` | OLD `modules/Surveys` (source of the Phase 4 ops) |
|---|---|---|
| Ports | `admincp/survey/*.php` — the custom survey engine (roadmap 5.1/5.2) | PHP-Nuke's native **poll** system |
| Tables | `nuke_survey*` | `nuke_poll_desc`, `nuke_poll_data`, `nuke_pollcomments` |
| Gate | `survey.modsurvey` | `nuke_modules.admins` for `Surveys`, or `radminsuper` (`Surveys/admin/index.php:19-29`) |

Laravel's only `nuke_poll_*` code is the **public** `Engagement\PollController`: list, show, results and vote.
**No poll administration exists in Laravel.** 3C §5's "partly Laravel" conflated the two systems.

### 22.2 What each listed operation actually is

| Op | Evidence | Classification |
|---|---|---|
| `AddCategory` / `EditCategory` / `DelCategory` | Linked only from `SelectCategory()` (`Surveys/admin/index.php:87`). That selector lists **`nuke_stories_cat`** (News categories), so a new poll can be attached to an article. **No handler exists anywhere in OLD:** the ops are absent from `Surveys/admin/case.php`, and the only repository-wide match is that link. | **Not a survey capability; dead links (NO_HANDLER).** Nothing to migrate, the same precedent as `KhotabRepairLostedLink` (3D §2). |
| `polledit_select` | `Surveys/admin/index.php:252`, a picker that leads to `polledit` / `savepoll` / `ChangePoll` | A picker for **poll editing, which Laravel does not have**. Alone, it would link to nothing. |
| `RemovePollComment` | `admin/modules/comments.php:93-112`, dispatched via `admin/case/case.comments.php` | Moderates poll comments. **Laravel does not render poll comments at all**: the `survey-comment*` routes are "deliberately NOT covered — SOURCE UNRECOVERABLE" (`routes/engagement.php:28-33`). |

The Gen 2 contextual links behind 3C's row go to the same unmigrated poll admin:
`7amlat/surveys/functions.php:132` and `:263` link to `admin.php?op=polledit` and `op=create`.

### 22.3 Legacy counter behavior is internally inconsistent

`RemovePollComment` decrements `nuke_poll_desc.comments` by one, then calls `removePollSubComments($tid)`. That
function decrements once per descendant, and then once more at `:90` using `$row['pollID']` after the loop:

- with no replies, `$row` is `false` and the `UPDATE` matches nothing;
- with replies, it reuses the last child's `pollID`.

A comment with replies is therefore over-decremented, with no floor. "Behavior First" cannot be applied to the
counter without an owner choice: reproduce the over-decrement, or decrement by the number of rows actually deleted.

### 22.4 Decision required (smallest question)

**Is Phase 4 the administration of PHP-Nuke polls (`nuke_poll_*`), given that `SurveyController` does not cover
polls?**

- **(a) Literal scope.** `polledit_select` plus `RemovePollComment` only; the category ops are closed as
  NO_HANDLER. *Not recommended:* the picker leads to an edit Laravel lacks, and the comments being moderated are
  not displayed by Laravel.
- **(b) Re-scoped (recommended).** The Gen 1 poll admin capability: create (`create`/`createPosted`), remove
  (`remove`/`removePosted`), edit (`polledit_select`/`polledit`/`savepoll`/`ChangePoll`), plus `RemovePollComment`.
  The category ops are closed as NO_HANDLER. The article-attachment half of `createPosted` (`nuke_stories*`) would
  need its own in/out ruling.
- **(c) Close Phase 4.** Polls are retired or remain OLD-owned, and the category ops are closed as NO_HANDLER.

Alongside (a) or (b), two further choices are needed:

1. the §22.3 counter semantics;
2. whether `survey.modsurvey` (the custom engine's capability) should also gate polls, or a separate permission
   should be minted. They are distinct legacy capability populations.

---

## 23. Phase 4 — PHP-Nuke Poll Administration Migration (2026-10-14)

### 23.1 Decision and corrected scope

The business decision is **APPROVED — POLL ADMINISTRATION REQUIRED**
(`docs/business-decisions/phase-4-poll-administration-business-decision.md`, §11). The owner's clarification is
**Behavior First / Parity First**: preserve what genuinely worked in OLD, with no redesign and no speculative
improvements. Later changes are post-parity enhancements.

Phase 4 is re-scoped from "Survey Administration Completion" to **PHP-Nuke Poll Administration Migration**:
`nuke_poll_desc`, `nuke_poll_data` and `nuke_pollcomments`. The custom-survey engine (`SurveyController`,
`nuke_survey*`) is a separate system. It is out of scope and untouched; a test proves no `nuke_survey*` row changes.

### 23.2 Recovered OLD contract

Sources: `modules/Surveys/admin/index.php` and `case.php`, `admin/modules/comments.php`, `admin/case/case.comments.php`.

| OLD op | Executable behavior | Laravel |
|---|---|---|
| `create` / `createPosted` (`poll_createPosted`, `:181-207`) | Inserts a `poll_desc` row: title, `timeStamp = time()`, `voters = 0`, `planguage = $language`, `artid = 0`, `comments = 0`. Inserts **12** `poll_data` rows, `voteID` 1–12 with count 0, **blank slots included** (the loop guarded only `FixQuotes`). Optionally inserts a News story (§23.3). | `CreatePollAction`. Same rows; `planguage` = `nuke_config.language` (`$language` when `multilingual` = 0). Story not reproduced. |
| `polledit_select` / `remove` (pickers, `:209-277`) | List every poll `ORDER BY timeStamp`, showing title and language | One admin list, same order, with Edit and Delete actions. UX consolidation only. |
| `polledit` (`:279-333`) | Shows the title and each existing slot with its `optionCount` (read-only) | Edit page: title, existing slots, vote counts |
| `savepoll` (`:335-352`) | Updates the title and `planguage` (a hidden field; `multilingual` = 0). Then, for voteID 1–12, `UPDATE optionText WHERE pollID AND voteID`: every row of a slot is rewritten, missing slots stay missing, and an omitted field is written as `''`. Counts, voters and timestamp are never touched. | `UpdatePollAction`. Identical, but writes only what changes. |
| `removePosted` (`:244-250`) | Deletes `poll_desc` and all `poll_data` rows. Comments and `poll_check` rows are **left** in place. | `DeletePollAction`. Identical scope. |
| `RemovePollComment` (`comments.php:93-112`) | Deletes the comment and its whole reply thread (recursive on `pid`), and decrements `comments` (§23.4). Gate: `radminsuper` only (`:21`). | `DeletePollCommentAction`. Same thread scope; counter corrected; super-admin role. |
| `ChangePoll` | Dispatched by `case.php:26` and `index.php:367`, but **the function `ChangePoll()` is defined nowhere**, so calling it is a fatal error. Nothing links to it. | Dead. Not migrated. |
| `AddCategory` / `EditCategory` / `DelCategory` | No handler anywhere (§22.2) | **Closed as dead.** Not migrated. |

### 23.3 Poll ↔ News/Article — dead, not reproduced

`createPosted`'s optional block inserts a `nuke_stories` row with `pollID = '0'`, and never sets the poll's `artid`.
The story and the poll were therefore **never linked**. The story itself landed in a News system that does not
exist:

- no `modules/News` in OLD;
- no News row in local `nuke_modules`;
- no story block registered in `nuke_blocks`;
- the newest story dates from 2008;
- 0 of 146 local polls have `artid > 0`.

No executable Poll ↔ Article association exists to preserve. The public `artid = 0` "standalone" filter is
unaffected, because every created poll has `artid = 0`.

### 23.4 Intentional corrections (Behavior First ≠ Bug Forever)

1. **Comment counter** (owner instruction).
   - OLD decremented once in `RemovePollComment`, once per descendant (`:86`), and once more per recursion level
     through a stale `$row` (`:90`), with no floor. A comment with replies drove the counter too low.
   - Laravel decrements each affected poll by **exactly the rows deleted**, floored at 0.
   - Only polls that lost rows are touched. There is no bulk counter repair; one local poll already has a
     mismatched counter, and it is left alone.
2. **Create's id lookup.**
   - OLD located the new poll with `SELECT pollID WHERE pollTitle = '<title>'`. With a duplicate title, the
     choices attached to the *older* poll. This explains the local polls with 24, 36 and 84 rows (4, 1 and 1
     polls).
   - Laravel uses the inserted id. Duplicate titles remain allowed.
3. **Missing comment.**
   - OLD decremented the request's `pollID` even when the comment did not exist.
   - Laravel returns 404 and changes nothing.
4. **Thread cycles.** A `pid` cycle would recurse forever in OLD. The breadth-first walk tolerates it.

### 23.5 Validation

The rules follow the real column sizes: `pollTitle varchar(100)`, `optionText char(50)`. Slots outside 1–12 are
ignored. Choices may all be blank, as in OLD.

**Hardening:** a non-empty title is required. OLD's form did not enforce one, but a title-less poll is
unidentifiable in every list.

### 23.6 Authorization

| Capability | Gate | Rationale |
|---|---|---|
| list / create / edit / delete | `polls.managepolls` (**minted**; label `_POLLSADMIN` "التحكم في الإستفتاءات") | OLD had one module-level gate (the `Surveys` module admins, or radminsuper). This is **not** `survey.modsurvey`, which belongs to the custom-survey engine; a test proves `survey.modsurvey` opens nothing here. |
| comment list / removal | `admin.role:super-admin` | OLD comments admin was radminsuper-only. No key minted (same precedent as the audit-log route). |

Gates are enforced by route middleware and re-checked in every action. The permission is a definition only: no
grant, no legacy import. The sidebar has one flat entry, «الإستفتاءات», so there is no new parent menu.

### 23.7 Implementation

- **Architecture:** `PollAdminController` → `PollRequest` → `Actions/Polls/{Create,Update,Delete,DeletePollComment}Action`
  → DB (`main`).
- **Models:** reuses `Engagement\Poll` and `PollOption`; adds `Engagement\PollComment` (`nuke_pollcomments`,
  key `tid`).
- **Transactions:** one per mutation, with poll, option and comment rows locked (`lockForUpdate`).
- **Routes:** 8 under `admincp/polls`.
- **Views:** `admin/polls/{index,create,edit,comments}`.
- **Audit:** in the same transaction as the mutation, through the Phase 0 recorder.

  | Action | Metadata |
  |---|---|
  | `add_poll` | title, language, non-blank choices |
  | `edit_poll` | `before`/`after` of the changed fields only |
  | `del_poll` | full snapshot, including every choice's vote count, since votes are unrecoverable |
  | `del_poll_comment` | `deleted_tids`, `deleted_count`, per-poll counter before/after. No comment text. |

  `PollAuditText` substitutes a `{redacted: markup, length}` marker for any markup-looking legacy text, so the
  recorder's markup guard can never block a mutation. No real poll text has markup today.
- **No-ops:** an unchanged edit writes nothing and records no audit row.
- **Legacy compatibility logging:** stays default-OFF.
- **Integration changes:**
  - `MainSchema::nukePollDesc` gained the real `planguage` and `comments` columns (defaulted);
  - `nukePollcomments` gained the real `tid` key (was the unreferenced `id`), plus `pid`, `date`, `name` and
    `subject`;
  - a new minimal `nukeConfig` fixture;
  - `Poll`'s `@property` list was extended.

  No public Poll code changed, and no Phase 0–3 code changed.

### 23.8 Results

| Gate | Result |
|---|---|
| Phase 4 focused (5 files) | **52 passed**, 204 assertions — administration 21, comments 10, authorization 7, audit 11, public compatibility 3 |
| Public poll and custom-survey suites (`PollController`, `SurveyPrettyUrl`, `HomeController`, `SharedLayout`, `SurveyController`, `SurveyModels`) | **67 passed** |
| Phase 0–3 regression, plus the sidebar test | **301 passed** |
| Full suite | **1,553 passed, 1 skipped** (pre-existing), 0 failed |
| PHPStan | **0 errors** |
| Pint, Phase 4 files | passed. `Engagement/Models/Poll.php` has pre-existing style findings; only docblock lines were added, and it was not reformatted. |

**Test design errors corrected during the run** (the code was unchanged):

- A route loop deleted the poll before the comment-route checks, giving 404 instead of 403. The checks were
  reordered.
- A miscounted string length; the test now computes it with `mb_strlen`.

**Mutation testing:** every mutation was caught, and the files were restored.

| Mutation | Tests failed |
|---|---|
| remove the counter floor | 1 |
| reinstate an OLD-style extra decrement | 4 |
| limit deletion to direct replies only | 2 |
| remove the comment super-admin check | 1 |
| audit blank choices | 1 |
| skip creating blank slots | 5 |
| remove the no-op audit guard | 1 |
| rewrite unchanged slots | 3 |
| delete leaves the choices behind | 1 |
| remove the markup marker | 1 |
| remove edit-action authorization | 1 |

### 23.9 Local runtime verification (real local MySQL, real HTTP)

The safety gate was `APP_ENV=local`, `main` = `127.0.0.1:3307/olddb`, with compatibility off. The run used
`php artisan serve` on `127.0.0.1`, real login, sessions and CSRF, with three disposable admins:

- an outsider holding `survey.modsurvey`;
- a manager holding `polls.managepolls`;
- a super-admin.

The seeder added the one new definition, which was absent before. **54/54 functional checks** passed, plus the
restoration checks:

- **Authorization.** The outsider got 403 on list and create, and created nothing.
- **Create.** 12 slots with blanks stored as `''`, language `arabic` from `nuke_config`, zero counters, and no
  `nuke_stories` change.
- **Public pages.** List and detail show the poll; a public vote incremented slot 2 and `voters`; results render.
- **Edit.** It kept the vote and the language. An unchanged resubmit added no audit row.
- **Comments.** The manager got 403 on comment removal, with the thread intact. The super-admin removed a 3-comment
  thread: only the sibling remained, and the counter went 4 → 1.
- **Delete.** It removed the poll and its choices and left the remaining comment (OLD scope). The public detail
  page then returned 404.
- **Audit.** The sequence was exactly `add_poll`, `edit_poll`, `del_poll_comment`, `del_poll`, with no
  `nuke_islamic_logs` row.

**Restoration.** All 15 baselines were identical after teardown:

- whole-table MD5 of `nuke_poll_desc`, `nuke_poll_data`, `nuke_pollcomments`, `nuke_poll_check`, `nuke_survey*`,
  `nuke_config` and `nuke_stories`;
- row counts of authors, audit and Spatie tables;
- the `admin_audit_log` schema `a9f9ebb3d7c08f0230cb60a0d547ec61`.

The vote rate-limiter key was cleared, and no `p4v*` rows remain.

**Caveat:** locally, `nuke_survey` and `nuke_survey_answers` are empty, so that runtime check is meaningful only for
`nuke_survey_questions`. The feature test covers a non-empty survey.

### 23.10 Remaining dependencies

- **Deploy with Phases 0–3.** No migration. Run `AdminPermissionSeeder`, which adds 1 definition, and grant
  `polls.managepolls` through the Laravel permission editor.
- ~~**`multilingual`.**~~ **CLOSED — confirmed from production (§23.11).**
- **Cutover — OLD poll admin screens.** Retire `modules/Surveys/admin` (create, edit, remove) and the OLD
  `RemovePollComment` link when OLD is decommissioned. Until then both writers can edit the same polls; there is no
  format hazard comparable to §20.1.
- **Cutover — Gen 2 links.** Retire or reroute Gen 2's poll create/edit links (`7amlat/surveys/functions.php:132`
  `polledit`, `:263` `create`/`polledit`) to the Laravel AdminCP at cutover.
- **Public comment pages.** Laravel public poll comment pages remain outside the migrated public functionality
  (`routes/engagement.php`). Moderation here covers the stored comments, which are still served by OLD while it is
  live.

### 23.11 Production confirmation — `multilingual` (2026-10-14)

The owner ran a read-only production check: **`nuke_config.multilingual = 0`**. That matches the local copy and the
implementation's assumption.

- With `multilingual = 0`, OLD's create and edit forms carried the language as a hidden value and showed no
  language selector, so **no language picker is required**.
- Create's use of `nuke_config.language`, and edit's preservation of the stored `planguage`, are parity as
  implemented.
- **No application or test change follows.** The §23.10 caveat is closed, and Phase 4 is **closed for
  implementation**.

### 23.12 Status

| Phase | Status |
|---|---|
| **0**–**3** | **IMPLEMENTED / LOCAL VERIFIED / PRODUCTION DEPLOYMENT PENDING** (frozen) |
| **4** — PHP-Nuke Poll Administration Migration | **IMPLEMENTED / LOCAL VERIFIED / PRODUCTION DEPLOYMENT PENDING** — closed for implementation (§23.11) |

Phase 5 has not started. *(Superseded by §24.)*

---

## 24. Phase 5 — Featured / Promotional Content (Advfiles) (2026-10-14)

### 24.1 Decision

**BUSINESS APPROVED — FEATURED / PROMOTIONAL CONTENT REQUIRED.** The owner instruction is to migrate the capability
if it genuinely existed and worked in OLD, at **behavioral parity**: no redesign and no speculative enhancements. Later
changes are post-parity requests.

Pass 3A (`old-admin-advfiles-pass-3a.md`) is unchanged and stands as the historical evidence. This section records
what the narrow contract confirmation added to it, and corrects it where needed.

### 24.2 Recovered contract

Sources: `modules/advfiles/admin/{index,case,links}.php`, `modules/Khotab/admin/index.php:384-423`, the templates
`themes/way2allah/templates/advfiles/*`, `modules/advfiles/index.php`, `7amlat/khotab/functions.php:296-299`, plus the
real local schema and data.

**Eligible entity.** Khotab items (`nuke_islamic_khotab`), and nothing else. Every OLD operation reads and writes
that table; there are no type variants.

**Storage:**

- `nuke_islamic_khotab.advfile` holds the workflow state.
- `nuke_islamic_advfile` holds the payload, 1:1 by shared `id`. Its unique key is named `khid`: the column was
  renamed, which explains Pass 3A's four `WHERE khid=` consumers.
- **Side effect not recorded in Pass 3A:** publish and unpublish move `nuke_islamic_authors.advfile`,
  `nuke_islamic_groups.advfile` and `nuke_islamic_series.advfile` by ±1 (`:101-103`, `:189-191`). OLD's public
  "promotional files" library (`modules/advfiles/index.php`) browses author → group → series → item, filtered on
  `advfile > 0`, using those counters.

| `advfile` | Meaning | Label |
|---|---|---|
| 0 | none — may be requested | غير مطلوب |
| −1 | requested, awaiting approval | طلب بانتظار الموافقة |
| > 0 | published. OLD writes 1. One local row holds 2; no code path writes 2, but every OLD reader tests `> 0`, so it is treated as published. | منشور في الملفات الدعائية |

**Capabilities.** Request: any administrator. OLD's `GreenZone('AdvFile')` is a no-op, so only `is_w2a_admin()`
ran, and Gen 2 shows «طلب رئيسية» to every admin when `advfile == 0`. Every management step was gated by one
module-level check: the `Advfiles` module admins, or radminsuper.

### 24.3 Transition matrix (as implemented)

| Op | Gate | From | To | Writes | Refusal / no-op |
|---|---|---|---|---|---|
| **request** (`KhotabRequestAdvFile`) | any admin | 0 | −1 | the item | `> 0`: refused with OLD's message. −1: **no-op** (OLD rewrote and logged again). |
| **approve** (`InsertAdvFile`/`ConfirmInsert`) | `khotab.addadv` | −1 | 1 | payload row (`credit`, `code`, `audio_url`); counters +1 | 0: refused with OLD's form message. `> 0`: refused (use edit). |
| **reject** (`ResetAdvFile`, the queue's delete icon) | `khotab.addadv` | −1 | 0 | the item | `> 0`: refused with OLD's message. 0: no-op. |
| **edit** (`ConfirmEditAdvFile`) | `khotab.addadv` | `> 0` | — | the changed payload columns only | not published, or no payload row: refused. Unchanged: no-op. |
| **remove** (`ConfirmDeleteAdvFile`) | `khotab.addadv` | `> 0` | 0 | payload deleted; counters −1, floored | not published: refused |
| **credit list** (`Credit`/`ConfirmCredit`) | `khotab.addadv` | — | — | `credit` on existing payload rows that change | unchanged: no-op |
| **queue** (`AdvfilesMain`) | `khotab.addadv` | lists `advfile = −1 ORDER BY id ASC` | | | |

**Credit** is reproduced exactly as OLD used it: an admin-set number the credit list is ordered by
(`credit > 0 ORDER BY credit DESC`). Nothing in any codebase decrements it (Pass 3A §4), so no metering is
invented.

### 24.4 Dead, broken or unreachable — not migrated

- **`AdvfilesUploadAdvFileImage`.** It writes `media/advfiles/{id/1000}/{id}.jpg` on OLD's filesystem, and **nothing
  in OLD, Gen 2 or Laravel reads that path.** It sets no flag.
- **`small_thumb` / `large_thumb`.** No code path writes them. They are carried through untouched.
- **The Flash `player.swf` and the `[audio]` embed rendering.** These belong to the public display.
- **The four `WHERE khid=` consumers.** They are a defect, not behavior.
- **Public display.** `ShowAdFile`, the library pages and `khotab-advfile*.htm` remain OLD-owned. Gen 2 routes
  them to an absent dispatcher, and Laravel renders none. **Out of Phase 5's administration scope** (§24.9).

**The pending queue itself was broken in OLD.** `AdvfilesMain()` iterates with `$db->sql_fetchrow` but declares no
`global $db`, and neither `header.php` nor the eval'd templates import it, so the page fatals once it starts listing.
That plausibly explains the 12 requests sitting at −1. The queue's intent is unambiguous (the SQL and template), so
Laravel provides a working queue.

### 24.5 Intentional corrections (Behavior First ≠ Bug Forever)

1. **Repeated request** is a no-op. OLD rewrote −1 and logged again.
2. **Approve requires a pending request.** OLD's form enforced this; its `ConfirmInsert` handler did not.
3. **Approve with a stale payload row** (3 local rows sit on unpublished items). OLD's INSERT failed on the unique
   key, yet it still published with the *old* payload and moved the counters. Here the submitted payload replaces
   the stale row.
4. **Reject only from −1.** OLD's Reset also wrote 0 from state 2, which would hide a published file while leaving
   its payload and counters behind.
5. **Remove is keyed on the published state, not the payload row.** OLD could not unpublish the 8 published items
   that have no row, and would have decremented counters for a row on an unpublished item.
6. **Counters.** Parents with id 0 are skipped, decrements are floored at 0, and no bulk repair is done. The 3
   existing author-counter mismatches are left as they are.
7. **Credit save** touches only existing payload rows, and only those whose value changes. OLD wrote whatever ids
   the request array named (Pass 3A A-6).
8. **Security mechanics not reproduced** (Pass 3A A-1 to A-6):
   - authorization is enforced;
   - every mutation is POST/PUT/DELETE with CSRF;
   - `code` is stored verbatim but **never rendered unescaped** in Laravel (it is shown as text).

UX modernization only: the admin list replaces OLD's queue page, and an item status page carries the actions its
state allows. `code` is edited as HTML source in a textarea; OLD used TinyMCE. No public-facing behavior changes.

### 24.6 Authorization

- **Request:** any authenticated admin (`admin.role`). This is OLD parity. Approval is the guarded step, and
  requesting only queues an item.
- **Management:** **`khotab.addadv`**. This is the real `admincp/khotab/menu.php:85` key (`'addadv' => 'إضافة
  ملفات دعائية'`), SOURCE_CONFIRMED, as `addcat` was for Phase 3. It is enforced by route middleware and re-checked
  in every management action.
- The permission is a definition only: no grant, no legacy import. Super-admin follows the existing model.
- The sidebar has one child under المرئيات, «ملفات الرئيسية» (OLD's own menu label), so there is no new parent.

### 24.7 Implementation

- **Architecture:** `AdvfileController` → `AdvfilePayloadRequest` / `AdvfileCreditsRequest` →
  `Actions/Advfiles/{Request,Approve,RejectAdvfileRequest,Update,Unpublish,UpdateAdvfileCredits}Action` → DB.
- **Support classes:** `AdvfileState` (labels), `AdvfileItemLock`, `AdvfileParentCounters`, `AdvfileAuditValue`,
  `AdvfileTransitionRefused`.
- **Models:** the new read model `Content\Models\Advfile` (`nuke_islamic_advfile`, shared id). Khotab items use the
  existing `KhotabItem`; there is no parallel representation.
- **Transactions:** one per mutation. The item row is locked, and every decision reads the **locked row**, not the
  bound model (tested). The payload and parent counter rows are locked too.
- **Routes:** 12 under `admincp/advfiles`.
- **Views:** `admin/advfiles/{queue,lookup,item,form,credits}`. Item pages link to the existing public Khotab page;
  no item CRUD is added.
- **Audit:** in the same transaction as the mutation, through the Phase 0 recorder. The item is the entity.

  | Action | Metadata |
  |---|---|
  | `request_adv` (OLD's own legacy action name), `reset_advfile` | state before/after |
  | `add_advfile` | state, credit, a **SHA-256 + length of `code` (never the code)**, `audio_url`, stale-row flag, counter before/after |
  | `edit_advfile` | changed fields only |
  | `del_advfile` | the removed payload's fingerprint, credit, flags and counters |
  | `credit_advfile` | one row per changed payload |

  A markup-looking `audio_url` is recorded as a marker. No-ops record nothing. Legacy compatibility logging stays
  default-OFF.
- **Integration changes:** the `MainSchema` fixtures gained the real `advfile` column (int, default 0) on Khotab,
  authors, groups and series, plus a new `nukeIslamicAdvfile` fixture. No Phase 0–4 code changed.

### 24.8 Results

| Gate | Result |
|---|---|
| Phase 5 focused (3 files) | **50 passed**, 208 assertions — workflow 28, authorization 8, audit 14 |
| Phase 0–4 regression, plus the sidebar test | **353 passed** |
| Existing Khotab/content tests (28 files using `nuke_islamic_khotab` / `KhotabItem`) | **591 passed** |
| Full suite | **1,603 passed, 1 skipped** (pre-existing), 0 failed |
| PHPStan | **0 errors** |
| Pint | passed on the Phase 5 files and on the touched shared files |

**Test design errors corrected during the run** (the code was unchanged):

- a fixture id collision in the queue-order test;
- a sidebar assertion that matched the queue URL as a prefix of the lookup URL.

**Mutation testing: 14 mutations.**

- **12 caught.** Two of these first survived because the refusal fell through to a later guard; the tests now pin
  the exact OLD messages, and both are caught.
- **1 equivalent.** Skipping parents with id 0 only saves a no-match query.
- **1 not observable in SQLite.** Removing the row lock cannot be seen there. Its intent is covered by the
  stale-model test, which catches "decide from the bound model".

### 24.9 Local runtime verification (real local MySQL, real HTTP)

The safety gate was `APP_ENV=local`, `main` = `127.0.0.1:3307/olddb`, with compatibility off. The run used
`php artisan serve` on `127.0.0.1`, real login, sessions and CSRF. Disposable fixtures:

- an item, with its own author, group and series (counters at 3);
- a requester (admin role only);
- a manager (`khotab.addadv`).

The seeder added the one new definition, which was absent before. **34/34 functional checks** passed, plus the
restoration checks:

- **Requester.**
  - Opened the item and requested it (0 → −1).
  - A repeat request changed nothing and added no audit row.
  - Got 403 on queue, approve, reject and remove.
  - A request on a published item was refused.
- **Manager, queue and reject.** The queue lists the item, and the queue order equals `advfile = −1 ORDER BY id`,
  including the 12 real pending ids, which were read only. Reject took the item −1 → 0, and it was re-requested.
- **Manager, approve.** The payload, including the `[audio]` code, was stored verbatim. State went to 1 and the
  counters moved 3 → 4 on author, group and series. The item page shows the code escaped. A second approve was
  refused, with no double count.
- **Manager, edit and credits.** The credit edit was saved. The credit list shows the item. An unchanged save added
  no audit row; a changed save was saved.
- **Manager, remove.** It deleted the payload, set state 0, and moved the counters back to 3.
- **Audit.** The sequence was exactly `request_adv`, `reset_advfile`, `request_adv`, `add_advfile`, `edit_advfile`,
  `credit_advfile`, `del_advfile`. No metadata contains the code, and no `nuke_islamic_logs` row was written.

**Restoration.** All 14 baselines were identical after teardown:

- MD5 of `(id, advfile)` over all 292,592 Khotab rows, and over authors, groups and series;
- MD5 of every `nuke_islamic_advfile` column;
- row counts of the Khotab, category-index, admin-author, audit, legacy-log and Spatie tables;
- the `admin_audit_log` schema `a9f9ebb3d7c08f0230cb60a0d547ec61`.

No `p5v*` rows remain, and the 12 real pending requests were untouched.

### 24.10 Remaining dependencies

- **Deploy with Phases 0–4.** No migration. Run `AdminPermissionSeeder`, which adds `khotab.addadv`, and grant it
  through the Laravel permission editor.
- **Cutover:**
  - reroute Gen 2's «طلب رئيسية» button (`7amlat/khotab/functions.php:296-299`, currently
    `old.way2allah.com/admin.php?op=KhotabRequestAdvFile`) to `admincp/advfiles/items/{id}`;
  - retire OLD's `modules/advfiles/admin` and `KhotabRequestAdvFile`.

  Until then both systems write the same columns. The writes are compatible: the same values and the same counters.
- **Public display stays OLD-owned.** That covers the promotional files library, `ShowAdFile`, and the
  `khotab-advfile*.htm` pages. Laravel renders none of it, and Gen 2's rewrites target an absent dispatcher.
  Retiring OLD therefore also retires public display of promotional files, unless a separate public migration is
  approved. Rendering `code` publicly would need the Pass 3A Q2 decision (raw HTML).
- **Production check, documentary only:** run `SHOW CREATE TABLE nuke_islamic_advfile` to confirm production
  matches the local schema. The module's own code and the local data both use `id`.

### 24.11 Status

| Phase | Status |
|---|---|
| **0**–**4** | **IMPLEMENTED / LOCAL VERIFIED / PRODUCTION DEPLOYMENT PENDING** (frozen) |
| **5** — Featured / Promotional Content (Advfiles) | **IMPLEMENTED / LOCAL VERIFIED / PRODUCTION DEPLOYMENT PENDING** |

Phase 6 has not started. *(Superseded by §25.)*

---

## 25. Phase 6 — W2ACD Administration (CD / Album Downloads) (2026-10-14)

### 25.1 Decision

**BUSINESS APPROVED — W2ACD / CD-ALBUM ADMINISTRATION REQUIRED.** After OLD is retired, staff must still be able to
administer CD / Album releases from the Laravel AdminCP, at **behavioral parity**, migrating only what actually
worked.

Settled facts, not reopened:

- the comma-list `link`/`cd` columns on `nuke_w2acd_w2acd` are the canonical download-link representation;
- the normalized `nuke_w2acd_mirror` table is empty and is not used;
- normalization would be a separate modernization project.

Passes 3C, 3D and 3E.1 stand unchanged as historical evidence.

### 25.2 Contract recovered (narrow verification)

Sources: `modules/w2acd/admin/{case,index}.php` and the templates `themes/way2allah/templates/w2acd/{insertcd,editcd}.htm`;
the public Laravel `W2acdController` and `W2acdItem`; the real local schema and data; and the deployment guide
`deploy-staging-v2/laravel-app/CPANEL-NO-SSH-DEPLOYMENT.md` §6.

**New finding, resolving 3E.1 open item 11:** the live comma lists **do have a writer**. OLD `cdsConfirmInsert` /
`cdsConfirmEdit` (`:315-563`) write:

- `link = implode(",", $link_url)`;
- `cd = implode(",", $link_title)`;
- `thumbnail` = the uploaded image names, comma-joined, with a trailing comma.

Earlier passes missed this. It **confirms** the settled representation rather than reopening it, and it makes
live-link administration recoverable. No stop was needed.

**Local data:** 39 releases, all in group 0. `nuke_w2acd_groups` and `nuke_w2acd_comments` are **empty**. `weight` is
0 and `order_in_group` is 1 on every row. `banner` is 1 on one row. `down = 2` and `trial = 0` on all rows. There is
no `author` or `ser_id` column.

**Laravel public reads** `title`, `link`/`cd` (paired by position), `thumbnail` (`/images/cds_image2/<file>`), the
`group_id` filter, `order_in_group DESC, mytime DESC`, `hits`, and `hidden` for the gallery. It does **not** read
banners, group titles or icons, comments, `weight` or `notes`.

### 25.3 Operation classification

| OLD op | Class | Laravel |
|---|---|---|
| `W2ACDInsertW2ACD` / `cdsConfirmInsert` | **WORKING** | **create** |
| `W2ACDEditW2ACD` / `cdsConfirmEdit` | **WORKING** | **edit** (links, images, title, group) |
| `del_cd_img` | **WORKING** (GET mutation) | part of edit: drop an image from the list |
| `W2ACDDeleteW2ACD` / `ConfirmDeleteW2ACD` | **WORKING** | **delete** |
| `Order_w2acd_in_group` | **WORKING**; `order_in_group` is read publicly | **order** |
| `W2ACDReArrange` / `ConfirmReArrange` (`weight`) | executable, but **output not consumed** (public ignores `weight`; all 0) | not migrated |
| `W2ACDOrder`, `fixweight` | **BROKEN** — filter on `author`/`ser_id`, which do not exist | not migrated |
| `W2ACDCount`, `W2ACDRepair` | **DEAD** — commented out in the dispatcher | not migrated |
| `RepairCount` | **BROKEN** (nonexistent columns) and unreachable | not migrated |
| `W2ACDExport`, `CheckRam`, `RepairRam`, `W2ACD`, `ابحث` | **DEAD** — functions not defined in the admin module | not migrated |
| `W2ACDInsertMirror` / `EditMirror` / `DeleteMirror` (+ confirms) | **DEAD** — normalized table, never used | not migrated (settled) |
| `W2ACDUploadBanner`, `W2ACDDeleteImage` / `confirm_delete_image`, `W2ACDAdd_banner` / `Edit_banner` / `Delete_banner` | executable, but **output not consumed** (Laravel never reads `banner` or `media/w2acd/banner`). The paths are also internally inconsistent: OLD writes `.gif` and its own displays look for `.jpg`. | not migrated |
| `W2ACDAddGroup` / `EditGroup` / `DeleteGroup`, `GroupIcon` / `UploadIcon` | executable, but **never used** (0 groups) and group rows/icons are **not read publicly** | not migrated. Releases keep `group_id` (any existing group, else 0) with the `w2acd` counter. |
| `W2ACDWaitingList`, `AddComments`, `DelComment`, `AddModComment`, the two Arabic comment ops | executable, but **never used** (0 comments) and **no public consumer** | not migrated |
| `W2ACDListModertors`, `ConfirmEditMod` (`_mod`) | moderator grants — excluded by instruction | not migrated |
| `W2ACDWaitingFB` / `ConfirmAddToShare` | a generic Facebook share queue (`nuke_waiting_fb` plus a cross-database `nuke_fb_autopost`), not CD administration | not migrated |
| `W2ACDListDown` | executable read-only report (`down = 0 OR trial > 5`), **always empty** (all `down = 2`), and nothing live writes `down`/`trial` for W2ACD | not migrated |
| `W2ACDDetailes`, `W2ACDMain` | display / menu only | replaced by the admin list and the edit page |
| `W2ACDRequestAdvFile`, `W2ACDRequestBanner`, `W2ACDUploadPdf` (public toolbar) | **DEAD** — links only, no handler anywhere. W2ACD has **no** promotional-file integration, so Phase 5 is untouched. | not migrated |
| `modules/w2a_cd/*` | **NOT W2ACD** — it administers `nuke_anasheed_*` (an Anasheed copy) | out of scope |

### 25.4 Storage contract (as implemented)

- **Release:** a `nuke_w2acd_w2acd` row.
  - **Create:** writes `title`, `mytime = time()`, `group_id`, `parent_id = group_id`, `link`, `cd` and `thumbnail`.
    It also writes `order_in_group = 1` (the real column default) and `lastvisit = 0`, `downloader = ''` (NOT NULL
    columns OLD's INSERT left to non-strict MySQL).
  - **Group counter:** `nuke_w2acd_groups.w2acd` moves ±1 on create and delete. Group 0 has no row.
- **Links:** two comma lists, paired by position (`CdLinkList`). A link is a pair: title plus URL.
- **Images:**
  - files go in the CD image directory; the list is kept in `thumbnail` with OLD's trailing comma;
  - the first image is the list cover; at most 5 (OLD's form had 5 slots); jpg, jpeg, png and gif accepted;
  - files are named `{slot}{time}.{ext}`, as OLD.
  - **Where files go:** the authoritative location is the directory Laravel's public pages read,
    `public_path('images/cds_image2')`. Production serves it from `public_html/images/`, a real directory per the
    deployment guide §6. It is configurable as `admin_cds.image_root` (`W2ACD_IMAGE_ROOT`) so tests and
    verification stay isolated; locally `public/images` is a symlink into the protected legacy tree.
- **Never written:** `nuke_w2acd_mirror`, comments, banners and group rows (except the counter). A test proves
  `nuke_w2acd_mirror` receives no write.

### 25.5 Intentional corrections (Behavior First ≠ Bug Forever)

1. **Link pairs stay aligned.** OLD dropped empty titles and empty URLs independently, which shifted later titles
   onto the wrong links. Every row is now both parts or neither. Commas are refused in either part (they are the
   delimiter), and the joined titles must fit `cd varchar(255)`.
2. **Title limit is 100.** That is the form's `maxlength` and edit's own check; create's unreachable 200 check is
   not used.
3. **A group change moves both groups' counters.** OLD left them drifting. Decrements are floored at 0, and there is
   no bulk repair.
4. **Ordering must name exactly the group's releases.** OLD updated any posted id. Only changed rows are written.
5. **Uploads never overwrite an existing file.** A suffix is added when the OLD name is taken, and files written
   by a failed transaction are removed.
6. **Edits are planned against the locked row,** not a possibly stale model.
7. **Unchanged** edits and orders write nothing and record no audit row.
8. **POST/PUT/DELETE with CSRF.** OLD's `del_cd_img` was a GET.

Parity kept: replaced or dropped image files are **not deleted** from disk (OLD never deleted them); `parent_id` is
set on create only.

### 25.6 Permissions and UI

- **`w2acd.managecds`** (minted; label «الاسطوانات الدعوية», OLD's own menu text). OLD gated the whole
  `modules/w2acd/admin` with one module check, and no `admincp/` menu names W2ACD. It is a definition only.
- **UI:** a flat sidebar entry, «الاسطوانات الدعوية» (no new parent):
  - a release list in public order, with a position column to reorder;
  - create and edit forms: title, links table, images (keep / replace / add), an optional group selector shown only
    when groups exist;
  - a delete action;
  - "view on site" links.

### 25.7 Implementation

- **Architecture:** `CdReleaseController` → `CdReleaseRequest` / `CdOrderRequest` →
  `Actions/Cds/{Create,Update,Delete}CdReleaseAction`, `ReorderCdReleasesAction` → DB.
- **Support classes:** `CdLinkList`, `CdImageList`, `CdGroupCounter`, `CdAuditText`.
- **Models:** reuses `Content\Models\W2acdItem` and `W2acdGroup`; no parallel model.
- **Transactions:** one per mutation, with the release and group rows locked.
- **Routes:** 7 under `admincp/cds`.
- **Config:** `config/admin_cds.php`.
- **Audit:** in the same transaction as the mutation, through the Phase 0 recorder.

  | Action | Metadata |
  |---|---|
  | `add_cd` | title, group, links, image names, group counter |
  | `edit_cd` | `before`/`after` of the changed fields only, plus group counters |
  | `del_cd` | a full snapshot |
  | `order_cd` | one row per changed release |

  No file contents are recorded; markup-looking text is replaced by a marker. Legacy logging stays default-OFF.
- **Integration changes:**
  - `MainSchema::nukeW2acdW2acd` gained the real `parent_id`, `notes` and `downloader` columns;
  - `nukeW2acdGroups` gained `w2acd`;
  - a new `nukeW2acdMirror` fixture (used only to prove it is never written).

  No Phase 0–5 code changed.

### 25.8 Results

| Gate | Result |
|---|---|
| Phase 6 focused (2 files) | **48 passed**, 206 assertions |
| Existing W2ACD and public content tests (`W2acdController`, `HomeController`, `RecordsView`, `ContentSidebarWidget`) | **76 passed** |
| Phase 0–5 regression, plus the sidebar test | **403 passed** |
| Full suite | **1,651 passed, 1 skipped** (pre-existing), 0 failed |
| PHPStan | **0 errors**, after removing one redundant `array_values()` it flagged |
| Pint | passed on the Phase 6 files and on the touched shared files |

**Mutation testing:** 16 mutations, including one neutral control.

- **14 caught directly.** They cover:
  - pair alignment and the comma guard;
  - the trailing-comma format;
  - file cleanup on failure;
  - the create counter, the floor, and the group-change counters;
  - the no-op guard;
  - the stale-model plan;
  - order completeness and order direction;
  - the explicit `order_in_group`;
  - delete authorization;
  - group resolution.
- **1 caught after adding a test.** The no-overwrite guard first survived; a test pre-occupying the OLD file name
  now catches it.
- **1 neutral control,** which passed as expected.

### 25.9 Local runtime verification (real local MySQL, real HTTP)

The safety gate was `APP_ENV=local`, `main` = `127.0.0.1:3307/olddb`. The run used `php artisan serve` on
`127.0.0.1`, started with `W2ACD_IMAGE_ROOT` pointing at an isolated scratch directory, with real login, sessions,
CSRF and multipart uploads. Disposable fixtures:

- a group (so ordering never touched the real 39 releases in group 0);
- an outsider (admin role only);
- a manager (`w2acd.managecds`).

The seeder added the one new definition.

**The first run reported 1 failure, a script defect.** It asserted "newest first" for two releases created in the
same second, whose `mytime` tied. The check was replaced with presence; ordering is verified after the explicit
reorder. Teardown restored everything, and the re-run passed **43/43**:

- **Authorization.** The outsider got 403 on list and create, and created nothing.
- **Create.** A release with 2 images and 2 links became comma lists with a trailing-comma image list. The images
  were written **only** to the isolated root. `group_id` and `parent_id` are the group, `order_in_group` is 1, and
  the group counter went 0 → 1 → 2. A comma in a link title was refused.
- **Public pages.** The group list shows both releases with the cover image. The item page shows the paired links.
  After reorder, `order_in_group` is 2/1 and the public order follows.
- **Edit.** Links were rewritten, one image was dropped from the list, and the file stayed on disk. An unchanged
  edit added no audit row.
- **Delete.** The counter went 2 → 1, and the public item page returns 404.
- **Audit.** The sequence was exactly `add_cd`, `add_cd`, `order_cd`, `edit_cd`, `del_cd`.
- **Untouched during the run:** the normalized mirror table, the real `images/cds_image2` directory (fingerprint of
  297 files), and the 39 real releases. No `nuke_islamic_logs` row was written.

**Restoration.** All 11 baselines were identical after teardown:

- whole-table MD5 of `nuke_w2acd_w2acd`, groups, mirror and comments;
- the real image-directory fingerprint;
- row counts of the admin, audit, legacy-log and Spatie tables;
- the `admin_audit_log` schema `a9f9ebb3d7c08f0230cb60a0d547ec61`.

No `p6v*` rows remain. Only the AUTO_INCREMENT counters advanced.

### 25.10 Remaining dependencies

- **Deploy with Phases 0–5.** No migration. Run `AdminPermissionSeeder`, which adds `w2acd.managecds`, and grant it
  through the Laravel permission editor.
- **Production:** leave `W2ACD_IMAGE_ROOT` unset, so the default `public_path('images/cds_image2')` applies. That
  directory must be writable by PHP. This is a deployment check, documentary only.
  **[Superseded 2026-11-09, register row N5:** in the split layout this default is wrong for Artisan and for any config cache
  (`config:cache` does not re-apply the public path). Set `W2ACD_IMAGE_ROOT` (and the other four roots) explicitly — see
  `docs/operations/split-layout-media-roots.md`.]**
- **Cutover.** Retire OLD `modules/w2acd/admin`. While both run, OLD's uploads land in OLD's own `images/` tree.
  Whether that is the same physical directory as Laravel's `public_html/images` in production is not established
  from the repository, so images uploaded through OLD during coexistence may not appear on Laravel pages.
  **Recommendation:** use Laravel for CD administration from deployment onward.
- **Not migrated, by classification (§25.3):** banners, groups and icons, comments, moderators, the share queue,
  the `weight` re-arrange, `ListDown`, and the dead or broken ops. Any of them would be a separate request; none is
  read by the current public pages.

### 25.11 Status

| Phase | Status |
|---|---|
| **0**–**5** | **IMPLEMENTED / LOCAL VERIFIED / PRODUCTION DEPLOYMENT PENDING** (frozen) |
| **6** — W2ACD Administration (CD / Album Downloads) | **IMPLEMENTED / LOCAL VERIFIED / PRODUCTION DEPLOYMENT PENDING** |

Phase 7 has not started.

---

## 26. Phase 7 — Mobile Downloads + Visitor Contributions: business approved, stopped at Step 1 (2026-10-14)

### 26.1 Decision received

- **BUSINESS APPROVED — MOBILE DOWNLOADS REQUIRED.**
- **BUSINESS APPROVED — VISITOR CONTRIBUTIONS & MODERATION REQUIRED.**

Rule: migrate what genuinely existed and worked in OLD, and do not recreate dead, broken or unreachable behavior.

The narrow Step 1 verification (the OLD `modules/Mobile` source plus a read-only local data check) found two
material issues that this rule cannot settle on its own. **Nothing was implemented.** Passes 3C and 3D stand unchanged.

### 26.2 Local data (read-only)

| Table | Rows | Notes |
|---|---|---|
| `nuke_downloads_categories` | 26 | Two levels via `parentid` (11 top-level). These are the "subcategories". |
| `nuke_downloads_subcategories` | — | **Table does not exist.** The code paths using it (`viewsdownload`, `sid`) are dead; `sid` is 0 on every row. |
| `nuke_downloads_downloads` | 341 | Dated **2006-12 → 2010-12**; nothing after 2010. About 1.5M historical hits. 166 carry votes, 108 a rating, 5 comments. |
| `nuke_downloads_newdownload` | 5 | Pending visitor submissions (ids 39–43, old). |
| `nuke_downloads_modrequest` | 57 | **All broken-link reports** (`brokendownload = 1`). 0 pending correction requests. |
| `nuke_downloads_votedata` | **0** | The rating and comment rows are gone, though the materials' counters remain. |
| `nuke_downloads_editorials` | **0** | — |

**Download targets:**

- 275 of 341 (81%) point to `way2allah.knoz4arab.com/media/mobile/…`. OLD's `getit()` rewrites the host to
  `way2allah.way2allah.com`, a subdomain whose existence is not established.
- 66 point to `way2allah.com` / `forums.way2allah.com` pages.
- The equivalent local path `media/mobile/` exists but is **empty** (the local checkout carries no media), so none
  of the 275 files can be confirmed.

### 26.3 Verified executable state of OLD

**Members.** OLD's visitor "member" is the vBulletin session: `is_user()` = `VB5('id') > 0`. That is the same
population as Laravel's existing `vbulletin` guard, so the visitor rules are reconstructible:

- submission is members-only (`downloads_anonadddownloadlock = 1`);
- correction requests are members-only (`blockunregmodify = 1`);
- broken-link reporting is offered only to members;
- ratings are allowed for anonymous visitors, once per IP per day.

**PHP version.** OLD's `.user.ini` logs to `/home/php8way2allah/logs/`, and `server_migration_parity.md` records the
site at PHP 8.x. The module still calls PHP 5-only functions, which fatal when reached:

| Area | Call site | Effect on PHP ≥ 7 |
|---|---|---|
| `newdownloadgraphic()` (`ereg`, runs unconditionally) | called by **`index()` (the main Mobile page)**, `viewdownload` (every category listing), new items, top rated, most popular, search | **The section's front page and all listings fatal** |
| `viewdownloaddetails`, `viewdownloadcomments`, `viewdownloadeditorial` | `ereg_replace` | details, comments and editorial fatal |
| `search` | `ereg_replace` / `ereg` | fatal |
| `ratedownload` (the rating form) | `ereg_replace` | fatal, so no rating can be reached |
| `Add` (visitor submission) | `ereg_replace` on the file size, before the INSERT | **submission fatals** |
| admin `downloads()` (the admin main page) | `ereg_replace` inside the loop over pending submissions (5 exist) | **the admin entry page fatals** |
| admin `DownloadsModDownload` | `ereg` inside the editorial/vote loops | runs only because those tables are empty |
| admin `DownloadsListModRequests` | `eregi_replace` inside the correction-request loop | runs only because there are 0 correction requests |

Free of PHP 5-only calls (behavior otherwise unverified):

- `getit` — the download redirect, with its `+1 hits`;
- `brokendownload(S)` and `modifydownloadrequest(S)` — reachable only by direct URL once the details page is
  broken;
- `addrating` — its insert, then a fatal in the footer;
- several admin POST handlers, reachable only through the broken admin main page.

**Reading:** if OLD runs PHP 7+ in production, the Mobile section has effectively been **non-functional publicly and
administratively since that upgrade**. It historically worked: the data from 2006–2010 and the hit and vote counters
prove it. This is consistent with no material after 2010 and 57 unhandled broken-link reports.

### 26.4 Decisions and facts required

1. **Owner decision (the material ambiguity).** Should Phase 7 rebuild the section's *historically working*
   behavior? That means treating the PHP 5→8 breakage as a defect to fix, as Phase 5 did for its crashed queue, but
   at the scale of nearly the whole module. Or should it migrate only what works on OLD today? That is essentially
   the download redirect and a few direct-URL handlers. The rule's two halves ("migrate what worked" and "don't
   recreate broken behavior") point in opposite directions here.
2. **Production fact (read-only, for the owner).** The PHP version OLD runs in production. Repository evidence
   points to PHP 8 but does not prove it.
3. **Production fact (read-only, for the owner).** Do the download files exist? Check whether `media/mobile/…`
   (for example `media/mobile/do3a/Do3a2.wav`) exists on the production media host, and whether
   `way2allah.way2allah.com` resolves. 81% of downloads depend on this, and the local checkout cannot show it.

Once these are answered, implementation can proceed from the contract already recovered (§26.2–§26.3).

### 26.5 Owner decision — Issue 1 closed (2026-10-14)

**Restore the section's historical working business behavior.** The PHP 7/8 incompatibility is classified as a
**runtime defect, not a business retirement**. "Working behavior" means the behavior that worked before the
incompatibility, not whatever happens not to crash on OLD's current runtime.

**In scope, where historically executable:**

- the public Mobile Downloads section;
- member material submissions;
- member correction requests;
- member broken-link reports;
- ratings and comments;
- staff content administration;
- staff moderation.

**Still dead — not revived:**

- code depending on the nonexistent `nuke_downloads_subcategories` table (`viewsdownload`, `sid`);
- undefined or unreachable handlers;
- historically unused or broken functionality, unless evidence establishes a real business contract.

Categories and subcategories use the real `parentid` hierarchy.

With this decision, §26.4 items 1 and 2 are resolved: the exact production PHP version no longer changes the scope.

**Still open — Issue 2, §26.4 item 3.** Production media availability must be verified before implementation. It is
to be checked by the owner, read-only. Implementation is not started.

### 26.6 Production media verification — Issue 2 resolved for scope (2026-10-14)

The owner ran these read-only checks:

| Check | Result |
|---|---|
| `/home/w2acp/public_html/media/mobile` | exists, **0 files** |
| `/home/php8way2allah/public_html/media/mobile` | does not exist |
| the seven representative historical files | **none found anywhere under `/home`** |
| `way2allah.knoz4arab.com` | **does not resolve** |
| `way2allah.way2allah.com` (OLD's rewrite target) | DNS lookup timed out from the production server |

Local data adds two facts:

- 275 of 341 materials use historical `media/mobile` URLs on `way2allah.knoz4arab.com`;
- **68** of those are **truncated** at the legacy `varchar(100)` limit. This is existing data damage.

**Owner decision: APPLICATION MIGRATION ≠ HISTORICAL MEDIA RECOVERY.**

- **Implement Phase 7's historical working behavior.**
- **Media and URLs:**
  - no media files are invented;
  - the 275 stored URLs are **not** rewritten to `/media/mobile/…`, because that directory is empty;
  - stored URLs are preserved unless a normal application operation changes one;
  - the 68 truncated URLs are **not** guessed or bulk-repaired.
- **Public pages** must handle unavailable historical downloads honestly: no pretended availability, and no redirect
  to a nonexistent file.
- **New materials** follow the recovered write contract (a stored URL).
- **Historical media recovery** (from backups, archives or another authoritative source) is a **separate, controlled
  task outside Phase 7**.

### 26.7 Recovered executable contract and classification

Sources: `modules/Mobile/{index,voteinclude,d_config}.php`, `modules/Mobile/admin/index.php`, OLD `.htaccess:804-870`,
OLD `mainfile.php` (`is_user()` = `VB5('id') > 0`), and the local schema and data.

| Area | OLD op(s) | Class | Laravel |
|---|---|---|---|
| Main page | `index` | WORKING (historically; PHP 7+ breakage is a defect, §26.5) | `mobiles.htm`: top-level categories by title, ≤3 subcategories each, "new" badge, totals |
| Category page | `viewdownload` | WORKING | 8 sort keys (`title/date/hits/rating` × A/D, default `titleA`), 10 per page, `?show=`; an unknown sort key redirects home (OLD) |
| Subcategory page | `viewsdownload` (`sid`, `nuke_downloads_subcategories`) | **DEAD** — the table does not exist | not migrated; subcategories are the `parentid` tree |
| New items | `NewDownloads`, `NewDownloadsDate` | WORKING | per-day counts, week/month totals, date page |
| Top rated / most popular | `TopRated`, `MostPopular` | WORKING (default form) | rating ≠ 0 and ≥ 5 votes, top 25 / hits, top 25 |
| Parameterized top/popular URLs | `mobile-{toprated,mostpopular}-{n}-{type}.htm` | **DEAD** — the rules pass `num/percent` as the count (SQL error) | not migrated |
| Search | `search` | WORKING | title/description; matching categories; order; paging |
| Details / comments / editorial | `viewdownload{details,comments,editorial}` | WORKING | per-voter-type rating split; comments newest first; editorial |
| Download | `getit` | WORKING (`hits + 1`, redirect) | as OLD for an available URL; **unavailable → 410 page, no redirect, no hit** (§26.6) |
| Submission | `AddDownload`/`Add` | WORKING (members only, `anonadddownloadlock = 1`) | members only; refuses a published URL; silent de-dup of pending matches |
| Correction request | `modifydownloadrequest(S)` | WORKING (members only, `blockunregmodify = 1`) | members only; every proposed field stored (`brokendownload = 0`) |
| Broken-link report | `brokendownload(S)` | WORKING (members only) | members only (`brokendownload = 1`); repeats kept, as OLD |
| Rating / comment | `ratedownload`, `addrating`, `voteinclude.php` | WORKING | 1–10; members may comment; own-material, member-once and anonymous-once-per-IP-per-day rules; summary recomputed from vote rows, truncated to a whole number |
| "Outside" (cross-site) voting | `outsidedownloadsetup`, `ratinguser = outside` | no evidence of use; needs a CSRF-exempt cross-site POST | **not migrated** (the calculator still weights any historical `outside` rows) |
| Link to us / points | `outsidedownloadsetup`, `update_points()` | PHP-Nuke member points: a no-op for vBulletin members | not migrated |
| `CoolSize` | dispatcher case, no function | **DEAD** | — |
| Staff: categories | `AddCat`, `AddSubCat`, `ModCat(S)`, `DelCat`, `Transfer` | WORKING (the `ModCat`/`DelCat` subcategory-table branches are dead) | add (top, or sub under a top), edit title/description, delete (cascade as OLD), transfer all materials |
| Staff: materials | `AddDownload`, `ModDownload(S)`, `DelDownload` | WORKING | add (duplicate URL refused), edit (all fields including hits), delete |
| Staff: submissions | `downloads()` queue → `AddDownload new=1`, `DelNew` | WORKING (the admin entry page crashes on PHP 7+, a defect) | approve as edited (submitter kept) / reject |
| Staff: requests | `ListModRequests`, `ChangeModRequests`, `ChangeIgnoreRequests`, `ListBrokenDownloads`, `DelBrokenDownloads`, `IgnoreBrokenDownloads` | WORKING | accept / ignore a correction; delete the material with **all** its requests, or ignore its broken reports |
| Staff: votes | `DelVote`, `DelComment`, `CleanVotes` | WORKING | delete a vote (recompute); blank a comment (`totalcomments − 1`); recalculate the materials that have vote rows |
| Staff: editorials | `AddEditorial`, `ModEditorial`, `DelEditorial` | WORKING | one per material; records the admin `aid` |
| Link checker | `DownloadCheck` / `DownloadsValidate` | **DEAD in OLD's own logic** — only the `$result3` (subcategory `sid`) loop exists, so "all" and "by category" output nothing | not migrated; no outbound requests anywhere in Phase 7 |

### 26.8 Data and storage

Phase 7 uses the existing tables as they are; there are no new tables or migrations:

- `nuke_downloads_categories` (`parentid` tree);
- `nuke_downloads_downloads`;
- `nuke_downloads_newdownload` (pending submissions);
- `nuke_downloads_modrequest` (`brokendownload` 1 = report, 0 = correction);
- `nuke_downloads_votedata` (a vote's `ratingcomments` *is* the comment);
- `nuke_downloads_editorials`.

**Downloads are external URLs.** OLD stored no files; the Mobile module has no upload path. Stored URLs are never
rewritten, and the 68 truncated URLs are left as they are (§26.6).

**Counter maintenance happens at mutation time only:**

- the vote recompute after a rate or a vote deletion;
- `totalcomments − 1`, floored at 0;
- `hits + 1` on download.

No bulk repair. The explicit staff "recalculate" touches only materials that still have vote rows, so the 166
historical counters stay until a new vote recomputes them. That is OLD's contract, documented in `MobileRating`.

### 26.9 Intentional corrections (Behavior First ≠ Bug Forever)

1. **The PHP 7/8 fatals** (`ereg*`) are not reproduced. Every historically working page works.
2. **Identity.** OLD took the submitter, requester or voter name from the PHP-Nuke cookie, which vBulletin members
   never had (blank in 339 of 341 rows), so its once-per-member vote rule treated all members as one. The member's
   vBulletin **username** is used instead.
3. **Unavailable downloads** (dead host per §26.6, or non-http) get a 410 page with no redirect and no hit. Stored
   URLs are untouched.
4. **New URLs:** an http(s) URL of at most 100 characters is required, so no new truncation is possible. Staff can
   still save historical URLs unchanged.
5. **Subcategory duplicate titles** are checked among siblings. OLD compared the new title with the parent's own
   title (`cid` written for `parentid`).
6. **Rating precision:** truncation to a whole number everywhere. That is what `addrating` stored (every stored
   value is whole); `DelVote`/`CleanVotes` stored decimals.
7. **`totalcomments` is floored at 0.** OLD had no floor.
8. **Rendering:**
   - descriptions and editorials are rendered as safe text (line breaks kept, tags stripped). 291 of 341 stored
     descriptions contain HTML, which OLD echoed raw;
   - the author's e-mail is not shown publicly (OLD printed it obfuscated);
   - all inputs are parameterized, and every mutation is POST/PUT/DELETE with CSRF.

### 26.10 Permissions, URLs, UI

**Permissions:**

- staff: **`mobile.managedownloads`** (minted, «إدارة قسم الموبايل»). OLD gated `modules/Mobile/admin` with one
  module check. It is a definition only.
- visitors: OLD's rules exactly — members = the vBulletin session (the existing `vbulletin` guard), anonymous for
  rating only. Visitor actions are not admin-audited, consistent with the site's other public actions.

**Preserved legacy URLs** (served at their exact paths, as the site's other legacy pages are):

- `mobiles.htm`, `mobiles-cat{cid}.htm`, `mobile{cid}-orderby{o}.htm`, `mobile{cid}-{min}-{o}-{show}.htm`;
- `mobiles-newmobiles.htm`, `mobile-shownew-{days}.htm`, `mobile-seldate-{ts}.htm`;
- `mobiles-mostpopular.htm`, `mobiles-toprated.htm`;
- `mobile-search-{q}[-{o}|-{min}-{o}-{show}].htm`;
- `mobileview-{details|comments|editorial}-{lid}[-{title}].htm`;
- `mobile-file-{lid}.htm`;
- `ratemobile-{lid}[-{title}].htm`;
- `mobiles-admobile.htm`, `mobile-mod-{lid}.htm`, `mobile-broken-{lid}.htm`.

Form posts go to clean `/mobile/...` paths. Each URL shape has its own thin controller method, because route
segments bind positionally (IF-051).

**Retired as dead:** `mobile-toprated-{n}-{type}.htm`, `mobile-mostpopular-{n}-{type}.htm`, `mobile-outside-{lid}.htm`.

**UI:**

- **Public:** 14 page views (plus 4 partials) under `mobile/`, on the site's public layout.
- **AdminCP:** a flat sidebar entry «قسم الموبايل» (no new parent), with a dashboard (counts plus recalculate),
  categories (tree, add, edit, delete, transfer), materials (list, add, edit with editorial, votes and comments),
  submissions, broken-link reports and correction requests.

### 26.11 Implementation and audit

- **Public:** `Content\Http\Controllers\MobileController` → `Mobile*Request` →
  `Content\Actions\Mobile\{SubmitMobileMaterial,RequestMobileChange,RateMobileMaterial}Action`, with the services
  `MobileCatalog`, `MobileRating` and `MobileSite`.
- **Staff:** `Admin\Http\Controllers\MobileAdminController` →
  `Admin\Actions\Mobile\{MobileCategoryAdmin,MobileMaterialAdmin,MobileRequestModeration,MobileVoteModeration,MobileEditorialAdmin}`.
- **Models and config:** six read models under `Content\Models\Mobile`; config in `config/mobile_downloads.php`
  (OLD `d_config.php` values, plus the verified dead host).
- **Transactions:** one per mutation, with row locks. A vote locks the material row, so concurrent votes cannot
  both pass a once-only rule.
- **Audit:** every staff mutation, in the same transaction, module `mobile`. The actions are `add/edit/del_mobile_cat`,
  `transfer_mobile`, `add/edit/del_mobile`, `approve/reject_mobile_submission`, `accept/ignore_mobile_correction`,
  `del/ignore_mobile_broken`, `add/edit/del_mobile_editorial`, `del_mobile_vote`, `del_mobile_comment` and
  `recalc_mobile_rating`.
  - Descriptions, editorials and comments are recorded only as SHA-256 + length.
  - Markup-looking text is replaced by a marker.
  - No-ops record nothing.
  - Legacy logging stays default-OFF.
- **Integration changes:** `MainSchema` gained six Mobile fixtures and `nukeConfig.anonymous` (the real
  `nuke_config` column). No Phase 0–6 code changed.

### 26.12 Results

| Gate | Result |
|---|---|
| Phase 7 focused | **70 passed**, 322 assertions — public 14, visitor 24, staff 32 |
| Phase 0–6 regression, plus the sidebar test | **451 passed** |
| Existing Content and Engagement suites | **945 passed, 1 skipped** (pre-existing) |
| Full suite | **1,721 passed, 1 skipped**, 0 failed |
| PHPStan | **0 errors**, after typing relations and closures it flagged |
| Pint | passed on the Phase 7 files and the touched shared files. `routes/content.php` has a pre-existing `ordered_imports` finding (the committed file already fails) and was not reformatted. |

**Mutation testing:** 20 mutations covered:

- the member gates;
- de-duplication and the published-URL check;
- the own-vote, member-once and anonymous-once rules;
- anonymous comments;
- truncation and weighting;
- dead-host availability and hit counting;
- sibling scoping and top-level cascade;
- delete-material request scope;
- correction-only accept;
- the comment floor and no-op;
- the staff duplicate-URL check;
- action authorization.

19 were caught directly. Sibling scoping first survived; a test now caught it, so all 20 are caught.

**Test design errors corrected during the run** (the code was unchanged):

- route parameters arrive as strings and bind positionally, so per-shape methods were added;
- a view-variable key collision.

### 26.13 Local runtime verification (real local MySQL, real HTTP)

The safety gate was `APP_ENV=local`, `main` = `127.0.0.1:3307/olddb`. The run used `php artisan serve` on
`127.0.0.1`, real admin login, sessions and CSRF, with disposable categories, materials, requests and votes, and
disposable admins:

- an outsider (admin role only);
- a staff member (`mobile.managedownloads`).

**Limitations of the run:**

- No local vBulletin database is configured (`DB_VBULLETIN_*` unset), so member-side HTTP flows are covered by the
  feature tests. Member rows to moderate were inserted directly as disposable data.
- No redirect was followed, so no outbound request was made.

**The first run stopped on a defect in the verification script**: a direct insert omitted `description`, which is
NOT NULL in the real schema (the app's own inserts always set it). Teardown restored everything, which was confirmed
by counts. The re-run passed **50/50**:

- **Real data, read only:** all real top categories are linked; a real historical material is marked unavailable,
  its download returns 410 with no redirect, and its hits are untouched; guest submission is refused.
- **Authorization:** the outsider got 403.
- **Staff:** created categories (a duplicate title was refused) and materials (dated now; size separators
  stripped; a duplicate URL was refused).
- **Public pages:** category, details as safe text, search and new materials work. An available download returned
  302 (not followed) and counted a hit; the dead-host download returned 410 with no hit.
- **Anonymous rating:** the vote was stored and the counters recomputed; a second vote the same day was refused.
- **Moderation:** submission approved (submitter kept), correction accepted, broken report ignored, comment then vote
  deleted (with recompute), editorial added (visible publicly), transfer, then a top-level delete cascade.
- **Audit:** exactly the 12 expected actions, and no `nuke_islamic_logs` row.

**Restoration.** All 13 baselines were identical after teardown:

- whole-table MD5 of the six Mobile tables and of `nuke_config`;
- row counts of the admin, audit, legacy-log and Spatie tables;
- the `admin_audit_log` schema `a9f9ebb3d7c08f0230cb60a0d547ec61`.

The real 341 materials, 26 categories, 5 submissions and 57 reports are unchanged, and no `p7v*` rows remain.

### 26.14 Remaining dependencies

- **Deploy with Phases 0–6.** No migration. Run `AdminPermissionSeeder`, which adds `mobile.managedownloads`, and
  grant it through the Laravel permission editor.
- **Cutover:**
  - point the site navigation to `/mobiles.htm` if a link is wanted;
  - retire OLD `modules/Mobile` and its admin.

  Until then OLD's Mobile pages remain broken on PHP 7+ (a pre-existing condition).
- **Member features in production** depend on the vBulletin session guard being configured (the existing
  `vbulletin` connection, cookie salt), as for any member feature.
- **Historical media recovery** is a separate, controlled task (§26.6): 275 historical URLs on a dead host, 68 of
  them truncated. Until it is done, those materials show "الملف غير متوفر حالياً".

### 26.15 Status

| Phase | Status |
|---|---|
| **0**–**6** | **IMPLEMENTED / LOCAL VERIFIED / PRODUCTION DEPLOYMENT PENDING** (frozen) |
| **7** — Mobile Downloads + Visitor Contributions / Moderation | **IMPLEMENTED / LOCAL VERIFIED / PRODUCTION DEPLOYMENT PENDING** |

## 27. Pre-deployment reconciliation and blocker resolution (2026-10-14)

### 27.1 Reconciliation outcome

A read-only reconciliation of Phases 0–7 before production deployment found one blocker. `/deploy/install` always ran `admin:sync-permissions` after `AdminPermissionSeeder`. `SyncLegacyAdminPermissionsAction` is generic: it intersects every legacy `nuke_authors` `{module}.{key}` flag with the seeded permissions, then calls `syncPermissions()`, which *replaces* the existing grants, and it toggles `super-admin` from `radminsuper`. Running the installer would therefore:

- import legacy assignments for the newly seeded keys (for example `khotab.addmirror`/`editmirror`/`delmirror`/`addcat`/`addadv`), contradicting "Laravel/Spatie is the sole authorization authority, no legacy grant import";
- erase every grant made in the AdminCP permission editor on each later deployment.

The installer was the only automatic caller of the sync. The permission editor, login and runtime authorization use Spatie only.

### 27.2 Decision: Option B, approved

- The installer no longer runs `admin:sync-permissions`.
- The command and the action are kept with no behavior change, as a deliberate, manual legacy-bootstrap tool only.
- Not done, by decision: no additive conversion, no exclusion lists, no legacy import, no change to existing grants or super-admin memberships, no super-admin UI.

**Consequence (intentional):** future changes to legacy `radminsuper` or legacy permission blobs do NOT automatically propagate to Laravel. Existing Laravel permissions and super-admin roles remain authoritative until explicitly changed through Laravel-owned mechanisms. The sync was the only path that mapped `radminsuper` to `super-admin`. No Laravel UI assigns that role today, so existing super-admin memberships stay as they are.

### 27.3 Changes (an integration point outside Phases 0–7, reported explicitly)

| File | Change |
|---|---|
| `app/Http/Controllers/DeploymentInstallerController.php` | `sync` removed from `STEPS` and from `runStep()`; docblock explains why and states the consequence |
| `tests/Feature/DeploymentInstallerControllerTest.php` | 3 new tests: the exact step list; the exact Artisan calls (no `admin:sync-permissions`); a rerun on populated data leaves `model_has_permissions` / `model_has_roles` unchanged |
| `database/seeders/AdminPermissionSeeder.php` | docblock correction: the keys are definitions only; the earlier "no legacy grant imported" claim did not hold while the installer ran the sync |
| `docs/decision-log.md` #32, `docs/PROJECT-HANDOFF.md` (§7 bullet, quick-reference row, §10), `deploy-staging/.../CPANEL-DEPLOYMENT.md` §11, `deploy-staging-v2/.../CPANEL-NO-SSH-DEPLOYMENT.md` §13 | dated superseded notes; history preserved |

The installer sequence is now: database check → `migrate --force` → `RoleSeeder` → `AdminPermissionSeeder` → `optimize:clear` → `config:cache` → `route:cache` → `view:cache`.

### 27.4 Verification

| Check | Result |
|---|---|
| A. Installer tests | **8 passed**, 29 assertions |
| B. No sync invocation | the recorded Artisan calls exclude `admin:sync-permissions`; the step list has no `sync` |
| C. Remaining steps | exactly migrate, RoleSeeder, AdminPermissionSeeder, optimize:clear, config:cache, route:cache, view:cache |
| D. A rerun cannot modify grants | populated editor grants plus a `radminsuper=1` admin with a legacy blob: snapshot unchanged after install |
| Mutation | with the sync step restored, all 3 new tests fail |
| E. Phase 0 authorization/permission regression (9 files) | **142 passed**, 427 assertions |
| F. Full suite | **1724 passed**, 1 skipped, 10852 assertions |
| G. PHPStan (controller, seeder) | 0 errors. Tests are outside the PHPStan paths; analysed directly, the test file shows only the existing Pest `$this->get/post` pattern, which also occurs at HEAD |
| G. Pint | seeder and test pass. The controller's 4 Pint findings already exist unchanged at HEAD and were left as they are |

Optional follow-ups (not scheduled): `--dry-run` hardening of `admin:sync-permissions`; Laravel-owned super-admin role management.

### 27.5 Status

| Item | Status |
|---|---|
| Phases **0**–**7** | **IMPLEMENTED / LOCAL VERIFIED / PRODUCTION DEPLOYMENT PENDING** (frozen) |
| Pre-deployment reconciliation | **PRE-DEPLOYMENT BLOCKER RESOLVED / READY FOR CONTROLLED PRODUCTION DEPLOYMENT** |

No deployment. No commit.

> **Superseded 2026-10-15 (§28):** the "ready for deployment" reading above was premature. The owner ruled that the
> OLD Admin migration was not complete: the contextual administration surface (admin controls on the normal public
> pages) was still missing. The blocker resolution itself stands. §28 records the contextual surface; deployment
> waits for a separate whole-migration closure review.
> **Final closure: §30.** The §27 installer fix remains in force.

## 28. Contextual administration surface — implementation and verification (2026-10-15)

### 28.1 Scope and owner decisions

**Scope correction (owner):** Phases 0–7 built the operations inside the AdminCP only. OLD's essential experience
also included an admin browsing the **normal public site** and finding, on each applicable page, controls for that
page's content. The reconciliation against Pass 3B/3C/3D found **0 of 25** such controls on the Laravel public site.
For 24 of them the Laravel operation already existed; one Laravel-owned function was missing (the Telawah per-item
link status). The cause: the public-site migration deliberately omitted every OLD admin control, because each one
pointed at OLD. The views record this as "ADMIN_ONLY / UNREACHABLE_IN_CURRENT_MIGRATION".

| Decision (2026-10-15) | Outcome |
|---|---|
| Social share queues (`KhotabWaitingFB`, `AnasheedWaitingFB`, `TelawahWaitingFB`) | **RETIRED** — no control, no replacement queue |
| Anasheed link-status block | **RETIRED** — no Anasheed link-quality subsystem |
| Return to the originating page | **YES** — through a validated same-site `return_to`; the existing AdminCP redirect is the fallback |
| CD create on `cds-group` pages | **Intentionally absent** — there is no Laravel public group page, and it is not moved to `cds-main` |

Everything EXTERNALLY_OWNED, RETIRED, DEAD or DEFERRED in §2 stays excluded (88 operations).

> **Superseded (2026-11-06, decision-log #100; ruling #72):** as a blanket exclusion, the sentence above no longer
> applies to OLD public contextual admin controls — under the owner rule of 2026-10-21, "externally owned" (BC#6) and
> "deferred" are not exclusions. Every public control was re-dispositioned module by module (§31–§53, #60–#82). The
> owner retirements in the table above stand. Kept as written for the record.

### 28.2 Architecture

| Part | Responsibility |
|---|---|
| `Contextual\ContextualControl` | A pointer to an **existing** admin route: label, icon, route and parameters, method, hidden fields, confirmation, return target. No business logic, no permission of its own |
| `Contextual\AdminRouteAccess` | Visibility = the target route's own `admin.role` / `admin.permission` middleware, applied exactly as `EnsureAdminHasRole` / `EnsureAdminHasPermission` apply it (super-admin rule included). An unknown or unguarded route fails loudly. An optional gate route covers links that open a shared page for one operation (add mirror → `store`) |
| `Contextual\ContextualAdmin` + `@contextualAdmin` | Per-request entry point. For a visitor it is false without a database query, and no control is even built |
| `x-admin.contextual-controls` | RTL toolbar or inline controls. GET → link; anything else → `POST` form with `@csrf`, `@method`, hidden fields, and `confirm()` where destructive. No mutation is ever a GET |
| `Contextual\ContextualReturn` + `HonorContextualReturn` | `safeTarget()` accepts only a same-site **public** path: no external host, scheme-relative URL, backslash, control character, user-info, `/admincp` or `/deploy`. The middleware swaps the final redirect only for the 24 contextual operation routes, only when the operation itself flashed `success` in this request (validation `errors`, refusals and no-ops keep the AdminCP redirect), and only to a target `safeTarget()` accepts. The success message is re-keyed and shown once, to the admin, on the public page |
| `Contextual\KeepContextualPagesPrivate` | A response that rendered controls gets `Cache-Control: no-store, private`; visitor responses are untouched |
| `Contextual\Controls\*` | The per-page control definitions (§28.3) |

Both middleware are appended to the `web` group through the HTTP kernel; the provider is `ContextualAdminServiceProvider`.

### 28.3 Final contextual implementation matrix

| # | Public page | Control (OLD / Gen 2 origin) | Phase | Target route | Placement | Status |
|---|---|---|---|---|---|---|
| K1 | Khotab item | إضافة وصلة (`AdminPanel` :801, `adminItemControls`) | 1 | GET `admin.mirrors.khotab.show` (gate: `store`) | toolbar | **IMPLEMENTED** |
| K2 | Khotab item | تعديل الوصلة (`Mirror()` :1217) | 1 | GET `admin.mirrors.khotab.edit` | beside each mirror | **IMPLEMENTED** |
| K3 | Khotab item | حذف الوصلة (`Mirror()` :1219) | 1 | DELETE `admin.mirrors.khotab.destroy` | beside each mirror | **IMPLEMENTED** |
| K4a | Khotab item | pick to move (OLD `MirrorMove` cookie) | 1 | POST `admin.mirrors.khotab.pick` | beside each mirror | **IMPLEMENTED** |
| K4b | Khotab item | move picked mirror here (`Detailes` :1011), plus cancel | 1 | POST `admin.mirrors.khotab.move` / `clipboard.clear` | toolbar, only when another item's mirror is picked | **IMPLEMENTED** |
| K5 | Khotab item | إضافة تصنيف (`AdminPanel` :799) | 3 | GET `admin.categories.khotab.item` | toolbar | **IMPLEMENTED** |
| K6 | Khotab item | remove category (`Tree()` :1152) | 3 | POST `admin.categories.khotab.item.remove` | beside each category chain; categories in `cat` but not shown publicly are listed under the toolbar | **IMPLEMENTED** |
| K7 | Khotab item | طلب رئيسية, only while `advfile = 0` (`AdminPanel` :848) | 5 | POST `admin.advfiles.request` (any admin) | toolbar | **IMPLEMENTED** |
| K8 | Khotab item | admin link status (`AdvancedStats` :1032) | 2 | GET `admin.link-quality.khotab.item` (no return_to) | toolbar | **IMPLEMENTED** |
| G1–G2 | Khotab group | اضف للكل / امسح من الكل (`ListCats` :404/:407) | 3 | POST `admin.categories.khotab.group.add-all` / `remove-all` | beside each of the group's categories | **IMPLEMENTED** |
| S1–S2 | Khotab series | the same pair (`ListCats`, Gen 2 `breadcrumb()`) | 3 | POST `admin.categories.khotab.series.add-all` / `remove-all` | beside each of the series' categories | **IMPLEMENTED** |
| A1 | Anasheed item | إضافة وصلة (`AdvancedStats` :869, Gen 2) | 1 | GET `admin.mirrors.anasheed.show` (gate: `store`) | toolbar | **IMPLEMENTED** |
| A2–A3 | Anasheed item | edit / delete mirror (`Mirror()` :525) | 1 | GET `…anasheed.edit` / DELETE `…anasheed.destroy` | beside each mirror | **IMPLEMENTED** |
| A4 | Anasheed item | إضافة تصنيف (`item_cp.htm`, Gen 2) | 3 | GET `admin.categories.anasheed.item` | toolbar | **IMPLEMENTED** |
| A5 | Anasheed item | remove category (`Tree()` :396) | 3 | POST `admin.categories.anasheed.item.remove` | admin category list (the page shows none publicly) | **IMPLEMENTED** |
| P1 | Poll list (`surveys.htm`, `/polls`) | Edit (`pollList` :241/:279) | 4 | GET `admin.polls.edit` | beside each poll | **IMPLEMENTED** |
| P2–P3 | Poll results (both URL forms) | Add / Edit (`pollResults` :418) | 4 | GET `admin.polls.create` / `admin.polls.edit` | under the results | **IMPLEMENTED** |
| W1 | CD item | تعديل (`item_cp_cd.htm`) | 6 | GET `admin.cds.edit` | toolbar | **IMPLEMENTED** |
| W2 | CD item | مسح (`item_cp_cd.htm`) | 6 | DELETE `admin.cds.destroy`, returns to `/cds-main.htm` | toolbar | **IMPLEMENTED** |
| M1 | Mobile: category, by date, most popular, top rated, search, comments | edit material (`DownloadsModDownload`) | 7 | GET `admin.mobile.materials.edit` | beside each material (comments page: once, for its material) | **IMPLEMENTED** |
| T1 | Telawah item | admin link status (`AdvancedStats` :317) | C5 | GET `admin.link-quality.telawah.item` — **new read-only page**, posting to the existing recheck / fix-size routes | toolbar | **IMPLEMENTED** |

**Counts: 25 accepted controls — 25 implemented, 0 missing.** The only new function is T1's read-only page. No
business logic was added or duplicated.

### 28.4 Changes outside the new contextual files — reported explicitly

| File | Change | Why |
|---|---|---|
| `routes/admin.php` | one GET route `admin.link-quality.telawah.item` (`telawah.repair`) and its import | C5's approved new page |
| `bootstrap/providers.php` | registers `ContextualAdminServiceProvider` | — |
| `layouts/app.blade.php` | `<x-admin.contextual-flash />` above the content | admin-only success notice after a return |
| `layouts/admin.blade.php` | "opened from the site" notice with a back link, only when a valid `return_to` is present | — |
| Public views: `khotab/item`, `khotab/group`, `khotab/series`, `anasheed/item`, `engagement/polls/index`, `engagement/polls/results`, `w2acd/show`, `mobile/_material`, `mobile/comments`, `telawah/item`, `components/content/media-quality-list` | `@contextualAdmin` blocks only | the attach points |
| **Frozen Phase 1/3/4/6/7 AdminCP views** — `mirrors/khotab/show` (5 forms), `mirrors/khotab/edit` (3), `mirrors/anasheed/show` (2), `mirrors/anasheed/edit` (2), `categories/item` (2), `polls/create`, `polls/edit`, `cds/form` (save only), `mobile/material-form` (save, delete) | **contextual-integration change:** one `<x-admin.contextual-return />` hidden field per form, nothing else | carry `return_to` through a GET-then-submit flow |

**No frozen controller, action, FormRequest, route permission, transaction or audit behavior changed.** The return
flow lives entirely in the `web` middleware. The CD delete form on the AdminCP edit page carries no `return_to`,
because the deleted CD's public page would no longer exist; the public delete control returns to the CD listing
instead.

### 28.5 Verification

| Check | Result |
|---|---|
| Contextual suite (7 files) | **97 passed**, 1130 assertions |
| — route-derived authorization vs the real middleware | identical for **every** admin route × 5 admins (plain, mirrors, categories, mixed, super-admin) |
| — return_to validator | 21 cases: external, scheme-relative, backslash, `javascript:`, `data:`, header injection, user-info, other port, AdminCP and deploy paths all rejected |
| — return flow on real routes | success → public page with the message; absent / invalid / external → AdminCP fallback; validation failure and refusal → AdminCP; routes outside the set ignore it; a denied admin is still 403 |
| — per page, per control | visitor: nothing. Admin without the permission: nothing, and the direct route refuses (401 / 403). Admin with the permission: the exact route, method and fields. Super-admin: every control. Mutations are CSRF forms with method spoofing and a confirmation where destructive. Per-record controls sit inside their record's card or beside their category |
| — real operations from the page | category remove (item, series bulk), mirror delete / edit / pick, advfile request, poll edit, CD delete, Mobile material edit: data changed and landed back on the page |
| Phase 0–7 admin regression (`tests/Feature/Admin`) | **716 passed** |
| Full suite | **1821 passed**, 1 skipped (the existing skip), 11982 assertions — up from 1724 by exactly the 97 contextual tests |
| PHPStan (level 5, `app` + `routes`) | **0 errors** |
| Pint, new and changed PHP files | clean. `routes/admin.php` keeps its existing `ordered_imports` finding: the import order was already out of order before this work, and the new import is in place |
| Visitor HTML, 18 public pages, local MySQL, before vs after | **identical content**; the only differences are whitespace from the new directive lines. One sidebar download counter differed (152 → 153). That is a value served by the existing performance query cache, refilled during a rolled-back view; the database value is unchanged (verified) |
| Local HTTP walkthrough (real kernel, local MySQL, real login form, real CSRF, file sessions; disposable admins inside rolled-back transactions on both connections) | **57 of 57 checks passed** — visitor 12, limited admin 16, super-admin 29; including CSRF-less POST → 419, external `return_to` → AdminCP fallback, `Cache-Control: no-store` on admin pages. Cleanup: 0 disposable admins and 0 session files left; data restored |
| OLD URLs / legacy permission data | none used: no control targets OLD, and nothing reads `nuke_*_mod`, `nuke_authors.permissions` or `radminsuper` (tested) |

### 28.6 Status

| Item | Status |
|---|---|
| Phases **0**–**7** | IMPLEMENTED / LOCAL VERIFIED (frozen) |
| Contextual administration surface (C0–C6) | **IMPLEMENTED / LOCAL VERIFIED** — 25 of 25 accepted controls |
| **ADMIN MIGRATION** | **IMPLEMENTATION COMPLETE / LOCAL VERIFIED** |
| **PRODUCTION DEPLOYMENT** | **STILL NOT STARTED** — a separate whole-migration closure review comes first |

No production queries. No deployment. No commit.

> **Superseded 2026-10-15 (§29, §30):** "IMPLEMENTATION COMPLETE" above was premature. Applying the owner's
> behavior-first rule found more working, unretired behavior: Telawah mirror edit / delete, public promotional files,
> public poll comments, and the public Telawah mirror list. All four are now built. The final closure is §30.

## 29. Closure correction — owner rule applied to three areas (2026-10-15)

**Owner rule (already established, re-stated):** working legacy behavior with no explicit retire / exclude /
external decision is migrated or preserved. "Working" follows §26.5: a PHP 7/8 incompatibility is a runtime defect,
not a retirement. The closure review's verdict (§28 follow-up) is **withdrawn**.

### 29.1 Telawah mirror administration — WORKING (edit, delete) → IMPLEMENTED

| OLD operation | Evidence (`modules/Telawah/`) | Classification |
|---|---|---|
| Public listing with admin edit / delete beside each mirror | `index.php:345-392` `Mirror()` — `LEFT JOIN` on `telawa_id`; `$admin_option` for admins | live reader in OLD; Gen 2 and Laravel public pages list no Telawah mirrors |
| Edit (`TelawahEditMirror` → `TelawahConfirmEditMirror`) | `admin_edit_mirror.htm` posts `comment`, `link`, `vedio`, `backupme`; `ConfirmEditMirror` `UPDATE`s exactly `comment`, `video`, `backupme`, `link` (`admin/index.php:1529-1553`). The form page's parent lookup uses a nonexistent `khid` (non-fatal) | **WORKING** |
| Delete (`TelawahDeleteMirror` → `TelawahConfirmDeleteMirror`) | confirmation template, then `DELETE` (`:1557-1589`); no counter (none exists, §3E.1 0.2.2) | **WORKING** |
| Insert (`Telawahaddlink` → `TelawahConfirmInsertMirror`) | `AddMirror()` prints «هذه الخاصية غير متاحة في الوقت الحالي»; the form include is commented out "as it is not completed yet"; `ConfirmInsertMirror()` `die()`s before any write (`:1433-1460`) | **DISABLED BY OLD ITSELF — not recreated** |

Data: 1 production row (3E.1 §0.4); the local row was created 2018-12-24.

**Implementation (reuses the Phase 1 pattern):**

| Part | Detail |
|---|---|
| Model | `TelawahMirror` (`nuke_telawah_mirror`) and `TelawahItem::mirrors()`; `TelawahItem`'s docblock corrected (3E.1 §4.1(a)) |
| Actions | `UpdateTelawahMirrorAction` (OLD's four columns only; no `trial`, `size`, `extension`, `creation_date` or parent write) and `DeleteTelawahMirrorAction` (physical delete): authorized, row lock, one transaction, structured audit inside it. OLD's own checks and log calls were commented out — corrected, as in Phase 1 |
| Permissions | `telawah.editmirror`, `telawah.delmirror` — OLD's own GreenZone token names; no `addmirror`. Definitions only |
| Routes | `admincp/mirrors/telawah` — lookup, item list, edit/update (`editmirror`), delete (`delmirror`); no store route |
| AdminCP | sidebar «الوصلات البديلة» under التلاوات; lookup by recitation or mirror id; item list; edit page with delete |
| Contextual | the public Telawah item page gives admins (only) the item's mirrors with edit / delete beside each; visitor HTML unchanged. Both operations join the validated `return_to` set |

**Verification:** 13 new tests (authorization matrix, exact write set, audit inside the transaction with rollback on
audit failure, no legacy log write, no insert route, AdminCP reachability, contextual controls, return flow); full
suite **1834 passed**, 1 skipped; PHPStan 0; Pint clean on changed files. Local runtime on the real local row (real
login, CSRF, sessions; rolled back): **14/14** — visitor nothing; edit-only admin sees edit, not delete, and is
refused delete (403); a contextual edit changed only `comment` and returned to the page with the notice and an audit
row; super-admin delete without CSRF → 419, with CSRF → deleted and returned. Row, audit table, admins and sessions
restored. The main walkthrough was re-run: 57/57. Locally `AdminPermissionSeeder` added the two definitions (41 → 43
permissions; grants unchanged at 111).

**Deployment:** no migration; the installer's `AdminPermissionSeeder` adds the two definitions; grant them in the
permission editor. Cutover: retire OLD `modules/Telawah/admin` mirror ops.

### 29.2 Public promotional-files display — WORKING (historically), NOT EXPLICITLY RETIRED → migration scope (not implemented)

- **What worked:** `khotab-advfile-{id}.htm` / `khotab-advfilefull-{id}.htm` → `ShowAdFile()` (`modules/advfiles/index.php:457-480`,
  `WHERE id`, Pass 3A: "public presentation — works"). Visitors reached it from OLD's Khotab item page button «الملف الدعائي»,
  shown when `advfile ≠ 0` (`templates/Khotab/detailes.htm:74-79`, `Khotab/index.php:1073`), and from three hard-coded homepage
  links. The library `khotab-advfiles.htm` → `MainPage` / `Author` / `Group` / `Series` browses on the `advfile` counters that
  Phase 5 maintains.
- **Broken parts:** the library item page's payload query (`Detailes`, `WHERE khid` — Pass 3A broken consumer); a `ser_id='ser_id'`
  literal in one `Search` branch; `MoreVedio` / `MoreAudio` undefined; the Flash player (obsolete).
- **Why it is unreachable today:** the module's first statement is an `eregi()` guard (`:14`, fatal on PHP 7/8 — a runtime defect
  under §26.5), and Gen 2 dropped the item button and routes these URLs to a dispatcher it does not have.
- **Retirement:** none recorded. §24.4/§24.10 only scoped public display out of Phase 5's *administration* work.
- **Use:** 1,129 published items, 1,133 payload rows, requests as late as 2023.
- **Open design point before building:** the payload is raw HTML echoed unescaped (Pass 3A A-5 / Q2). The evidence-backed default
  (Pass 3B §8 H-Q2): 99% anchor-wrapped images, 38% `[audio]`, 0 `<script>` / `<iframe>`, 12 `<embed>`/`<object>` — render through an
  allowlist (link + image + audio), never raw.

### 29.3 Public poll comment pages — WORKING (historically), NOT EXPLICITLY RETIRED → migration scope (not implemented)

- **What worked:** `modules/Surveys/comments.php` (PHP-Nuke comments: `DisplayTopic` with mode/order/threshold, `singlecomment`,
  `showreply`, `Reply` / `replyPreview` / `CreateTopic`, `moderate`), behind OLD's six `survey-comment*` / `survey-commreply` /
  `survey-showreply` / `survey-comments.htm` rewrites. Local data: **752 comments on 71 polls, 2005-07 → 2011-07**.
- **Why it is unreachable today:** 19 `ereg`/`eregi` calls (runtime defect under §26.5); Gen 2 keeps the six rewrites but has no
  dispatcher; Gen 2's homepage poll box still reads the comment count (`home_functions.php:420`).
- **Retirement:** none. The opposite: Phase 4's business decision answered "Poll comments and moderation?" with **Built**, under a
  question that said "If yes, comments stay part of the product". `routes/engagement.php` records the six routes as "OPEN / SOURCE
  UNRECOVERABLE" from Gen 2's source; OLD's source recovers them.
- **Before building:** a targeted read of `comments.php` for the exact contract (who may post, threading and display modes, the
  `moderate` scoring).

### 29.4 Status

| Item | Status |
|---|---|
| Telawah mirror administration (edit, delete) | **IMPLEMENTED / LOCAL VERIFIED** |
| Public promotional-files display | **WORKING, migration-owned — awaiting go-ahead to implement** |
| Public poll comment pages | **WORKING, migration-owned — awaiting go-ahead to implement** |
| **WHOLE MIGRATION** | **FINAL LEGACY-BEHAVIOR GAPS UNDER RECONCILIATION** — 2 known migration-owned public features not yet built |
| **PRODUCTION DEPLOYMENT** | **BLOCKED** |

No production queries. No deployment. No commit.

> **Superseded 2026-10-15 (§30):** both public features were then approved, implemented and locally verified, and the
> public Telawah mirror list was added. Final status: §30.5.

## 30. FINAL MIGRATION CLOSURE RECORD — final public features and the final gap reconciliation (2026-10-15)

> **Historical note (2026-11-06, decision-log #100, R4):** this record describes the 2026-10-15 closure. Later owner
> work — §31–§55 and decision-log #60–#100 — governs current status; see the banner at the top of this plan.
>
> **This section is the final implementation and closure record of the OLD administration migration.** Owner-accepted
> 2026-10-15. Status: **WHOLE MIGRATION — CLOSED / LOCALLY VERIFIED · KNOWN MIGRATION-OWNED FUNCTIONAL GAPS — 0 ·
> PRODUCTION DEPLOYMENT — NOT STARTED.** Decision: `decision-log.md` #59. Definition of Done status: §7.1.

Owner decisions: **MIGRATE/PRESERVE** public promotional files and public poll comments (§29.2, §29.3).

### 30.1 Public promotional files (OLD `modules/advfiles/index.php`)

| OLD | Laravel |
|---|---|
| `khotab-advfile-{id}.htm` → `ShowAdFile($id, 0)` (popup content) | same URL — bare page for the popup |
| `khotab-advfilefull-{id}.htm` → `ShowAdFile($id, 1)` | same URL — inside the site layout |
| `khotab-advfiles.htm` → `MainPage()` (preachers, by name, with counts) | same URL |
| `op=Author` / `op=Khotab`, `op=Group`, `op=Series` (no rewrites in OLD) | `/khotab-advfiles-author-{id}.htm`, `-group-{id}`, `-series-{id}` |
| item page button «الملف الدعائي» → popup (`detailes.htm:74-79`) | on the Khotab item page, for a published file |
| admin: credit + «تعديل الملف» / «مسح الملف» on the file pages; delete / edit / view beside each listed item | contextual controls → Phase 5 `admin.advfiles.edit` / `unpublish` (`khotab.addadv`); popup controls open the whole window; unpublish returns to the item page |

Listing semantics are OLD's: the Phase 5 `advfile` counters select and count; a series is listed only while its own
`count` > 0; items are ordered by `weight` DESC. **Correction:** hidden authors, groups, series and items are not listed,
because their pages 404.

**Not migrated:** `Detailes` (its payload query uses a nonexistent `khid`; RealPlayer), `KhotabSearch` (reachable only
from dead "Copy of" templates; results lead to `Detailes`), `MoreVedio` / `MoreAudio` / `AuthorVedio` (no handler),
`Repair` (counter repair, DEAD by §2 row 14), the Flash player.

**Safe rendering (`SafeLegacyHtmlRenderer`), the owner's policy:** the stored value is parsed and rebuilt from an
allowlist and never modified:

- http(s) links (`rel="nofollow noopener noreferrer"`, new tab);
- http(s) images with numeric sizes;
- inert formatting without attributes, except a validated `align` / `dir`;
- native `<audio>` / `<video>` for browser-playable files — from `audio_url`, the `[audio]` shortcode, or a URL
  recoverable from a legacy `<embed>` / `<object>` (e.g. a Flash player's `soundFile` MP3);
- otherwise «الملف غير متوفر حالياً»: RealMedia, `mms://`, Flash streams.

Removed: `script`, `iframe`, `style`, `form`, `svg`, `math`, `meta`, `base` (with content), every event handler and
`style` attribute, and `javascript:` / `vbscript:` / `data:` / `file:` / obfuscated schemes.

**Real-data audit (local, read-only):** all **1,133** stored files rendered — **0** unsafe outputs, **0** errors, 2,168
images, 3,645 links, 574 native audio players, 440 "unavailable" notices.

### 30.2 Public poll comments (OLD `modules/Surveys/comments.php`)

**Recovered working behavior:**

| Area | Behavior |
|---|---|
| Viewing | OLD's navigation bar — "poll title" \| N تعليق/تعليقات, «أرسل تعليق», the ownership notice, the guest notice when `anonpost` = 0; each top-level comment in full — subject (or [لا يوجد موضوع]), (التقييم: score), بواسطة name (or the `anonymous` name) في date, body cut at `commentlimit` with «أقرأ باقي التعليق...», [ رد على هذا \| الأصل ] |
| Threading and display | top level `pollID` + `pid`, `score >= thold`, `order` 0 stored / 1 newest / 2 score; children `pid = tid ORDER BY date, tid`; modes `thread` (default; replies as nested links to their sub-thread), `nested`, `flat`, `nocomments` |
| Posting | a member (the vBulletin session, as Phase 7), or anyone when `anonpost` = 1; a staff session alone is refused (OLD "Nice try"). The member's username with `score` 1; anonymous or «ارسال الموضوع باسم زائر» (only when `anonpost` = 1) with no name and `score` 0. Subject default "Re: …"; `pid` 0 or a comment of the same poll; `comments + 1`; the IP is stored, never shown |
| Preview and post | «استعراض» shows the preview and the form again; «موافق!» saves and lands on the poll's comment page at the new comment |
| Feature switch | `pollcomm` = 0 turns the pages off (404) |

**URL contracts:**

| OLD URL | Laravel |
|---|---|
| `survey-comment-{poll}-tid-{id}-{mode}-{order}-{thold}.htm` (single comment) | kept |
| `survey-comment-{poll}-pid-{id}-…` (OLD redirected to the results, where comments were shown) | redirect to the poll's comment page |
| `survey-commreply-{pid}-{poll}-…` (reply form) | kept |
| `survey-showreply-{tid}-{poll}-{pid}-…` (sub-thread) | kept |

Empty preference segments are accepted, as OLD's rules allowed. The poll's own page (OLD reached it through the results
page, whose include is commented out) is `/survey-comments-{poll}.htm`, linked from the results page as «تعليقات: N».

**Dead, not migrated:**

- `survey-comments.htm` and the poll-less `survey-showreply` shape (no poll id, rendered nothing);
- `DisplayBabies()` (never called);
- comment scoring (`moderate`): every `reason` is 0 and every score a creation default;
- the karma moderation queue (empty; it read `nuke_users.karma`);
- PHP-Nuke account, journal, PM and website links, and `update_points()`.

**Security corrections:**

- stored comments rendered through `SafeLegacyHtmlRenderer` (OLD echoed them raw);
- new comments stored as escaped text (OLD's form said HTML is not allowed);
- emails and IPs never shown (OLD printed emails publicly and IPs to admins);
- CSRF on the form;
- Arabic names and subjects shown as written (OLD's `eregi("[a-z0-9]")` turned them into «زائر» in replies).

**Contextual:** «مسح» beside each comment → Phase 4 `admin.polls.comments.destroy` (super-admin, as OLD's
`RemovePollComment`), returning to the comment page. All **752** stored comments render with 0 unsafe output.

### 30.3 Telawah public mirror list (found by the final reconciliation)

OLD's public Telawah item page listed the item's mirrors for visitors («قائمة الروابط البديلة للمادة»: description
linked to the mirror, a listen control for MP3s, type, size; `index.php:345-392`). Under the owner's rule it is
preserved: the Laravel Telawah page lists them (http(s) links only; a native player for playable audio; OLD's video
"play" pointed at a Khotab route and never worked). The §29.1 admin edit / delete now sit in that list, beside each
mirror (the admin-only list it replaced is removed).

### 30.4 Verification

| Check | Result |
|---|---|
| New tests | renderer **38**, promotional pages **10**, poll comments **15**; Telawah mirror tests updated (13) |
| Phase 4 / 5 regression | Phase 4 poll tests **75** passed; Phase 5 within the full suite |
| Contextual + Telawah | **110** passed |
| Full suite | **1897 passed**, 1 skipped (the existing skip), 12,446 assertions |
| PHPStan (`app` + `routes`) | **0 errors** |
| Pint (changed PHP files) | clean. Existing `ordered_imports` findings in `routes/content.php` and `Engagement/Models/Poll.php` are present at HEAD, unchanged |
| Real-data render audit | 1,133 files + 752 comments: 0 unsafe output, 0 errors |
| Local HTTP walkthrough (MySQL, real login / CSRF / sessions, disposable admins, rolled back) | final features **27/27**; contextual **57/57**; Telawah **14/14**. Data, audit log, admins and sessions restored |
| Visitor HTML, 18 baseline pages | content identical to the baseline |

**Known limitation:** there is no local vBulletin database, so member posting is verified by the test suite (vBulletin
guard), not by a real local member session. Guest refusal (403), CSRF (419) and the admin paths ran for real.

**Fixture changes, reported:** `MainSchema` gained the production columns of `nuke_pollcomments`, the PHP-Nuke comment
settings on `nuke_config`, and `nuke_telawah_mirror`; `TelawahControllersTest` now creates `nuke_telawah_mirror` (the
page reads it). No assertion of an earlier phase changed.

### 30.5 Final migration-gap reconciliation

Every family in §2, the contextual surface (§28) and the §29 corrections now has a terminal disposition:

- implemented and locally verified;
- externally owned (BC#6);
- retired by owner decision;
- dead / broken / disabled by OLD itself;
- data/media recovery (Mobile dead-host media, mirror orphans, Advfiles anomalies).

**No working, unretired, Laravel-owned legacy behavior remains missing.**

Remaining items are cutover and closure tasks, not functionality:

- the OLD / Gen 2 retirement list (closure review §5);
- DoD 5a (`JoinMe` / `BackUp` residual paths), 5b (OLD Cat writer), 6 (Gen 2 toolbars), 9 (`nuke_*_mod` archival),
  11 (record the closure in `PROJECT-HANDOFF.md` / `decision-log.md`);
- the pre-deployment prerequisites.

| Status | |
|---|---|
| **WHOLE MIGRATION** | **CLOSED / LOCALLY VERIFIED** |
| **KNOWN MIGRATION-OWNED FUNCTIONAL GAPS** | **0** |
| **PRODUCTION DEPLOYMENT** | **NOT STARTED** |

No production queries. No deployment. No commit.

---

## 31. KHOTAB CLOSED LOCALLY — page-by-page OLD public admin parity re-review (2026-10-21 → 2026-10-22)

Decision: `decision-log.md` #60.

| Surface | Status |
|---|---|
| Khotab Group — `khotab-group-{id}.htm` | **OWNER APPROVED** |
| Khotab Series — `khotab-series-{id}.htm` | **OWNER APPROVED** |
| Khotab Item — `khotab-item-{id}.htm` | **OWNER APPROVED** |
| Khotab Author — `khotab-video-{id}.htm` / `khotab-audio-{id}.htm` | **OWNER APPROVED** |
| Khotab Main — `khotab-video.htm` / `khotab-audio.htm` | **OWNER APPROVED** |
| Khotab public parity — final follow-up batch | **COMPLETE** |
| **Known local Khotab parity gaps** | **0** |
| **Production deployment** | **NOT STARTED** |

### 31.1 Why §30's closure was revisited for Khotab

§30.5 counted "externally owned (BC#6)" as a terminal disposition. On 2026-10-21 the owner replaced that with a stricter
rule: **a working OLD public admin control is preserved in Laravel**, even if similar functionality exists in another
AdminCP, unless the owner explicitly retired or excluded it, or source proves it dead / unreachable. Inferred
"externally owned" rows no longer justify an omission; a PHP 7/8 incompatibility is a runtime defect, not a retirement
(§26.5).

Khotab was therefore **not** closed from a broad audit. It was re-reviewed page by page and operation by operation:

- source of truth: the OLD working copy, whose `modules/Khotab/admin/index.php` and `modules/Khotab/index.php` match
  production by owner-verified SHA-256; any other differing OLD file that mattered was settled by an owner-run
  read-only production check (e.g. `db/mysql_modern.php`, `includes/logs.php`, `nuke_islamic_letters` absence);
- per page, two stages: an operation-level investigation report (every OLD control → op → handler → auth → writes →
  Laravel equivalent → status), then implementation only after owner approval;
- verification per page: focused tests, the full suite, PHPStan, Pint, an OLD-vs-Laravel browser comparison
  (admin and visitor) on representative real records, OLD-vs-Laravel database behavior runs on identical disposable
  fixtures, and **owner visual approval** before the next page began.

### 31.2 Reviewed surfaces

1. **Group** — «لوحة تحكم المجموعة»: re-arrange, fast-copy categories, edit, delete, add series, add item (with the
   archive.org step), the unclassified-items list, per-row detach; admins see hidden content.
2. **Series** — «لوحة تحكم السلسلة»: the same family for a series; admins see a hidden series and its hidden items.
3. **Item** — «لوحة تحكم المشرفين»: edit (super-admin-only link change after download), move (dependent selects),
   delete, banner upload / replace / delete / request, PDF and DOCX upload / replace / delete / download, «سحب الصور»
   (safe archive.org POST), the inline «إحصائيات متطورة» block, the moderator comment and per-comment edit / publish /
   hide; admins open hidden items and items of hidden groups / series. The earlier phases' categories, mirrors,
   mirror pick / move, request-promotional-file and link-health controls were regression-tested, not rebuilt.
4. **Author** — «لوحة تحكم الداعية» on the video and audio pages: edit (with image upload and `authors.txt` refresh),
   add group, add standalone series, add standalone item, delete (refused while any item exists), the author-scoped
   unclassified list, and the current-author counter repair; admins see hidden / empty groups and series and hidden
   items.
5. **Main** — the video and audio author directories: add author, the all-authors counter sweep, the site-wide
   pending-comments queue (per-row publish / hide permissions, working pagination, return from the per-comment edit
   page); admins see every author.
6. **Final public-parity follow-up batch** — hidden-item direct downloads blocked for visitors; the public item banner
   and its embed-code pages; `khotab-pdf-{id}.htm` compatibility and the item-PDF download counter; the OLD author-JPG
   sidebar thumbnail fallback (including the Author page's own sidebar).

### 31.3 Intentional differences from OLD (summary)

- Mutations are authorized, CSRF-protected POST / PUT / DELETE requests, transactional and audited; OLD's GET mutation
  links and GET confirmations became forms.
- Hidden visitor content follows Laravel's 404 policy where approved; admins keep the OLD contextual visibility they need,
  on private / no-store responses.
- Broken PHP 8 / runtime behavior is not reproduced where the intended capability was recoverable (e.g. «سحب الصور»,
  add author after OLD's `sql_nextid()` fatal, the banner embed-code pages).
- Counter maintenance is deterministic and server-side: the Author page repairs the invoked author (OLD repaired the next
  author and swept onward through meta refreshes); Main runs one set-wise sweep over every author with the same
  per-author semantics.
- OLD's broken pagination (a literal `{PAGE}`) is not reproduced.
- The dead / unreachable `KhotabListDown` is not implemented; `KhotabWaitingFB` stays retired; `KhotabCopyKhotab` and the
  adv-file edit / delete handlers stay dead.
- `khotab-pdf-{id}.htm` keeps the Gen 2 / Laravel author-transcripts meaning first, then 301-redirects unambiguous OLD
  item-PDF URLs to `/khotab-item-pdf-{id}.htm` (which counts the download as OLD did); 31 ambiguous ids resolve to the
  author.
- The public item banner and its HTML / VBulletin / phpBB embed codes are restored (never a broken image).
- The OLD author-JPG sidebar thumbnail fallback is restored; the author profile card is unchanged.
- Visitors can no longer download a hidden item directly.
- `nuke_islamic_letters` is not recomputed (absent in production); `authors.txt` is regenerated through the configurable
  `media.autocomplete_root`.

### 31.4 Open, non-blocking follow-ups (they do not keep Khotab open)

- **A. Deployment verification — production `authors.txt` mapping:** verify `config('media.autocomplete_root')` in
  production; PHP runtime write access; deployment must not overwrite the regenerated data with the stale tracked
  `public/w2a_autocomplete/authors.txt`, or must regenerate after deploying; after an author add / edit / delete, the
  header autocomplete reflects it.
- **B. Deployment verification — upload ownership / permissions:** PHP runtime umask; PHP write access; web-server read
  access; for `media_root/banner`, `media_root/pdf`, `media_root/docx` and `media_root/authors`. Laravel writes
  `0666 & ~umask` files and `0775 & ~umask` directories. **chmod 0777 is not recommended.**
- **C. Performance / schema follow-up:** possible indexes `nuke_islamic_khotab(ser_id)` and
  `nuke_islamic_khotab(group_id)`. No migration or `ALTER` is approved. Before any production schema change: MariaDB
  version, `SHOW INDEX`, online-DDL capability / behavior, and a deployment and rollback plan.

### 31.5 Regression rule

Khotab is not reopened or modified during later domain work unless a demonstrated regression requires it, or new
concrete OLD / production evidence proves a missed parity behavior. It is not refactored merely so adjacent Anasheed /
Telawah code can be made more generic.

### 31.6 Verification at closure

| Check | Result |
|---|---|
| Full suite | **2,023 tests — 2,022 passed, 1 existing skip** |
| PHPStan | **0 errors** |
| Pint (changed files) | clean, except `ordered_imports` findings already present at HEAD (`routes/admin.php`, `routes/content.php`) |
| Browser / behavior comparison | every surface compared with the local OLD working copy (admin and visitor); disposable fixtures, admins and counters cleaned up or restored |

No production queries run by this work (production evidence was supplied by the owner). No deployment. No commit.

## 32. ANASHEED GROUP CONTEXTUAL ADMIN CONTROLS — CLOSED LOCALLY, OWNER APPROVED (2026-10-23)

**Scope closed:** only the contextual admin controls of the Anasheed Group page (`var-group-{id}.htm`), OLD's
«لوحة تحكم قسم المنوعات» (`themes/way2allah/templates/Anasheed/group_cp.htm`, OLD `modules/Anasheed/index.php`
`Group()`), reviewed in Anasheed Investigation #1 (Group / Main boundary) and implemented in Implementation #1.
**This is not Anasheed parity completion.** *(Historical, as of that date: the Anasheed OLD public admin-controls migration was later closed — #67, 2026-10-29; Anasheed visitor parity is a separate, unstarted scope.)* Decision: `decision-log.md` #61.

| OLD control | Status |
|---|---|
| اضافة صورة للقسم (`AnasheedGroupIcon` / `UploadIcon`) | preserved — **OWNER APPROVED** |
| تعديل القسم (`AnasheedEditGroup` / `ConfirmEditGroup`) | preserved — **OWNER APPROVED** |
| إضافة مادة (`AnasheedInsertAnasheed` / `ConfirmInsertAnasheed`) | preserved — **OWNER APPROVED** |
| إضافة قسم فرعي (`AnasheedAddGroup` / `ConfirmAddGroup`) | preserved — **OWNER APPROVED** |
| حذف القسم (`AnasheedDeleteGroup` / `ConfirmDeleteGroup`) | preserved with the approved safety correction (also refuses while sub-groups exist) — **OWNER APPROVED** |
| ترتيب القسم (`Order_anasheed_in_group`) | **DEAD_OR_UNREACHABLE — production-schema confirmed** (no `nuke_anasheed_anasheed.banner`); never implement or repair |
| Panel appearance | **OWNER APPROVED** |

- Visitor behavior was intentionally outside scope and is unchanged (byte-identical visitor HTML before / after).
  Investigation #1's visitor findings (ordering, page size, 98/16, series block, `.grx`, cards, description, 404, Main,
  News) remain recorded and unacted on.
- OLD Main's `Group(0)` panel is out of scope; no Main was introduced.
- Khotab remains closed and untouched.
- **Regression rule:** this surface is not reopened unless a demonstrated regression or new concrete OLD / production
  evidence requires it.
- **Verification:** 25 new tests; full suite 2,071 — 2,070 passed, 1 existing skip; PHPStan 0 errors; changed files
  Pint-clean (pre-existing `routes/admin.php` import-order finding).
- **Next:** Anasheed Stage A, Investigation #2 — item page (`var-item-{id}.htm`) public admin controls.
- **Production-evidence correction (2026-10-23):** live OLD (`old.way2allah.com`, PHP 8.1) runs the old-project
  **working copy** (SHA-256-verified), not HEAD, and production has `nuke_anasheed_anasheed.is_published`
  (`NOT NULL DEFAULT 0`). The earlier "no `is_published`" basis was wrong. «إضافة مادة» was reopened only for that
  defect and now preserves OLD's «نشر علي المنصة» checkbox (default checked → 1; unchecked → 0). Status unchanged
  after verification: **CLOSED LOCALLY — OWNER APPROVED**. Details: `decision-log.md` #61.
- No deployment. No commit.

## 33. ANASHEED ITEM CONTEXTUAL ADMIN CONTROLS — MANUAL REVIEW COMPLETE, OWNER APPROVED (2026-10-28)

**Scope:** the contextual admin controls of `var-item-{id}.htm` (OLD `Detailes()`: «لوحة تحكم المادة», `Mirror()`,
`Tree()`, `Comments()`), from Anasheed Investigation #2; source = the production-matching old-project working copy
(PHP 8.1). Decision and details: `decision-log.md` #62.

| OLD control | Status |
|---|---|
| T1 اضافة صورة للمادة (item frame) | implemented (`anasheed.addbanner`) — **OWNER APPROVED** |
| T4 تعديل المادة | implemented (`anasheed.editanasheed`), with the owner-approved size and parent_id corrections — **OWNER APPROVED** |
| T6 حذف المادة | implemented (`anasheed.delanasheed`) as the owner-approved safe delete — **OWNER APPROVED** |
| K1 إضافة تعليق إداري | implemented (`anasheed.addcomment`), with the owner-approved comments recount — **OWNER APPROVED** |
| K2 (تعديل التعليق) — publish / hide | implemented: page + publish `anasheed.addcomment`, hide `anasheed.delcomment` (OLD's GreenZone split; corrected 2026-10-27) — **OWNER APPROVED** |
| T5 إضافة وصلة, M1 / M2 edit / delete mirror | ALREADY_PRESERVED (Phase 1), untouched |
| T7 إضافة تصنيف, C1 remove category | ALREADY_PRESERVED (Phase 3), untouched |
| T2 / T3 تعديل / حذف صورة للمادة | DEAD_OR_UNREACHABLE (never rendered; absent `banner` columns) |
| T9 / T10 / T11 banner | DEAD_OR_UNREACHABLE (`die('قيد التطوير')`) |
| T8 share queue, link-status block | OWNER_RETIRED (2026-10-15) |

- Visitor behavior unchanged (byte-identical visitor HTML).
- Verification: 22 new tests; full suite 2,096 — 2,095 passed, 1 existing skip; PHPStan 0 errors.
- K1 / K2 manually verified on item 6233 (DB + audit evidence, 2026-10-27). K2 permission parity gap confirmed and
  fixed locally (was all `anasheed.delcomment`); full suite 2,108 — 2,107 passed, 1 skip; PHPStan 0. Details:
  `decision-log.md` #62.
- **Status: MANUAL REVIEW COMPLETE — OWNER APPROVED (2026-10-28)** — T1, T4, T6, K1 and K2 (edit / publish / «مسح»
  = hide via `view = 2`) each owner approved. Final K2 permissions: `anasheed.addcomment` adds an admin comment and
  edits / publishes comments; `anasheed.delcomment` «مسح» / hides (labels: `decision-log.md` #62). *(Implemented
  2026-10-23; previously "IMPLEMENTED LOCALLY — AWAITING OWNER VISUAL / FUNCTIONAL REVIEW".)* Regression rule: not
  reopened without new concrete OLD / production evidence or a demonstrated regression. Not Anasheed parity completion. *(Historical, as of that date: the Anasheed OLD public admin-controls migration was later closed — decision-log #67 / §38, 2026-10-29; Anasheed visitor parity is a separate, unstarted scope.)*
- No deployment. No commit.

## 34. ANASHEED MAIN CONTEXTUAL ADMIN CONTROLS — CLOSED, 0 PRESERVE CANDIDATES (2026-10-28)

**Scope:** OLD Anasheed Main (`Group(0)`, the dispatcher default; `modules.php?name=Anasheed`, `var-group-0.htm`) —
its «لوحة تحكم قسم المنوعات» panel, where every link carries an empty id. Decision and evidence: `decision-log.md` #63.

| OLD control (on Main) | Status |
|---|---|
| اضافة صورة للقسم | DEAD_OR_UNREACHABLE («لم يتم العثور…» for id 0) |
| تعديل القسم | DEAD_OR_UNREACHABLE (same) |
| إضافة مادة | DEAD_OR_UNREACHABLE (`ConfirmInsertAnasheed` dies «القسم غير موجود») |
| إضافة قسم فرعي | DEAD_OR_UNREACHABLE — **production-confirmed**: session `sql_mode` is `STRICT_TRANS_TABLES,…`, so `INSERT … parent_id = ''` fails (silently) and no root group is created |
| حذف القسم | DEAD_OR_UNREACHABLE («لم يتم العثور…») |
| ترتيب القسم | DEAD_OR_UNREACHABLE (production schema, #61) |

- Production evidence (owner-run, read-only, 2026-10-28; `old.way2allah.com`, `/home/php8way2allah/public_html`,
  EA-PHP 8.1): global and session `sql_mode` both
  `STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION`.
- **Status: CLOSED — 0 preserve candidates; no Laravel implementation required.** Laravel's lack of a Main page is a
  visitor-flow difference, not migration work.
- **Anasheed News:** no OLD public admin controls → out of current work.
- **Next:** investigate `anasheed_without_cats.htm` only (authors / channels pages: not yet).
- No deployment. No commit.

## 35. ANASHEED `anasheed_without_cats.htm` — MANUAL REVIEW COMPLETE — OWNER APPROVED (2026-10-29)

**Scope:** OLD's admin-only list of Anasheed items without a category (`anasheed_without_cats()`,
`modules/Anasheed/index.php:259-301`) and its `-page-N.htm` pagination. Decision, trace and production evidence:
`decision-log.md` #64.

| OLD control | Status |
|---|---|
| The page `anasheed_without_cats.htm` + `anasheed_without_cats-page-{n}.htm` | implemented (`anasheed.addcat`; OLD selection, `id DESC`, 50 / page) |
| «إضافة تصنيف» per row (`cat_add_to_anasheed`) | ALREADY_PRESERVED behavior (T7); entry point implemented → T7 picker, successful add returns to `/var-item-{id}.htm` |
| `redirct_to` on that link | DEAD_OR_UNREACHABLE (never read by the handler) |

- Production: 10 of 11 files hash-identical; `mainfile.php`'s globals and `is_w2a_admin()` sections confirmed
  (super-admin-only gate on production).
- Verification: 7 new tests; full suite 2,115 — 2,114 passed, 1 skip; PHPStan 0.
- Owner manual review (2026-10-29, super-admin): the list and `-page-2.htm` rendered correctly in the site layout
  (page 2 starts at row 51); on item #17258 «الحلقة 20 (نموش)», «إضافة تصنيف» opened the T7 picker (no existing
  categories), the add succeeded with no 500, the return landed on `/var-item-17258.htm` showing the category, and the
  item left the list. Full record: `decision-log.md` #64.
- **Status: MANUAL REVIEW COMPLETE — OWNER APPROVED (2026-10-29)** (previously "IMPLEMENTED LOCALLY — AWAITING OWNER
  MANUAL REVIEW"). Authors / channels pages: not started at the time (later done — §36, §37).
- No deployment. No commit.

## 36. ANASHEED `anasheed_without_authors.htm` — MANUAL REVIEW COMPLETE — OWNER APPROVED (2026-10-29)

**Scope:** OLD's admin-only list of Anasheed items with `author_id IS NULL` (`anasheed_without_authors()`,
`modules/Anasheed/index.php:303-345`) and its `-page-N.htm` pagination. Decision, trace and production evidence:
`decision-log.md` #65.

| OLD control | Status |
|---|---|
| The page `anasheed_without_authors.htm` + `anasheed_without_authors-page-{n}.htm` | implemented (`anasheed.editanasheed`; `author_id IS NULL` only, `id DESC`, 50 / page) |
| «تعديل» per row (`AnasheedEditAnasheed`) | ALREADY_PRESERVED behavior (T4); entry point implemented → T4 form |
| `redirct_to` (return to the same list page after a successful edit) | live in OLD → preserved, opt-in (`return_to`, validated by `ContextualReturn::safeTarget()`); page 1 = bare URL |

- Production: `admin/case.php`, `lang-arabic.php`, `includes/sql_layer.php` hash-identical; `mainfile.php:231-232`
  (`sql_layer` + `$dbi = sql_connect(…)`) and `$dbtype = "MySQL"` confirmed; the rest already verified (#61, #64).
- T4 without `return_to` is unchanged (redirect to `/var-item-{id}.htm`); `ContextualReturn` / `ROUTES` untouched.
- Verification: 11 new tests; Anasheed + contextual suites 199/199; full suite 2,126 — 2,125 passed, 1 existing skip; PHPStan 0.
- Owner manual review (2026-10-29, super-admin, all PASS): list and `-page-2.htm` rendered correctly (page 2 starts
  at row 51); from page 2, #11751 saved with author «غير مصنف» returned to `-page-2.htm` and stayed listed (row 51);
  #11708 changed to «بدون شيخ» returned to the list and left it; normal T4 on #9876 (no `return_to`) returned to
  `/var-item-9876.htm`. Full record: `decision-log.md` #65.
- **Status: MANUAL REVIEW COMPLETE — OWNER APPROVED (2026-10-29)** (previously "IMPLEMENTED LOCALLY — AWAITING OWNER
  MANUAL REVIEW"). Channels page: not started at the time (later done — §37).
- No deployment. No commit.

## 37. ANASHEED `anasheed_without_channels.htm` — MANUAL REVIEW COMPLETE — OWNER APPROVED (2026-10-29)

**Scope:** OLD's admin-only list of Anasheed items without a specific channel (`anasheed_without_channels()`,
`modules/Anasheed/index.php:345-387`) and its `-page-N.htm` pagination. Decision, trace and production evidence:
`decision-log.md` #66.

| OLD control / behavior | Status |
|---|---|
| The page `anasheed_without_channels.htm` + `anasheed_without_channels-page-{n}.htm` | implemented (`anasheed.editanasheed`; `channel_id IS NULL OR = 0`, `id DESC`, 50 / page) |
| Listed set vs page count (OLD: NULL + 0 rows, NULL-only count) | owner decision: the NULL + 0 rows OLD production showed, paged over that same set |
| «تعديل» per row (`AnasheedEditAnasheed`) | ALREADY_PRESERVED behavior (T4); entry point implemented → T4 form |
| `redirct_to` (return to the same list page) | preserved through the #65 opt-in return; page 1 = bare URL; no T4 change |

- Production: `channel_id` `int(11)` NULL default NULL; NULL 8,532, zero 2,025, `= ''` 2,025, positive 6,082, total
  16,639 → always the paginated branch, mismatch confirmed.
- Verification: 10 new tests; Anasheed + contextual suites 209/209; full suite 2,136 — 2,135 passed, 1 existing skip; PHPStan 0.
- Owner manual review (2026-10-29, super-admin, all PASS): list and `-page-2.htm` rendered correctly (page 2 starts
  at row 51); from page 2 — an unchanged save returned to `-page-2.htm` with the item listed, «بدون قناة» kept the item
  listed, a real channel removed it; normal T4 from the item page returned to `/var-item-{id}.htm`. Full record:
  `decision-log.md` #66.
- **Status: MANUAL REVIEW COMPLETE — OWNER APPROVED (2026-10-29)** (previously "IMPLEMENTED LOCALLY — AWAITING OWNER
  MANUAL REVIEW").
- No deployment. No commit.

## 38. ANASHEED OLD PUBLIC ADMIN-CONTROLS MIGRATION — COMPLETE — OWNER CLOSED (2026-10-29)

**Final sweep** of the whole OLD Anasheed module (rewrites, both dispatchers, every reachable module file, all templates,
semantic admin-gate / admin-link searches). Full record: `decision-log.md` #67.

| Surface | Final status |
|---|---|
| Group (§32, #61) | CLOSED LOCALLY — OWNER APPROVED |
| Item (§33, #62) | MANUAL REVIEW COMPLETE — OWNER APPROVED (2026-10-28) |
| Main (§34, #63) | CLOSED — 0 preserve candidates |
| `anasheed_without_cats.htm` (§35, #64) | MANUAL REVIEW COMPLETE — OWNER APPROVED (2026-10-29) |
| `anasheed_without_authors.htm` (§36, #65) | MANUAL REVIEW COMPLETE — OWNER APPROVED (2026-10-29) |
| `anasheed_without_channels.htm` (§37, #66) | MANUAL REVIEW COMPLETE — OWNER APPROVED (2026-10-29) |
| `ArrangeAnasheed()` arrows, `Channel` op | DEAD_OR_UNREACHABLE (no caller / commented out) |
| `modules/Anasheed/_index.php` (direct `file=_index` only) | DEAD_OR_UNREACHABLE — PHP 8 fatal before its admin blocks (`:86`, `:234`); production SHA-256 `3ec53510…0267f` = local |

- Initial sweep: NEW_UNREVIEWED_CANDIDATE 0, PRODUCTION_EVIDENCE_REQUIRED 1 (`_index.php`); after the owner's
  read-only hash check: **NEW_UNREVIEWED_CANDIDATE 0, PRODUCTION_EVIDENCE_REQUIRED 0.**
- **Status: ANASHEED OLD PUBLIC ADMIN-CONTROLS MIGRATION — COMPLETE — OWNER CLOSED (2026-10-29).** Anasheed visitor
  parity: separate scope, not started. Telawah / Fatwa: not started.
- No deployment. No commit.

> **Historical note (2026-10-29):** "Telawah: not started" above was true at Anasheed closure. Telawah's OLD public
> admin-controls migration is now COMPLETE — OWNER CLOSED (§39).

## 39. TELAWAH OLD PUBLIC ADMIN-CONTROLS MIGRATION — COMPLETE — OWNER CLOSED (2026-10-29)

Investigation (23 OLD controls classified) → production evidence (owner, read-only: Telawah files = the working copy,
`admin/index.php` `d93db419…774aaff4`; `user_table.htm` absent; 0 items with comments) → eight preserve candidates
implemented with the four owner-approved safety decisions. Full record: `decision-log.md` #68.

| Surface | Control | Status |
|---|---|---|
| Main `recite.htm` («لوحة التحكم») | M1 «إضافة قارىء» | OWNER APPROVED |
| Group `recite-group-{id}.htm` («لوحة تحكم الاقسام الفرعية») | G1 «تعديل القسم» (parent kept), G3 «إضافة تلاوة», G4 «إضافة قسم فرعى», G5 «حذف القسم» (safe) | OWNER APPROVED |
| Item `recite-item-{id}.htm` («لوحة تحكم المشرفين») | I1 «تعديل», I2 «مسح» (safe), I3 «نقل إلى قسم شيخ أخر» | OWNER APPROVED |
| Item | «حالة الوصلة» (§28.3 T1), mirror edit / delete (§29.1) | ALREADY_PRESERVED_AND_CLOSED — unchanged; link-quality manual regression PASS |
| — | M2, G2, G6–G8, I4, I7, C1 (production evidence), C2, C3, O1 | DEAD_OR_UNREACHABLE — not implemented |
| Item | I5 share queue | OWNER_RETIRED (§28.1) |

- Permissions: OLD's `nuke_telawah_mod` tokens `telawah.addgroup` (M1, G4), `editgroup`, `delgroup`, `addtelawah`,
  `edittelawah` (I1, I3), `deltelawah` — definitions only.
- Owner manual review (local, 2026-10-29) PASSED surface by surface — Main, group (incl. all three G5 cases: sub-group →
  refused, recitation → refused, empty → deleted), item (I1 with and without a group change, I3 move and same-group
  re-submit, I2) and the «حالة الوصلة» link-quality regression. Evidence: `decision-log.md` #68.
- C1 «مسح التعليق»: final DEAD_OR_UNREACHABLE. Closure verification: focused 28 / 28, PHPStan 0 errors, tree unchanged.
- **Status: TELAWAH OLD PUBLIC ADMIN-CONTROLS MIGRATION — COMPLETE — OWNER CLOSED (2026-10-29).** Known local Telawah
  admin-control parity gaps: 0. Local parity closure only — production deployment, production permission grants and
  seeder runs, and cutover are NOT STARTED.
- No deployment. No commit. Fatwa: not started.

## 40. FATWA OLD PUBLIC ADMIN-CONTROLS MIGRATION — COMPLETE — OWNER CLOSED (2026-11-01)

Investigation (Phase 1) → production evidence (owner, read-only: `modules/Fatwa/admin/index.php` `8e54214c…a0a5d320cd68147b20f9fa6`
= the working copy; no prefixless `_fatwa%` tables; `place_of_fatwa` non-empty 291; multi-question fatwas 0; multi-topic
questions 3) → targeted `topic_id` investigation → fourteen preserve candidates implemented with the owner-approved
corrections → owner manual review → closure. Full record: `decision-log.md` #69.

| Surface | Control | Status |
|---|---|---|
| Category `fatawa-topics-{cat}` / `fatawa-category-{cat}` | C1 «اضافة موضوع للتصنيف» | OWNER APPROVED |
| Topic `fatawa-group-{t}-{c}` | T1 «اضافة سؤال للموضوع», T2 «تعديل الموضوع» | OWNER APPROVED |
| Question `fatawa-all-{q}` / `auther-all-fatawa-{a}-{q}` | Q1 «اضافة فتوى للسؤال», Q2 «تعديل السؤال», Q4 «اضافة لموضوع أخر», Q5 «نقل لموضوع أخر» / Q6 «حذف من موضوع» | OWNER APPROVED |
| Each fatwa on those pages | A1 «اضافة الى اسئلة اخرى», A2 «نقل الفتوى من سؤال لأخر» / A3 «حذف الفتوى من على سؤال», A4 «تعديل», A5 «حذف» | OWNER APPROVED |
| Q1 / A4 forms | X2 size lookup | OWNER APPROVED |
| — | C2, T3, Q3, D1–D7, X1 | DEAD_OR_UNREACHABLE — not implemented |

- Permission: one key, `fatwa.manage` (owner decision 1) — definition only, granted to nobody; super-admin passes.
- Owner decisions 2 and 3 implemented: the sheikh's `fatwa` counter recounted from actual rows; the canonical
  `topic_id` (first topic of the first question) maintained by every relationship operation; no historical sweep.
- **Blocker fixed (owner-approved):** R1 / R2 / R3 — the topic question list (rows and count), the question pages'
  fatwas (also author-scoped) and the per-question count now use OLD's delimiter-aware pipe membership
  (`FatwaPipeIds::whereContains`), so Q4 / Q6 / A1 / A3 states are listed under every member; and every
  `general_question_id` reader / contextless link that concatenated `|1|3|` into 13 now reads each id (author and channel
  lists, author counts) or the first stored id (sidebar, author, day, channel, search links).
- Separate follow-up (NOT fixed): `(int) str_replace('|', '', …)` on a multi-topic question's topic list loses its
  page's breadcrumb / sidebars.
- Verification: focused 51 / 51; related regression 562 / 562; full suite 2,215 (2,214 passed, 1 skipped, 0 failed);
  PHPStan level 5: 0 errors; Pint clean on new / Fatwa files.
- Implementation status (historical, superseded below): FATWA ADMIN CONTROLS — IMPLEMENTED LOCALLY — AWAITING OWNER
  MANUAL REVIEW (2026-10-30).
- Owner manual review (local, 2026-10-31 – 2026-11-01) PASSED for all fourteen — C1, T1, T2, Q1, Q2, Q4, Q5, Q6, A1–A5
  (multi-topic / multi-question states, true moves, minimum relationships and rule-B re-filing verified on test fatwa
  20900) and X2 (real MP3 URL → blur → «حجم المادة» filled `12.19` MB, not saved). Evidence: `decision-log.md` #69.
- Dead / unreachable unchanged: C2, T3, Q3, D1–D7, X1. Closure verification: no source file changed after the green
  run; tests not re-run.
- **Open follow-ups (NOT closed here):** (1) **FATWA LEGACY-ROUTE PAGINATION FIX — immediate next engineering task**:
  `fatawa-topics-{category}-{page}.htm` and `fatawa-group-{topic}-{category}[-{page}].htm` read the page from the route
  segment but their pagers emit `?page=N`, which is ignored; OLD builds segment URLs; fix = segment paginator URLs,
  queries / routes / data unchanged, regression tests (no `?page=N`; distinct page sets). (2) Multi-topic `topic_id`
  cast bug (`|258|91|` → `25891`) — separate, still open.
- **Status: FATWA OLD PUBLIC ADMIN-CONTROLS MIGRATION — COMPLETE — OWNER CLOSED (2026-11-01).** All fourteen preserved
  controls implemented and owner approved. Known local Fatwa OLD-public-admin-control gaps: 0. Not Fatwa visitor parity;
  the two follow-ups stay open. Local parity closure only — production deployment, permission grants, seeder runs and
  cutover are NOT STARTED.
- No deployment. No commit.

## 41. FATWA LEGACY-ROUTE PAGINATION FIX — COMPLETE — OWNER CLOSED (2026-11-01)

§40's immediate follow-up. `fatawa-topics-{c}-{p}.htm` and `fatawa-group-{t}-{c}[-{p}].htm` read the page from the route
segment, but their pagers linked to `?page=N`, which was ignored. `FatwaTopicController::withLegacyUrls()` (the
`AnasheedWithoutCatsController` pattern) now makes the pager URLs `/fatawa-topics-{c}-{n}.htm` and
`/fatawa-group-{t}-{c}-{n}.htm`, as OLD `pagination()`; routes, queries, page size, order and filters unchanged. Tests:
`FatwaLegacyPaginationTest` 9 / 9; Fatwa focused 219 / 219; related regression 471 / 471; PHPStan 0 errors. Full record:
`decision-log.md` #70.

- Implementation status (historical, superseded below): IMPLEMENTED LOCALLY — AWAITING OWNER MANUAL REVIEW.
- Owner browser verification (2026-11-01): PASS — `/fatawa-topics-21-1.htm` → pager «3» → the route-segment page,
  correct results, no `?page=3`.
- **Status: FATWA LEGACY-ROUTE PAGINATION FIX — COMPLETE — OWNER CLOSED (2026-11-01).** Known pagination gap on the two affected surfaces: 0.
  **Still OPEN, separate:** the multi-topic `topic_id` cast bug (`|258|91|` → `25891`; `fatwaTopicsKeyedById()`, the
  `fatwaGeneralQuestionsByAuthor()` topic lookup, …). No deployment / cutover. No commit.

## 42. FATWA MULTI-TOPIC GENERAL-QUESTION topic_id FIX — COMPLETE — OWNER CLOSED (2026-11-01)

The separate follow-up from §40 / §41. Four readers collapsed a general question's pipe topic list into one integer
(`|258|91|` → 25891): `renderAllAnswers()` (`fatawa-all-{q}`, `auther-all-fatawa-{a}-{q}`), `fatwaQuestionsForChannel()` /
`fatwaTopicsKeyedById()` (`fatawa-channel-*`) and `fatwaGeneralQuestionsByAuthor()` (`auther-questions-*`). They now take
the question's FIRST topic (`FatwaPipeIds::firstId()`), as OLD did on those surfaces. Membership readers and admin readers
were already pipe-aware; the advanced-search scalar count reproduces OLD `modules/search` and is unchanged. Tests:
`FatwaGeneralQuestionTopicTest` 9 / 9; Fatwa Content 177 / 177; admin controls 51 / 51; pagination 9 / 9; related
regression 480 / 480; PHPStan 0 errors. Full record: `decision-log.md` #71.

- Implementation status (historical, superseded below): IMPLEMENTED LOCALLY — AWAITING OWNER MANUAL REVIEW.
- Owner browser verification (2026-11-01): PASS — `/fatawa-all-6244.htm` (`|258|91|`) showed «فقه الجنايات والحدود» →
  «موضوع شبهات» → the question; no topic 25891.
- **Status: FATWA MULTI-TOPIC GENERAL-QUESTION topic_id FIX — COMPLETE — OWNER CLOSED (2026-11-01).** Known local gaps for this cast defect: 0. No schema / data rewrite.
  Fatwa admin controls (§40) and pagination (§41) stay OWNER CLOSED, not reopened. Separate, NOT STARTED visitor-parity
  observations (#71): OLD's one trail per topic on multi-topic `fatawa-all`; OLD's last-topic sidebar / admin category;
  the channel list's `fatawa-group-0-0.htm` fallback for a topicless question. This is not Fatwa visitor-parity
  completion and not production readiness. No deployment / cutover. No commit.

## 43. REMAINING OLD PUBLIC ADMIN-CONTROLS DISCOVERY — COMPLETE — AWAITING OWNER REVIEW OF INVENTORY (2026-11-01)

Discovery only — nothing implemented. Replaces the open "Khotab → Anasheed → Telawah → Fatwa → …" with an evidence-based
inventory of every remaining OLD module (`old-project/modules/`, the production working copy). Full record:
`decision-log.md` #72.

**Method.** (1) Admin gating in public PHP (not `*/admin/`): `is_w2a_admin(`, `is_admin(`, `if ($admin`, `GreenZone(`,
`AvAdmin(`, `radminsuper`, `$_SESSION[admin`; (2) emitted admin targets: `$admin_file.php?op=`, `admin.php?op=`,
string-built `op=` links, hidden `op` form fields; (3) templates traced through each module's own `IncMe("…")` calls
(`way2allah_functions.php:134` resolves `themes/$ThemeSel/templates/<module>/`) — admin-side templates rendered only by
`admin.php` handlers excluded; (4) reachability: `.htaccess` rules, inbound links, call sites, `nuke_modules.active` /
`nuke_blocks` (local copy, read-only), and the owner rule that a PHP 7/8 fatal is a runtime defect, not a retirement
(§26.5, §31.1) — an `eregi()` / `mktime()` fatal alone never makes a routed page dead. Sanity check: the method finds the
known panels of all four closed modules (Khotab `index.php` 50 hits + `group_cp` / `series_cp` / `author_cp` /
`main_admin_menu`; Anasheed 24 + `group_cp` / `item_cp`; Telawah 24 + `author_cp` / `Comments.php`; Fatwa 29 + `details.htm`
/ `list_fatwa_*`).

| Module | OLD public surface | Controls found | Class | Previous disposition | Laravel | Complexity | Order |
|---|---|---|---|---|---|---|---|
| `w2acd` | `cds-main.htm` (= `Group(0)`), `cds-group-{id}.htm`, `cds-item-{id}.htm` | group panel «لوحة تحكم قسم الاسطوانات»: صورة / تعديل / إضافة إسطوانة / قسم فرعي / حذف القسم; item: تعديل / نقل / حذف الإسطوانة, اضافة الى قائمة الشير, رفع / تعديل / حذف البانر | **REVIEW_REQUIRED** | §25.3 "never used / output not consumed / not CD administration"; §28.1 CD create on group pages owner-excluded | partial — item edit / delete only (§28 W1–W2) | MEDIUM | 1 |
| `search` | `search.htm` | series row «out» icon → `KhotabUpSeries` (series in a group) | **REVIEW_REQUIRED** → **DEAD_OR_UNREACHABLE (superseded 2026-11-03, §50 / §51: `group_id` never selected; the icon cannot render)** | Pass 3C "0 ops (visibility only)" — wrong | missing (operation exists: `admin.khotab…series.detach`) | SMALL | 2 |
| `advanced_search` | `advanced_search.htm` | the same `KhotabUpSeries` icon | **REVIEW_REQUIRED** → **DEAD_OR_UNREACHABLE (superseded 2026-11-03, §50 / §51: `$group_id` undefined in `ListSeries()`; the icon cannot render)** | Pass 3C "0 ops (visibility only)" — wrong | missing | SMALL | 2 (with `search`) |
| `Surveys` | `surveys.htm`, `survey-results-*`, `survey-comment-*` | Edit poll (list ×2), Add / Edit (results), «حذف» comment | ALREADY_PROVEN_PRESERVED | §28 P1–P3, §30.2 | present | — | — |
| `Mobile` | category, new-by-date, top rated, most popular, search, comments | edit material (`DownloadsModDownload`) | ALREADY_PROVEN_PRESERVED | §26 owner-restored; §28 M1 | present on all six (the 7th, `viewsdownload`, dead) | — | — |
| `advfiles` | `khotab-advfile(full)-{id}.htm`, `khotab-advfiles.htm`, author / group / series lists | credit + «تعديل الملف» / «مسح الملف»; edit / delete beside listed items | ALREADY_PROVEN_PRESERVED | §24, §30.1 | present (`Detailes` dead — nonexistent `khid`) | — | — |
| `Locations` | `location-*.htm` | only in `Locations::Detailes()` / `Mirror()` / `ListCats()` | DEAD_OR_UNREACHABLE | §2 row 22 "eregi" (reasoning superseded) | n/a — `location-{l}-item-{k}.htm` is served by the Khotab module and Laravel delegates it to the Khotab item page with its controls | — | — |
| `BannersRequest` | none (direct `modules.php?name=BannersRequest`) | «إلغاء الطلب» (`DelRequest`, AJAX) | DEAD_OR_UNREACHABLE | §2 row 12 (BC#6) | n/a (Khotab «طلب بانر» preserved) | — | — |
| `modules/index.php` | none (`new_modules.php` without `name`) | an older Khotab copy | DEAD_OR_UNREACHABLE | Pass 3C "dispatcher" | n/a | — | — |
| `w2a_cd` | none (direct URL only) | Anasheed-copy panels (`group_cp*`, `item_cp*`) | INSUFFICIENT_EVIDENCE → **DEAD_OR_UNREACHABLE (superseded 2026-11-03, §52 / §53: production SHA match; `is_admin()` undefined on production; OWNER CLOSED)** | §25.3 "NOT W2ACD"; #67 "outside this sweep" | none | — | — |
| `Cat`, `Cat - Copy` | `categories.htm`, `category-group/series-*` | none — admins see hidden groups / series / items | NO_OLD_PUBLIC_ADMIN_CONTROLS | Pass 3C 12 ops (admin-side templates) | — | — | — |
| `Satellite` | `channels.htm`, `channel-*` | none — admins see hidden items; `$show_admin_panel` is never read | NO_OLD_PUBLIC_ADMIN_CONTROLS | Pass 3C 1 op (admin-side template) | — | — | — |
| `home_files` | `home-file-{id}.htm` | none — admin branch unreachable (`WHERE hf_show='yes'`) | NO_OLD_PUBLIC_ADMIN_CONTROLS | Pass 3C 1 op | — | — | — |
| `new_album` | `gallery*.htm` | none — guard commented out; `album_item.htm` admin-side only | NO_OLD_PUBLIC_ADMIN_CONTROLS | Pass 3C 1 op | — | — | — |
| `Your_Account` | `login.htm` | none contextual — admin greeting + links to the AdminCP / `admin.php` home | NO_OLD_PUBLIC_ADMIN_CONTROLS | Pass 3C 0 ops | — | — | — |
| 42 other modules¹ | — | none — no admin gating; template `op=` links are admin-side or visitor-only | NO_OLD_PUBLIC_ADMIN_CONTROLS | — | — | — | — |

¹ `123flashchat`, `ads`, `AlFatawa`, `alQuran`, `BackUp`, `banners_ads`, `cd`, `Chat`, `chat_server`, `Classificate_anasheed`,
`contact`, `Copy of gal`, `default_picasa_album`, `docx`, `Dump_files`, `FAQ`, `fb_share_list`, `Feedback`, `flashxml`,
`gal`, `gal2`, `heartcharge`, `hedaya_table`, `help`, `high_rank`, `live`, `Montada`, `noor`, `questionnaire`, `Quiz`,
`Radio`, `ramadan`, `ramadan_competition`, `report_ads`, `RSS`, `SelfTest`, `share`, `stats`, `Tadabor`, `tadbor`,
`Tutorials`, `update_khotab_count`.

**Counts (58 scanned = 57 module directories after the 4 closed modules + `modules/index.php`):** REVIEW_REQUIRED 3;
ALREADY_PROVEN_PRESERVED 3; DEAD_OR_UNREACHABLE 3; OWNER_RETIRED 0; NO_OLD_PUBLIC_ADMIN_CONTROLS 48; INSUFFICIENT_EVIDENCE 1.

**Recommended order:** (1) W2ACD — `cds-main` / `cds-group` panel, then `cds-item`; (2) `search` + `advanced_search`
(one review unit). Next: **W2ACD**.

- **Status: REMAINING OLD PUBLIC ADMIN-CONTROLS DISCOVERY — COMPLETE — AWAITING OWNER REVIEW OF INVENTORY.** No module reopened, nothing implemented, no closure history rewritten. No deployment.
  No commit.

## 44. W2ACD OLD PUBLIC ADMIN CONTROLS — INVESTIGATION COMPLETE — AWAITING OWNER DECISIONS (2026-11-01)

Investigation only (§43's next module). Source: OLD `modules/w2acd/index.php`, `modules/w2acd/admin/index.php`,
`themes/way2allah/templates/w2acd/*`, `.htaccess:516-561`. OLD gate: the public panels render for `is_w2a_admin()`; the
handlers need the `admin.php` session plus `radminsuper`, the `w2acd` module `admins` list or `check_if_user_admin_cd()`
(`GreenZone()` returns immediately). Laravel: `w2acd.managecds`. Full record: `decision-log.md` #73.

| ID | OLD page | Control | OLD handler | Classification |
|---|---|---|---|---|
| M1 | `cds-main` (`Group(0)`) | اضافة صورة للقسم | `GroupIcon(0)` → «لم يتم العثور» | DEAD_OR_UNREACHABLE |
| M2 | `cds-main` | تعديل القسم | `EditGroup(0)` / `ConfirmEditGroup` — `UPDATE … WHERE id='0'`, no row | DEAD_OR_UNREACHABLE |
| M3 | `cds-main` | إضافة إسطوانة | `InsertW2ACD(group 0)` | OWNER_RETIRED (§28.1) |
| M4 | `cds-main` | إضافة قسم فرعي | `AddGroup(0)` / `ConfirmAddGroup` — creates a top-level group | PRESERVE |
| M5 | `cds-main` | حذف القسم | `DeleteGroup(0)` — no group row; refused while group 0 has CDs | DEAD_OR_UNREACHABLE |
| G1 | `cds-group-{id}` | اضافة صورة للقسم | `GroupIcon` / `UploadIcon` → `media/w2acd/icons/{⌊id/1000⌋}/{id}.jpg`, `icon = 1` | PRESERVE |
| G2 | `cds-group-{id}` | تعديل القسم | `EditGroup` / `ConfirmEditGroup` — title, parent, des; `child` counters | PRESERVE |
| G3 | `cds-group-{id}` | إضافة إسطوانة | `InsertW2ACD(group_id)` | OWNER_RETIRED (§28.1) |
| G4 | `cds-group-{id}` | إضافة قسم فرعي | `AddGroup` / `ConfirmAddGroup` | PRESERVE |
| G5 | `cds-group-{id}` | حذف القسم | `DeleteGroup` / `ConfirmDeleteGroup` — refused while it holds CDs | PRESERVE |
| I1 | `cds-item-{id}` | تعديل الإسطوانة | `EditW2ACD` / `cdsConfirmEdit` | ALREADY_PRESERVED (§28 W1) |
| I2 | `cds-item-{id}` | نقل الإسطوانة | the same `EditW2ACD` form (group select) | PRESERVE (control only; operation exists) |
| I3 | `cds-item-{id}` | حذف الإسطوانة | `DeleteW2ACD` / `ConfirmDeleteW2ACD` | ALREADY_PRESERVED (§28 W2) |
| I4 | `cds-item-{id}` | اضافة الى قائمة الشير | `AddToShare` / `ConfirmAddToShare` | OWNER DECISION — proposed OWNER_RETIRED |
| I5 | `cds-item-{id}` (`banner = 0`) | رفع بانر | `add_banner` → `UploadBanner` → `media/w2acd/banner/{⌊id/1000⌋}/{id}.gif`, `banner = 1` | PRESERVE |
| I6 | `cds-item-{id}` (`banner ≠ 0`) | تعديل البانر | `edit_banner` → `UploadBanner` (overwrites) | PRESERVE |
| I7 | `cds-item-{id}` (`banner ≠ 0`) | حذف البانر | `W2ACDDelete_banner` / `W2ACDConfirmDelete_banner` — `banner = 0`, file kept | PRESERVE |
| D1 | `cds-item-{id}` | per-mirror edit / delete | `Mirror()` — never called | DEAD_OR_UNREACHABLE |
| D2 | `cds-item-{id}` | «تعديل التعليق» | `Comments()` — never called | DEAD_OR_UNREACHABLE |
| D3 | `cds-item-{id}` | admin comment form (`comment_admin.htm`) | `Comments()` — never called | DEAD_OR_UNREACHABLE |
| D4 | — | `AdvancedStats()` | never called | DEAD_OR_UNREACHABLE |

- G1, G2, G4 (on group pages), G5 and the parity of M4 need a public `cds-group-{id}.htm` page — Laravel has none (only
  Gen 2's `/w2acd/cds.php?id=`, CDs only) — and new group operations (Laravel has none).
- I5–I7's output is read only by `cds-code-{id}-{type}.htm` (`.gif`, works) and `cds-news.htm` (`.jpg`, mismatched);
  Laravel has neither page.
- **Status: W2ACD OLD PUBLIC ADMIN CONTROLS — INVESTIGATION COMPLETE — AWAITING OWNER DECISIONS.** Nothing implemented. No deployment. No commit.

## 45. W2ACD OLD PUBLIC ADMIN CONTROLS — PHASE 1 IMPLEMENTED LOCALLY — AWAITING OWNER MANUAL REVIEW (2026-11-01)

Owner decisions on §44 (2026-11-01): I4 OWNER_RETIRED; I5 / I6 / I7 PRESERVE with `cds-code-{id}-{type}.htm` restored;
`cds-news.htm` excluded; public `cds-group-{id}.htm` approved for a later phase; OLD group defects corrected there; I2
PRESERVE; M3 / G3 remain OWNER_RETIRED. Rule: preserve OLD behavior and flow, implemented properly; no OLD defects, no
redesign.

| ID | Status |
|---|---|
| I1 تعديل الإسطوانة | ALREADY_PRESERVED (unchanged) |
| I2 نقل الإسطوانة | IMPLEMENTED LOCALLY — the existing edit form and its group selector |
| I3 حذف الإسطوانة | ALREADY_PRESERVED (unchanged) |
| I4 اضافة الى قائمة الشير | OWNER_RETIRED |
| I5 رفع بانر / I6 تعديل البانر / I7 حذف البانر | IMPLEMENTED LOCALLY — `media/w2acd/banner/…/{id}.gif`, real GIF content |
| `cds-code-{id}-{type}.htm` | IMPLEMENTED LOCALLY — public HTML / vBulletin / phpBB codes |
| M1–M5, G1–G5, `cds-group` | not started (Phase 2+) |

Tests: `CdPagePublicAdminParityTest` 17 / 17; `tests/Feature/Admin` + `W2acdControllerTest` 1,037 / 1,037; PHPStan 0.
Full record: `decision-log.md` #74.

- **Status: W2ACD OLD PUBLIC ADMIN CONTROLS — PHASE 1 IMPLEMENTED LOCALLY — AWAITING OWNER MANUAL REVIEW.** Not owner closed. No deployment. No commit.
- **Owner manual review (2026-11-02): I2, I5, I6, I7 OWNER MANUAL PASS.** I6's first attempt looked unchanged; a
  read-only investigation proved the replacement, write path = served path and the new `?v=` URL, and the owner's
  repeat with a clearly different image passed — no defect, no code change (mtime `?v=` resolution: hardening note
  only). I2's group selector was empty only because local data has no groups. Phase 1 known local control gaps: 0.
  **W2ACD OLD PUBLIC ADMIN CONTROLS — PHASE 1 OWNER MANUAL REVIEW COMPLETE.** Module not owner closed. `decision-log.md` #75 A.

## 46. W2ACD OLD PUBLIC ADMIN CONTROLS — PHASE 2 IMPLEMENTED LOCALLY — AWAITING OWNER MANUAL REVIEW (2026-11-02)

Scope: the public `cds-group-{id}.htm` (+ `-page-{n}.htm`) page (OLD `Group()`) and its panel «لوحة تحكم قسم
الاسطوانات». Full record: `decision-log.md` #75 B.

| ID | Control | Status |
|---|---|---|
| — | `cds-group-{id}.htm` / `cds-group-{id}-page-{n}.htm` | IMPLEMENTED LOCALLY — breadcrumb, sub-groups (id DESC), CDs (OLD order, no hidden filter, 24 / page), hits + 1; 404 for an unknown group; group 0 → `cds-main` |
| G1 | اضافة صورة للقسم | IMPLEMENTED LOCALLY — gif / jpg / png ≤ 10,000 bytes → real JPEG at `media/w2acd/icons/…/{id}.jpg`, `icon = 1`, back to the parent group |
| G2 | تعديل القسم | IMPLEMENTED LOCALLY — title / parent (OLD's offered set) / des; `child` counters moved; `channel_id` kept |
| G3 | إضافة إسطوانة | OWNER_RETIRED — not shown |
| G4 | إضافة قسم فرعي | IMPLEMENTED LOCALLY — under the bound group; duplicate title refused; `child + 1` |
| G5 | حذف القسم | IMPLEMENTED LOCALLY — the owner-approved Anasheed semantic: refused while CDs or sub-groups remain; no cascade; parent `child − 1` |
| M1–M5 | `cds-main` panel | not started (M4 PRESERVE lives on `cds-main`, outside this surface) |

Not restored (owner decision if wanted): the «تحميل سلسلة» box (`DownSeries()` — broken in OLD) and ads position 9.
Noted: Laravel `cds-main.htm` shows no top-level group cards (OLD `Group(0)` did), so group pages are reached by URL /
breadcrumb only for now.

Tests: `CdGroupPagePublicAdminParityTest` 33 / 33; `CdAuthorizationAuditPhase6Test` 17 / 17 (19 routes); Admin +
Content 2,050 passed, 1 skipped (pre-existing); Pages + Support + Unit 180 / 180; PHPStan 0.

Sub-group card visual parity (2026-11-02, decision-log #75): rebuilt in OLD `group_cd_content.htm` order — title,
then the 89×88 icon beside four stacked lines (sub-groups, CDs, visits, comment); W2ACD-scoped classes only; G4 create
manually proven, G4 OWNER MANUAL PASS awaits the owner's visual re-check. `CdGroupPagePublicAdminParityTest` 34 / 34.

- **Status: W2ACD OLD PUBLIC ADMIN CONTROLS — PHASE 2 IMPLEMENTED LOCALLY — AWAITING OWNER MANUAL REVIEW.** Not owner closed. No deployment. No commit.
- **Update (2026-11-03):** owner manual review complete — see §47.

## 47. W2ACD OLD PUBLIC ADMIN CONTROLS — PHASE 2 OWNER MANUAL REVIEW COMPLETE (2026-11-03)

Full record: `decision-log.md` #76.

| ID | Control | Status |
|---|---|---|
| G1 | اضافة صورة للقسم | OWNER MANUAL PASS — real upload saved and displayed on group #1 |
| G2 | تعديل القسم | OWNER MANUAL PASS — renamed to «قسم تجريبي محلي معدل»; page + breadcrumb updated |
| G4 | إضافة قسم فرعي | OWNER MANUAL PASS — «قسم فرعي تجريبي» created; corrected sub-group card OWNER APPROVED |
| G5 | حذف القسم | OWNER MANUAL PASS — refused while a sub-group remained (parent intact); empty sub-group deleted, back to `/cds-group-1.htm` with the message |
| G3 / I4 | إضافة إسطوانة / share list | OWNER_RETIRED (unchanged) |
| I2, I5, I6, I7 | Phase 1 | OWNER MANUAL PASS (unchanged, §45 / #75 A) |

Phase 2 known local admin-control gaps: 0. Phase 1 known local gaps: 0. Remaining in W2ACD: the `cds-main` panel
(M1–M5; M4 PRESERVE) — not started. Separate, not started: series box / ads 9 (owner decision), `cds-main` group cards
(visitor parity).

- **Status: W2ACD OLD PUBLIC ADMIN CONTROLS — PHASE 2 OWNER MANUAL REVIEW COMPLETE.** W2ACD module NOT owner closed. No deployment. No commit.

## 48. W2ACD OLD PUBLIC ADMIN CONTROLS — PHASE 3 IMPLEMENTED LOCALLY — AWAITING OWNER MANUAL REVIEW (2026-11-03)

Scope: the `cds-main.htm` panel «لوحة تحكم قسم الاسطوانات» (OLD `Group(0)`, `group_cp_for_cd.htm` with id 0). Full
record: `decision-log.md` #77. OLD source re-verified; §44 classification confirmed.

| ID | Control | Status |
|---|---|---|
| M1 | اضافة صورة للقسم | DEAD_OR_UNREACHABLE — not shown |
| M2 | تعديل القسم | DEAD_OR_UNREACHABLE — not shown |
| M3 | إضافة إسطوانة | OWNER_RETIRED — not shown |
| M4 | إضافة قسم فرعي | IMPLEMENTED LOCALLY — top-level group (`parent_id = 0`); duplicate top-level title refused; `size` / `dur` 0; back to `cds-main` |
| M5 | حذف القسم | DEAD_OR_UNREACHABLE — not shown |

Tests: `CdMainPublicAdminParityTest` 10 / 10; W2ACD 78 / 78; Admin + Content 2,061 passed, 1 skipped (pre-existing);
Pages + Support + Unit 180 / 180; PHPStan 0.

- **Status: W2ACD OLD PUBLIC ADMIN CONTROLS — PHASE 3 IMPLEMENTED LOCALLY — AWAITING OWNER MANUAL REVIEW.** Not owner closed. No deployment. No commit.
- **Update (2026-11-03): M4 OWNER MANUAL PASS — PHASE 3 OWNER MANUAL REVIEW COMPLETE** (gaps 0); see §49.

## 49. W2ACD OLD PUBLIC ADMIN-CONTROLS MIGRATION — COMPLETE — OWNER CLOSED (2026-11-03)

Full record: `decision-log.md` #78.

| ID | Control | Final status |
|---|---|---|
| M1 / M2 / M5 | اضافة صورة للقسم / تعديل القسم / حذف القسم (`cds-main`) | DEAD_OR_UNREACHABLE — not shown |
| M3 | إضافة إسطوانة (`cds-main`) | OWNER_RETIRED |
| M4 | إضافة قسم فرعي (`cds-main`) | OWNER MANUAL PASS (Phase 3) |
| G1 / G2 / G4 / G5 | `cds-group` group controls | OWNER MANUAL PASS (Phase 2) |
| G3 | إضافة إسطوانة (`cds-group`) | OWNER_RETIRED |
| I1 / I3 | تعديل / حذف الإسطوانة | ALREADY_PRESERVED |
| I2 / I5 / I6 / I7 | نقل الإسطوانة; banner upload / edit / delete | OWNER MANUAL PASS (Phase 1) |
| I4 | اضافة الى قائمة الشير | OWNER_RETIRED |
| D1–D4 | never-called OLD functions | DEAD_OR_UNREACHABLE |

Known local W2ACD OLD-public-admin-control gaps: 0. Separate and not blocking (unchanged): series box / ads 9 (owner
decision), `cds-main` group cards (visitor parity), `search` + `advanced_search` (REVIEW_REQUIRED), `w2a_cd`
(INSUFFICIENT_EVIDENCE). Next per §43: `search` + `advanced_search` — not started.

- **Status: W2ACD OLD PUBLIC ADMIN-CONTROLS MIGRATION — COMPLETE — OWNER CLOSED (2026-11-03).** Local closure only. No deployment. No commit.

## 50. SEARCH + ADVANCED_SEARCH OLD PUBLIC ADMIN-CONTROLS — INVESTIGATION COMPLETE — AWAITING OWNER REVIEW (2026-11-03)

Investigation only (§43's next unit). Full record: `decision-log.md` #79.

| ID | Page | Control | Why | Classification |
|---|---|---|---|---|
| S1 | `search.htm` (video / audio / dumped_files series) | «out» icon → `KhotabUpSeries` | gate `$Series["group_id"] > 0`, but `group_id` is not in the SELECT (`modules/search/index.php:153`) | DEAD_OR_UNREACHABLE |
| A1 | `advanced_search.htm` (series) | «out» icon → `KhotabUpSeries` | gate `$group_id > 0`, an unassigned local in `ListSeries()` (`modules/advanced_search/index.php:209`) | DEAD_OR_UNREACHABLE |

Handler `UpSeries()` (series and its items `group_id = 0`, back to the Khotab group) is working and already preserved on
its live caller, the Khotab group page (`DetachKhotabSeriesAction`). Not controls: admin hidden-content visibility.
PRESERVE_MISSING 0, NEEDS_EVIDENCE 0. One unit. Recommended implementation: none. Separate visitor-parity observation:
`advanced_search.htm` unregistered in Laravel.

- **Status: SEARCH + ADVANCED_SEARCH OLD PUBLIC ADMIN-CONTROLS — INVESTIGATION COMPLETE — AWAITING OWNER REVIEW.** Nothing implemented. No deployment. No commit.
- **Update (2026-11-03): OWNER CLOSED** — see §51.

## 51. SEARCH + ADVANCED_SEARCH OLD PUBLIC ADMIN-CONTROLS — COMPLETE — OWNER CLOSED (2026-11-03)

Owner decision on §50. Full record: `decision-log.md` #80. S1 (`search.htm`) and A1 (`advanced_search.htm`), the
series «out» icon → `KhotabUpSeries`: DEAD_OR_UNREACHABLE. PRESERVE_MISSING 0, ALREADY_PRESERVED (here) 0,
OWNER_RETIRED 0, NEEDS_EVIDENCE 0. Known local gaps 0. No implementation. Supersedes §43's two REVIEW_REQUIRED rows.
Outside, NOT STARTED: Laravel `advanced_search.htm` (visitor), admin hidden-result visibility, search visitor parity.
Next unstarted admin-control task (§43): `w2a_cd` — INSUFFICIENT_EVIDENCE, one read-only production `sha256sum`.

- **Status: SEARCH + ADVANCED_SEARCH OLD PUBLIC ADMIN-CONTROLS — COMPLETE — OWNER CLOSED (2026-11-03).** No deployment. No commit.

## 52. W2A_CD OLD PUBLIC ADMIN-CONTROLS — INVESTIGATION COMPLETE — AWAITING OWNER REVIEW (2026-11-03)

Investigation only (§43's last item). Full record: `decision-log.md` #81. Production `modules/w2a_cd/index.php` SHA-256
`95d929b6…54c59e` = local. No rewrite, no inbound link; an Anasheed-module copy with CD templates. Every admin block is
gated by `is_admin()`, which OLD's code never defines (fatal on any PHP version); 29 of 34 templates missing.

| ID | Control | Classification |
|---|---|---|
| WC1 | `Group()` group panel (6 Anasheed group ops) | DEAD_OR_UNREACHABLE |
| WC2 | `Detailes()` item panel (edit / move / delete / share) | DEAD_OR_UNREACHABLE |
| WC3 | `Mirror()` per-link edit / delete | DEAD_OR_UNREACHABLE |
| WC4 | `Comments()` admin form / «(تعديل التعليق)» | DEAD_OR_UNREACHABLE |
| WC5 | `ArrangeAnasheed()` up / down (no caller) | DEAD_OR_UNREACHABLE |
| WC6 | `AdvancedStats()` block (no caller) | DEAD_OR_UNREACHABLE |

PRESERVE_MISSING 0, NEEDS_EVIDENCE 0. Supersedes §43's INSUFFICIENT_EVIDENCE row. With this, every §43 item is
dispositioned. Recommended: owner close, no implementation.

- **Status: W2A_CD OLD PUBLIC ADMIN-CONTROLS — INVESTIGATION COMPLETE — AWAITING OWNER REVIEW.** Nothing implemented. No deployment. No commit.
- **Update (2026-11-03): OWNER CLOSED** — see §53.

## 53. W2A_CD — OWNER CLOSED; OLD PUBLIC ADMIN-CONTROLS REMAINING DISCOVERY INVENTORY — COMPLETE (2026-11-03)

Full record: `decision-log.md` #82. Production evidence (read-only): `modules/w2a_cd/index.php` SHA-256
`95d929b6…54c59e` = local (MATCH); `grep -rln "function is_admin"` outside `special/` and `alden-elmo3amalh/` — no output.
WC1–WC6 DEAD_OR_UNREACHABLE (WC5 / WC6 also have no caller); PRESERVE_MISSING 0, NEEDS_EVIDENCE 0; known local gaps 0.

| §43 item | Final |
|---|---|
| W2ACD | OWNER CLOSED (§49, #78) |
| `search` + `advanced_search` | OWNER CLOSED — DEAD_OR_UNREACHABLE (§51, #80) |
| `w2a_cd` | OWNER CLOSED — DEAD_OR_UNREACHABLE (this section, #82) |
| the other 55 rows | as §43 classified them |

- **Status: W2A_CD OLD PUBLIC ADMIN-CONTROLS — COMPLETE — OWNER CLOSED (2026-11-03).**
- **Status: OLD PUBLIC ADMIN-CONTROLS REMAINING DISCOVERY INVENTORY — COMPLETE (2026-11-03).** Discovery inventory only —
  not migration completion, not visitor parity, not production readiness, not deployment / cutover (all separate, NOT
  STARTED). No deployment. No commit.

## 54. WAY2ALLAH WHOLE-MIGRATION CLOSURE REVIEW — AUDIT COMPLETE — AWAITING OWNER REVIEW (2026-11-03)

Audit only (§28.6 requires this review before deployment; §30.5 lists the remaining closure / pre-deployment work).
Decision record: `decision-log.md` #83. Declares nothing complete.

**Remaining-work register** (L = blocks local migration closure; D = blocks production deployment):

| ID | Item | Class | L | D |
|---|---|---|---|---|
| R1 | 246 uncommitted entries since `4309357` (2026-10-08) — commit / review baseline (297 after R2, 2026-11-03) | OPEN_OWNER_DECISION | yes | yes |
| R2 | Fresh whole-suite + PHPStan + Pint on the final tree (last whole run #69) — done 2026-11-03, #84 | COMPLETE_EVIDENCED (#84; was OPEN_IMPLEMENTATION) | — | — |
| R3 | §7.1 DoD table refresh (dated 2026-10-15) | COMPLETE_EVIDENCED (#100; was OPEN_IMPLEMENTATION (docs)) | — | — |
| R4 | Plan banner / §30 "final closure record" vs the 2026-10-21 rule and §31–§53 | COMPLETE_EVIDENCED (#100; was OPEN_IMPLEMENTATION (docs)) | — | — |
| R5 | `PROJECT-HANDOFF.md` §7 / §8 stale current statements | COMPLETE_EVIDENCED (#100; was OPEN_IMPLEMENTATION (docs)) | — | — |
| R6 | #82 cites "§34" — should be §30.5 | COMPLETE_EVIDENCED (#100; was OPEN_IMPLEMENTATION (docs)) | — | — |
| R7 | One consolidated OLD / Gen 2 retirement checklist (DoD 5a), incl. §31–§53 modules | COMPLETE_EVIDENCED (#100; was OPEN_IMPLEMENTATION (docs)) | — | — |
| R8 | Deployment migration inventory: `admin_audit_log` + `download_rejections` (§10.5 lists one) **[2026-11-08 note (N4): both October tables' `down()` now refuses to drop a non-empty table; production `migrate:rollback` remains prohibited.]** | PRE_DEPLOYMENT_ONLY | no | yes |
| R9 | Permission-grant plan for 81 definitions **[2026-11-08 note (N4): run `php artisan admin:audit-grants` before and after granting; exit 1 / 2 blocks proceeding until reviewed. Still OPEN.]** | OPEN_OWNER_DECISION | no | yes |
| R10 | Production super-admin assignment (sync removed, §27; no Laravel UI) **[2026-11-08 note (N4): run `php artisan admin:audit-grants` before assignment (super-admin holders and stale grants under reused ids are reported, never repaired). Still OPEN.]** | OPEN_OWNER_DECISION | no | yes |
| R11 | Rebuild the deployment package (current zip 2026-09-14) | PRE_DEPLOYMENT_ONLY | no | yes |
| R12 | Database backup + rollback procedure (handoff §1) | PRE_DEPLOYMENT_ONLY | no | yes |
| R13 | Documented read-only deploy-time checks (§10.6, §13.15, §25.10, §26.14) **[Added 2026-11-08, #128 / R26 — cutover checks: (1) OLD PHP-Nuke no longer publicly served after the Laravel cutover; (2) OLD `modules/gal2/loader.php` and (3) `modules/gal2/__loader.php` not publicly accessible; (4) old web roots, aliases, virtual hosts and static-file mappings do not expose retired PHP-Nuke files; (5) Laravel does not serve or copy these helpers. Read-only; not performed now.]** **[Added 2026-11-08, #132 / R26-b — alQuran cutover checks: (6) OLD `modules/alQuran/` handlers (`index.php` search, `Murattal.php`, `Asbab.php`) not publicly accessible after the cutover (via `alQuran.htm`, `new_modules.php` or `modules.php`); checks (1) and (4) above cover the retired web roots, aliases and virtual hosts for these files too. Read-only; not performed now.]** | PRE_DEPLOYMENT_ONLY | no | yes |
| R14 | Business confirmations #9–#12 and Part V infrastructure (queue driver, vBulletin reachability, …) — currency unclear **[Clarification 2026-11-07 (R25): vBulletin reachability now explicitly includes the forum cookie checks listed in the R25 cookie-compatibility note below — scope otherwise unchanged; still OPEN.]** **[N1 / R14-A 2026-11-08 — IMPLEMENTED AND TESTED LOCALLY — PRODUCTION VERIFICATION PENDING.** Root cause: the web group's `EncryptCookies` could not decrypt the forum's unencrypted auth cookies and nulled them, so `VbulletinSessionGuard` never saw a forum cookie over HTTP (member features failed closed). Fix: `App\Http\Middleware\EncryptCookies` replaces the framework middleware in the web group (`bootstrap/app.php`, `web(replace:)`) and exempts exactly `{prefix}sessionhash`, `{prefix}userid`, `{prefix}password`; the guard reads the same names. Configuration contract: one prefix, `services.vbulletin.cookie_prefix` (`VBULLETIN_COOKIE_PREFIX`, default `bb_` — the Gen 2 `check_login()` names), via `App\Domain\Identity\Support\VbulletinCookieNames`; no alternative names accepted; an invalid prefix exempts nothing and authenticates nobody. vB5 builds all three cookies as `cookie_prefix . name` (bundled `includes/vb5/cookie.php`, `auth.php`); the local forum config has `bb` (`bbsessionhash`), so **the production prefix must be verified** before setting it. Validation unchanged: session row, `userid > 0`, idhash = md5(User-Agent + first three IPv4 octets), 900-s window, existing forum user; remember-me md5(password . salt) with constant-time compare, fail-closed without the salt; vB5 token remember-me unsupported (fails closed); read-only (no `lastactivity` refresh). Laravel session / XSRF and all other cookies stay encrypted. Tests: `tests/Feature/Identity/ForumCookieHttpAuthenticationTest.php` (29, through the real HTTP stack, synthetic sessions); mutation — without the exemption 8 fail; full suite 2,605 — 2,604 passed, 1 skipped; PHPStan 0; Pint PASS. Still open: production cookie prefix and domain (must cover the forum and site hosts), HTTPS / Secure flags, proxy / CDN (the idhash uses `REMOTE_ADDR`; no trusted proxies are configured), forum session timeout / IP-check settings, `VBULLETIN_COOKIE_SALT`, forum database reachability; owner verification with a test forum account. R14 NOT closed.]** | OPEN_OWNER_DECISION | no | yes |
| R15 | DoD 2 / 3 per-phase production verification | PRE_DEPLOYMENT_ONLY (after) | no | no |
| R16 | DoD 5a `JoinMe` / `BackUp/admin:1690` residual paths — unused or replaced? | OPEN_PRODUCTION_EVIDENCE | no | no (OLD retirement) |
| R17 | DoD 5b OLD Cat writer (§20.1) | PRE_DEPLOYMENT_ONLY (cutover) | no | no |
| R18 | DoD 6 Gen 2 inline admin toolbars | PRE_DEPLOYMENT_ONLY (cutover) | no | no (OLD retirement) |
| R19 | DoD 9 `nuke_*_mod` archival (Laravel side verified) | PRE_DEPLOYMENT_ONLY (cutover) | no | no |
| R20 | W2ACD: OLD `images/` vs Laravel `public_html/images` identity during coexistence (§25.10) | OPEN_PRODUCTION_EVIDENCE | no | no |
| R21 | vBulletin member poll-comment posting (no local vBulletin) **[Clarification 2026-11-07 (R25): member posting depends on Laravel recognising the forum session — see the R25 cookie-compatibility note below; scope otherwise unchanged; still open.]** **[2026-11-08, N1 / R14-A: the local blocker (forum cookies nulled by `EncryptCookies`) is fixed; a cookie-authenticated member posting a poll comment with `anonpost = 0` is now tested through the HTTP stack. Production member verification still pending.]** | PRE_DEPLOYMENT_ONLY (after) | no | no |
| R22 | Optional hardening: `admin:sync-permissions --dry-run`; mtime `?v=` | OPEN_OWNER_DECISION (optional) | no | no |
| R23 | Remaining discovery inventory (#72 / §43) | COMPLETE_EVIDENCED (#82) | — | — |
| R24 | §8 side items (Telawah mirror BLOCKED; D1b′ open; §23.10 / §24.10 public pages OLD-owned) | SUPERSEDED (§29.1, §11.3, §30.1–§30.2) | — | — |
| R25 | Gen 2 root `vb5.php` cross-login bridge (`nuke_users` lookup, password check disabled, vBulletin `login2`) — Laravel does not route `/vb5.php`; whether production serves it outside Laravel is unknown. Business Confirmation #5 (preserve during migration), #101 (admin auth), R14 / R21. Not a confirmed regression, not retired — added 2026-11-07, `decision-log.md` #126 **[R25 investigation 2026-11-07 — source-level evidence accepted; classification E, PRODUCTION EVIDENCE REQUIRED; provisional architectural default A (bridge stays legacy-served) — NOT finally approved; final disposition needs production evidence and owner confirmation. Verified role: `nuke_users` username lookup only (password check commented out), authentication delegated to vBulletin `login2`, vBulletin sets the login cookies, redirect to the forum or the vBulletin admin panel; no registration, identity sync or `nuke_users` write. Laravel does not depend on it directly — not proof it can be retired. `/vb5.php` is not assigned in the deployment plan (ownership UNKNOWN; do not assume it stays reachable): verify production availability, inbound traffic, external call sites, web-server URL ownership, the forum install path (hard-coded `/home2/…/forums`, may not exist after the server move) and post-cutover compatibility. Security baseline: the committed Gen 2 file (`2cdca62`) still contains `var_dump($nukeUser)` (removed only by the existing uncommitted local change — verify the corrected file is the one deployed) and hard-coded database credentials (remediation needs separate authorization); CSRF disabled; username enumeration via differentiated errors. Cookie risk cross-referenced to R14 / R21 (§30.5 note below). Still OPEN — production deployment blocker.]** | OPEN_OWNER_DECISION | no | yes |
| R26 | Legacy-only security findings in OLD `modules/gal2/`: `loader.php` unrestricted `readfile($_GET['file'])` (potential arbitrary local file disclosure) and `__loader.php` unescaped `$_GET['file']` in inline JavaScript (potential reflected XSS) — OLD PHP-Nuke, not Laravel; absent from the local Gen 2 / Laravel trees; production exposure UNKNOWN. Closure evidence: verified public inaccessibility or removal of both files (cutover checks in R13). If reachable before cutover → separate current-production concern for prompt owner-controlled mitigation, not deferred to the migration. Independent of visitor parity — added 2026-11-08, `decision-log.md` #128 **[R26-a = the `gal2` findings above (unchanged). Added 2026-11-08, #132 — R26-b, OLD `modules/alQuran/` (source-level; not Laravel vulnerabilities; exploitation and public reachability NOT verified): (1) potential SQL injection — `dosearch()` puts the unescaped `$searchterm` into SQL; (2) potential unsafe dynamic includes — `Murattal.php` `include "Murattal/$tartil.html"`, `Asbab.php` `include "Asbab/$get.htm"`; (3) legacy `eregi()` guards whose runtime behaviour depends on the OLD production PHP version. Required: determine before cutover whether these endpoints are publicly reachable — if so, escalate for prompt owner-controlled mitigation (a current-production concern, not deferred to the migration); after cutover verify they are inaccessible (R13 check 6) and not exposed by retired web roots, aliases or virtual hosts (R13 checks 1 and 4). OLD code not modified. R26 stays OPEN.]** | OPEN — PRODUCTION EXPOSURE UNKNOWN | no | yes |
| N2 | AdminCP login brute-force protection (`POST /admincp/login` had no attempt accounting; found by the 2026-11-08 production-readiness assessment) **[2026-11-08: IMPLEMENTED AND TESTED LOCALLY — PRODUCTION CONFIGURATION VERIFICATION PENDING.** Owner-approved policy on Laravel's RateLimiter (`App\Domain\Admin\Support\AdminLoginThrottle`, `AdminAuthController::login()` only): failed credential checks — wrong password or unknown username — count against two 15-minute counters, 10 per client IP and 5 per username + IP (username trimmed and lower-cased); CSRF and validation failures are not counted; no username-only counter, no permanent lockout. When either is exceeded: HTTP 429 + `Retry-After`, the login page with one generic message (same for existing and unknown usernames), the username kept and the password never flashed, and `AdminGuard::attempt()` is not called. A success clears only the username + IP counter. Throttle events log HMAC identifiers only. `AdminGuard`, `LegacyPasswordVerifier` (bcrypt / MD5 / SHA1), rehash, sessions, permissions, logout and Gen 2 unchanged; no dummy hashing. Window: fixed 15 minutes from a counter's first failure (framework limiter). Tests: `tests/Feature/Admin/AdminLoginThrottleTest.php` (18, incl. time travel, CSRF, X-Forwarded-For ignored, logs); mutation — throttle off → 11 fail; admin + identity 1,119 / 1,119; full suite 2,669 — 2,668 passed, 1 skipped; PHPStan 0; Pint PASS. Limitations: distributed guessing from many IPs is limited only per IP (no username-only counter, by decision); increments strictly atomic only on database / Redis / memcached (the default `file` store may under-count concurrent failures); accent variants of a username are not folded (the IP counter still applies). Production: shared cache store for all workers; trusted-proxy configuration if a proxy / CDN fronts the site (otherwise all visitors share one IP counter and 10 failures lock everyone out for 15 minutes); production `aid` collation.]** | IMPLEMENTED LOCALLY — PRODUCTION CONFIGURATION PENDING | no | yes |
| N3 | Host-header / absolute-URL generation (found by the 2026-11-08 production-readiness assessment; the three send-to-friend mails were already moved to APP_URL under #106) **[2026-11-08: IMPLEMENTED AND TESTED LOCALLY — PRODUCTION HOST/PROXY VERIFICATION PENDING.** Owner-selected option A: `APP_FORCE_ROOT_URL` (config `app.force_root_url`, **default false**). When true, `App\Support\Http\CanonicalUrl` (called from `AppServiceProvider::boot()`) forces Laravel's URL root and scheme to APP_URL (`URL::forceRootUrl` + `URL::forceScheme`) and routes the default paginator path through the URL generator — so `url()`, `route()`, `asset()`, path / route redirects and default pagination links use the canonical host and scheme, not the request Host header. APP_URL must be an absolute http(s) URL with a valid host (optional port / path; no user info, query or fragment); an invalid value is never forced or guessed — HTTP requests fail with a RuntimeException naming the setting (not the value), console commands skip the forcing so the configuration can be fixed. Only configuration is read (config-cache safe — verified with a cached configuration in a temporary path: spoofed-Host 301 → `https://way2allah.com/radio.htm`). Covered and tested (Host `attacker.example`, APP_URL `https://way2allah.com`): the five permanent redirects (`modules.php` ×2 forms, radio operations, Khotab comment page, `cds-group-0`, `khotab-pdf-{item}`) — 301, same path and query, HTTPS canonical host; both banner embed-code pages (Khotab, W2ACD; item / release and image URLs, all three code types); named routes, form actions, `asset()` URLs and default paginator links; relative `Route::redirect` aliases stay relative; social metadata and the send-to-friend mails stay on APP_URL; flag off → unchanged request-based behaviour. Tests: `tests/Feature/Support/CanonicalUrlTest.php` (39); mutation — forcing disabled → 16 fail; redirect / banner / send-to-friend / social / AdminCP set 230 / 230; full suite 2,708 — 2,707 passed, 1 skipped; PHPStan 0; Pint PASS. Not covered: code that reads the Request directly (e.g. four AdminCP paginators passing `$request->url()` — private admin pages), ContextualReturn's same-host check (unchanged by instruction), relative URLs, MEDIA_BASE_URL / THUMBNAIL_BASE_URL. No trusted-host or trusted-proxy change. Production (OPEN): verified canonical APP_URL; the actual public hostnames; trusted-host policy; trusted-proxy configuration; CDN cache-key behaviour; HTTPS termination; production activation of APP_FORCE_ROOT_URL (owner approval).]** | IMPLEMENTED LOCALLY — PRODUCTION HOST/PROXY PENDING | no | yes |
| N4 | Admin grant identity safety & migration rollback hardening (found by the 2026-11-08 default-database / admin-id-reuse investigation: `nuke_authors.id` can be reused after a MySQL 5.7 restart, and a reused id inherits a deleted admin's Spatie grants; the `2026_09_02` index `down()` would drop production's hand-deployed same-named indexes; the October `main` tables' `down()` would drop data) **[2026-11-08: IMPLEMENTED AND TESTED LOCALLY — PRODUCTION AUDIT RUN PENDING.** (1) `AdminStaffController::store()`: insert inside a `main` transaction; before commit `App\Domain\Admin\Support\AdminGrantInspector` counts `model_has_roles` / `model_has_permissions` rows (default connection, `model_type` = AdminUser) under the new id; any → rollback, controlled refusal naming `admin:audit-grants`, warning log with id and counts only — grants never deleted or reassigned; check failure → rollback (fail closed). (2) Duplicate `aid` refused (database collation comparison); creation serialised by cache lock `admin-staff-create` (30 s, 5 s wait); no legacy unique constraint; Gen 2 / OLD creation not covered. (3) `php artisan admin:audit-grants [--json]`, SELECT-only: confirmed = orphan admin roles / permissions, unexpected model types, forum-guard grants with missing forum user, duplicate usernames, duplicate non-zero uids; informational = super-admin holders, radminsuper / role drift, uid = 0 with grants; exit 0 / 1 / 2 (incomplete) / 64; historical identity changes not detectable. (4) Four `2026_09_02` index migrations: `down()` non-destructive (no-op), `up()` byte-identical (SHA-256 snapshot test); `LegacyIndexGuard` unchanged (its docblock now out of date — recorded). (5) `2026_10_12_100000` / `2026_10_22_130000`: `down()` throws via `App\Support\Database\NonEmptyTableGuard` when the table has any row (migration stays recorded, table untouched); empty / missing behaves as before; `up()` unchanged. Tests: `tests/Feature/Admin/AdminGrantSafetyTest.php` (15), `tests/Feature/Support/MigrationRollbackSafetyTest.php` (17); 8 mutation checks all caught. Production (OPEN): run the audit before and after R10 / R9; revoke Laravel grants before any admin deletion; shared cache store for the lock; production `migrate:rollback` remains prohibited; session-id reuse residual (AdminGuard unchanged). PROJECT-HANDOFF.md §10 production runbook.]** **[2026-11-08: CLOSED LOCALLY — OWNER-ACCEPTED — PRODUCTION VERIFICATION PENDING** (`decision-log.md` #134). Final acceptance review: PASS WITH DOCUMENTED LIMITATIONS, no confirmed defects; optional lock-ownership check not implemented (owner decision). Production prerequisites (OPEN): verify `nuke_authors` is InnoDB; verify a shared cache store; run `admin:audit-grants` before and after R9 / R10; keep the prohibition on `migrate:rollback` / `migrate:reset` / `migrate:refresh` / `migrate:fresh`; retire legacy admin management per X9 before cutover. Residual risks: legacy admin creation bypass; session id reuse; rare cache-lock lease-expiry race; partial migration rollback; historical identity reuse undetectable.]** | CLOSED LOCALLY — OWNER-ACCEPTED — PRODUCTION VERIFICATION PENDING | no | yes |
| N5 | P0 split layout & media roots (found by the 2026-11-08 production-readiness infrastructure audit and the P0 read-only investigation: `public_path()` is the web root only for HTTP; Artisan, queue workers and `config:cache` — including the installer's — fall back to `laravel-app/public`, so five media-root defaults could be cached wrong; visitor readers bypassed the configured root; CD images used the client extension; gallery downloads lacked containment; packages could replace the split front controller or overwrite runtime files) **[2026-11-09: IMPLEMENTED AND TESTED LOCALLY — PRODUCTION VERIFICATION PENDING.** Owner decisions D1–D6 (`decision-log.md` #135). (1) `.env.production.example` with the five absolute placeholders (`KHOTAB_MEDIA_ROOT`, `ANASHEED_MEDIA_ROOT`, `W2ACD_MEDIA_ROOT`, `W2ACD_IMAGE_ROOT`, `AUTOCOMPLETE_ROOT`); keys and names unchanged; `bootstrap/app.php` / `public/index.php` unchanged. (2) `Author`, `Location`, `HomeController`, `ContentSidebarWidget` existence checks via `MediaPathResolver::absolute()` (configured Khotab root); URLs / fallbacks unchanged. (3) S1: `CdImageList` stores the server-detected extension (getimagesize + finfo agree; else `UnsupportedCdImageException`, controlled AdminCP error). (4) S2: `GalleryController::download()` via `App\Support\Filesystem\PublicFileResolver` (canonical containment, #107 media-symlink rule). (5) `php artisan media:check-roots` (read-only; exit 0 / 1 / 64). (6) `php artisan deploy:verify-package` (read-only; exit 0 / 1 / 2 / 64) + `deploy/split-layout/newapp-index.php`. Tests: `SplitLayoutMediaRootsTest` (14), `MediaUploadDownloadContainmentTest` (14), `DeploymentPackageVerifierTest` (10); `KhotabPublicParityTest` fixture moved into the configured root (assertions unchanged). Production (OPEN): the five values, config re-cache, `media:check-roots`, misplaced-file inventory (owner-approved reconciliation), directory ownership / writability, `open_basedir`, handler mappings, queue / cron, `link` data shape. Reference: `docs/operations/split-layout-media-roots.md`.]** **[2026-11-09 correction (final acceptance review concern 3 FAIL → fixed):** `deploy:verify-package` validates every entry name before the protected-path rules (`unsafe_entry_name`, raw central-directory names cross-checked), refuses duplicates (`duplicate_entry`, read by index) and symlinks (`symlink_entry`), and adds `--full` (mandatory for full releases; `front_controller_missing`); `DeploymentPackageVerifierTest` 38 tests. CD upload logic, visitor readers, media-root configuration and the template unchanged.]** **[2026-11-09: CLOSED LOCALLY — OWNER-ACCEPTED — PRODUCTION VERIFICATION PENDING** (`decision-log.md` #136). Final acceptance completed; all confirmed verifier defects fixed (unsafe names, duplicates, symlinks, absolute paths, hidden NUL names, fake directory markers); full suite 2,805 passed / 1 skipped / 0 failed, PHPStan 0, Pint clean. The 2026-09-14 full package (SHA-256 `83109454…2163126`, 7 036 entries) is REJECTED FOR FUTURE DEPLOYMENT (3 autocomplete lists, 2 bootstrap cache files, 1 noncanonical front controller). Accepted limitations: ZIP64 unsupported; extractor behaviour not production-verified; non-NUL control characters allowed in entry names; CD MIME-agreement branch without a deterministic test; visitor page-level / writer-to-reader coverage gaps; CLI `public_path()` intentionally not unified. Production prerequisites (OPEN): PROJECT-HANDOFF.md §10 P0 closure block.]** | CLOSED LOCALLY — OWNER-ACCEPTED — PRODUCTION VERIFICATION PENDING | no | yes |
| N6 | Trusted proxies & hosts / client IP (found by the 2026-11-09 read-only Trusted Proxies & Hosts investigation: no trusted proxies or trusted hosts configured; D1 forum session IP binding differs from vBulletin; D2 IPv6 visitor comment IPs exceed the legacy varchar(15) columns) **[2026-11-09: D2 FIXED LOCALLY — D1, TRUSTED PROXIES AND TOPOLOGY OPEN.** Visitor comment IPs (`KhotabItemController::storeComment()`, `AnasheedItemController::storeComment()`) are fitted to the legacy `varchar(15)` columns (`nuke_islamic_comments.ip`, `nuke_anasheed_comments.ip`) — first 15 characters, as the AdminCP comment path already did — so an IPv6 visitor's comment no longer fails the strict-mode insert. The column is write-only attribution (no reader in Laravel, Gen 2 or OLD; not used for authentication, rate limiting, duplicate detection or any security decision); the country code is still computed from the full address. No schema change. Tests: `tests/Feature/Content/CommentIpLegacyWidthTest.php` (12; the legacy width is modelled with SQLite triggers that fail like MySQL strict mode). Limitations: an IPv6 (or IPv4-mapped IPv6) address is stored truncated and not reversible, so stored comment IPs cannot identify an IPv6 visitor; GeoIP remains IPv4-only (`''` for IPv6, unchanged); other legacy IP columns of similar width (e.g. `nuke_poll_check.ip` varchar(20), not written by Laravel today) were not changed. D1 (forum session IP binding differs from vBulletin: vBulletin derives its idhash IP from `CF-Connecting-IP` / the first public `X-Forwarded-For` entry / `REMOTE_ADDR`, or the proxy address when `proxyiplist` matches, with `4 - ipcheck` octets; Laravel uses `REMOTE_ADDR` with 3 octets) remains OPEN — forum authentication unchanged by owner decision. Trusted Proxy configuration remains OPEN (none configured; forwarded headers ignored); trusted hosts not enabled. Production topology remains UNKNOWN (proxy / CDN, HTTPS termination, origin access, vBulletin `proxyiplist` / `ipcheck`, IPv6 reachability). `.env.production.example` now carries `APP_FORCE_ROOT_URL=true` and `SESSION_SECURE_COOKIE=true` — TEMPLATE ONLY; not activated in production, which requires a verified https `APP_URL` and HTTPS service (owner-approved step). `decision-log.md` #137; PROJECT-HANDOFF.md §10 trusted-proxies runbook.]** **[2026-11-09: D1 IMPLEMENTED AND TESTED LOCALLY — PRODUCTION CONFIGURATION UNVERIFIED** (`decision-log.md` #138; owner decisions OD1–OD4). `App\Domain\Identity\Support\VbulletinSessionBinding` reproduces the legacy vBulletin request / session source (local copy 5.5.5 / SIMPLE_VERSION 562): IPADDRESS / ALT_IP from raw `REMOTE_ADDR`, public `CF-Connecting-IP`, the first public `X-Forwarded-For` entry, the mirrored proxy list (exact or `*` prefix) and proxy header, the proxied swap, IPv6 canonicalisation, `ipSubstring` with `4 - ipcheck` parts and the `vBulletin` User-Agent fallback — never `request()->ip()`. `VbulletinSessionGuard` now requires, for the session-hash cookie: not expired (`cookietimeout`), `idhash` = md5(UA . ipSubstring(ALT_IP)), AND `session.host` matching IPADDRESS (vBulletin's second check, previously missing). Configuration: `VBULLETIN_IPCHECK` (default 1), `VBULLETIN_IPCHECK_ALLOW_UNBOUND` (default false; required for ipcheck=4), `VBULLETIN_COOKIE_TIMEOUT` (default 900), `VBULLETIN_PROXY_IP_LIST` / `VBULLETIN_PROXY_IP_HEADER` (default empty) — raw scalars, config:cache-safe; malformed or unsafe values (invalid ipcheck, unapproved ipcheck=4, `all` / `*`, malformed entries, invalid header name) make the session cookie fail closed with a logged reason (no secrets). Read-only guard (no `lastactivity` refresh); cookie handling, remember-me and missing-salt behaviour unchanged. Tests: `tests/Feature/Identity/VbulletinSessionBindingTest.php` (96) against `tests/Support/Fixtures/VbulletinIpGoldenVectors.php` — 28 golden vectors produced by executing the legacy vBulletin request constructor and IP utility (PHP 7.4 with its real traits and PHP 8.3, identical; no vBulletin code stored); existing guard fixtures gain vBulletin's `session.host` column (assertions unchanged); 12 mutation checks caught. Deviations by design: `all` / `*` refused; ipcheck=4 needs explicit approval; the host comparison is strict (`===`) where vBulletin uses PHP's loose `==` (stricter, never weaker). Not verified: the production vBulletin version (headers 5.5.5, SIMPLE_VERSION 562), `ipcheck`, `cookietimeout`, `proxyiplist` / `proxyipheader`, and that the forum and Laravel see the same `REMOTE_ADDR` / forwarded headers. Production configuration UNVERIFIED (defaults reproduce the previous behaviour); forum authentication production verification PENDING; Trusted Proxies remain OPEN (Laravel's `TrustProxies` is still unconfigured and no longer affects forum binding); R25 remember-me (vB5 sha224 token) remains SEPARATE; members browsing only Laravel pages still expire after `cookietimeout` (read-only guard, OD3).]** **[2026-11-09: D1 CLOSED LOCALLY — ACCEPTED WITH DOCUMENTED LIMITATIONS — PRODUCTION VERIFICATION PENDING** (`decision-log.md` #139; final acceptance review verdict B). **F1 — security precondition for proxy mode:** when `VBULLETIN_PROXY_IP_LIST` is non-empty, forum session security depends entirely on the proxy's header handling. A request from a listed proxy binds `idhash` to the proxy's own address (shared by every client) and `session.host` to the configured header — or, when that header is missing, multi-valued or invalid, to the client's `CF-Connecting-IP` / first public `X-Forwarded-For` entry (vBulletin's own fallback). Behind a proxy that does not overwrite the header and sanitise client headers, a stolen session cookie plus User-Agent satisfies BOTH checks (demonstrated by an adversarial test; identical in vBulletin). The default empty list is not affected. **If the conditions below cannot be verified, do not enable proxy mode.** Preconditions before proxy mode: (1) Verify the actual proxy topology (which proxy / CDN sits in front of the origin). (2) Verify the configured proxy egress IPs (`VBULLETIN_PROXY_IP_LIST` holds exactly those addresses). (3) Verify the proxy overwrites the configured client-IP header (`VBULLETIN_PROXY_IP_HEADER`) with exactly one valid IP on every request. (4) Verify client-supplied `CF-Connecting-IP` and `X-Forwarded-For` cannot reach the origin without sanitisation (a missing or invalid configured header falls back to them). (5) Verify the origin cannot be reached through an unintended path that defeats the trust boundary. (6) Verify forum and Laravel requests see compatible network information (same vhost / proxy chain, same `REMOTE_ADDR` and headers). (7) Verify the actual vBulletin proxy settings (`Misc.proxyiplist` / `Misc.proxyipheader`). **F2 — wildcard breadth:** the literal `all` and bare `*` are refused, but broad wildcard lists (e.g. `1*, 2*, …`) still create excessive trust. Configure only verified proxy egress addresses. D1 closure tests: `VbulletinSessionBindingTest` now 140 tests / 393 assertions, adding 18 adversarial golden vectors (`tests/Support/Fixtures/VbulletinIpAdversarialVectors.php`, executed from the vBulletin source under PHP 7.4 and 8.3 — identical): multi-value / missing / invalid configured proxy header, CF-Connecting-IP and X-Forwarded-For fallbacks, the F1 spoofed-fallback acceptance under unsafe proxy handling (and its rejection when sent directly or with the default list), the ipcheck=2 numeric-prefix case (`1.10` vs `1.1`: vBulletin's loose `==` accepts, Laravel's strict `===` rejects — deliberate), IPv6 CF-Connecting-IP, uppercase IPv6 X-Forwarded-For, ULA / link-local filtering, IPv4 with port, CGNAT 100.64/10 (public, as in vBulletin), missing and whitespace REMOTE_ADDR, Trusted Proxies / Hosts still unconfigured, both checks mandatory with the default list. 7 further mutation checks caught. No production code changed. Accepted limitations: F1 (proxy mode safe only under the verified preconditions); F2 (wildcard breadth); strict `===` deviation for ipcheck=2 numeric prefixes (stricter, rare false logout); a missing REMOTE_ADDR yields empty addresses, as in vBulletin (never in HTTP); a refused configuration is logged only when a matching session cookie is presented, and on every such request; the approval flag accepts boolean spellings such as `yes` / `on`; production `session.host` width, PHP 7.2 filter equivalence (verified on 7.4.33 / 8.3.6) and vBulletin version / options unverified. Trusted Proxies remain OPEN.]** | D2 FIXED · D1 CLOSED LOCALLY (DOCUMENTED LIMITATIONS) — PRODUCTION VERIFICATION PENDING · PROXIES / TOPOLOGY OPEN | no | yes |

**R25 cookie-compatibility note (2026-11-07) — PRODUCTION-DEPENDENT COMPATIBILITY RISK, not a confirmed regression:** the local forum configuration copy (`7amlat/admincp/forumConfig/vbconfig.php:72`) sets `cookie_prefix = 'bb'`, and vBulletin 5 prefixes cookie names directly (`includes/vb5/cookie.php`), so the forum would set `bbsessionhash` (and, with remember-me, `bbuserid` / `bbpassword`); Laravel `VbulletinSessionGuard` reads `bb_sessionhash`, `bb_userid`, `bb_password` (the convention of Gen 2 `check_login()`, `w2a_config.php:279-289`), while Gen 2 `VB5()` / `is_user()` (`new_functions.php:35-72`) read `bbsessionhash` / `bbuserid` — Gen 2 itself uses both. vBulletin 5's remember-me `password` cookie is token-based (`includes/vb5/auth.php`), whereas Laravel's fallback expects `md5(user.password . salt)`. Earlier source-level parity findings (e.g. #126) do **not** establish end-to-end cookie compatibility. Before deployment verify (R14 / R21): the actual forum cookie prefix and domain, the session-hash scheme, the remember-me scheme, Laravel guard recognition, and one controlled integration test. The guard is unchanged.

**[Update 2026-11-08, N1 / R14-A:** besides the naming question above, Laravel's `EncryptCookies` was nulling the forum cookies before the guard read them — fixed locally; the guard and the exemption now read one configured prefix (default `bb_`), to be set to the verified production prefix. The remember-me and idhash points above stand.]**

**Visitor parity — separate track, not a blocker under any governing document:** Fatwa (#71: multi-topic `fatawa-all`
trails, last-topic sidebar / admin category, `fatawa-group-0-0.htm` fallback); W2ACD (series box, ads 9, `cds-main`
group cards; `cds-news` owner-excluded); Anasheed visitor parity; `advanced_search.htm` (Laravel emits no link to it);
admin hidden-content visibility on search / Cat / Satellite; handoff §3 `cds-series .grx`, `fatawa-category-{id}`, RSS.

**Visitor-parity update (2026-11-04).** Superseding "not a blocker" above: owner rule (2026-11-03) — no push or production
deployment until every confirmed HIGH and MEDIUM visitor-parity gap is resolved and owner-approved; LOW items may be
deferred only with explicit owner approval; R1 deferred until then. Tracking:

| Module | Status | HIGH / MEDIUM open | LOW deferred (owner-approved) | Record |
|---|---|---|---|---|
| Advanced Search (`advanced_search.htm` / `video-advanced-search.htm`, sheikh-page form) | **OWNER CLOSED LOCALLY (2026-11-04)** | 0 | G8 «new» badges; G9 empty-search message | `decision-log.md` #85, #86 |
| Anasheed (news pages, archive.org links, group listing, item page, hidden items, `.grx`, sub-group cards, series block, pager, item sidebar) | **OWNER CLOSED LOCALLY (2026-11-04)** | 0 (A1, A2 HIGH and A3–A6 MEDIUM resolved; A7–A12 implemented; A13 no change; A14 404 kept) | OLD ad slots 5 / 9 and «new» badges; broken legacy archive.org links tracked separately as data | `decision-log.md` #87, #88 |
| Fatwa (#71 items F1–F4, `fatawa-category` F5, `fatawa-topics-{cat}` F5b, `fatawa-channel-0` F6) | **OWNER CLOSED LOCALLY (2026-11-05)** | 0 (F6 HIGH and F5b MEDIUM resolved, owner manual PASS; F3 admin-only, out of scope; F4 / F5 no gap) | F1 per-topic breadcrumbs, F2 last-topic sidebar | `decision-log.md` #89, #90 |
| W2ACD (series box W1, ads 9 W2, `cds-main` group cards W3; `cds-news` owner-excluded) | **OWNER CLOSED LOCALLY (2026-11-05)** | 0 (W3 MEDIUM resolved, owner manual PASS) | W1 series box / `.grx` (OLD output wrong — Anasheed links); W2 ad slot 9 | `decision-log.md` #91, #92 |
| Admin hidden-content visibility (search / Cat / Satellite; investigated scope) | **OWNER CLOSED LOCALLY (2026-11-05)** | 0 (V1 MEDIUM fixed and verified, owner manual PASS — admin path automated; sidebar exposure HIGH fixed — `category-series` pair + `khotabRandomFeatured()` (#94) and the nine remaining Khotab lists: author ×2, video flag ×2, radio, channel ×2, live stream ×2 (#95); «جديد المواد» UI refinement OWNER VERIFIED (#96)) | V2 search, V3 Cat, V4 Satellite admin visibility; V5 OLD visitor exposure intentionally not reproduced (Laravel's safer behaviour preserved) | `decision-log.md` #93, #94, #95, #96, #97 |
| Remaining public widget visibility (Telawah sidebar widgets; homepage widgets and their shared components) | **H1 OWNER CLOSED LOCALLY (2026-11-06)** | 0 (H1 MEDIUM — homepage Khotab videos / audios / PDF widgets — fixed; owner homepage visual PASS, exclusion automated-tested) | T1 Telawah `hidden` (dormant) defensive filter; Anasheed A6 preserved (owner-closed) | `decision-log.md` #98, #99 |
| Homepage slider (OLD `status = 1 AND website = 0`; Laravel `status = 1 AND website = 1`) | **UNCLASSIFIED FOLLOW-UP — not investigated** | unknown — not a confirmed regression | — | `decision-log.md` #98 |
| Homepage slider (#98 follow-up) — **[2026-11-07 update of the row above, history kept]** | **FUNCTIONALLY EQUIVALENT TO GEN 2 — CLOSED LOCALLY (2026-11-07)** — Gen 2 `slider.php:3` `status = 1 AND website = 1`, `order_index`, LIMIT 10, matched by Laravel; OLD `website = 0` / LIMIT 8 is an OLD-to-Gen 2 change; presentational differences only | none — not a regression | 0 | `decision-log.md` #124 |
| Cat / Satellite legacy routes and visitor parity (`series_list-*`, `var-channel*`, `channel(s)-ramadan*`, `category-group-*`; Laravel pages follow Gen 2 `categories/` / `channels/`) | **UNCLASSIFIED FOLLOW-UP — not investigated** | unknown | — | `decision-log.md` #93 |
| Handoff §3 items (`cds-series .grx` — source exists, see #91 / IF-044 correction; RSS) | Not yet investigated under the rule — gaps not yet classified | unknown | — | — |

**Visitor-parity baseline (owner, 2026-11-06, `decision-log.md` #102):** PRIMARY Legacy / Gen 2 (`way2allah.com`),
SUPPLEMENTARY OLD (`old.way2allah.com`), TARGET Laravel. OLD-only functionality is neither auto-retired nor
auto-reimplemented. Route reconciliation (2026-11-06): Gen 2 217 rules / 208 patterns, 86 without a Laravel route;
OLD 233 / 213, 102 without. Implemented: `khotab-item-{id}-page-{n}.htm` → 301 `?page={n}` (awaiting owner
verification). Rows added:

| Item | Status | HIGH / MEDIUM open | Record |
|---|---|---|---|
| Khotab comment-page URL `khotab-item-{id}-page-{n}.htm` | **IMPLEMENTED LOCALLY — OWNER VERIFIED (2026-11-06)** | 0 | #102, #103 |
| OLD-only visitor functionality (Cat / Satellite routes, Feedback, FAQ, RSS, flashxml, Locations group / series, help, hedaya_table, home-file, questionnaire, per-author dumps, Khotab play / thumbs / banner, recite-play, fatawa play / brokenlink / friend, album download, nashr-hedayah, W2ACD comments / friend, stats, noor, alQuran) | **UNRESOLVED — evidence or owner decision per item** **[Update 2026-11-08: every family listed here is now owner-dispositioned — see the family rows below (#107–#132); `stats`, `noor`, `alQuran` closed locally under #130–#132 as historical URL compatibility only. History kept.]** | unknown | #102 |
| Business Confirmation #13 inventory (disposition per Gen 2 pattern without a Laravel route) | **CLARIFIED (owner, 2026-11-06) — 82 patterns accounted for**: 59 LOCAL_GEN2_HANDLER_MISSING, 5 PRODUCTION_404_VERIFIED, 4 live URLs missing, 4 functionally replaced, 8 retired, 1 deferred, 1 covered; open items below | 0 confirmed | #102, #103, handoff §4 #13 |
| Three query-gated `modules.php` 301s; three radio operation URLs; four replaced form / AJAX URLs | **DISPOSITIONED (owner, 2026-11-06)** — two `modules.php` 301s and radio GET → `radio.htm` IMPLEMENTED AND TESTED LOCALLY (awaiting owner verification) **[2026-11-08, #133: OWNER-ACCEPTED ON AUTOMATED EVIDENCE]**; `w2acd DownSeries` stays deferred LOW; four form / AJAX URLs: functional replacement, no compatibility routes | 0 | #103, #104 |
| Anasheed item comment script (`public/scripts/anasheed_scripts.js` → unrouted `add-anasheed-comment-{id}.htm`, no CSRF token) | **FIXED — OWNER VERIFIED LOCALLY — CLOSED (2026-11-06)** (`var-item-9877.htm`, three scenarios PASS) | 0 | #104, #105 |
| Anasheed send-to-friend script (no CSRF token → 419) | **FIXED — OWNER VERIFIED LOCALLY — CLOSED (2026-11-06)** for the visitor-facing form workflow (local `log` mailer — email delivery and production mail configuration not verified) | 0 | #105, #106 |
| Send-to-friend pre-production concerns: no rate limiting / anti-abuse; visitor-controlled From address; unhandled mail failure | **UNRESOLVED — pre-production** (not deferred, not retired) **[2026-11-08, #133: applies to all three endpoints — `/khotab-item-{id}/send-friend`, `send-friend-anasheed-{id}.htm`, `fatawa-friend-sendemail-{id}.htm` (no throttle); production blocker, not a visitor-parity gap.]** **[2026-11-08: IMPLEMENTED AND TESTED LOCALLY — PRODUCTION MAIL VERIFICATION PENDING — shared limits (3 / IP / rolling 10 min, 10 / recipient / rolling 24 h, 429), 100 / 254 length limits, site From + visitor Reply-To, APP_URL mail links (these three mails), controlled `-1` / 503 on transport failure; production: shared-lock cache store, APP_URL, trusted proxies, mail transport + SPF / DKIM / DMARC, smoke test. Detail: decision-log #106 note.]** | unknown | #106 |
| Radio page script POSTs (`public/scripts/w2a_radio.js`) — 405 only for hand-made requests | **OWNER-APPROVED NO CHANGE — CLOSED LOCALLY (2026-11-06)** — triggers not rendered, `save-last-listen` never runs (as on Gen 2); source-evidence closure; radio GET compatibility IMPLEMENTED AND TESTED LOCALLY | 0 | #104 |
| Gallery full-album ZIP (`download-album-{id}.htm`, OLD-only family) | **OWNER VERIFIED LOCALLY — CLOSED (2026-11-06)** — album 1 ZIP downloaded with the expected images, no admin login needed; not production-verified | 0 | #107 |
| Feedback family (OLD-only: `feedback.htm`, `continue-feedback-{id}.htm`, `confirm-feedback.htm`, `communication.htm`, `list_cities.htm`) | **OWNER-APPROVED RETIRED OLD-ONLY WORKFLOWS — GEN 2 CONTACT PARITY SATISFIED (Facebook Messenger) — CLOSED LOCALLY (2026-11-06)**; tables preserved; URLs not implemented (reopen only on evidence of active use) | 0 | #108 |
| Category Group (`category-group-{g}-{c}.htm`, OLD-only family) | **FUNCTIONALLY COVERED — NO APPLICATION CHANGE REQUIRED — CLOSED LOCALLY (2026-11-06)** — content via `category-{id}.htm` / `khotab-group-{id}.htm`; no redirect (reconsider only on evidence of active use) | 0 | #109 |
| Satellite Ramadan (`channels-ramadan.htm`, `channel-ramadan-{id}.htm`, OLD-only family) | **FUNCTIONALLY COVERED — NO APPLICATION CHANGE REQUIRED — CLOSED LOCALLY (2026-11-06)** — OLD 2012-only snapshot; content via channel / series / author pages, `/ramadan.htm` (1434–1447) as Gen 2; LOW; no redirect (reconsider only on evidence of active use) | 0 | #110 |
| Series List (`series_list-{id}-{col}-{sort}[-{author}].htm`, OLD-only family) | **FUNCTIONALLY COVERED — NO APPLICATION CHANGE REQUIRED — CLOSED LOCALLY (2026-11-07)** — same `lastupdate DESC` order and content as Gen 2; Gen 2 DataTables likely fails (no `<thead>`, source inference); LOW; no redirect | 0 | #111 |
| Channel series page length (Laravel card grid vs Gen 2 400 px scroll box; up to 968 series per channel) | **UNVERIFIED UX OBSERVATION** — not a functional parity gap; no change | — | #111 |
| Var Channel (`var-channel.htm`, `var-channel-{id}.htm`, OLD-only family) | **FUNCTIONALLY COVERED — NO APPLICATION CHANGE REQUIRED — CLOSED LOCALLY (2026-11-07)** — OLD Anasheed-by-channel view absent in Gen 2; all channel-tagged Anasheed content reachable via existing pages; LOW; no redirect | 0 | #112 |
| Gen 2 Anasheed category listing channel link → Khotab `channel-{channel}-{category}.htm` | **UNVERIFIED OBSERVATION** — possibly an incorrect historical link; not a Laravel parity gap; not investigated | — | #112 |
| RSS family (15 `.rss` RewriteRules; inventory in #113) | **OWNER-APPROVED RETIRED FROM GEN 2 BASELINE — NO IMPLEMENTATION REQUIRED — CLOSED LOCALLY (2026-11-07)** — visitor parity LOW concern; external usage UNKNOWN; production verification incomplete (5 URLs 404 on 2026-08-12); not "functionally covered" | 0 | #113, IF-045 |
| Locations (`location-{l}.htm`, `-author-`, `-item-`, `alhedaya-room.htm`; `locations.htm` and group / series URLs unbuilt per #39 D3) | **TARGETED DEFECT FIXED AND TESTED LOCALLY — OWNER-APPROVED UI — CLOSED LOCALLY (2026-11-07)** — location-author pages scoped to the requested location; location 10 unchanged; nonexistent location 404; orphan link rows unchanged; shared-component UI approved locally; full suite 2,536 passed / 1 skipped; production verification pending | 0 | #114 |
| Khotab historical media URLs (`khotab-play-{id}`, `play-mirror-{khid}-{id}`, `khotab-playmirror-{khid}-{id}`, `khotab-thumb-{id}`, `khotab-banner.htm`) | **HISTORICAL URL COMPATIBILITY ONLY — OWNER DECISION APPROVED — NO IMPLEMENTATION REQUIRED — CLOSED LOCALLY (2026-11-07)** — playback / mirror playback covered (`/media-player`); OLD-only gallery and random banner not restored; no routes or redirects; production responses and inbound links UNKNOWN; #60 not reopened | 0 | #115 |
| Social-sharing metadata (`og:image` / `twitter:image`: Gen 2 item-specific; none in Laravel templates) | **OPEN — investigation candidate** — outside the #115 family; not implemented; scope (which pages, which values) not yet traced **[Update 2026-11-08 (owner-approved fix): IMPLEMENTED AND TESTED LOCALLY — AWAITING OWNER VERIFICATION — Gen 2 `header.php` tags restored in the shared layout (one partial); Khotab item frame / gif image when the file exists, else `logo-light.png`; absolute URLs on `APP_URL`; full suite 2,555 / 2,554 passed / 1 skipped; deployment check: `APP_URL` must be the public site URL. Detail: IF-056.]** **[Owner verification 2026-11-08: OWNER VERIFIED — CLOSED LOCALLY — `/privacy` default image and `/khotab-item-230000.htm` frame image PASS; other tags automated-tested only; not production-verified; deployment checks: production `APP_URL`, public reachability of social images, media path / `khotab_gifs` availability.]** | 0 | IF-056, #115 |
| Fatwa historical URLs (`fatawa-play-{id}`, `fatawa-download-{id}`, `fatawa-brokenlink-{id}`, `fatawa-friend-{id}`, `fatawa-friend-sendemail-{id}`) | **HISTORICAL URL COMPATIBILITY ONLY, WITH TARGETED PLAYBACK DEFECT FIXED — CLOSED LOCALLY (2026-11-07)** — no routes or redirects; single-fatwa play-button quoting fixed and regression-tested; owner local browser checks passed for supported media; `.avi` unsupported under #20; production verification pending | 0 | #116 |
| Fatwa media-link data quality (10 malformed, 11 empty `media_link`) | **OPEN — data quality** — no database change approved | — | IF-058, #116 |
| Player message «يمكنك تجربة جودة أخرى» for Fatwa (no qualities exist) | **DEFERRED — UX** — no implementation approved | — | IF-057, #116 |
| Telawah historical recite-play (`recite-play-{id}.htm`) | **HISTORICAL URL COMPATIBILITY ONLY, WITH TARGETED MP4 PLAYBACK GAP FIXED — CLOSED LOCALLY (2026-11-07)** — no route or redirect; `fromTelawahItem()` follows `vedio` (9205 / 9207 play, owner local browser checks); #20 unchanged; production verification pending | 0 | #117 |
| W2ACD historical URLs (14 active Gen 2 patterns; `cds-news` #74 and `cds-series .grx` #92 unchanged) | **HISTORICAL URL COMPATIBILITY ONLY — NO IMPLEMENTATION REQUIRED — CLOSED LOCALLY (2026-11-07)** — 5 covered, 3 historical only (`cds-item-…-page-…`, `cds-download`, `cds-mirror`), 4 OLD-only retired (comments, send-to-friend); no routes or redirects; production verification pending | 0 | #118 |
| Historical per-author PDF downloads (`dumped-lectures-{author}.htm`, `authors-dump-files.htm`; covered: `dumped-lectures.htm`, `khotab-pdf[-{author}].htm`, `khotab-item-pdf-{id}.htm`) | **HISTORICAL URL COMPATIBILITY ONLY — NO IMPLEMENTATION REQUIRED — CLOSED LOCALLY (2026-11-07)** — static PDFs, no generation; no routes or redirects; item 269113 file missing locally (media observation); `khotab-pdf-{itemid}` ambiguity inherited from Gen 2; production verification pending | 0 | #119 |
| FAQ (`faq.htm`, `faq-{id_cat}-{categories}.htm`) | **OLD-ONLY, RETIRED BEFORE GEN 2 — HISTORICAL URL COMPATIBILITY ONLY — CLOSED LOCALLY (2026-11-07)** — read-only OLD module, no active OLD / Gen 2 link; not restored, no route or redirect; local tables empty, production contents unknown | 0 | #120 |
| Help (`help-{id}.htm`; `landing_page.htm`) | **`help-{id}.htm` OLD-ONLY, RETIRED BEFORE GEN 2 — HISTORICAL URL COMPATIBILITY ONLY — CLOSED LOCALLY (2026-11-07)** — OLD contextual fancybox «شرح» popups, no Gen 2 handler or active link; not restored, no route or redirect; `nuke_help` (103 local rows, 29 non-empty) preserved; `landing_page.htm` already covered by `AboutController`; About / Share not reopened | 0 | #121 |
| hedaya_table (`more-hedaya.htm`, `update-hedaya-list.htm`, `hedaya_cache_files.htm`) | **ALREADY RESOLVED BY PRIOR OWNER DECISION — CLOSED LOCALLY (2026-11-07)** — weekly live-lesson schedule already retired (Business Confirmation #4, #14, IF-033); two OLD-only maintenance endpoints retired; no routes or redirects; `nuke_hedaya_*` data preserved | 0 | #122 |
| home-file (`home-file-{id}.htm`) | **OLD-ONLY, RETIRED BEFORE GEN 2 — HISTORICAL URL COMPATIBILITY ONLY — CLOSED LOCALLY (2026-11-07)** — OLD homepage-file detail page; Gen 2 generator `w2a_index_files()` commented out; not restored, no route or redirect; `nuke_home_files` / `_mawad` data preserved; slider #98 unaffected | 0 | #123 |
| Homepage slider (#98) | **FUNCTIONALLY EQUIVALENT TO GEN 2 — CLOSED LOCALLY (2026-11-07)** — Laravel matches Gen 2 `slider.php` selection; OLD `website = 0` / LIMIT 8 superseded by Gen 2; no UI, query or test change | 0 | #124 |
| questionnaire (`questionnaire.htm`) | **OLD STAFF HR SURVEY, RETIRED BEFORE GEN 2 — HISTORICAL URL COMPATIBILITY ONLY — CLOSED LOCALLY (2026-11-07)** — no Gen 2 handler or link; not restored, no route, redirect, form, email or write endpoint; `nuke_w2a_questionnaire` (65 local) preserved; separate from `wizard.php` / `estebian` (IF-050) | 0 | #125 |
| login / logout (`login.htm`, `logout.htm`) | **HISTORICAL URL COMPATIBILITY ONLY — CLOSED LOCALLY (2026-11-07)** — historical PHP-Nuke `Your_Account` URLs, no Gen 2 handler or navigation; not restored, no routes or redirects; vBulletin session recognition preserved at source level (not end-to-end verified); `vb5.php` bridge stays OPEN (R25) | 0 | #126 |
| Flash XML (`var-news.xml`, `khotab-news.xml`) | **HISTORICAL URL COMPATIBILITY ONLY — CLOSED LOCALLY (2026-11-08)** — OLD `flashxml` feeds for a Flash player, no Gen 2 handler or consumer; no routes, redirects or XML endpoints; Anasheed content covered by «تشاهدون الآن»; Khotab feed OLD-only retired; not RSS (#113 unchanged) | 0 | #127 |
| nashr-hedayah (`nashr-hedayah.htm`) | **HISTORICAL URL COMPATIBILITY ONLY — CLOSED LOCALLY (2026-11-08)** — OLD client-side Picasa album browser (`gal2`), assets missing, service discontinued, no Gen 2 handler or link; no route, redirect or Picasa integration; separate from Gallery ZIP (#107, closed); legacy helper findings tracked as R26 (OPEN) | 0 | #128 |
| live-channel-admin (`live-channel-admin.htm`) | **HISTORICAL URL COMPATIBILITY ONLY — CLOSED LOCALLY (2026-11-08)** — not an admin endpoint: the public OLD live-TV page (`modules/live`, `nuke_live`) with a commented-out `isadmin` preview flag; Gen 2 / Laravel live-stream pages unchanged; no route, redirect or replacement. All currently identified historical URL families now have an owner disposition **[clarification 2026-11-08: premature — `stats`, `noor`, `alQuran` (#102) were still open; closed under #130–#132]** — visitor parity still OPEN | 0 | #129 |
| stats (`stats.htm`, `stats-{n}.htm`) | **HISTORICAL URL COMPATIBILITY ONLY — CLOSED LOCALLY (2026-11-08)** — OLD read-only statistics page (`modules/stats`, Google Charts of section item counts; `stats-2` placeholder values); no writes, forms or email; no OLD / Gen 2 link; locally inactive module; Gen 2 `stats/` is an unrelated vendor bundle; no route, redirect or replacement | 0 | #130 |
| noor (`noor.htm`) | **HISTORICAL URL COMPATIBILITY ONLY — CLOSED LOCALLY (2026-11-08)** — OLD «نور البيان» release templates (`modules/noor/tmp/`, missing locally — production unknown); OLD theme link, commented out in Gen 2; Laravel navigation follows Gen 2; no route, redirect or replacement | 0 | #131 |
| alQuran (`alQuran.htm`) | **HISTORICAL URL COMPATIBILITY ONLY — CLOSED LOCALLY (2026-11-08)** — OLD third-party Quran reader (Mushaf, search, index, audio, Asbab al-Nuzul, Murattal) on the `quran` table; OLD theme link, commented out in Gen 2; not rebuilt (owner); the Quran memorization application page is unrelated. OLD-only security concerns tracked as R26-b, cutover check R13 (6). All currently identified historical URL families now have an owner disposition — visitor parity still OPEN | 0 | #132 |
| Channel author «اخترنا لك هذه المادة» (`channel-{id}-{author}.htm`; Gen 2 `channels/author.php:75-82` `randomitems()`) — LOW gap found by the final visitor-parity evidence review (2026-11-08) | **IMPLEMENTED AND TESTED LOCALLY — AWAITING OWNER VERIFICATION (2026-11-08)** — shared `khotabRandomFeatured()` + `<x-content.featured-items>`; hidden items excluded (#94); IF-012 empty boxes and the channel page unchanged; full suite 2,562 / 2,561 passed / 1 skipped **[Update 2026-11-08 — CHANNEL AUTHOR UI & NAVIGATION: IMPLEMENTED AND TESTED LOCALLY — AWAITING OWNER VERIFICATION. Owner rejected the layout; root cause: `channels/author.blade.php` lacked Gen 2's two-column structure. Five owner-approved decisions applied (full-width item rows; empty IF-012 portlets kept; Gen 2 heading «مرئيات …» + three-level breadcrumb; stretched-link author links — series → `channel-{c}-{a}.htm`, group → `khotab-video-{a}.htm` — no nested anchors, grid default markup byte-identical; two-column author-page grid). Empty sections omitted on both channel pages (Gen 2). Full suite 2,576 / 2,575 passed / 1 skipped; PHPStan 0; Pint PASS. Owner check: `http://127.0.0.1:8123/channel-3-2.htm` (desktop and a narrow window) and `/channel-3.htm` (author links on cards). Detail: IF-012 notes.]** **[Owner verification 2026-11-08: OWNER VERIFIED — CLOSED LOCALLY (#133).]** | 0 HIGH / MEDIUM | IF-012 note |
| Live Stream (`/live-stream.htm`, `/live-channel-{id}.htm`; `/live-stream/featured` = Gen 2 `live.php`) | **OWNER VERIFIED — CLOSED LOCALLY (2026-11-08)** for the two pages; `/live-stream/featured` automated tests only; historical `live.php` purpose — non-blocking note | 0 | #133 |
| #104 A / C, Gallery, Telawah, Channels, Radio, Homepage | **OWNER-ACCEPTED ON AUTOMATED EVIDENCE (2026-11-08)** — closure dispositions, not new manual tests | 0 | #133 |
| **VISITOR PARITY (whole track)** | **CLOSED LOCALLY — OWNER-ACCEPTED (2026-11-08)** — 0 open HIGH / MEDIUM; LOW items fixed, accepted or owner-deferred; all identified historical URL families dispositioned (#104, #107–#132); IF-058, #111 and #112 observations recorded as non-blocking. **Production deployment NOT approved** — #106 and R1, R8–R14, R21, R25, R26 (a / b) and production media / configuration remain OPEN | 0 | #133 |
| Pre-deployment (gallery ZIP): production PHP `ext-zip`; writable `storage/app/private/gallery-zips` (≈154 MB full cache); production media path compatibility (images inside `public/` or the resolved `public/media` target) | **OPEN — deployment prerequisite** | — | #107 |
| `modules.php` redirect (Gen 2 `.htaccess:188` 301 vs production 404 in #44) | **Conflict resolved (#103)** — query-gated 301s; #44 tested other query strings; disposition tracked in the row above | — | #102, #103, #44 |
| Documentation inconsistencies (IF-026 count / premise, IF-045, IF-053, handoff §3 / §8, `routes/engagement.php` comments, coverage review basis) | **RECONCILED (#103)** except `routes/engagement.php:15, :33` code comments (application file — needs approval) | — | #102, #103 |

The Cat / Satellite and handoff §3 rows above are superseded in framing (not in history) by these rows; the homepage

**[Note 2026-11-08, #133: VISITOR PARITY — CLOSED LOCALLY — OWNER-ACCEPTED. The "not declared complete" / UNCLASSIFIED / "not yet investigated" statements in this section are superseded by the dated rows above; production items stay OPEN.]**
slider row stays UNCLASSIFIED. Visitor parity is not declared complete. **[Note 2026-11-07, #124: the slider row is now classified — FUNCTIONALLY EQUIVALENT TO GEN 2, closed locally.]**

Admin hidden-content visibility closure (2026-11-05, `decision-log.md` #97): documentation-only owner closure of the
investigated scope; not a sitewide security audit, not production verification, not whole-migration closure. R1 deferred;
no push / deploy.

- **Status: WAY2ALLAH WHOLE-MIGRATION CLOSURE REVIEW — AUDIT COMPLETE — AWAITING OWNER REVIEW.** Nothing implemented. No deployment. No commit.

**Register update — R2 FULL QUALITY GATE — COMPLETE — OWNER APPROVED (2026-11-03; `decision-log.md` #84).** The gate
first failed on `DomainBoundaryTest` (Content → Admin; never run by `composer test` / CI because `phpunit.xml` lacked the
Architecture suite) and on the repository-wide Pint baseline (41 files, all failing at HEAD). Resolved without weakening
either: the `App\Support\Http\AdminViewer` port (aliased to the per-request `ContextualAdmin`); Backup API → Admin;
`SiteOption` → `App\Support\Settings`; `Location` → `Content\Models`; the Architecture suite added to `phpunit.xml`;
an owner-approved formatting-only Pint pass on exactly those 41 files (+171 / −155, identical to the approved preview).
Final evidence: full normal suite 2,303 tests — 2,302 passed, 0 failed, 1 skipped, 17,661 assertions (Architecture
included); Architecture 5 / 5; PHPStan level 5: 0 errors; `vendor/bin/pint --test` PASS. R2 also supplies DoD 10's
evidence; recording that in §7.1 remains R3.

Reconciled after R2 (no other row changed; nothing else marked complete):

| Class | Rows | Blocks local closure | Blocks deployment |
|---|---|---|---|
| COMPLETE_EVIDENCED | R2, R23 | — | — |
| OPEN_OWNER_DECISION | R1, R9, R10, R14 (+ R22 optional) | R1 | R1, R9, R10, R14 |
| OPEN_IMPLEMENTATION (docs) | R3, R4, R5, R6, R7 | all five | none |
| OPEN_MANUAL_REVIEW | — | — | — |
| OPEN_PRODUCTION_EVIDENCE | R16, R20 | none | none (OLD retirement) |
| PRE_DEPLOYMENT_ONLY | R8, R11, R12, R13, R15, R17, R18, R19, R21 | none | R8, R11, R12, R13 |
| SUPERSEDED | R24 | — | — |

Local-closure blockers: **6** (R1 owner decision; R3–R7 documentation). Deployment blockers: **8** (R1, R8, R9, R10, R11,
R12, R13, R14). R11's package rebuild now waits only on R1.

**Register update (2026-11-06, decision-log #100).** R3–R7 → COMPLETE_EVIDENCED (documentation reconciliation: §7.1
refresh, banner / §30 notes, handoff §7 / §8 marked, #82 reference note, §55 checklist; §28.1 marked superseded).
Admin Controls: local functional closure accepted by the owner, one disclosed AdminCP item pending disposition (#41
backup sidebar group). Local-closure blockers now: **1** (R1, owner decision, deferred). Deployment blockers unchanged:
**8** (R1, R8, R9, R10, R11, R12, R13, R14); production-evidence and after-deployment rows (R15, R16, R17–R21) unchanged
and OPEN. No production item is marked complete.

---

## 55. CONSOLIDATED OLD / GEN 2 RETIREMENT CHECKLIST (R7, DoD 5a) — compiled 2026-11-06

Compiled from existing records only (decision-log #100); no new investigation. Every item is OPEN and is done at
cutover / OLD retirement, never before Laravel serves the replacement in production. Nothing here is a missing Laravel
function.

| # | Item | Action at cutover / retirement | Source |
|---|---|---|---|
| X1 | `JoinMe` bulk re-parent; `BackUp/admin:1690` mirror insert (out of scope, legacy residual risk) | Establish unused or replaced (production evidence, R16) before OLD retires | §2 row 24, §7 DoD 5a |
| X2 | OLD Cat admin category-assignment UI (writes a non-canonical format) | Retire it, or restore its canonical writer | §20.1, DoD 5b, R17 |
| X3 | Gen 2 inline admin toolbars — e.g. `7amlat/khotab/functions.php:296` «طلب رئيسية» via `$inlineAdminUrl`; 8 Gen 2 files gate on `is_w2a_admin` | Stop rendering when Gen 2's public pages are replaced or retired | §7 DoD 6, #83, R18 |
| X4 | `nuke_*_mod`, `GreenZone()`, `AvAdmin()` (Laravel side confirmed unreferenced) | Archive or document the tables once nothing live references them | §7 DoD 9, R19 |
| X5 | OLD poll admin screens (`modules/Surveys/admin` create / edit / remove; `RemovePollComment` link) and Gen 2 poll links (`7amlat/surveys/functions.php:132` `polledit`, `:263` `create` / `polledit`) | Retire OLD screens; retire or reroute Gen 2 links to the Laravel AdminCP | §23 cutover notes |
| X6 | W2ACD media: OLD `images/` vs Laravel `public_html/images` identity during coexistence | Confirm on production before switching | §25.10, R20 |
| X7 | OLD module admin handlers (`admin.php?op=…`) and public admin controls for Khotab, Anasheed, Telawah, Fatwa, W2ACD — replaced by Laravel contextual controls | Retire with the OLD public pages | §31–§53, #60–#78 |
| X8 | Dead / unreachable OLD admin code with no Laravel replacement needed: `search` / `advanced_search` series icon, `w2a_cd`, Locations, BannersRequest, `modules/index.php`; copy files `Anasheed/_index.php`, `Khotab/_index.php`, `Fatwa/Before_shams_index.php`, `Fatwa/index_strange.php` | Retire with OLD | #72, #80, #82, #67 |
| X9 | Gen 2 / OLD `admincp/` (replaced by the Laravel AdminCP), including the `admincp/backup/` sidebar group «النسخ الإحتياطي» — its add-admin, permission-edit and `backup.*` permission functions are covered; its read-only Backup Booking Monitor is OWNER-APPROVED DEFERRED LOW (not implemented) | Retire at cutover; the monitor's absence is a known LOW difference, not a blocker | #22–#42, #41, #101 |
| X10 | `admin:sync-permissions` | Manual legacy-bootstrap tool only — never a deployment step | §27 |
| X11 | Root `backup.php` external API (active contract, Confirmation #7) — Laravel keeps the same `POST /backup.php` route and behaviour | Route the endpoint to Laravel at cutover without changing the contract | #33, #84 |

Visitor-parity follow-ups (Cat / Satellite legacy routes, RSS, slider, T1) are not retirement items.

---

## 56. ADMIN CONTROLS — FUNCTIONALLY COMPLETE LOCALLY (2026-11-06) — WITH OWNER-APPROVED LOW DEFERRALS

Explicit owner approval, decision-log #101. Documentation only; no production access.

| Area | Status | Record |
|---|---|---|
| AdminCP core (login, dashboard, permissions, staff, survey, SoundCloud, YouTube, questionnaire, uploaders, link quality, broadcasting, locations; chat removed) | Functionally complete | #22–#42, #38, #39 |
| Backend administration Phases 0–7 | Implemented, locally verified, owner-accepted 2026-10-15; production verification pending (DoD 2 / 3) | #59, §30, §7.1 |
| OLD public admin controls | Dispositioned module by module, all owner closed | #60–#82, §31–§53 |
| AdminCP backup sidebar group «النسخ الإحتياطي» (`7amlat/admincp/backup/`) | Add admin, permission edit, six `backup.*` permissions — COVERED; Backup Booking Monitor — OWNER-APPROVED DEFERRED LOW | #41, #100 (corrected), #101 |
| Root `backup.php` machine API (separate from the sidebar group) | Ported — `POST /backup.php` → `BackupApiController` | #33, #84 |
| Super-admin sidebar | Laravel behaviour approved; operations keep their authorization | #100 |
| AdminCP visual parity | SUBJECT_TO_OWNER_REVIEW | #42 |

**Known HIGH / MEDIUM Admin functional gaps: 0.** **Owner-approved LOW deferrals:** Backup Booking Monitor (#101 —
legacy route, query and output preserved there for future work); admin visibility of hidden content on search / Cat /
Satellite, V2–V4 (#93, #97). Owner retirements and intentional non-migrations stand (§28.1, #59). Not every legacy
feature is implemented.

**Not claimed:** production readiness, production verification or deployment, OLD Admin retirement, whole-migration
completion. Production / cutover work: §54 register (R1 deferred) and §55 checklist — all OPEN.
