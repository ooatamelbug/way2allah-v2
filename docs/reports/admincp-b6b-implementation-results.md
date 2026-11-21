# AdminCP B6b: `banners_ads` and `report_ads` administration (F01)

**Date:** 2026-11-14.

**Approval:** owner, "Owner Approval: B6b Implementation". Reviewed: "B6b Owner Review and Final Validation" (§9).

**Plan:** `docs/reports/admincp-feedback-implementation-plan.md` §F01 (parity rows B1–B4, R1–R4).

**Status:** **CLOSED LOCALLY** (2026-11-14, after the owner review in §9). **Not deployed and not production-verified.**

- Not committed, not pushed, not deployed. No production access.
- No schema change, no data migration. The local MySQL copy was read only.
- **No public advertising:** no banner strip, no report submission, no tracking.
- **Not started:** B6c, B2–B5, B7b. B6a code is unchanged.
- `7amlat` and `old-project` are unmodified.

---

## 1. Verified OLD behaviour

Sources: `old-project/modules/banners_ads/admin/*`, `old-project/modules/report_ads/admin/*`, their templates in
`themes/way2allah/templates/{banners_ads,report_ads}/`, `way2allah_functions.php`, `js/inc.js`, and Gen 2
(`7amlat`). Data: the local 2025 copy (read only).

### 1.1 `banners_ads`: «إعلانات بنرات المواد»

| Question | Finding |
|---|---|
| Routes and actions | one op, `admin.php?op=w2a_banners_ads_main` (menu `links.php`). With `confirm=1` and `items_ids` (comma list) it saves; otherwise it only shows. `case.php` also routes the B6a op names to this file, but its switch handles only `w2a_banners_ads_main` |
| Tables and columns | `nuke_banners_ads (id, media_id int DEFAULT 0, creation_date timestamp DEFAULT CURRENT_TIMESTAMP)`. Reads `nuke_islamic_khotab (id, title, banner, cat, author)`, `nuke_islamic_authors (id, name)`, `nuke_w2a_cat (id, title, main_cat)` |
| Permission | `radminsuper = 1`, or the admin's `name` in `nuke_modules.admins` for `banners_ads` (local copy: 3 names, including `test-1`) |
| Workflow | two multi-selects: «المواد المختارة» (the current set) and «المواد المقترحة» (`get_all_banners()`: Khotab items `WHERE banner = 1 ORDER BY BINARY title`, hidden ones included). JS moves items between them, rejects duplicates and id 0, and fills `items_ids`. «حفظ التعديلات» posts. Save = `DELETE FROM nuke_banners_ads`, then one `INSERT … SET media_id={id}` per id (SQL-injectable) |
| Filters | category (full-path list «أب=>ابن») and author dropdowns. Their AJAX endpoints (`get_cats_with_conditions_ajax.php`, `ajax/get_cats_with_conditions.php`) are **unauthenticated, SQL-injectable, and return only debug text** (the result loop is commented out or never echoed). They compare the pipe-delimited `cat` column (`'|12|34|'`) with `=`. **The filters never worked** |
| Edge cases | an empty list posts `items_ids=""`, so `DELETE` all, then a failing `INSERT media_id=` (the set is emptied). Titles were printed raw into `<option>` |
| **Current OLD state** | `w2a_banners_ads_main()` calls `$db->sql_query()` without `global $db`, and neither `header.php` nor the theme binds `$db` at top level, so **the page fatals** at the first query after the filter form. CONFIRMED by code reading; not executed |
| Data (local copy) | **16 rows**, ids 968–983, all `creation_date = 2019-12-01 06:43:29` (one full replacement), all referencing visible `banner = 1` items: no orphans, no duplicates. 1,363 items have `banner = 1` |
| Public dependency (B6c) | OLD `index.php:347` → `get_random_three_banners()` (`way2allah_functions.php:557`): one random `media_id`, rendered as the large home banner `media/banner/{floor(id/1000)}/{id}.gif` linking to `khotab-item-{id}.htm`. Gen 2's call is commented out (`new_content.php:210`). Laravel renders none. **The table only matters once B6c renders the strip** |

### 1.2 `report_ads`: «الإبلاغ عن اعلانات غير لائقة»

