# Administrator Authorization & Contextual Controls — Audit (2026-11-21)

**Scope:** the Laravel administrator authorization system end to end — permission definitions, the permission editor,
route middleware, controller / action checks, the Khotab audio/video scope, and every contextual control rendered on
public pages. **Audit only:** no application code, permission, configuration or production data was changed.

**Evidence (this folder):**

| File | Content |
|---|---|
| `evidence/admin-permission-inventory.csv` | all 86 permissions: module, editor label, routes (with controller@action), public controls, extra conditions, verification |
| `evidence/admin-public-control-matrix.csv` | all 116 public contextual controls: builder, label, page type, target route, requirement, extra conditions, expected visibility, observed execution |
| `evidence/admin-authorization-test-matrix.csv` | 132 executed scenarios: grant → render → execute → revoke → hide → deny |

---

## 1. Executive summary

| | |
|---|---|
| Permissions defined (seeded, grantable in the editor) | **86** |
| Permissions required by at least one route | 68 (all 68 are seeded — none ungrantable) |
| Admin routes reviewed | **324** |
| In-code authorization calls cross-checked against their routes | 169 (no mismatch) |
| Public contextual controls discovered | **116** (35 builder methods, 25 public page types; 35 sample pages) |
| Controls verified end to end | **112** (4 UNVERIFIED, §6) |
| Grants / revokes through the editor stored exactly | 106 / 106 |
| Permission scenarios executed end to end | 53 public-control permissions × 2 admin types + 15 AdminCP-only permissions (27 routes) + 4 role types + 6 escalation probes |
| Control executions | 786: **729 authorized**, **57 visible-but-denied (403)** |
| Controls shown without the permission | **0** |
| Controls still shown / still reachable after revoke | **0 / 0** |

**Confirmed defects:** CRITICAL 0 · HIGH 0 · **MEDIUM 1** (AUTH-01) · **LOW 2** (AUTH-02, AUTH-03).

**Safe to continue operating:** yes. No path lets an administrator see or perform an action they are not permitted to;
every revoke takes effect on the next request; escalation is refused. AUTH-01 is a usability defect (a control that
leads to "403"), not an authorization bypass.

**Fix required before closing Administrator Management:** AUTH-01 should be fixed (it is the visible symptom
moderators will meet once Khotab moderators are granted section-scoped keys); AUTH-02 / AUTH-03 are recommended.

---

## 2. Method

All dynamic checks ran against a **disposable** copy of the database (`am_disp`, copied from the local `olddb`) and a
scratch copy of the permission store — never against shared or production data. Rendering public pages there
increments `hits` / `lastvisit` only in the copy (the earlier side effect on local data is not repeated).

1. **Inventory** (booted application): every route's full middleware, every seeded permission, every contextual
   control builder (label → route) and the public views (page types) that render it.
2. **Static consistency:** each controller method's injected actions and in-method checks vs. the route's own
   permission (169 calls; 12 candidates, all display-only branches — e.g. `canDelete` flags — no mismatch).
3. **End to end, per permission** (53 permissions with a public control):
   - **Grant** through the real editor (`PUT /admincp/permissions/{id}` as a super-admin) and read back what was stored.
   - **Render** every public page type where that permission has a control (real sample records, two per page type
     where audio/video matters), as:
     - a **standalone** administrator (`uid = 0`, created through the real add-admin form) holding *only* that permission;
     - a **forum-linked** administrator (`uid > 0`) holding that permission plus both Khotab media keys.
   - **Execute** every visible control through its route's complete middleware stack (session, CSRF, route-model
     binding, `admin.permission` / `admin.role`, `admin.khotab-media`) without running the controller; safe page links
     were also fully rendered (HTTP 200).
   - **Revoke** through the editor, re-render (control must be gone) and replay each captured control (must be 403).
   - No test administrator was given the `admin` role or media keys unless the scenario required them.
