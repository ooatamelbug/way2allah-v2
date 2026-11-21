# AdminCP B6a: advertising administration (F01, `ads` module)

**Date:** 2026-11-14.

**Approval:** owner, "B1 Closure and B6a Implementation" (B6a only).

**Plan:** `docs/reports/admincp-feedback-implementation-plan.md` §F01 (parity matrix A1–A11).

**Status:** **CLOSED LOCALLY (2026-11-14).** Owner-accepted after the security review in §3.1.

**Not deployed and not production-verified.** The deployment prerequisites in §6 remain open.

- Not committed, not pushed, not deployed. No production access.
- No database schema change. No data migration. The local MySQL copy was read only.
- **No public advertising.** Nothing renders, counts views or counts clicks, and no rendering switch was added.
- **Not started:** B6b (`banners_ads`, `report_ads`) and B6c (public rendering, popup, banner strip, click and view
  tracking). Also untouched: B2–B5 and B7b.
- `7amlat` and `old-project` are unmodified. Existing uncommitted work is preserved.

---

## 1. Changed files

### New

| File | Role |
|---|---|
| `app/Domain/Admin/Http/Controllers/AdAdminController.php` | list and filters, create, edit, update, delete, show/hide, reports |
| `app/Domain/Admin/Http/Requests/Ads/AdRequest.php` | FormRequest for create and edit (OLD fields, server-side validation, upload checks) |
| `app/Domain/Admin/Actions/Ads/AdRecord.php` | OLD column semantics for create/edit, the super-admin rule for code ads, and audit-safe snapshots |
| `app/Domain/Admin/Actions/Ads/CreateAdAction.php` | OLD `add_ads()`, audited |
| `app/Domain/Admin/Actions/Ads/UpdateAdAction.php` | OLD `edit_ads()`: changed columns only, audited (before and after) |
| `app/Domain/Admin/Actions/Ads/DeleteAdAction.php` | OLD `del_ads()`: hard DELETE, with the full row kept in the audit |
| `app/Domain/Admin/Actions/Ads/ToggleAdAction.php` | OLD `update_show()`: POST with an explicit target state, audited |
| `app/Domain/Admin/Actions/Ads/StoreAdImageAction.php` | OLD `uploadfile()`, secured |
| `app/Domain/Admin/Support/LegacyAds.php` | OLD positions (1–12), shares, type and path values, status logic, safe preview URLs |
| `app/Domain/Admin/Support/AdsReport.php` | OLD `view_report()` / `draw_ads_chart()` data as a table |
| `resources/views/admin/ads/{index,create,edit,_form,reports}.blade.php` | AdminCP pages |
| `tests/Support/AdAdminHarness.php` | test harness; `nuke_ads` in the **production shape** (local 2025 copy) |
| `tests/Feature/Admin/AdsAdministrationB6aTest.php` | CRUD, validation, legacy compatibility, list, filters, toggle, reports |
| `tests/Feature/Admin/AdsSecurityB6aTest.php` | permissions, code-ad authorization, uploads, methods and CSRF, XSS, audit |
| `tests/Feature/Admin/AdsNoPublicOutputB6aTest.php` | no public output or side effects |
| `tests/Feature/Admin/AdsLegacyUrlB6aTest.php` | security review: legacy URL preservation, unsafe URL rejection, exemption-bypass and permission-scope regressions |

### Edited

| File | Change |
|---|---|
| `routes/admin.php` | `admincp/ads` group behind `admin.permission:ads.manageads` (8 routes); controller import |
| `app/Domain/Admin/Support/AdminDashboardModules.php` | menu entry «الإعلانات» → `admin.ads.index`, gated by `ads.manageads` |
| `app/Domain/Admin/Support/PermissionLabels.php` | module label «الإعلانات», icon, key label `ads.manageads` «إدارة الإعلانات» |
| `database/seeders/AdminPermissionSeeder.php` | defines `ads.manageads` (definition only; no grants imported) |
| `docs/reports/admincp-feedback-implementation-plan.md` | B1 closed locally; B6a status |

**Not touched:**

- the `nuke_ads` schema, its data, and `HomeAd` (the existing model is reused as is);
- every public controller and view;
- Advfiles (`khotab.addadv`, «ملفات الرئيسية»);
- the download infrastructure.