| Question | Finding |
|---|---|
| Routes and actions | `op=w2a_bad_ads_main` (list) and `op=w2a_bad_ads_delads&id=…` (delete, **GET**, injectable, no CSRF). Nothing else |
| Table | `nuke_report_for_bad_ads (id, user_email varchar(150), ads_url varchar(255), db_creation_date timestamp DEFAULT CURRENT_TIMESTAMP)`. **0 rows** locally |
| Permission | `radminsuper`, or the module's `nuke_modules.admins` (local copy: 2 names, including `test-1`) |
| Workflow | list `ORDER BY id DESC` with columns م / بريد المستخدم / رابط الإعلان / تاريخ الإبلاغ / حذف; empty state «لم يتم إرسال أي إبلاغات حتى الأن». **`ads_url` printed raw as `<a href="$row[ads_url]">`** (stored XSS / `javascript:`); e-mail raw |
| Read-only, deletable, other? | **read and delete only**; no edit, export, reply or status |
| Is report creation reachable? | **No.** OLD and Gen 2 `inc.js` define `show_report_for_bad_ads_dialog()` and a jQuery dialog for `#report_for_bad_ads`, but **no template or page contains that element or calls the function**, and **nothing anywhere INSERTs** into the table |
| **Current OLD state** | `w2a_bad_ads_main()` has the same missing `global $db`, so **the list fatals** (by code reading) |
| Public dependency | none (no writer exists) |

## 2. Implemented functionality