4. **AdminCP-only permissions** (15): grant → each parameterless route reachable → revoke → 403.
5. **Roles:** visitor, super-admin / owner (#3), administrator with no permissions, deactivated administrator
   (`admin_deactivation.enabled = true`), on all 35 sampled pages.
6. **Escalation probes:** a non-super administrator holding the staff permissions tries to change permissions,
   promote itself, create a super-admin, grant permissions at creation, and set its own password.

---

## 3. Authorization chain (verified)

| Step | Where | Result |
|---|---|---|
| Admin login | `AdminGuard` (`aid` + password against `nuke_authors`) | forum-independent; standalone (`uid = 0`) and linked admins behave identically in every scenario |
| Session on public pages | same Laravel app, `web` group, `admin_auth_id` | AdminCP and public pages share the session; the owner's manual check agrees |
| Roles / permissions | Spatie, `config('database.default')` (`App\Support\Permission\Permission` / `Role`) | grants written by the editor are read on the next request |
| Editor persistence | `PermissionController::update` → `syncPermissions` (replaces the set) | every grant stored exactly; every revoke empties the set (106/106) |
| Cache | Spatie caches permission *definitions* only; assignments are loaded per request | grant and revoke effective on the very next request |
| Route authorization | `EnsureAdminHasPermission` / `EnsureAdminHasRole` (super-admin passes permission checks) | consistent with the 169 action-level checks |
| Record scope | `EnsureKhotabMediaScope` + `KhotabMediaScopeConstraint` (Khotab only — the only registered constraint) | enforced; **not reflected in visibility** → AUTH-01 |
| Visibility | `ContextualAdmin::visible()` → `AdminRouteAccess::allows()` | mirrors `admin.permission` / `admin.role` exactly; ignores every other middleware |
| Rendering | `<x-admin.contextual-controls>` inside `@contextualAdmin` | visitors build nothing; pages with controls are sent `no-store, private` |
| Deactivated admin | `AdminDeactivation` in the guard (switch on) | session rejected: 0 controls, AdminCP 401 |

**Role matrix (35 pages):** visitor 0 controls, AdminCP 401 · super-admin/owner 497 controls, AdminCP 200 · admin without
permissions 12 controls (only the any-admin controls below), `/admincp/staff` 403 · deactivated 0 controls, 401.

**Escalation (non-super admin with `authors.addstuff` + `authors.liststuff`):** change own / another admin's permissions
403 · promote self 403 · create a super-admin → refused («إضافة مشرف عام متاحة لحسابات الملاك فقط») · grant permissions at
creation → refused · set own password 403. Afterwards: own permissions unchanged, no role, no account created.

**Intentional any-admin items (OLD parity, not defects):** «طلب رئيسية» (`admin.advfiles.request`), «مواد لم تصنف»
(Khotab author / group / series), the read-only «إحصائيات متطورة» block on item pages, and admin visibility of hidden
Khotab content on author pages. Poll-comment «مسح» is super-admin-only by route (`admin.polls.comments.destroy`).

---

## 4. Permission inventory (summary — full list in the CSV)

| Category | Count | Permissions |
|---|---|---|
| Public control + AdminCP routes | 53 | Anasheed 13, Khotab 27, Telawah 8, `fatwa.manage`, `polls.managepolls`, `mobile.managedownloads`, `w2acd.managecds` (see matrix) |
| AdminCP only (no public control by design) | 15 | `survey.modsurvey`, `soundcloud.update_soundcloud`, `youtube.update_youtube`, `locations.add_location`, `locations.del_location`, `questionnaire.listallquest`, `questionnaire.listquest`, `broadcasting.editstream`, `khotab.uploaders`, `anasheed.delcomment`, `ads.manageads`, `banners_ads.managebanners`, `report_ads.managereports`, `authors.liststuff`, `authors.addstuff` |
| Scope modifiers | 2 | `khotab.media_audio`, `khotab.media_video` — no route of their own; consulted by `KhotabMediaScopeConstraint` |
| **Inert** (grantable, grant nothing) | 16 | AUTH-02 |

Super-admin-only actions (role routes, no permission key): permission editor, staff profile / rename / promote / demote /
deactivate / reactivate, poll-comment delete.

---

## 5. Permission-to-control mapping (summary — all 116 controls in the CSV)

Controls are page-specific: a permission with N keys rarely shows N buttons on one page. Examples verified:

| Module | Permission | Page type | Control | Target route |
|---|---|---|---|---|
| Khotab | `khotab.addauthor` | main listing | «أضـــف شـــيـــخ» | `admin.khotab.main.authors.create` |
| Khotab | `khotab.editauthor` | main listing / author | «إصلاح العدادات» / «تعديل بيانات الداعية», «صيانة العدادات» | `…main.repair-counts` / `…authors.edit`, `…authors.repair-counts` |
| Khotab | `khotab.approvecomment` or `khotab.delcomment` | main listing / item | «تعليقات تنتظر النشر (N)» / «(تعديل التعليق)» | `…main.comments` / `…comments.edit` |
| Khotab | `khotab.editgroup` | group | «تعديل المجموعة» | `admin.khotab.groups.edit` (+ media scope) |
| Khotab | `khotab.edititem` | item | «تعديل», «صورة الغلاف», «سحب الصور» | `…items.edit`, `…items.cover`, `…items.images` (+ media scope) |
| Khotab | `khotab.editmirror` | item (mirror cards) | «تعديل الوصلة», «اختيار للنقل» | `admin.mirrors.khotab.edit`, `…pick` (+ media scope) |
| Khotab | `khotab.addadv` | promotional file page (`/khotab-advfile-{id}.htm`) | «تعديل الملف», «مسح الملف» | `admin.advfiles.edit`, `…unpublish` |
| Anasheed | `anasheed.addcomment` | item (with comments) | «(تعديل التعليق)», «إضافة التعليق» | `admin.anasheed.comments.edit`, `…items.comments.store` |
| Telawah | `telawah.repair` | item | «حالة الوصلة» | `admin.link-quality.telawah.item` |
| W2ACD | `w2acd.managecds` | main / group / item | 10 controls (sections, CD, banner) | `admin.cds.*` |
| Fatwa | `fatwa.manage` | general question / topic / category | question, fatwa, topic and category controls | `admin.fatwa.*` |

Record-dependent controls appear only when the record qualifies (comments exist, a docx / PDF / banner exists, a file is
published, a mirror is picked, a question belongs to several topics). Not a defect.

---

## 6. Findings

### AUTH-01 — MEDIUM — Khotab controls are shown to moderators the media scope then refuses (403)

| | |
|---|---|
| Module | Khotab (items, series, groups, mirrors) |
| Permissions affected | the 12 section-scoped keys: `khotab.additem`, `edititem`, `moveitem`, `delitem`, `addseries`, `editseries`, `delseries`, `addgroup`, `editgroup`, `delgroup`, `editmirror`, `delmirror` |
| Evidence | `app/Domain/Admin/Contextual/AdminRouteAccess.php:32` (`allows()` evaluates only `admin.role` / `admin.permission`) · `app/Domain/Admin/Contextual/ContextualAdmin.php:65-80` (`visible()` relies on it) · `app/Domain/Admin/Http/Middleware/EnsureKhotabMediaScope.php:25-55` (refuses on the record's section) · `app/Domain/Admin/Support/Constraints/KhotabMediaScopeConstraint.php:33,45` |
| Reproduction | grant `khotab.editgroup` alone (no `khotab.media_*`) in the editor → open `/khotab-group-22.htm` → «تعديل المجموعة» is shown → click → **403**. With `khotab.media_video` (video group) the same control opens the edit page (200). |
| Measured | 57 visible-but-403 control instances across all 12 keys, audio and video pages; 0 with the matching media key. |
| Expected | a control is shown only when its action would be allowed — including the record's audio/video section. |
| Actual | visibility ignores the section; the server correctly refuses. Not a bypass. |
| Root cause | the visibility mirror (`AdminRouteAccess`) was written before the media scope existed (2026-11-17) and was not extended; the test harness masks it (AUTH-03). |
| Minimum safe fix | in `ContextualAdmin::visible()`, for a control whose target route's only permission is one of `KhotabMediaScopeConstraint::SCOPED`, also require `AdminRecordAuthorizer::allows($admin, $permission, $record)`, resolving the record from the control's route parameters exactly as `EnsureKhotabMediaScope` does (`item` / `group` / `series` / `mirror` model, else `media` → `KhotabMediaTarget`). Extract that resolution into one shared helper used by both, so they cannot drift. No permission names, policies or server-side checks change. |
| Regression tests | moderator with `khotab.edititem` only: item control hidden on audio and video items; + `media_audio`: shown on audio, hidden on video; + both: shown on both; super-admin unaffected; same for a group, series and mirror control. |
| Production impact | code deployment (1–2 files). No database, configuration or cache change. Related deployment-state risk (already documented): until `AdminPermissionSeeder` has run, the same actions answer **500** (`PermissionDoesNotExist`) instead of 403. |
| Related, UNVERIFIED | AdminCP-internal Khotab list pages (e.g. a series' item list, the mirrors index) probably show the same kind of links without the section check; not exercised in this audit. |

### AUTH-02 — LOW — 16 permissions are grantable in the editor but grant nothing

| Permission(s) | Why inert |
|---|---|
| `survey.modquestion`, `survey.modanalysis` | every survey route requires `survey.modsurvey`; Gen 2 also only ever checked `modsurvey` (`admincp/survey/add_survey.php:173`; the other two are menu labels in `menu.php`) |
| `chat.listrooms`, `chat.editroom`, `chat.deleteroom`, `chat.listroom` | chat-room administration retired (owner decision 2026-08-23, `routes/admin.php:165-173`) |
| `authors.editstuff`, `authors.deletestuff` | admin edits are super-admin role routes; admins are deactivated, never deleted (F02) |
| `questionnaire.deletequest` | no delete route exists |
| `broadcasting.addstream` | no add route exists |
| `backup.allsite`, `backup.backupkhotab`, `…khotabmirror`, `…telawah`, `…anasheed`, `…anasheedmirror` | the Backup API authorizes from the legacy `nuke_authors.permissions` blob and `nuke_modules.admins` (`BackupApiAuthenticator.php:100,119`), not from Spatie |

Impact: an administrator may be "granted" something that does nothing (misleading, not unsafe).
Minimum fix (owner decision): mark them «لا تأثير لها في لوحة التحكم الجديدة» in the editor, or stop seeding them;
for `backup.*`, decide whether the Backup API should honour Spatie grants. Tests: editor shows the marker; nothing else
changes.

### AUTH-03 — LOW — the contextual test harness hides scope and role defects

`tests/Support/ContextualAdminHarness.php:83` gives every test administrator the `admin` role, and `:85` adds both Khotab
media keys unless a test opts out (`tests/Support/KhotabMediaScope.php:16`). Real editor-created moderators have neither,
so the suite could not detect AUTH-01, and no test exercises grant → render → revoke through the editor.
Fix: harness defaults to "permissions only"; add the integration tests in §7.

### Checked — no defect

- All 68 route permissions are seeded and grantable; no route requires an undefined name.
- Route permission vs. action permission: consistent everywhere (169 calls).
- AdminCP menu: 28 entries, each shown exactly to holders of its route's permission.
- No control shown without its permission; none left visible or reachable after revoke.
- Forum membership is not required anywhere in authentication, visibility or authorization.
- Escalation probes all refused.

### UNVERIFIED (not executed end to end)

| Item | Why | Static evidence |
|---|---|---|
| «حذف من موضوع», «حذف الفتوى من على سؤال» (Fatwa) | no local record belongs to several topics / questions | same route rule (`fatwa.manage`) as the 10 Fatwa controls that passed |
| «نقل الوصلة … إلى هذه المادة», «إلغاء اختيار الوصلة» (Khotab mirrors) | need a mirror picked into the session clipboard | `khotab.editmirror` (+ media scope); covered by `tests/Feature/Admin/Contextual/ContextualKhotabItemTest.php` («offers "move the picked mirror here" only when another item's mirror is picked») |
| 24 AdminCP-only routes with record parameters | need specific records | route middleware mapped (inventory CSV) |
| Production | no production access | §8 |

---

## 7. Authorization test matrix (to add as permanent tests with the AUTH-01 fix)

For one representative control per authorization pattern — plain permission (Anasheed, Telawah, W2ACD, Fatwa, polls,
mobile), alternative permissions (`approvecomment` or `delcomment`), gate route («إضافة وصلة»), Khotab scoped
(item / group / series / mirror, audio and video), any-admin, super-admin-only:

1. super-admin grants the key through `PUT /admincp/permissions/{id}`;
2. as the moderator (standalone and forum-linked), `GET` the public page → control present;
3. submit / open the control → authorized (not 401 / 403 / 500);
4. revoke through the editor → control absent;
5. replay the control → 403;
6. Khotab scoped: repeat 2–5 with no media key, `media_audio` only, `media_video` only, both.

No `admin` role and no media keys unless the case needs them. Use in-memory or disposable databases; never real
counters.

---

## 8. Production checklist (owner, after AUTH-01 is deployed)

1. In AdminCP, give a test moderator `khotab.edititem` only → open a video item page: «تعديل» must **not** appear.
2. Add `khotab.media_video` → reload: «تعديل» appears and opens the edit page.
3. Open an audio item: «تعديل» must not appear (no `media_audio`).
4. Remove `khotab.edititem` → reload: the control disappears; opening `/admincp/khotab/items/{id}/edit` directly gives 403.
5. Repeat 1–4 for a standalone administrator (no forum account) — identical behaviour.
6. A super-admin still sees every control.

Production configuration items that can change visibility (read-only to check, no change implied):
`SESSION_DOMAIN` (the admin must be signed in on the same host as the public page — already confirmed by the owner);
`database.default` (permissions are read from the default connection — AdminCP and public pages are the same app);
`php artisan permission:cache-reset` only if permission *definitions* were added without it. Route and view caches do
not affect visibility (`AdminRouteAccess` reads the live route collection).