## 2. Implemented behaviour (OLD → Laravel)

| OLD (`modules/ads/admin`) | Laravel B6a |
|---|---|
| Access: `radminsuper`, or the admin's name in `nuke_modules.admins` for `ads` | permission **`ads.manageads`** (super-admin passes). **Raw HTML/JS ads (type 0): super-admin only** (create, edit, convert, show/hide) |
| Menu «الإعلانات» (`links.php`) | the same single entry, separate from «ملفات الرئيسية» |
| `w2a_ads_main`: search by name, from/to (`startdate` or `enddate` between), show (all / 0 / 1), type (all / 0 / 1), position (all / 1–12); `ORDER BY show DESC, id DESC`; columns name, status, shown toggle, position, views, clicks, edit, delete | same filters (GET query, invalid values ignored) and order; same columns; status «ساري / لم يبدأ بعد / منتهي» per OLD's exact rules (period and numview); paginated at 50 rows (OLD had no pagination) |
| `w2a_ads_newads` / `add_ads()` | same fields; `percentage` = 0 (OLD's field was commented out); `period` writes dates only, `numview` writes `required_num_view` only; code ads store the code in `image_path` with `path_type` 2 |
| `w2a_ads_editads` / `edit_ads()` | same fields and partial-write rules (the other counting side and the current upload are kept); only changed columns written; an unchanged save writes nothing |
| `w2a_ads_delads` (confirm page, then DELETE) | DELETE with a confirmation prompt; full row snapshot in the audit; image file left on disk (as OLD) |
| `update_show` (GET `?ads_id=&sh=`) | **POST + CSRF** with an explicit target `show` (idempotent) |
| `uploadfile()`: any extension to `media/bann/{time}.{ext}` | JPEG/PNG/GIF/WebP only, checked by name **and** content and required to decode; ≤ 2 MiB; extension taken from the detected type; `media/bann/{time}_{random}.{ext}`; absolute URL stored as OLD did |
| `w2a_ads_reports` / `view_report()`: one position, from/to, type → bar chart in «المجموعة N» rows | same inputs and overlap rule (enabled ads only); rows in non-overlapping groups («المجموعة N»), each with name (edit link), start, end, type, share and days in range; count and share sum (OLD `get_sum_count_ads`) |

**Legacy compatibility:**

- The existing production row (id 1: malformed link `ttps://…`, uploaded image, numview, `percentage` 0,
  `num_view` 76) lists correctly and can be edited.
- An **unchanged** legacy link or image URL is accepted as stored. Only a changed one must be a valid http(s) URL.
- `num_view` and `num_click` are never written by administration.

## 3. Security

| Requirement | Implementation |
|---|---|
| FormRequest validation | `AdRequest` (create and edit); `reports` validates its period; the toggle validates `show ∈ {0,1}` |
| Parameterized queries | query builder and Eloquent only; the name filter escapes `%`, `_` and `\` |
| POST and CSRF for state changes | store POST, update PUT, delete DELETE, toggle POST; all in the `web` group (`PreventRequestForgery`). GET or wrong-verb requests are 405 (tested); a tokenless cross-site POST is rejected by the middleware (tested directly) |
| Upload MIME validation | `extensions` + `mimes` + `mimetypes` (content) + `getimagesize()` + size; no SVG, HTML, PHP, double extensions or truncated images (9 rejection cases tested) |
| Executable files | rejected by name and content; stored names never use the client's extension |
| Stored XSS in previews | every value escaped; previews render `<img src>` only for http(s) or root-relative URLs; code is shown only as escaped textarea text to super-admins; `javascript:` values never reach `src`/`href` (tested) |
| Raw HTML/JS ads | **super-admin only for create, edit, type conversion (either direction), enable/disable and delete**. Enforced **in the actions**, and re-checked on the row locked inside each transaction (not only the UI). Non-super-admins see no code option or field, and no edit, toggle or delete controls on code ads (hidden, not 403 links). Image ads are unchanged |
| Audit | `add_ad`, `edit_ad` (before/after of changed columns), `toggle_ad`, `del_ad` (full row) in `admin_audit_log` with actor, module `ads`, entity `ad`. **Raw code is never copied**: SHA-256 fingerprint plus length; markup in other values is redacted |
| Double checks | every action re-authorizes (`AdminRecordAuthorizer`), independent of the route |

### 3.1 Security review after owner feedback (2026-11-14)

| Check | Result |
|---|---|
| **Code-ad delete** | **Changed:** deleting a raw HTML/JS ad is now **super-admin only** (route via the action, the action itself, and the locked row); the list hides its delete control from managers. Image-ad permissions are unchanged |
| **Consistency of code-ad protection** | create, edit, convert to code, convert from code, enable/disable and delete all call the same server-side rule. Update, toggle and delete re-check it on the row locked inside the transaction, so an ad that became a code ad after it was loaded is still refused (tested) |
| **Unchanged legacy values** | kept as stored (for example the production row's `ttps://…` link) when only unrelated fields change. AdminCP renders them only as escaped form values, never as `href` or `src` (tested with `javascript:` values) |
| **VULNERABILITY 1 (fixed): exemption bypass** | The "unchanged value" exemption for `image_url` compared against `image_path` whatever its role. A super-admin converting a **code ad** to an image ad could resubmit the raw HTML as an "unchanged image URL". That would leave an image ad whose `image_path` holds markup, which managers could then toggle and which OLD's renderer would inject into `src`. **Fix:** the exemption applies only when the stored value is an image ad's **typed URL** (type 1, `path_type` ≠ upload). An upload path is not an exemption either |
| **VULNERABILITY 2 (fixed): quote breakout for public legacy renderers** | New URLs were checked for scheme, `FILTER_VALIDATE_URL`, whitespace and `< > "` only. OLD/Gen 2 render `link` inside `href='…'` and `image_path` inside the position-12 popup's single-quoted JavaScript string, so `'`, `` ` `` or `\` could break out there. **Fix:** new or changed URLs also reject `'`, `` ` `` and `\` |
| **New or changed unsafe URLs** | rejected on create and edit, for both `image_url` and `link`: `javascript:` (any case), `data:`, `vbscript:`, protocol-relative `//`, leading space, tab, newline, space, quotes, angle brackets, backtick, backslash, and changed malformed values (15 cases × 3 submissions) |
| **Unrelated-field edits** | cannot carry a new URL through; any changed value is validated (tested) |
| **Previews** | no `src`/`href` ever receives a `javascript:` value; all output escaped; code shown only as escaped text (tested) |
| **Mutation check** | with each fix reverted, 5 of the new tests fail: 3 breakout cases (`'`, `` ` ``, `\`) and the 2 exemption-bypass tests. With the fixes restored (byte-identical), all pass |
| **Permission scope** | a test asserts `ads.manageads` gates exactly the 8 `admin.ads.*` routes. Future B6b routes must use their own keys |

**Residual (pre-existing, not changed by B6a):** legacy rows may already hold unsafe values that OLD/Gen 2 public
renderers print verbatim. B6a never rewrites stored data, and Laravel renders no ads. B6c must escape at render time.

## 4. Tests and results

### Targeted (B6a)

| File | Result |
|---|---|
| `AdsAdministrationB6aTest` | **31 passed** |
| `AdsSecurityB6aTest` | **28 passed** (the earlier "manager may delete a code ad" test was replaced by the owner's rule, plus 2 new code-ad authorization tests) |
| `AdsNoPublicOutputB6aTest` | **4 passed** |
| `AdsLegacyUrlB6aTest` | **24 passed** (new) |
| **Total B6a** | **87 passed** |

The first runs had **2 failures, both test defects**, which were fixed:

- **(a)** The assertion `not->toContain('href="javascript:')` matched the admin sidebar's own `href="javascript:;"`
  parent links. It was narrowed to the injected payload.
- **(b)** This Laravel version's `web` group uses `PreventRequestForgery`, not `ValidateCsrfToken`. The test now
  asserts the real class.

Pint then reformatted the two new test files (imports and braces only).

### Full validation

| Check | Result |
|---|---|
**Final, after the security review (2026-11-14):**

| Check | Result |
|---|---|
| Targeted B6a (4 files) | **87/87 passed** |
| **Full suite** `vendor/bin/pest` (with a JUnit log) | **3,462 tests: 3,423 passed, 0 failed, 0 errors, 39 skipped**; 28,180 assertions; exit 0 |
| PHPStan | **0 errors** |
| Pint `--test` | **passed** |
| Free disk | 15 G (no cleanup performed) |

The first attempt at this full run was stopped by the 590 s command timeout, not by a failure. It was rerun without
the timeout; the result above is that rerun.

**Earlier (initial B6a, before the review):** 3,436 tests; 3,397 passed; 0 failed; 39 skipped; 28,010 assertions;
PHPStan 0; Pint passed.

**Skipped (39): unchanged, environment-gated, unrelated to B6a.**

| Count | Test file | Needs |
|---|---|---|
| 20 | `MariaDbIntegrationTest` | a disposable MariaDB (`W2A_MARIADB_TEST_*`) |
| 9 | `MariaDbArchiveTest` | same |
| 5 | `MariaDbMonitorTest` | same |
| 4 | `ShadowBenchmarkTest` | `W2A_SHADOW_BENCH=1` |
| 1 | `ThumbnailControllerTest` | PHP GD (missing locally) |

**No test was modified to pass.**

- The two test defects fixed during development were in the new B6a tests.
- The one replaced B6a test ("manager may delete a code ad") encoded the earlier default that the owner reversed.
- No pre-existing test or threshold was changed.

## 5. Remaining parity gaps (intentional or minor)

| # | Gap | Why |
|---|---|---|
| 1 | Reports are a **table**, not OLD's bar chart | OLD's chart was order-dependent and printed debug arrays; same data, deterministic grouping |
| 2 | Report count and share sum are filtered by the selected **type** | OLD's summary query ignored type while its chart honoured it |
| 3 | "Days in range" is inclusive | OLD drew bars from day differences; display only |
| 4 | Name filter is collation-based `LIKE` | OLD used `BINARY LIKE` (case- and diacritic-sensitive) |
| 5 | `percentage` is **not editable**; 0 on create, **preserved** on edit | OLD's forms had the field commented out, and OLD's edit silently reset it to 0 |
| 6 | Uploads limited to JPEG/PNG/GIF/WebP ≤ 2 MiB | OLD accepted any file; its form also listed ICO, which is not allowed now |
| 7 | End date must be ≥ start date; dates must be Y-m-d | OLD validated nothing server-side |
| 8 | Delete confirmation is a browser prompt | OLD had a separate confirmation page |
| 9 | The list is paginated (50) | OLD had no pagination |
| 10 | The image file is not deleted on delete or replace | same as OLD |
| 11 | No locking against OLD's admin editing the same row | coexistence is last-write-wins, as before |

## 6. Owner decisions (approved 2026-11-14)

| # | Decision | Status |
|---|---|---|
| 1 | **`ads.manageads`** is the single B6a permission. It **must not authorize future B6b modules** (`banners_ads`, `report_ads` will need their own keys) | approved; scope enforced by a test |
| 2 | OLD's five `nuke_modules.admins` assignments are **not imported automatically**. A super-admin assigns the key after deployment | approved |
| 3 | Upload limit **2 MiB**; JPEG, PNG, GIF and WebP only | approved |
| 4 | Reports as **tables**, not OLD's broken chart | approved |
| 5 | The share (`percentage`) is **read-only**; its stored value is preserved | approved |
| 6 | Legacy raw HTML/JS ads stay **stored verbatim**, never executed in AdminCP | approved |
| 7 | **Only super-admins may delete code ads** (replaces the earlier "managers may delete" default) | approved and implemented |

**Deployment prerequisites (retained; not done):**

1. Run the permission seeder (`AdminPermissionSeeder`) so `ads.manageads` exists.
2. Production media-root verification: `admin_khotab.media_root` must be the directory served at `media.base_url`
   (`media:check-roots`, as for F06).
3. `APP_URL` verification: it must be the public site URL, because new upload URLs are built from it.
4. Confirm that scripts cannot execute in `media/bann/`. B6a writes only images, but OLD wrote arbitrary files
   there.

## 7. Confirmations

- **No production changes**, no production access, no production database writes.
- **No commits, pushes or deployments.**
- **No public advertisements activated or rendered.** Public pages neither read nor write `nuke_ads` (tested with all
  12 positions enabled).
- The `nuke_ads` schema and existing records are unchanged. The OLD and Gen 2 code are unchanged.
- **B6b, B6c, B2–B5 and B7b were not started.**