| OLD | Laravel B6b |
|---|---|
| `w2a_banners_ads_main` view | `GET admincp/banners-ads` (`admin.banners_ads.index`): «المواد المختارة» (current set with id, title, author, `creation_date`; markers for «مخفية», «لم تعد تحتوي على بنر», «المادة غير موجودة») and «المواد المقترحة» (`banner = 1`, title order, 100 per page, current members marked «مضافة» instead of offered twice) |
| Category / author filters (broken) | working server-side filters: category (OLD's full-path list, matched through `khotab_category_index`), author, and title (OLD's AJAX supported a title term); invalid values ignored |
| Save (`DELETE` all + `INSERT` each) | `PUT admincp/banners-ads` (`admin.banners_ads.update`): the new set = ticked current members (`keep[]`) + ticked suggestions (`add[]`). Applied as a **diff in one transaction**: only removed rows are deleted and only new items inserted. **Unchanged rows keep their `id` and `creation_date`.** An unchanged save writes nothing. Nothing ticked empties the set (OLD's outcome), audited. The form carries a fingerprint of the set it was rendered from (`set_version`); a save from a stale form is rejected (§9.1) |
| `w2a_bad_ads_main` | `GET admincp/report-ads` (`admin.report_ads.index`): the same columns and order, paginated (50), OLD's empty-state text |
| `w2a_bad_ads_delads` (GET) | `DELETE admincp/report-ads/{id}` (`admin.report_ads.destroy`), CSRF, audited |
| Menu | «إعلانات بنرات المواد» and «الإبلاغ عن اعلانات غير لائقة» (OLD labels), each shown only to its key holders |
| Permissions | **`banners_ads.managebanners`** and **`report_ads.managereports`** (new, one per OLD module). `ads.manageads` grants neither; neither grants B6a. Super-admin passes both (the existing middleware and `AdminRecordAuthorizer` model). No legacy assignments imported |

## 3. Changed files

**New:**

| File | Role |
|---|---|
| `app/Domain/Admin/Http/Controllers/BannerAdsAdminController.php` | view and filters, save |
| `app/Domain/Admin/Http/Controllers/BadAdReportsAdminController.php` | list, delete |
| `app/Domain/Admin/Actions/Ads/UpdateBannerAdsAction.php` | diff-based save, validation of `keep` / `add`, audit |
| `app/Domain/Admin/Actions/Ads/DeleteBadAdReportAction.php` | delete, audit (e-mail fingerprinted) |
| `app/Domain/Admin/Support/SafeExternalLink.php` | strict "may this stored URL be a link" check (B6a's URL rule) |
| `resources/views/admin/banners-ads/index.blade.php`, `resources/views/admin/report-ads/index.blade.php` | AdminCP pages |
| `tests/Support/BannerAdsHarness.php` | production-shaped `nuke_banners_ads` / `nuke_report_for_bad_ads` |
| `tests/Feature/Admin/BannerAdsB6bTest.php`, `BadAdReportsB6bTest.php`, `AdsB6bAuthorizationTest.php`, `AdsB6bNoPublicOutputTest.php` | tests |

**Edited:**

| File | Change |
|---|---|
| `routes/admin.php` | 4 routes in two groups, each behind its own key; 2 imports |
| `app/Domain/Admin/Support/AdminDashboardModules.php` | 2 menu entries |
| `app/Domain/Admin/Support/PermissionLabels.php` | 2 module labels, icons, 2 key labels |
| `database/seeders/AdminPermissionSeeder.php` | defines `banners_ads.managebanners`, `report_ads.managereports` (definitions only) |
| `docs/reports/admincp-feedback-implementation-plan.md` | B6b status |

**Owner-review changes (§9):** `UpdateBannerAdsAction.php` (`set_version` check, `fingerprint()`),
`BannerAdsAdminController.php` (renders and requires `set_version`), `banners-ads/index.blade.php` (hidden field),
`tests/Support/BannerAdsHarness.php` (`version()`), and the three B6b test files (payloads carry `set_version`; 11 tests
added).

**No B6a file was changed.**

## 4. Security findings and handling

| # | Finding (OLD) | Handling (B6b) |
|---|---|---|
| S1 | SQL injection in the save (`media_id={$ids}`) and the delete (`id = {$_GET['id']}`) | validated integer arrays and route parameter; query builder only |
| S2 | Unauthenticated, injectable filter endpoints echoing SQL | not ported; filters are server-side, behind the module key |
| S3 | Delete via GET without CSRF | DELETE + CSRF (GET is 405, tested) |
| S4 | Stored XSS: report `ads_url` printed raw into `href` and as text; titles and author names raw in `<option>` | everything escaped. A report URL is a link **only** if it passes `SafeExternalLink` (B6a's strict rule: absolute http(s), no whitespace, controls, `< > " ' \`` or `\`), with `rel="noopener noreferrer nofollow"`. Otherwise it is escaped text (6 unsafe forms tested) |
| S5 | Tampered ids (adding non-banner or missing items, "keeping" non-members) | rejected with a validation error, nothing written (tested) |
| S6 | Personal data (reporter e-mail) | shown to authorized admins as OLD did; **not copied to the audit log** (SHA-256 of the normalized e-mail) |
| S7 | Lessons from B6a's URL review | B6b stores **no** new URLs (it writes only integer `media_id`s and deletes reports); the stored-URL rendering rule is the same as B6a's |
| S8 | *(found in the owner's concurrency review, in B6b itself)* a stale banner form silently removed another admin's concurrent addition | **fixed**: set fingerprint checked under the row lock; stale saves rejected, nothing written (§9.1) |

No security vulnerability was found in the B6b implementation. The owner's concurrency review found one **correctness
defect** (S8, lost update), now fixed.

**Residual, not addressed by B6b:** OLD and Gen 2 public code that may render these values (only the B6c banner strip
reads `nuke_banners_ads`, and it writes only integers into its markup).

## 5. Tests and results

### Targeted (B6b)

| File | Tests | Covers |
|---|---|---|
| `BannerAdsB6bTest` | **23 passed** (15 + 8 from the review) | view and current set, category / author / title filters, diff save (ids and dates preserved), unchanged save, empty set, rejection of non-banner, missing and tampered ids, id validation, legacy members, escaping, read-only views. *Review:* fingerprint in the form, stale form after another admin's add or removal rejected, missing / malformed / forged fingerprint refused, fingerprint order-insensitive, legacy duplicates tolerated |
| `BadAdReportsB6bTest` | **12 passed** (11 + 1) | empty state, order and columns, 6 unsafe stored URL / e-mail forms never rendered as link or markup, delete with audit (e-mail fingerprinted), markup redaction, 404, wrong verbs. *Review:* deletion leaves the reported ad, the banner set and the Khotab items unchanged |
| `AdsB6bAuthorizationTest` | **12 passed** (10 + 2) | the 4 routes and their own keys, visitor, `ads.manageads` refused, per-key isolation (neither key opens the other or B6a), super-admin, action re-checks, menu, verbs and CSRF tokens, B6a table untouched, no public references. *Review:* real CSRF enforcement on the DELETE and the PUT |
| `AdsB6bNoPublicOutputTest` | **2 passed** | home and a shared-layout page do not query or change either table and render no strip |
| **B6b total** | **49 passed** | 38 at implementation + 11 from the owner review |

### Full validation

| Check | Result |
|---|---|
| **Full suite** `vendor/bin/pest` (with a JUnit log), after the owner review | **3,511 tests: 3,472 passed, 0 failed, 0 errors, 39 skipped**; 29,974 assertions; exit 0 (implementation run: 3,500 tests; +11) |
| PHPStan | **0 errors** |
| Pint `--test` | **passed** (Pint formatted the new files once, before the final run) |

**Skipped (39): unchanged, environment-gated, unrelated.** MariaDB integration 20, MariaDB archive 9, MariaDB
monitor 5 (`W2A_MARIADB_TEST_*`); shadow benchmarks 4 (`W2A_SHADOW_BENCH=1`); thumbnails 1 (PHP GD missing
locally).

**No threshold, non-B6b test or B6a code was changed.** The review changed existing B6b test payloads only to carry the
required `set_version`.

## 6. Differences from OLD

| # | Difference | Why |
|---|---|---|
| D1 | Save is a **diff** (unchanged rows keep `id` and `creation_date`), not delete-all and re-insert | owner rule "preserve identifiers / no destructive changes"; same resulting set |
| D2 | Filters **work** (category through `khotab_category_index`, author, title) | OLD's filters never returned results; this is the evident intent, not a new feature. ⚑ see decision 2 |
| D3 | Suggestions are paginated (100), with members marked «مضافة» | OLD put all 1,363 items in one multi-select; duplicates were blocked in JS |
| D4 | A member whose item lost its banner (or vanished) can be **kept** | OLD would silently drop it on any save (re-insert from the visible list); here stored data is not dropped unless unticked |
| D5 | Nothing ticked empties the set, without an error | OLD reached the same empty result through a failing insert |
| D6 | Report URLs are links only when strictly safe; delete needs DELETE + CSRF | security |
| D7 | Report date shown as stored (`Y-m-d H:i:s`) | OLD used `CoolShortDate()` |
| D8 | Hidden items are offered (as OLD), with a «مخفية» marker | parity, made visible |
| D9 | A save from a page loaded before another admin changed the set is refused («قائمة المواد المختارة تغيرت، من فضلك أعد تحميل الصفحة») | OLD's delete-all replace let the last writer silently win (§9.1) |

## 7. Remaining parity gaps

| # | Gap |
|---|---|
| G1 | No JavaScript two-list mover; checkboxes and one save |
| G2 | **The B6c dependency:** the home banner strip (`get_random_three_banners()` plus the two forum banners from `nuke_banners`) is not rendered, so the set has no public effect yet. `media/banner/{b}/{id}.gif` presence in production is unverified (plan E8) |
| G3 | **No report submission exists** (unreachable in OLD and Gen 2). Not added, per scope |

## 8. Owner decisions (approved in "B6b Owner Review and Final Validation")

| # | Decision | Implementation |
|---|---|---|
| 1 | **Approved:** `banners_ads.managebanners` and `report_ads.managereports` as separate keys | as implemented; `ads.manageads` grants neither (tested) |
| 2 | **Approved:** keep the working category, author and title filters; they restore OLD's intended functionality | as implemented |
| 3 | **Approved:** preserve banner-selection rows whose item lost its banner (or vanished); never delete historical selections automatically | as implemented: kept until an admin unticks them (tested) |
| 4 | **Approved:** such rows must be identifiable in AdminCP, and **must not be rendered as broken banners when B6c is implemented** | AdminCP marks them «لم تعد تحتوي على بنر» / «المادة غير موجودة» (tested). **Binding B6c requirement:** the public strip must draw only from members whose item exists, is visible and has `banner = 1` (and, per plan E8, whose banner file exists) |
| 5 | **Approved:** only a fingerprint of the reporter e-mail in the audit log | as implemented (SHA-256 of the trimmed, lowercased e-mail; tested) |
| 6 | **Approved:** no automatic import of OLD admin assignments | not imported; a super-admin assigns the keys |
| 7 | **Approved:** retain `report_ads` for legacy functional parity, although the table is empty and no public submission workflow exists | shipped, read and delete only |
| 8 | **Approved:** no new report-submission workflow | none added; no public route references the table (tested) |

**Deployment prerequisites (not done):**

1. Run `AdminPermissionSeeder` (adds the two keys).
2. A super-admin assigns the keys.
3. No media, URL or APP_URL dependency: B6b uploads and stores no files or URLs.
4. Confirm on production (read only) that `nuke_banners_ads` is InnoDB and the server runs at REPEATABLE READ, as
   the local copy does (§9.1). Both are MySQL defaults.

---

## 9. Owner review: concurrency and report deletion

### 9.1 Banner-selection concurrency

| Question | Finding | Evidence |
|---|---|---|
| Is the update atomic? | **Yes.** One transaction on `main` covers the locking read of the set, all validation, the deletes, the inserts and the authoritative `admin_audit_log` row (same connection). Any exception rolls all of it back. The optional legacy-log mirror runs in a savepoint and is off by default | code reading; rejection tests assert set and audit unchanged |
| Can concurrent saves give partial or inconsistent results? | **No.** `SELECT media_id FROM nuke_banners_ads FOR UPDATE` (no `WHERE`) takes next-key locks on every row and the gaps under InnoDB REPEATABLE READ, so two saves run one after the other. The second save's locking read sees the first save's committed result. For an **empty** set, both saves can take the gap lock; the first insert then deadlocks and MySQL rolls that transaction back whole, so the admin gets an error and nothing is half-written | local copy: InnoDB, MySQL 5.7.44, `REPEATABLE-READ`. By reasoning only: the SQLite test database has no row locks, so this was **not executed** |
| Can a stale form silently overwrite another admin's change? | **Yes. Defect confirmed and fixed.** Removal is inferred from absence (OLD's replacement semantics). A form loaded before another admin's save therefore removed that admin's addition, even when the second admin changed nothing | a probe test **failed before the fix**: a stale `keep=[a,b]` removed `c`, which another admin had just added |
| Are duplicate selections prevented? | **Yes, for new rows.** Within one request, `add` is validated `distinct`, deduplicated, and current members are excluded. Concurrent saves are serialized by the lock and the fingerprint. The legacy table has only a primary key and no unique index on `media_id`; none was added, because that would change the schema. **Legacy duplicates are tolerated:** the fingerprint ignores them, no further duplicate is added, and unticking removes every row for the item | tests; the local data has no duplicates |

**Fix (the smallest safe one; no version column, no schema change, no extra locks):**

- The page renders `set_version`, a SHA-256 of the sorted, distinct `media_id`s it displayed.
- The save requires `set_version` (64 characters).
- The action recomputes the fingerprint from the **locked** rows and compares the two with `hash_equals`.
- On a mismatch it rejects with the existing «قائمة المواد المختارة تغيرت، من فضلك أعد تحميل الصفحة» and writes nothing, not even an audit row.

**Mutation check:** with the comparison removed, 3 of the new tests fail (two stale-form cases and the forged
fingerprint).

**Residual (documented, not fixed):** the guarantees above assume InnoDB at REPEATABLE READ (deployment prerequisite 4).
Under READ COMMITTED there are no gap locks. Two *simultaneous first* saves into an **empty** set could then both
insert, which can duplicate a row; such a duplicate is harmless and the admin can untick it. Not observed; the
production set is not empty.

### 9.2 Report deletion

| Check | Result | Coverage |
|---|---|---|
| Authorization enforced server-side | route middleware `admin.permission:report_ads.managereports`, plus `AdminRecordAuthorizer` inside `DeleteBadAdReportAction` | existing: visitor, `ads.manageads`, banners key all refused; direct action call refused |
| CSRF active | `PreventRequestForgery` is in the `web` group, and the route is in that group | **added:** the real middleware, with the test bypass off, rejects a token-less DELETE (and the banner PUT) and accepts a valid token |
| GET cannot delete | GET and POST on `/admincp/report-ads/{id}` → 405; OLD-style `…/delete` → 404; nothing changes | existing |
| Audit details sufficient and non-sensitive | `del_bad_ad_report`, module `report_ads`, entity id, actor, IP, time, plus the report's `ads_url` (markup redacted) and `db_creation_date`. The e-mail is stored as a SHA-256 fingerprint only | existing |
| Deleting a report never touches its advertisement | the action deletes one `nuke_report_for_bad_ads` row by id and nothing else. There is no relation to `nuke_ads` (the report stores a free-text URL) | **added:** a report whose URL equals an ad's link; after deletion `nuke_ads`, `nuke_banners_ads` and `nuke_islamic_khotab` are byte-identical |

**No defect found** in report deletion.
