# AdminCP feedback: implementation plan (F01–F10)

**Date:** 2026-11-14. **Status:** owner-reviewed 2026-11-14.

> **F01–F10 closure pass (2026-11-17)** — report `docs/reports/admincp-f01-f10-closure-results.md` (decision-log
> #150). Not deployed.
>
> | Point | Status |
> |---|---|
> | F01 | **ON HOLD — AWAITING STAKEHOLDER CLARIFICATION** (2026-11-17). Frozen: no implementation, change, activation or placement assumptions. Placement matrix for that clarification: `admincp-f01-placement-approval-matrix.md` |
> | F02 | OWNER APPROVAL REQUIRED: Laravel-side deactivation implemented behind `admin_deactivation.enabled` (OFF); migration on `main` not run; legacy systems not covered |
> | F03 | CODE COMPLETE — DATA ACTION PENDING (repair verified end to end on disposable MySQL) |
> | F04 | CODE COMPLETE LOCALLY — NOT DEPLOYED (2026-11-17). All «حفظ المادة» routes download through the existing protected proxy (owner-approved expansion; `f04-save-material-download-results.md` §6). Production prerequisites: deploy the stalled-source fix first; guard and slot limit; rejections table |
> | F05 | BLOCKED — SPECIFIC EVIDENCE NEEDED (reported item and mirror id) |
> | F06 | CLOSED LOCALLY |
> | F07 | CODE COMPLETE — DATA ACTION PENDING (46-row repair verified; original record unidentified) |
> | F08 | CLOSED LOCALLY |
> | F09 | CLOSED LOCALLY (post-backup link lock restored; wording optional) |
> | F10 | CLOSED LOCALLY |

- **B1: CLOSED LOCALLY** (owner, 2026-11-14), limited to F06, F05-1 and F02-UI.
  - **Final validation:** 3,375 tests; 3,336 passed; 0 failures; 0 errors; 39 expected skips; 26,596 assertions;
    PHPStan 0 errors; Pint passed; `SpoolWriterTest` 17/17 with original settings.
  - **Not deployed and not production-verified.**
  - **Still outstanding:** F06's production media-root verification (`admincp-b1-outstanding-validation.md` §2). The
    reported medium-quality playback issue remains **open**, separately from F05-1.
  - See `docs/reports/admincp-b1-implementation-results.md`.
- **B6a (F01 `ads` administration): CLOSED LOCALLY** (owner, 2026-11-14), after the security review.
  - **Delivered:** list and filters, create, edit, delete, show/hide, image upload, reports, the single permission
    `ads.manageads` (which must not authorize future B6b modules), the menu entry «الإعلانات», and audit logging, on
    the unchanged `nuke_ads` table.
  - **Raw HTML/JS ads:** super-admin only for create, edit, conversion, enable/disable and **delete**.
  - **Security review:** two URL-validation weaknesses were found and fixed (the exemption bypass, and quote
    breakout for the legacy public renderers), with regression tests.
  - **Owner decisions:** OLD's admin assignments are not imported; uploads are 2 MiB JPEG/PNG/GIF/WebP; reports are
    tables; the share is read-only and preserved; legacy code is stored and never executed in AdminCP.
  - **Final validation:** 3,462 tests; 3,423 passed; 0 failures; 0 errors; 39 expected skips; 28,180 assertions;
    PHPStan 0 errors; Pint passed.
  - **Not deployed and not production-verified.** Prerequisites: the permission seeder, production media-root
    verification, `APP_URL` verification, and confirmation that scripts cannot execute in `media/bann/`.
  - **No public advertising.** (B6b and B6c-1: see below.)
  - See `docs/reports/admincp-b6a-implementation-results.md`.
- **B6b (F01 `banners_ads` and `report_ads` administration): CLOSED LOCALLY** (2026-11-14, after the owner review).
  - **Delivered:** the banner-set page and its save, the report list and delete, the dedicated keys
    `banners_ads.managebanners` and `report_ads.managereports` (`ads.manageads` grants neither), two menu entries,
    and audit logging, on unchanged tables.
  - **OLD findings:** both OLD pages fatal on an undefined `$db`; OLD's filters never worked; report creation is
    unreachable in OLD and Gen 2.
  - **Owner decisions (approved):** separate keys; keep the working filters; preserve selections whose item lost its
    banner and mark them in AdminCP. **B6c must not render them as broken banners.** Reporter e-mail is fingerprinted
    in the audit; no import of OLD assignments; `report_ads` retained for parity; no new report submission.
  - **Owner review:** a stale-form lost update in the banner save was confirmed and fixed with a set fingerprint
    (`set_version`) checked under the row lock. Report deletion: no defect; CSRF enforcement and ad-isolation tests
    were added.
  - **Final validation:** full suite 3,511 tests; 3,472 passed; 0 failures; 0 errors; 39 expected skips; 29,974
    assertions; PHPStan 0 errors; Pint passed.
  - **Not deployed and not production-verified.** Prerequisites: the permission seeder, key assignment by a
    super-admin, and confirmation that production `nuke_banners_ads` is InnoDB at REPEATABLE READ.
  - **No public advertising.** (B6c-1: see below.)
  - See `docs/reports/admincp-b6b-implementation-results.md` §8–§9.
- **B6c-1 (F01 public advertising rendering foundation): CLOSED LOCALLY** (2026-11-14, after the owner review). **Not
  deployed, not activated, nothing placed.**
  - **Delivered:**
    - `config/ads.php` with `ads.rendering_enabled` and `ads.popup_enabled`, both **default OFF**;
    - OLD's eligibility verbatim (start day excluded, end day included, numview `<=`);
    - a uniform, read-only pick per position;
    - `<x-ads.slot>` for positions 1–12 (position 12 as an inert, closed `<dialog>`);
    - `<x-ads.banner-strip>`, drawing only existing, visible items with a valid banner.
  - **Safety:**
    - with the switch off: zero ad queries and zero output;
    - no writes in any state (no view or click counter, no auto-hide);
    - code ads withheld and unmodified;
    - URLs must pass `SafeExternalLink`; everything escaped; no values in JavaScript.
  - **Nothing placed on any public page; no activation.** Placement, counters, the click route and popup opening are
    B6c-2 (not started).
  - **Owner review (approved decisions):**
    - **Eligibility:** OLD's start-day exclusion and the view-limit `<=` are preserved for now.
    - **Not approved:** view counting and counter-based auto-hide (writing on every page view).
    - **View-limited ads** must not be activated in production until a reliable counting mechanism is approved.
    - **Clicks:** OLD's click endpoint is not reused; a secure replacement needs separate design and approval.
    - **Code ads** stay withheld; AdSense needs separate approval.
    - **Unsafe URLs:** the ad is withheld; stored values are not rewritten.
    - **No fallback images:** an empty slot.
    - **Popup** disabled and never opened.
    - **Placement:** none invented. Position 9 stays deferred, position 11 unresolved, and the strip disabled.
  - **Final review:**
    - safety scenarios 1–10 all tested (no code execution, no counter writes, bounded queries);
    - architecture verified, with 2 new arch rules;
    - no open B6c-1 defect.
  - **Open for B6c-2:** the date-based auto-hide write-back, the code-ad share, popup opening and the switch,
    positions 5/6/8, 9 and 11, position 3 markup, and strip placement (B6c-1 report §9.4).
  - **Final validation:** full suite 3,613 tests; 3,574 passed; 0 failures; 0 errors; 39 expected skips; PHPStan
    0; Pint passed.
    The URL rule moved to `App\Support\Http\SafeExternalUrl` (B6b's `SafeExternalLink` delegates to it) to honour the
    Content/Admin domain boundary.
  - Two B6a/B6b guard tests were narrowed to allow the switch-gated foundation. Their public-page assertions are
    unchanged.
  - Mapping, the code-ad strategy, 13 open decisions and activation prerequisites:
    `docs/reports/admincp-b6c1-rendering-foundation-results.md`.
- **B4 (F08 Variety item visibility): CLOSED LOCALLY** (2026-11-15, after the owner review). **Not deployed.
  DEPLOYMENT BLOCKED** until production routing and legacy exposure are verified.
  - **Admin control:**
    - «إخفاء / إظهار المادة» on the edit page and the item toolbar;
    - `PUT …/visibility` with the target state, behind `anasheed.editanasheed`, CSRF;
    - writes only `hidden`; audited `hide_item` / `unhide_item`;
    - `is_published` untouched.
  - **`hidden` semantics** (established from code): every legacy reader tests only `== '0'`, and no legacy code
    writes the column. So `0` is public, and `2` (32 local rows, all Cartoon, 2009–2010) and `1` are hidden. The 2s
    are not normalized. They are public today on live Gen 2, which ignores the column.
  - **Laravel surfaces:**
    - hidden items are excluded from group, news, home widgets, related lists, search, category and `.grx`;
    - direct and paged URLs are **404** for visitors and for admins without `anasheed.editanasheed`;
    - comments, send-to-friend, player and downloads are refused;
    - holders and super-admins keep the A6-style preview (private response).
  - **Cache:** no Laravel cache holds Variety rows, so changes take effect immediately (tested).
  - **32 `hidden = 2` items:** the owner approved that they remain hidden (2026-11-15); no data change.
  - **Card counts (final adjustment):** sub-group cards now show the publicly visible count (one aggregate query per
    page, `hidden = 0`, same "items directly in the group" scope). The stored counter is preserved and never written.
    Locally 24 of 1,204 card numbers change: 9 from hidden items and 15 from pre-existing drift. No schema, index or
    data change.
  - **Legacy exposure matrix:** report §4. Every legacy row's production reachability is UNKNOWN. Gen 2's `anasheed/*`
    and `vars/*`, the comment routes, OLD's RSS/XML and OLD's `Play` / `getit` / `getmirror` / `Group` do not
    filter hidden items.
  - **Validation (after the final adjustment):** full suite 3,652 tests; 3,613 passed; 0 failures; 39 expected
    skips; PHPStan 0; Pint passed.
  - Decision-log #141. Report: `docs/reports/admincp-b4-implementation-results.md`.
- **B5 (F10 custom Khotab cover): CLOSED LOCALLY** (2026-11-16). **Not deployed.** Production prerequisites are
  open (media persistence, write permissions, backups, no script execution, GD WebP, legacy routing).
  - **Validation:** full suite 3,684 tests; 3,645 passed; 0 failures; 39 expected skips; PHPStan 0; Pint passed.
  - **Storage:** a side file `media/khotab_covers/{b}/{id}.jpg` (the file is the state); **no migration, no schema
    change**. Legacy flags and Archive.org files are untouched.
  - **Priority:** cover first on home, sidebars, featured cards and the social image (over the GIF), and in the
    AdminCP preview; otherwise the existing behaviour. The player, featured eligibility and pages without thumbnails
    are unchanged.
  - **AdminCP:** «صورة الغلاف» (video items, `khotab.edititem`, CSRF, audited).
  - **Processing:** content-validated JPEG / PNG / WebP up to 2 MiB → JPEG, at most 1280 px. Per-item lock, atomic
    swap with rollback.
  - **Legacy:** OLD and Gen 2 keep showing archive frames; the OLD `get_gif_jpg.php` risk is preserved.
  - Decision-log #142 (supersedes the §F10 table design). Report: `docs/reports/admincp-b5-implementation-results.md`.
- **B2 (F03, F05-2, F07): INVESTIGATION COMPLETE; F05-2 (BOTH PARTS) IMPLEMENTED. B2 NOT CLOSED** (F03, F07 open)
  (2026-11-16). Report: `docs/reports/admincp-b2-investigation.md`.
  - **F05-2 (1) MP4 mirror playback: IMPLEMENTED LOCALLY — CLOSED LOCALLY (2026-11-16) after validation passed; not deployed** (
    decision-log #143, report `docs/reports/admincp-b2-f05-2-implementation-results.md`).
    - `MediaPlayerService::khotabMirrorPlaysAsVideo()`: an audio-flagged mirror whose URL **path** ends in `.mp4`
      plays and is labelled as video **only when its parent item is a video item**. Render-time only, no data change.
    - Covers the 601 mirrors (e.g. 834 / item 4337, 564 / 3441, 799 / 4282). **Intentional Gen 2 deviation.**
  - **F05-2 (2) non-browser formats: IMPLEMENTED LOCALLY — CLOSED LOCALLY (2026-11-16) after validation passed; not deployed** (owner
    Option B, decision-log #144, report `docs/reports/admincp-b2-f05-2-part2-results.md`).
    - Khotab and Variety players: RMVB / WMV / RM / AVI / FLV / WMA (closed list, URL path extension) answer with
      «هذه الصيغة غير مدعومة للتشغيل المباشر في المتصفح…» and the page's existing download route — only when that
      route can serve the link; otherwise «لا يتوفر رابط تحميل لهذه المادة حالياً». No player is initialised.
    - Watch buttons, labels, download routes and every other format unchanged. Plan decision 20 is resolved.
  - **F03-A repair command: IMPLEMENTED LOCALLY — CLOSED LOCALLY (2026-11-16) after validation passed; PRODUCTION REPAIR NOT RUN** (2026-11-16,
    decision-log #145, report `docs/reports/admincp-b2-f03-counter-repair-results.md`).
    `php artisan khotab:repair-f03-series-counts`: dry run by default; `--apply` / `--rollback` with `--actor`,
    verified backup, closed 22-row baseline. The production run needs the owner prerequisites (report §7).
  - **F03-B admin view of empty / hidden series: IMPLEMENTED LOCALLY — CLOSED LOCALLY (2026-11-16) after validation passed; not deployed** (2026-11-16, decision-log
    #146, report `docs/reports/admincp-b2-f03b-implementation-results.md`). `khotab.editseries` / super-admin see the
    group's hidden («++مخفي++») and empty («فارغة» = nothing the series page would list) series; private response;
    visitors unchanged.
  - **F03: RESOLUTION PROPOSED** (2026-11-16). Report: `docs/reports/admincp-b2-f03-resolution-proposal.md`.
    F03-A approved as A2 (above; data repair not run); F03-B implemented (above).
    - **F03-A, refined scope:** **22 visible series** with `count ≤ 0` (7 at 0, 15 negative) holding 316 of their own
      visible items (e.g. 15627, 15495–15509, 15616), not B2's "45". 38 of those 45 hold only other authors' items,
      and 25 are hidden.
    - **Root cause (historical):** the 2025-09-15/16 renumbering of 680 series (`old_id = id − 800`), done by no code
      in any tree. No reachable writer creates such counters; series created since are consistent.
    - **Recommended:** a targeted, guarded `count` repair of the 22 rows (owner-run SQL, row backup, trivial rollback).
      **«إصلاح العدادات» rejected as unsafe:** for author 154 it would regroup 1,149 items of 44 other authors and
      inflate 82 series. A read-path change was **not** recommended: it diverges from Gen 2 / OLD and leaves 0 / negative
      counts on cards.
    - **F03-B (admin view of empty series on the group page):** design proposed with `khotab.editseries`
      (alternative: any admin); not implemented.
    - **Separate, not proposed:** renumbering damage — 8,187 foreign items in 565 series, 1,349 orphaned items, 19
      copy series 16082–16100 with false counts.
    - The reported series id is still needed.
  - **F07-A application safeguards: IMPLEMENTED LOCALLY — CLOSED LOCALLY (2026-11-16) after validation passed; not deployed** (2026-11-16, decision-log #147, report
    `docs/reports/admincp-b2-f07a-implementation-results.md`):
    - the Variety invalid-link notice;
    - route-exact download buttons;
    - a warn-only editor check (decision 22, Variety);
    - ASF / RAM / RA / MPG added to the unsupported list.

    **The original complaint is not resolved; data corrections D1–D9 are not done.**
  - **F07-B2 repair command + GetRight filter + hidden-mirror download: IMPLEMENTED LOCALLY — CLOSED LOCALLY (2026-11-16) after validation passed; not deployed;
    REPAIR NOT EXECUTED** (2026-11-16, decision-log #148, report `docs/reports/admincp-b2-f07b2-implementation-results.md`).
    - `php artisan anasheed:repair-f07-links`: 46 approved rows, byte-exact (`HEX(link)`), backup, audited, rollback.
    - The local dry run shows 46 pending.
    - Production prerequisites are in the report (§10).
  - **F07-B1 data-repair plan: INVESTIGATION COMPLETE** (2026-11-16). Report
    `docs/reports/admincp-b2-f07b-data-repair-plan.md`; inventory `docs/reports/admincp-b2-f07b-inventory.csv`.
    The HIGH wave was approved and implemented as F07-B2 (above); MEDIUM / LOW still await decisions.
    - **267 records** need a decision (235 items, 32 mirrors).
    - **58 HIGH**, 28 of them HEAD-verified on archive.org, including all 27 host-less `/download/` items.
    - **184 MEDIUM**, mostly 173 host-less `/cartoon/` items: `vars` host, unverified.
    - **25 LOW**, including 11 truncated `media-w..._hane_hlme` values (404).
    - **31 legacy third-party-host rows** for redirect-safety review.
    - **Proposed:**
      - a manifest-driven, byte-exact (`utf8_unicode_ci` is PAD SPACE / case-insensitive), audited repair command
        with backup and rollback (not implemented);
      - a one-line GetRight filter (broken items are listed today);
      - a host-allowlist decision (no open redirect; the stale third-party hosts are the residual risk).
  - **F07: RESOLUTION PROPOSED; ORIGINAL REPORT STILL UNIDENTIFIED** (2026-11-16). Report:
    `docs/reports/admincp-b2-f07-resolution-proposal.md`. F07-A (above) implemented; data corrections await approval.
    - **Malformed Variety links:**
      - 8 concatenated file names (16755–16759, 15030, 12581, 12195);
      - 14823 starts with a stray «ر» (not `?`, as B2 said);
      - 2 empty (6485, 10796);
      - 202 host-less paths: 27 `/download/…`, three of them HEAD-verified on archive.org; 175 `/cartoon/amin2|3/…`,
        `vars` host by sibling evidence, unverified;
      - 7 broken mirrors.
    - The malformed `.mp4` rows still render a `<video>` that fails ("player appears but fails"). F05-2 Part 2 already
      covers Variety's six non-browser formats.
    - **Download routes:** malformed links redirect to local site paths (empty → home page); the page's download
      buttons stay visible.
    - **Proposed:**
      - per-row data corrections with evidence (D1–D9);
      - an optional player guard for non-absolute links;
      - optionally hiding the misleading buttons;
      - extending the format list with ASF / RAM / RA / MPG (3GP / OGV / M4V / OGG are browser-dependent: owner);
      - a **warn-only** editor check (decision 22).
  - **No existing decision was changed.**
- **Approved and run locally:** B7a validation only (see `docs/reports/admincp-b7a-download-validation.md`).
  **Owner decision (2026-11-14): the browser Blob download is NOT approved for B7b.** The reasons were archive.org MP4
  CORS, memory use, no mobile validation, and no guaranteed download on fallback. The B7a report is kept as evidence.
  Expanding the PHP proxy is not approved at this stage either. Alternatives are compared in
  `docs/reports/f04-download-architecture-comparison.md` (research only).
- **F01:** scope confirmed (§F01.0). **B6a and B6b closed locally (not deployed); B6c-1 closed locally (not deployed, nothing activated or placed); B6c-2 not started.**
- **Everything else** remains a plan awaiting approval.

**Inputs:**

- the investigation `docs/reports/admincp-production-feedback-investigation.md`;
- the owner's approved business decisions for F01, F02, F04 and F08 (2026-11-14).

**At the time of writing (before approvals), nothing in this plan had been implemented.** No code, database, configuration, scheduler or production change was
made while writing it. Evidence came from:

- read-only code reading in Laravel, Gen 2 (`7amlat`) and OLD (`old-project`);
- read-only `SELECT`s on the local MySQL 5.7 `olddb` copy (the 2025 dump);
- three anonymous `HEAD`/`Range` requests to public archive.org files, with no project data sent.

**Labels used throughout:**

- **CONFIRMED:** verified in code or data during this planning pass or the investigation.
- **INFERRED:** follows from code reading but was not executed.
- **UNVERIFIED:** needs production evidence.

---

## 0. Summary

| Track | Items | Nature | Batch | Deployment risk |
|---|---|---|---|---|
| **A. Low-risk defect fixes** | F06, F05-1, F02-UI, F09 | Small, isolated code fixes; no schema change | B1 | **Low** |
| **A′. Evidence-gated fixes** | F03, F05-2, F07 | Same size, but blocked on owner evidence (ids and URLs) | B2 | **Low** |
| **B. Legacy parity migration** | F01 (ads, banners_ads, report_ads) | Port OLD functionality onto the existing tables; secure equivalents for unsafe behaviour | B6a–B6c | **Medium** (public rendering) |
| **C. New features** | F02 deactivation, F08 hide, F10 custom thumbnail | New behaviour; F02 and F10 add one Laravel-owned table each | B3, B4, B5 | **Medium** (F02 is security-sensitive) |
| **D. Download infrastructure** | F04 «حفظ المادة» | Changes how non-Khotab downloads are delivered | B7a (spike), B7b | **Low** (client-side option) or **High** (proxy option) |

**Key planning findings that change the investigation's picture:**

1. **F02.** Deactivating in Laravel alone is **insufficient**, for three independent reasons:
   - `admin:sync-permissions` rebuilds Spatie grants from `nuke_authors` (it would re-grant a removed role);
   - Gen 2 admincp authenticates against `nuke_authors` directly;
   - the OLD admin bridge authorises from `nuke_authors.uid` on every request for up to **30 days** from a signed
     cookie, with **no password check**.

   See §F02.
2. **F04.** archive.org sends **no `Content-Disposition`**, with or without `?download=1`, so an attachment response
   from the source is **impossible**. It does send `Access-Control-Allow-Origin: *` and supports ranges, which makes a
   **client-side save without a server proxy** feasible for archive.org sources.
3. **F04 scope.** Most non-Khotab sources are formats that browsers **already download** rather than play (`.wmv`,
   `.rmvb`, `.flv`, `.rm`). The problem is confined to `.mp4` and `.mp3`.
4. **F08.** Gen 2's public `anasheed/item.php`, `vars/var.php` and `vars/download.php` **do not filter `hidden`**. If
   Gen 2 public URLs are still served anywhere, a hidden clip stays reachable there. Laravel cannot fix that.
5. **F10.** `nuke_islamic_khotab.thumbnail` is **not free**: 6,467 rows hold archive.org thumbnail URLs (ids ≤ 32,180).
   `build_thumb` is 0 on all 292,592 rows. Neither column may be repurposed; the plan uses a new Laravel-owned table.
6. **F06.** Setting `author_image` fixes only **part** of the display. The series, group and item pages and Fatwa
   «by-authors» deliberately ignore `author_image` (Gen 2 `get_author_img()` parity) and read
   `media/authors/sq/{id}.png`. See §F06.

---

## 1. Approved business decisions (as received)

| ID | Decision |
|---|---|
| F01 | Migrate the OLD advertising system (`ads`, `banners_ads`, `report_ads` plus public rendering). Functional parity only, no redesign, existing tables, no new features. Keep it distinct from Advfiles («ملفات الرئيسية»). Follow AdminCP conventions and coexist with legacy. Replace unsafe behaviour with the smallest secure equivalent. **Do not activate ads; do not modify data.** |
| F02 | Administrators are **deactivated, never deleted**. Super-admin only. Keep the account and the audit history. Revoke access, with safe reactivation. No self-deactivation, and the last active super-admin cannot be deactivated. Existing sessions and future requests both lose access. Hide permission-editing controls from unauthorised users, with backend authorisation kept. The cross-system mechanism must be investigated first. |
| F04 | «حفظ المادة» must really save the file, for all modules, with playback kept. Renaming is not a fix; the `download` attribute does not work cross-origin. Determine archive.org support. If a proxy is needed, estimate its capacity. Keep the production capacity configuration; do not auto-extend the 400 slots; do not change unreleased telemetry without review. Identify the sources where a guaranteed download is impossible. A controlled browser test is required. |
| F08 | Hide a variety clip using the existing `hidden` field. Hidden from listings, search, home, related widgets and group pages. Direct URL **404 for visitors**. Playback and download refused. AdminCP keeps visibility, with a toggle, a permission and an audit entry. Cache invalidation is included. `is_published` is **not** redefined. Legacy exposure is documented. |
| Others | F03, F05, F06, F07, F09 and F10 remain in scope per the investigation, under the constraints in §§F03–F10. |

---

## 2. Feature plans

### F01: OLD advertising migration (Track B, parity)

#### F01.0 Owner scope confirmation (2026-11-14)

The owner confirmed that the advertising parity migration **must include**:

| # | Item |
|---|---|
| 1 | the existing advertising administration module (`ads`: A1–A11) |
| 2 | the legacy `banners_ads` module (B1–B4) |
| 3 | the legacy `report_ads` module (R1–R4) |
| 4 | the existing public advertising positions (P1–P12) |
| 5 | **the legacy popup advertisement** (position 12, P11) |
| 6 | **the legacy homepage banner strip** (P13) |

Constraints that apply:

- **Functional parity**, and **no new advertising features**.
- **All public advertising stays disabled by default** through the global safety switch `ads.rendering_enabled`
  (env default `false`). That covers the popup and the banner strip.
- **F01 is not implemented in this batch.**

This resolves decisions 7 (pos 12 popup: **port it**) and 8 (banner strip: **port and place it** on the home page as
OLD did, still behind the switch) in §9. The parity entries P11 and P13 change from "owner choice" to "in scope".

Still open for F01: decisions 1–6 and 9–11; the existence of `media/banner/{b}/{id}.gif` in production (E8). If those
files are absent, the strip's large banner renders the item link without an image, as OLD would.

#### F01.1 Inventory

**Entry points:**

| Legacy | Ops | Laravel today |
|---|---|---|
| `old-project/modules/ads/admin/index.php` (1,657 lines) and `case.php` | `w2a_ads_main` (list and filter), `w2a_ads_newads` / `add_ads`, `w2a_ads_editads` / `edit_ads`, `w2a_ads_delads` / `del_ads`, `update_show` (GET toggle), `w2a_ads_reports` / `view_report` | none (`HomeAd` model on `nuke_ads`, unused) |
| `old-project/modules/banners_ads/admin/index.php` | `w2a_banners_ads_main` (filter items, then replace the set) | none |
| `old-project/modules/report_ads/admin/index.php` | `w2a_bad_ads_main` (list), `w2a_bad_ads_delads` (delete) | none |
| `old-project/modules/ads/admin/increase_click_Ajax.php` | unauthenticated click counter | none |
| `old-project/get_cats_with_conditions_ajax.php` | category cascade for banners_ads filters | Laravel has its own category cascades (to reuse) |
| `way2allah_functions.php:423` (OLD) / `7amlat/home_functions.php:437` (Gen 2) | `show_ads_byposition()`, `switch_on_ads_type()`, `hide_ads()`, `ads_availability()`, `check_percentage_in_position()` | none |
| `way2allah_functions.php:557` | `get_random_three_banners()`, `get_random_two_montada_banners()` | none |
| `assets/frontend/layout/scripts/inc.js` | `increaseclick(id)`, `link_this_ads(id)` (fancybox popup) | none |

**Tables** (connection `main`; all exist; **no schema change planned**):

| Table | Columns | Local dump rows |
|---|---|---|
| `nuke_ads` | `id, name, image_path, position, percentage, type, ads_show_type, required_num_view, show, link, startdate, enddate, num_view, num_click, path_type` | **1** (`show = 0`, position 4, malformed link `ttps://…`) |
| `nuke_banners_ads` | `id, media_id, creation_date` | several (ids from 968, dated 2019-12-01) |
| `nuke_report_for_bad_ads` | `id, user_email, ads_url, db_creation_date` | **0**; **no writer exists** in OLD or Gen 2 |
| `nuke_banners` (read-only dependency) | `image1/url1/title1, image2/…` forum banners for the home strip | present |
| `nuke_modules.admins` (read-only dependency) | CSV of admin names per module | `ads`: 5 names; `banners_ads`: 3; `report_ads`: 2 (names withheld here; see the dump) |

`show` is a MySQL reserved word. Eloquent quotes it; any raw SQL must use backticks.

**Legacy permissions (CONFIRMED):** access is granted to `radminsuper = 1` **or** to an admin whose `name` appears in
`nuke_modules.admins` for that module. **Not** through the `nuke_authors.permissions` blob, so
`admin:sync-permissions` never maps it.

**Positions** (from `position_array`):

| Pos | Label | Size | OLD page(s) rendering it | Gen 2 live | Laravel page that would host it |
|---|---|---|---|---|---|
| 1 | يسار الرئيسية | 120×600 | home | no | home |
| 2 | وسط الرئيسية | 120×240 | home | no | home |
| 3 | يمين الرئيسية | 120×240 | home | **yes** (`new_content.php:333`) | home |
| 4 | تفاصيل المادة علوي | 728×90 | Khotab `Detailes`, Anasheed `Detailes`, w2acd `Detailes` | no | `khotab-item`, `var-item`, w2acd item |
| 5 | تفاصيل المادة جانبي | 250×250 | Khotab `Top10` (`has_ads && details`), Anasheed `Top10`, w2acd `Top10` | no | the item sidebar top-10 widget |
| 6 | دروس ومحاضرات صوتية | 250×250 | Khotab `Top10` (audio) | no | the Khotab audio sidebar top-10 |
| 7 | صفحة الشيخ علوي | 728×90 | Khotab `Author` / `Group` / `Series` | no | `khotab-author`, `khotab-group`, series |
| 8 | صفحة الشيخ جانبي | 250×250 | Khotab `Top10` (other) | no | the Khotab sidebar top-10 |
| 9 | صفحة المنوعات علوي | 728×90 | Anasheed `Group`, w2acd `Group` (decision-log #91 W2: **owner-deferred**) | no | `var-group`, w2acd group |
| 10 | صفحة القناة علوي | 728×90 | satellite channel lists | no | channel pages |
| 11 | عرض الديالوج سفلي | 468×60 | Khotab, Anasheed and w2acd `Play` | no | the player dialog or modal, **if Laravel has one** (UNVERIFIED; to locate in B6c) |
| 12 | بداية تحميل الصفحة | 500×700 | home (fancybox popup on load) | no | home (needs a modal; fancybox is not a Laravel dependency) |

**Shares** (`percentage_array`): 100 كامل, 50 نصف, 33 ثلث, 25 ربع. **The public renderer ignores the share.** It is
used only by the reports and availability helpers (`check_percentage_in_position`).

#### F01.2 Parity matrix

Key: **P** = ported as is. **S** = secure equivalent (same visible behaviour, safe implementation). **F** = legacy
defect fixed (owner confirmation needed where marked ⚑). **X** = not ported.

**Admin: `ads`**

| # | OLD behaviour | Laravel equivalent | Class |
|---|---|---|---|
| A1 | List with filters: name, start/end date, show, type, position | `admincp/ads` index with the same five filters, paginated (OLD unpaginated; only 1 row today) | P |
| A2 | Add: name, `type` (1 image, 0 code), `path_type` (1 upload, else URL), position (1–12), percentage (100/50/33/25), `show`, link, `ads_show_type` (`period` → start and end dates; `numview` → `required_num_view`) | Form with the same fields and Arabic labels; FormRequest validation (enumerated position, percentage and type; dates Y-m-d with end ≥ start; integer ≥ 1 for `required_num_view`; link `http`/`https` URL) | S |
| A3 | Edit: the same fields, plus counters shown | Same form; `num_view` and `num_click` shown read-only | P |
| A4 | Delete: confirm page, then hard `DELETE` | Confirm, then `DELETE` (hard, as OLD), via POST and CSRF. The full row snapshot goes to `admin_audit_log` metadata, so a deletion is recoverable by hand | S |
| A5 | `update_show`: **GET** `ads_id` & `sh`, SQL-injectable | POST toggle with CSRF, bound model, audited | S |
| A6 | Upload (`uploadfile()`): **any extension** to `/home2/w2acp/public_html/media/bann/{time}.{ext}`; stores the absolute `https://way2allah.com/media/bann/{time}.{ext}` | Image allow-list `jpg`, `jpeg`, `png`, `gif`, `webp` (MIME sniffed, not by name; **no SVG or HTML**); size limit (proposed 2 MiB ⚑); written under the configured media root `bann/` with a random name; **stores the same absolute-URL shape** in `image_path`, so OLD and Gen 2 renderers keep working | S |
| A7 | Type 0 «Google AdSense»: raw HTML/JS stored in `image_path` and echoed verbatim | ⚑ **Owner choice:** (a) keep raw code, but only super-admins may create or edit type-0 ads, every change is audited, and the code is rendered only inside its slot; or (b) an allow-list that accepts only the Google AdSense `<script async src="https://pagead2.googlesyndication.com/…">` and `<ins class="adsbygoogle" …>` structure. **Recommended: (a)**, because (b) is a new feature and (a) matches OLD's trust model with tighter access | S |
| A8 | Reports: `view_report`, charts per position from `ads_availability` and `check_percentage_in_position` (share sum over overlapping dates) | Read-only report page: per position, the active and scheduled ads and the share sum over overlapping date ranges. Same arithmetic, rendered as tables (OLD's chart library is not ported ⚑, no new charting) | P/S |
| A9 | Availability check exists but is **not** enforced on add or edit | Not enforced (parity). Shown as an informational warning on the form only if the owner wants it ⚑ (default: no warning) | P |
| A10 | SQL injection in every op | Eloquent and bindings throughout | S |
| A11 | Access: `radminsuper` **or** the name is in `nuke_modules.admins` | New Spatie permissions `ads.main` and `ads.reports`; the `super-admin` role passes. Assignment: see F01.3 | S |

**Admin: `banners_ads`**

| # | OLD behaviour | Laravel equivalent | Class |
|---|---|---|---|
| B1 | Filters: author dropdown, category cascade (`get_cats_with_conditions_ajax.php`); lists matching items and the current banner set (`get_all_banners`) | `admincp/banners-ads`: the same filters through existing Laravel category and author lookups; the current set is shown with item titles | P |
| B2 | Save: `DELETE FROM nuke_banners_ads`, then one `INSERT media_id=` per id (full replacement, injectable) | The same full replacement **inside one transaction**; ids validated as existing Khotab items; audit entry with the before and after id lists | S |
| B3 | Menu shares the `w2a_ads_*` case names | Separate menu entry under one «الإعلانات» group | P |
| B4 | Access: module admins for `banners_ads` | Permission `banners_ads.main` | S |

**Admin: `report_ads`**

| # | OLD behaviour | Laravel equivalent | Class |
|---|---|---|---|
| R1 | List `nuke_report_for_bad_ads ORDER BY id DESC` | Read-only list, paginated | P |
| R2 | Delete by id | POST and CSRF delete, audited | S |
| R3 | **No public writer exists** (table has 0 rows) | No writer added (that would be a new feature). The screen will be empty; documented | P |
| R4 | Access: module admins for `report_ads` | Permission `report_ads.main` | S |

**Public rendering**

| # | OLD/Gen 2 behaviour | Laravel equivalent | Class |
|---|---|---|---|
| P1 | Random ad for the position with `show = 1` | One query: `position = ? AND show = 1 AND eligible ORDER BY RAND() LIMIT 1` (the table is tiny, so `RAND()` is acceptable) | P |
| P2 | `period` eligibility `curdate > startdate && curdate <= enddate`, so **the start day is excluded** | ⚑ `startdate <= curdate <= enddate` (fix), or keep the exclusive start (parity). **Recommended: fix** | F⚑ |
| P3 | Not yet started: **recursion without exit** (infinite loop if it is the only ad) | Filtered out in SQL; no recursion | F |
| P4 | Ended `period`, or `numview` with `num_view > required_num_view`: `hide_ads()` sets `show = 0`, then retries | Filtered out in SQL. ⚑ Whether to keep the write-back `show = 0` from a public request: **recommended keep** (parity; the admin list then shows it as stopped). It is a single bounded `UPDATE … WHERE id = ? AND show = 1` | P⚑ |
| P5 | Recursive output discarded when `$return = true` | Not applicable (no recursion) | F |
| P6 | No ad found: default images `images/ads3.jpg` (pos 1) and `images/ads.jpg` (pos 2/3) linking `#` | ⚑ **Owner choice.** Laravel home **does not show these today**, so rendering defaults is a visible change even with zero active ads. **Recommended: render nothing** when no ad is eligible (keeps today's page) | ⚑ |
| P7 | Type 0: echo raw `image_path` | Echo raw (A7 decides who may author it) | P |
| P8 | Type 1: wrapper div per position (`ads_details`, `ads_details_2`, `ads_details_bottom`, `ads_dialog`); `<a href=link onclick=increaseclick(id) target=_blank rel=nofollow><img height=250 src=…>`; `/images/`→`/media/` and http→https rewrites | A Blade component `<x-ads.slot :position="n"/>` with the same wrapper classes and the same rewrites. The link goes to `GET /ads/click/{id}` (P10). `rel="nofollow noopener sponsored"` ⚑ (`sponsored` is optional) | P/S |
| P9 | Every render: `num_view = num_view + 1` (a write per page view) | Kept (parity), as `increment()`. ⚑ **Performance note:** on the home page this is one write per view per rendered slot. Pages served from cache would not count | P⚑ |
| P10 | Click: AJAX POST to `increase_click_Ajax.php`, unauthenticated, injectable, **always writes `num_click = 1`** | `GET /ads/click/{id}`: `num_click + 1`, then a 302 to the ad's **stored** link (not an open redirect: the target comes from the DB). Throttled per IP. JS is not needed. Fixes the "always 1" defect | S/F |
| P11 | Position 12: fancybox popup 700×500 on page load, `link_this_ads(id)`, hidden input `ads_link_url` | A minimal `<dialog>` modal (no fancybox dependency), shown once per page load, with close; the click goes through P10. ⚑ Whether to port pos 12 at all (it is the most intrusive) | P⚑ |
| P12 | Placements per page (F01.1 table) | Slots added to the Laravel views listed in F01.1. **Pos 9 stays deferred** (decision-log #91 W2) unless the owner lifts it | P |
| P13 | Home banner strip (`index.php:347`): 2 forum banners from `nuke_banners` plus one random `nuke_banners_ads.media_id` → `media/banner/{floor(id/1000)}/{id}.gif` → `khotab-item-{id}.htm` | ⚑ **Gen 2 commented it out** (`new_content.php:210`) and Laravel has none. Recommended: port the strip as a component but **leave it unplaced** until the owner decides. The `media/banner/*.gif` files are UNVERIFIED in production | P⚑ |

**Global safety switch.** All public slots are wrapped in `config('ads.rendering_enabled')` with an env default of
**`false`**.

- This is a deployment-safety guard, not a feature. With it off, the deployed code renders nothing and writes nothing.
- It is turned on only by explicit owner action.
- Even when on, nothing shows until an ad has `show = 1`. The only existing row is `show = 0`.

#### F01.3 Permissions and coexistence

- **Permissions.** Seed `ads.main`, `ads.reports`, `banners_ads.main` and `report_ads.main` (guard `admin`, namespace
  `{module}.{key}`).
- **Who gets them.** Legacy rights live in `nuke_modules.admins` (names), which the sync does not read.
  - **Options:** (a) the owner assigns them via the permission editor after deploy; or (b) extend
    `SyncLegacyAdminPermissionsAction` to map `nuke_modules.admins` CSV names for these three modules.
  - ⚑ **Recommended: (a)**. It is a manual step for about 5 people. (b) is a new mapping rule with name-matching risk:
    Arabic names, trailing commas, a test account `test-1`.
- **Menu.** A «الإعلانات» group in `AdminDashboardModules` holds the three entries, each gated by its permission. It
  stays separate from Advfiles («ملفات الرئيسية», `khotab.addadv`).
- **Coexistence.**
  - OLD's ad admin may still be reachable through the nuke bridge; both write the same rows with last-write-wins. No
    locking is added (parity).
  - The image URL shape is unchanged, so OLD and Gen 2 renderers display Laravel-uploaded images.
  - The Gen 2 public pos-3 renderer keeps working against the same rows.
- **Data.** No migration, no backfill, no edits to the existing row.

#### F01.4 Affected files (planned)

**New:**

- `app/Domain/Admin/Http/Controllers/AdsAdminController.php`, `BannersAdsAdminController.php`,
  `ReportAdsAdminController.php`;
- FormRequests;
- `app/Domain/Admin/Actions/Ads/*` (Store, Update, Delete, ToggleShow, UploadAdImage, ReplaceBannerSet,
  DeleteBadAdReport);
- `app/Domain/Content/Services/AdSlotService.php` (selection, eligibility, view and click counting);
- `app/Http/Controllers/AdClickController.php` (or under the Content domain);
- `resources/views/admin/ads/*`, `admin/banners-ads/*`, `admin/report-ads/*`;
- `resources/views/components/ads/slot.blade.php`, `components/ads/popup.blade.php`;
- `config/ads.php`;
- a permission seeder entry.

**Edited:**

- `routes/admin.php`, `routes/content.php` (click route);
- `AdminDashboardModules.php`;
- `app/Domain/Content/Models/HomeAd.php` (casts, constants for positions and shares);
- public views per F01.1 (home, Khotab item, author, group and series, Anasheed item, channel, w2acd; pos 11 if a
  player dialog exists).

#### F01.5 Security

| Risk | Mitigation |
|---|---|
| SQL injection | bindings |
| Arbitrary upload (PHP or HTML) | MIME allow-list, re-encode-free but random names, no executable extension, stored under media (ensure the web server does not execute PHP in `media/bann`: UNVERIFIED production `.htaccess`, see below) |
| GET state change | POST and CSRF |
| Stored XSS via type-0 | restricted authorship (A7) |
| Open redirect | the click target is read from the DB only |
| Click inflation | throttle |

The `media/bann` check is **pre-existing legacy exposure**: OLD already wrote arbitrary extensions there. Recommend
the owner check for unexpected files in `media/bann/`.

---

### F02: administrator deactivation (Track C, new feature, security-sensitive)

> **B3 / F02 — FINAL ARCHITECTURE DECIDED, NOT IMPLEMENTED (2026-11-16).** Report:
> `docs/reports/admincp-b3-security-design-review.md` §13.
> - **Topology:** the latest owner evidence (2026-09-24 cutover) has Gen 2, OLD **and** Laravel (`main` and default)
>   on **`w2acp_db2025-2`**, on one MariaDB 10.11.19 server. The "Laravel = `w2acp_laravel`" premise used in the
>   P0-A final review is the **pre-cutover** state and is superseded. **Re-verify (C1) before B3.**
> - **Decision:**
>   - one state table, `admin_account_states`, in `w2acp_db2025-2`, created by a Laravel `main` migration;
>   - Gen 2 and OLD read it through their existing connections, with no grant change and no cross-database
>     transaction;
>   - `uid` is kept; credentials are neutralised in the same transaction as defence in depth;
>   - with P0-A's `cv`, this ends open Gen 2 AdminCP sessions immediately.
> - **Sequence:**
>   - Laravel: migrations, then enforcement, then actions and UI, then enable;
>   - Gen 2: G1, G2, G3 / E2;
>   - OLD: O1, O2, O4, plus the O3 web-server deny;
>   - an owner smoke test.
> - **Owner decisions: §13.9.** No code, schema or data was changed.

> **SECURITY P0-A (2026-11-16): D1/D2 + EDITOR HOTFIX IMPLEMENTED AND TESTED LOCALLY — NOT DEPLOYED.**
> - **Trusted identity:** a credential-only `$_SESSION['w2a_admincp']` (`id` and `auth_at`), set only after the
>   password check, with `session_regenerate_id(true)`. It is re-read from `nuke_authors` on every AdminCP request and
>   cleared on logout.
> - **Authorisation:** comes only from that key; `$_SESSION['w2a']` is display-only and re-synced from the DB.
> - **Editors:** super-admin-only permission edits and other-admin password resets; self-change needs the current
>   password; POST with CSRF; validated and existing targets; whitelisted permission keys; parameterised writes;
>   transactional `admin_audit_log` entries without secrets.
> - **Also fixed:**
>   - the `main.php` module-`index.php` login-POST gate bypass;
>   - `RenameUser()` and the default-password literal removed.
> - **Tests:**
>   - disposable-DB HTTP suite **83/83** (pre-fix code: 60 failures, confirming poisoning, takeover and SQL injection
>     at runtime);
>   - static **28/28**; bridge checks **19/19**.
> - **Deploy prerequisites:**
>   - `admin_audit_log` in production (migration approval);
>   - rotate the 15 default-password accounts;
>   - remove the `var_dump` lines;
>   - `admincp/backup/edit_author.php` is git-ignored (copy it manually or delete it).
> - Report: `docs/reports/security-p0-gen2-admin-editor-implementation-results.md`.
>
> **SECURITY P0-A — CLOSED LOCALLY · NOT DEPLOYED · PRODUCTION RELEASE BLOCKERS OPEN (final closure, 2026-11-16).**
> - **Credential-bound AdminCP sessions:** HMAC `cv` with the dedicated `W2A_ADMINCP_SESSION_SECRET`; fails closed;
>   a reset ends the target's sessions; a self-change keeps only the current session.
> - **Audit:** a diff-only permission audit in `nuke_islamic_logs`.
> - **Backup editor:** an `.htaccess` deny was prepared and tested on a local Apache.
> - **Tests:** HTTP 116/116, static 38/38, bridge 19/19.
> - **Blockers (owner-operated):**
>   - provision the secret;
>   - InnoDB check;
>   - rotate the 15 accounts;
>   - backup-editor deny;
>   - Laravel admin-login status;
>   - file baseline backup.
> - Checklist: `docs/reports/security-p0-gen2-admin-editor-implementation-results.md` §C.5.
>
> **P0-A final review (2026-11-16):**
> - **Changes:** audit moved to Gen 2's own `nuke_islamic_logs` (production Gen 2 = `w2acp_db2025-2`, Laravel =
>   `w2acp_laravel`); debug output removed.
> - **Tests:** HTTP 90/90, static 31/31.
> - **Release blockers remain:**
>   - audit-store approval;
>   - production InnoDB check;
>   - rotate the 15 accounts;
>   - backup-editor deny/remove;
>   - session-revocation decision;
>   - Laravel production login divergence.
> - See the report's §0.7.
>
> *(Superseded:)* **SECURITY P0-A implementation (2026-11-16): BLOCKED — the local hardening is insufficient; no code changed.**
> Gen 2 public pages (`w2a_header()` → `VBUserType()`) write the AdminCP session's `usertype` / `admin` from a forged
> `admin_from_vb` / `bbuserid` cookie (session poisoning into any admin). There is no session regeneration at login.
> Dependency fix D1 / D2 (a credential-only `$_SESSION['w2a_admincp']` key + regeneration) awaits approval. Report:
> `docs/reports/security-p0-gen2-admin-editor-implementation-results.md`.
>
> **SECURITY P0-A (2026-11-16): INVESTIGATION COMPLETE — HOTFIX PREPARED, NOT APPLIED; PREREQUISITE FOR B3.**
> Report: `docs/reports/security-p0-gen2-admin-editor-hotfix-plan.md` (decision-log #149).
>
> - **Confirmed live account takeover:** Gen 2 `admincp/authors/edit_author.php` (permissions + any admin's `pwd`)
>   and `admincp/backup/edit_author.php` (permissions, SQL injection, GET writes).
> - **The 15 shared accounts hold the hard-coded default password** from Gen 2's admin-creation code. **Rotate now.**
> - **Fix:** a database-backed super-admin check, CSRF, self-service only for one's own password, audit. Sound only
>   after the hardened `main.php` / `index.php` (P0) are deployed.
>
> **B3 security design review (2026-11-16): COMPLETE — AWAITING OWNER DECISIONS; B′ NOT APPROVED; NOTHING
> IMPLEMENTED.** Report: `docs/reports/admincp-b3-security-design-review.md`.
>
> - **Recommends Option B:**
>   - Laravel state on `main`;
>   - credential neutralisation (a non-empty `password` marker, a `pwd` marker, `API` NULL) with **`uid` preserved**;
>   - mandatory legacy patches P0 (deploy the hardened copies), L1 (Gen 2 per-request state check), L2 (OLD bridge
>     state check, super-admin shortcut removal) and L3 (Gen 2 forum hook).
> - **Complete deactivation is not possible without legacy changes.**
> - **New urgent finding V8:** Gen 2 `edit_author.php` lets **any** admin session rewrite any admin's permissions
>   (SQL-injectable) and reset any admin's `pwd`. Combined with the 15 shared-value accounts (Gen 2 plain-text
>   fallback) and 3 super-admins on `pwd`, this is a takeover chain. **Rotate and fix E1 first.**
> - The review includes a read-only production verification checklist (marker strings + SHA-256 references).
>
> **Earlier B3 investigation (2026-11-16):** `docs/reports/admincp-b3-admin-deactivation-security-proposal.md`
> (B′ with a `uid` sentinel; superseded on the `uid` question by the review above).
>
> - **Recommended:** Option B′. That is a Laravel state table (proposed on `main`) enforced by `AdminGuard`, the sync,
>   the backup API and the CLI actors, plus, in the same transaction, unusable password markers, a unique negative
>   `uid` sentinel and a cleared `API` key on `nuke_authors`.
> - Two optional owner-applied legacy patches (L1 Gen 2, L2 OLD) close the surviving-session gaps.
> - **The report corrects §F02.2 below:**
>   - `pwd` is `varchar(40)`, so the 64-hex marker does not fit;
>   - `uid = 0` collides with 2 rows and the backup API, so use `-id`;
>   - the state table belongs on `main` for atomicity.
> - **Blockers found:**
>   - the committed `7amlat` / `old-project` code trusts forgeable cookies (only the uncommitted working copies are
>     hardened; production version unverified);
>   - 15 accounts share one 9-character non-hash `pwd`, accepted by Gen 2's plain-text fallback;
>   - Gen 2 public pages treat any forum user id cookie as admin.

#### F02.1 Cross-system identity analysis (CONFIRMED by code reading)

| System | How an admin authenticates | What it re-checks per request | Effect of a Spatie role removal alone |
|---|---|---|---|
| **Laravel AdminCP** (`AdminGuard`) | `aid` + `password`/`pwd` (`LegacyPasswordVerifier`; bcrypt rehash on login) | Session holds only the id; **`user()` re-reads `nuke_authors` by id every request** | Permission routes 403, but `admin.role` (no role argument) still passes. Not sufficient |
| **Spatie grants** | — | — | **Rebuilt by `admin:sync-permissions`** from `nuke_authors.radminsuper` and the `permissions` blob. A removed role returns on the next run |
| **Gen 2 admincp** (`7amlat/admincp/index.php`) | `aid` + `password` (bcrypt) or `pwd` (md5 / sha1 / **plain text**) against `nuke_authors` | **Nothing**: `$_SESSION['w2a']->admin` and `usertype` are a **snapshot at login** (`main.php:43-48`) | none |
| **OLD admin** (`old-project/mainfile.php:390-470`, the nuke bridge) | HMAC cookie `w2a_user = uid.issued_at.mac`, **30-day** lifetime, produced by Gen 2 login | `SELECT * FROM nuke_authors WHERE uid = …` every request. **No password check** | none |
| **vBulletin** | — | Gen 2 loads `user WHERE userid = nuke_authors.uid` at login for group-based sidebar permissions | none |

**Consequences:**

1. The deactivation state must be **enforced by `AdminGuard`**, which covers existing Laravel sessions at the next
   request.
2. The sync must **skip** deactivated admins.
3. **Gen 2 future logins** can be stopped only by making the stored credentials unusable.
4. **OLD bridge sessions** can be stopped only by making the `uid` lookup fail.
5. **Existing Gen 2 PHP sessions cannot be revoked from the database.** They live until PHP session expiry
   (`session.gc_maxlifetime`, UNVERIFIED in production; the PHP default is 1,440 s of inactivity). This is a documented
   **residual risk**.

#### F02.2 Chosen persistence mechanism (proposed)

1. **A new Laravel-owned table `admin_account_states`** on the default connection, alongside Spatie and
   `admin_audit_log`. **No schema change to `nuke_authors`.**

   | Column | Notes |
   |---|---|
   | `admin_id` | unique; `nuke_authors.id` |
   | `deactivated_at` | nullable |
   | `deactivated_by_admin_id` | |
   | `reason` | nullable, ≤ 500 |
   | `original_uid` | nullable |
   | `reactivated_at` | nullable |
   | `reactivated_by_admin_id` | nullable |
   | `timestamps` | |

   The row is kept forever (history); reactivation clears `deactivated_at`. Every transition is also written to
   `admin_audit_log`.

2. **Laravel enforcement:**
   - `AdminGuard::user()` returns `null` (and forgets the session key) when the admin is deactivated. Existing sessions
     drop at their next request; every `admin.role` route returns 401, then redirects to login.
   - `resolveValidAdmin()` refuses deactivated admins at login.
   - The deactivation set is cached briefly (≤ 60 s) with explicit invalidation on change, to avoid one extra query per
     request. ⚑ **Alternatively, no cache** (one indexed lookup per admin request; admin traffic is small).
     **Recommended: no cache.**
   - `SyncLegacyAdminPermissionsAction` skips deactivated admins and **does not remove their Spatie grants**. Grants are
     preserved for reactivation, and inert because the guard refuses the user.

3. **Legacy enforcement** (writes to shared `nuke_authors`, in the same operation):
   - `uid` is set to `0`. `original_uid` is kept in `admin_account_states`. This makes the OLD bridge lookup fail on the
     next request (`WHERE uid = …` finds nothing), and Gen 2 no longer loads the vBulletin user.
   - `pwd` and `password` are set to an **unguessable random marker** (64 random hex characters behind a fixed prefix;
     never shown, never stored elsewhere). This defeats every Gen 2 branch, including the **plain-text** comparison,
     because nobody knows the value. bcrypt, md5 and sha1 checks cannot match it.
   - `aid`, `name`, `email`, `radminsuper`, `permissions` and `nuke_modules.admins` are **untouched**. History,
     attribution and audit joins stay intact.

4. **Reactivation:**
   - Super-admin only.
   - Restores `uid` from `original_uid`.
   - **Requires setting a new password** in the same form (bcrypt via `Hash::make`). Old password hashes are **not**
     retained, so no credential copy exists anywhere.
   - ⚑ Whether reactivation must restore the old password: not possible by design. **Recommended: accept.**

5. **Rules** (enforced in the Action, under a lock):
   - The actor must have `super-admin`.
   - No self-deactivation.
   - The target must not be the **last active super-admin**: the count of `radminsuper = 1` or Spatie `super-admin`,
     minus deactivated, must stay ≥ 1.
   - Uses an atomic cache lock `admin-deactivation`, so two super-admins cannot deactivate each other simultaneously.

6. **Ordering and partial failure.** The connections may differ, so this is not a single transaction:
   1. write the Laravel state first (Laravel access is cut immediately);
   2. then the `nuke_authors` update in one statement.

   On a failed legacy write, the state row records `legacy_applied = false` (an extra column) and the UI shows «تعطيل
   جزئي، أعد المحاولة»; the Action is idempotent. Reactivation runs in reverse order.

7. **Why not other mechanisms:**

   | Mechanism | Why not |
   |---|---|
   | Spatie role removal | undone by the sync; `admin.role` with no argument still passes |
   | `radminsuper = 0` | does not stop logins |
   | Deleting `nuke_authors` | forbidden |
   | A new column on `nuke_authors` | legacy code would not read it, so the credential and uid changes are needed anyway; it would add a shared-schema change for nothing |

#### F02.3 UI changes

- **Staff list** (`resources/views/admin/staff/index.blade.php:51,77`): «تعديل الصلاحيات», «تعطيل» and «إعادة تفعيل»
  are rendered **only** for `super-admin`.
- **Status badge** «معطّل» on deactivated rows. Deactivated admins stay listed, with a filter.
- **Backend:**
  - new routes `admincp/staff/{admin}/deactivate|reactivate` (POST, CSRF) inside `admin.role:super-admin`;
  - the existing `permissions/{admin}` stays super-admin.
- **Menu:** an optional super-admin-only menu entry for staff management (investigation F02).
- **Permission editing on a deactivated admin:** the edit page shows read-only state with the reactivation form.

#### F02.4 Affected files (planned)

**New:**

- migration `create_admin_account_states_table` (default connection, additive);
- `app/Domain/Admin/Models/AdminAccountState.php`;
- `app/Domain/Admin/Actions/Staff/DeactivateAdminAction.php`, `ReactivateAdminAction.php`;
- requests;
- view partials.

**Edited:**

- `app/Domain/Identity/Guards/AdminGuard.php`;
- `SyncLegacyAdminPermissionsAction.php`;
- `AdminStaffController`, `routes/admin.php`;
- `staff/index.blade.php`, `permissions/edit.blade.php`;
- `AdminUser` (an `isDeactivated()` helper);
- `AuditAdminGrants` (report deactivated admins).

**Also:** `PermissionController::update` currently writes **no audit entry**. Adding one is recommended in the same
batch ⚑ (a small, convention-aligned addition).

#### F02.5 Residual risks (owner acknowledgement needed)

| # | Risk |
|---|---|
| R1 | **Gen 2 sessions already open** keep admin access until PHP session expiry. Closing the gap requires either a **legacy patch** (a per-request `nuke_authors` re-check in `7amlat/admincp/main.php`; `7amlat` is not modified by this project, so it is owner-applied), or a host-level session purge. ⚑ |
| R2 | The OLD bridge cookie producer lives in `7amlat/admincp/index.php`, which has **uncommitted local modifications** in the `7amlat` working copy. Whether production runs the HMAC version is UNVERIFIED. If production still runs the unsigned JSON cookie, OLD access is governed by the uid existing, which `uid = 0` still defeats. |
| R3 | Any **other** system reading `nuke_authors.uid` (for example forum-side tooling) would also see `0`. No such reader was found in Laravel, Gen 2 or OLD beyond those above. INFERRED. |
| R4 | `backup.php` / `BackupApiController` authenticate volunteers by `nuke_authors.API` key. ⚑ **Should deactivation also block the API key?** Recommended: yes, the guard check applies to `BackupApiController` too. Gen 2 `backup.php` would still accept it (legacy). |

---

### F08: hide a variety clip (Track C, new feature)

#### F08.1 Surfaces to change (Laravel)

| Surface | Location | Today | Change |
|---|---|---|---|
| Group page item list | `AnasheedGroupController::itemsForGroup()` `:133` | no `hidden` filter (documented parity) | add `hidden = 0` for visitors; admins with `anasheed.editanasheed` see all, with a «مخفية» badge |
| Group news, "more" lists (pinned and newest) | `AnasheedNewsController` `:50`, `:55` | none | `hidden = 0` |
| Home trending and latest | `ContentListingService` `:2334`, `:2356` | none | `hidden = 0` |
| Sidebar department lists | `ContentSidebarWidget::anasheedDepartmentQuery()` `:195` | none | `hidden = 0` |
| Related, top-10 and other Anasheed sidebar queries | `ContentSidebarWidget` (the remaining `nuke_anasheed_anasheed` reads) | mixed | audit every read and add the filter where missing; an inventory test enforces it (F08.4) |
| Category, advanced search, group-download | `ContentListingService` `:571`, `:590`, `:1863` | filter present | unchanged |
| Direct `var-item-{id}` | `AnasheedItemController` `:93-104` | 200, details suppressed (OLD parity A6) | **404 for visitors**; an admin with `anasheed.editanasheed` sees the page with a «هذه المادة مخفية عن الزوار» banner. **This supersedes parity A6** (decision-log entry required) |
| Send-to-friend | `AnasheedItemController` `:241` | no check | 404 when hidden |
| Comments (post) on a hidden item | Anasheed comment endpoint | INFERRED: no check | refuse (404) |
| Player | `MediaPlayerService` `:156`, `:178-180` | refused | unchanged |
| `var-download` / `var-mirror` | `AnasheedItemController` `:134`, `:146` | refused | unchanged |
| Group item counts | wherever counts are displayed | may include hidden | ⚑ exclude hidden from visitor-facing counts. **Recommended:** yes if it is computed at query time; leave stored counters alone |

#### F08.2 Admin

- **Toggle.** «إخفاء المادة» / «إظهار المادة» on the Anasheed item admin edit page and in the public contextual
  controls (`AnasheedItemControls`).
- **Route.** POST, CSRF, permission `anasheed.editanasheed`; writes only `hidden` (0/1).
- **Audit.** `admin_audit_log` action `anasheed.item.hide` / `anasheed.item.unhide`, with the before and after values.
- **`is_published` is not touched or reinterpreted.**
- **Admin lists** show hidden items with a badge (already includes all rows).

#### F08.3 Cache invalidation

- **Home latest and trending** (300 s cache): on toggle, forget the specific keys used by `ContentListingService`. The
  exact keys are to be enumerated in B4; `SimpleCache` / Laravel cache.
- If a key cannot be targeted, accept ≤ 300 s staleness and document it. **The direct URL, player and download take
  effect immediately** (they are uncached).
- **B4 finding (2026-11-14):** the home latest and trending *Variety* widgets are **not** cached. The cached home
  keys hold Khotab rows only, so no invalidation is needed and no staleness exists (B4 report §4).

#### F08.4 Legacy exposure (CONFIRMED by code reading)

| Legacy public code | `hidden` filter? |
|---|---|
| Gen 2 `anasheed/item.php:28` | **no** |
| Gen 2 `vars/var.php:7` | **no** |
| Gen 2 `vars/download.php:20` | **no** |
| Gen 2 `anasheed/functions.php` `:241`, `:270`, `:314`, `:459` | **no** |
| Gen 2 `vars/getright.php:15` | yes |
| OLD `modules/Anasheed` `Detailes`, `Play`, `Group` | yes (`:472`, `:610`, `:655`, `:935`) |

If Gen 2 public Anasheed URLs are still routed in production, a hidden clip **remains reachable there**: item page,
listing and download. Laravel cannot prevent this. ⚑ **Owner:** confirm that production rewrites send `var-*` and
`anasheed/*` to Laravel only (UNVERIFIED).

---

### F04: «حفظ المادة» must save the file (Track D, download infrastructure)

#### F04.1 Evidence

**archive.org (CONFIRMED, 2026-11-14):**

| Request | Response |
|---|---|
| `GET /download/{item}/{file}` | 302 to a storage node, then 206 `audio/mpeg` |
| With `?download=1` | identical behaviour |
| `Content-Disposition` | absent in every response |
| `Access-Control-Allow-Origin: *` | present on both the redirect and the final file |
| `Accept-Ranges: bytes` | present |

A restricted item returned 401/403 (not representative).

**⇒ An attachment response from archive.org is impossible.** No redirect-based approach can force a save.

> **Correction from B7a (2026-11-14), which supersedes the CORS statement above for video.**
>
> The `/download/` redirect always carries `Access-Control-Allow-Origin: *`, but the **storage-node response does
> not, for `video/mp4`**:
>
> - **0 of 36** sampled Way2Allah MP4s (Khotab and Anasheed) carried it;
> - **4 of 4** sampled MP3s did;
> - the result was stable per file across two runs;
> - unrelated public items behave the same (4/4 MP4 without, 4/4 MP3 with).
>
> The client-side option (A) therefore **cannot save archive.org MP4s**; it falls back to today's behaviour (the
> player opens). See `docs/reports/admincp-b7a-download-validation.md`. Option A as written is not a solution for the
> reported video case.

**Source mix (local 2025 copy, visible rows):**

| Module | archive.org | own subdomain (`vars.way2allah.com`) or relative | third-party | YouTube / SoundCloud |
|---|---|---|---|---|
| Khotab items | 291,777 | 488 | 234 | 6 |
| Khotab mirrors | 421,302 | 217 | 121 | 3,930 / 482 |
| Anasheed | 8,456 | 8,034 (7,818 `vars.way2allah.com`, about 180 `/cartoon/…` relative) | 2 | 1 |
| Telawah | 6,967 | — | **1,856** (`knoz4arab` 1,153, `ghyth` 386, `almuaiqly` 222, `hatemfarid` 81, …) | 37 |
| Fatwa (`media_link`) | 13,344 | 9 | 0 | 0 (11 empty) |

Lessons reuse Khotab items. Mobile (`nuke_downloads_downloads.url`) was not measured (see §5).

**Formats (Anasheed visible):**

| Extension | Rows | Browser behaviour |
|---|---|---|
| `.mp4` | 8,185 | plays inline |
| `.mp3` | 509 | plays inline |
| `.wmv` | 2,718 | already downloaded by browsers |
| `.rmvb` | 2,591 | already downloaded by browsers |
| `.flv` | 1,489 | already downloaded by browsers |
| `.rm` | 96 | already downloaded by browsers |

**Only `.mp4` and `.mp3` exhibit the reported defect.**

#### F04.2 Options

| Option | How | Server cost | Works for | Fails for |
|---|---|---|---|---|
| **A. Client-side save** (recommended) | The button calls `fetch()` on the normalised https archive.org URL (CORS `*`), streams to a Blob, and saves with `<a download="{title}.{ext}">` on the blob URL. Progress indicator; cancel. | **zero** PHP and bandwidth | archive.org sources (≈ 97 % of rows) | non-CORS hosts (own subdomain unless configured; third parties); very large files on low-memory devices (the whole file is held in browser memory); iOS Safari quirks |
| A + size cap | Read `Content-Length` (CORS-safelisted) first. Above a cap (⚑ proposed 300 MiB), or on any fetch error, fall back to today's redirect plus a «إن فُتح الملف للتشغيل: اضغط بزر الفأرة الأيمن ← حفظ باسم» hint | zero | as A | fallback is not a guaranteed save |
| **B. Server proxy** (existing `ProxiedDownloadStreamer` + `DownloadCapacityGuard`) | Route the redirect modules through the Khotab streamer, with an attachment header | **high**, see F04.3 | every reachable http(s) source | YouTube and SoundCloud pages (not files); hosts that block server fetches |
| C. Own-domain header | For `vars.way2allah.com`, an Apache `Header set Content-Disposition attachment` rule (on `?dl=1`) on that vhost | none | the 7,818 own-domain Anasheed rows | needs a host config change: owner and production action, outside this repo |

**Sources where a guaranteed download is impossible with any option except B:** third-party Telawah hosts (1,856), plus
any host without CORS.

**Impossible even with B:** YouTube and SoundCloud entries, which are pages, not files (YouTube 30+, SoundCloud 7 in
Telawah; Khotab mirrors 4,412, already handled by the Khotab route's 302-to-page behaviour). Also broken or retired
links.

#### F04.3 Proxy (option B) capacity estimate: reference only, not recommended now

From the local lab (`docs/reports/download-local-fastcgi-validation.md`):

- Each proxied download holds **one PHP-CGI process and one slot for the entire transfer**.
- About **35–40 MiB of unique memory** per process.
- Under `mod_fcgid`, the process and spawn limits cap concurrency **before** the slot pool. Spawn throttling queues
  requests.
- Under plain CGI, a client disconnect kills php-cgi (no terminal telemetry event).
- 400 concurrent is **not validated** (lab maximum 140).

**Occupancy.** By Little's law: concurrent slots = arrival rate × mean transfer time.

- Example: a 60 MiB `.mp4` to a 4 Mbit/s client takes about 126 s. At only **1 download per second** across the
  redirect modules, that is about **126 concurrent slots**, about 4.4–5 GiB of PHP memory, **in addition to** Khotab's
  existing load on the same pool.
- **Bandwidth.** Doubled server-side (ingress from archive.org plus egress to the client), versus zero today.

**Failure behaviour:**

| Condition | Result |
|---|---|
| pool full | 503 (the existing guard). A user who got a working redirect yesterday would get 503 |
| stalled upstream | freed after the idle timeout (about 10 s in the lab) |
| client abort | freed in 0.5–1.8 s under FastCGI |

**Constraint conflict.** The owner forbids auto-extending the 400 slots. B would have to share the existing pool, or
take a carved sub-pool, which reduces Khotab capacity. The arrival rate for Anasheed, Telawah and Fatwa downloads is
**UNVERIFIED** (needs access-log counts or the Phase 3A telemetry once released).

**⇒ B is not recommended** unless the owner provides the arrival-rate evidence and accepts the capacity trade-off.

#### F04.4 Plan (option A + cap + fallback; B7)

1. **B7a spike** (local only):
   - a Playwright test in Chromium, Firefox and WebKit against
     - a local CORS-enabled fixture server (small, 50 MiB and 400 MiB files);
     - a local no-CORS fixture (must fall back);
     - one real public archive.org `.mp4` and `.mp3`.
   - **The controlled browser test the owner requires:** assert the `download` event, the saved filename and the byte
     count, and that playback buttons are unaffected.
2. **B7b implementation:**
   - a small JS module bound to the «حفظ المادة» button, used by `media-details-card.blade.php:76` and
     `fatawa/question-all.blade.php:232`;
   - `href` stays the existing download route, so no-JS and fallback behaviour is today's;
   - the button carries `data-direct-url` (server-computed, normalised https archive.org URL, **only** when the host is
     archive.org) and `data-filename`;
   - **Download counting.** Today the redirect route increments `downcount` on `GET`. A client-side save bypasses that
     route, so the count would be lost. Changing the route's counting or telemetry semantics is out of bounds without
     review (owner constraint).

     **Recommended minimal path:** a tiny POST `…/{id}/counted` endpoint per module, reusing each module's existing
     increment method. It is called once when the blob save completes, and is throttled. It is not a telemetry change.

     ⚑ **Owner choice:** accept this counter path, or accept that client-side saves are not counted.
3. **No change** to `DownloadCapacityGuard`, slot configuration, `ProxiedDownloadStreamer`, the Khotab route, or the
   unreleased telemetry.

---

### F03: new series not visible (Track A′)

- **Plan, if H1 is confirmed:** in `KhotabGroupController:36`, pass the admin flag to `seriesByAuthorAndGroup()` for
  admins with `khotab.editauthor` (or any admin; ⚑ which permission). Show «مخفية» / «فارغة» badges in the admin view
  only. Visitors are unchanged (`count > 0`, `hidden = 0`).
- **If H2:** run the existing repair-counts tool; no code.
- **Blocked on:** the series id, plus read-only production queries (investigation §F03).
- **Files:** `KhotabGroupController`, `ContentListingService::seriesByAuthorAndGroup()`, `khotab/group.blade.php`.
- **Risk:** low.

### F05: medium-quality playback (Track A for part 1, A′ for part 2)

- **F05-1** (no evidence needed):
  - in `MediaPlayerService::fromKhotabItem()` and `fromMirror()`, apply `ArchiveOrgLink::normalize()` and an
    http→https upgrade for archive.org at render time, **as the Anasheed branches already do** (`:165`, `:186`);
  - stored data untouched; non-archive links unchanged.
- **F05-2:** for qualities whose format no browser plays (`.rmvb`, `.wmv`, `.avi`, `.rm`, `.flv`; 93 % of medium
  mirrors), render «هذه الجودة غير قابلة للتشغيل في المتصفح، يمكنك تحميلها» with the download link, instead of an
  empty or failed player. ⚑ The wording.
- **Do not claim F05 fixed** until the reported item and mirror id is verified (investigation §7.3).
- **Risk:** low. The player output changes only for archive.org http and node URLs.

### F06: author image (Track A)

- **Change:**
  - `UploadKhotabAuthorImageAction` additionally sets `nuke_islamic_authors.author_image =
    'media/authors/{b}/{id}.jpg?v={mtime}'` after a successful write;
  - the previous value is recorded in the audit metadata, so a revert is possible;
  - existing files (`sq/{id}.png`, older jpgs) are **never** modified or deleted;
  - `author_image` is written **only** on upload (the owner confirmed in the investigation: an upload overrides).
- **Pages fixed:**
  - `khotab/author.blade.php:89`;
  - `preacher-card.blade.php:7`;
  - chat-room `author` / `lesson` / `index` (all use `displayImageUrl()`, author_image first).
- **Pages NOT fixed by this change** (CONFIRMED; they deliberately use the Gen 2 `get_author_img()` sq-png path):
  - `khotab/series.blade.php:54`;
  - `khotab/group.blade.php:130-134`;
  - `khotab/item.blade.php:324-326`;
  - `fatawa/by-authors.blade.php:40` (filesystem check).

  ⚑ **Owner choice:**
  - (a) accept a partial fix (Gen 2 parity on those pages);
  - (b) switch those four to `displayImageUrl()` (a visitor-parity deviation; needs a decision-log entry);
  - (c) on upload, also write `sq/{id}.png`. **Rejected:** it overwrites legacy images.

  **Recommended: (b)**, because otherwise the reported defect persists on the most-viewed pages.
- **Shared Fatwa rendering:** `Author` is shared with Fatwa and chat-room authors. The change writes only when a
  **Khotab** admin uploads for that author id. Fatwa «by-authors» reads the filesystem and is unaffected unless (b)
  includes it ⚑.
- **Precondition (UNVERIFIED):** `admin_khotab.media_root` must equal the public media root
  (`docs/operations/split-layout-media-roots.md`).
- **Risk:** low.

### F07: variety video playback (Track A′)

- **Blocked on** the exact URL and item.
  - If it is a `khotab-group` item, F05-1 covers the http and retired-node causes.
  - If it is `var-group`, check the stored link and archive.org processing state.
- **Optional:** a save-time **warning** (not a block) on Khotab and Anasheed item and mirror forms when a video link
  is `http://`, not `.mp4`, or a `/details/` page. ⚑ (a UI addition).
- **Do not claim it fixed** without verifying the item and source.

### F09: move / backup labels (Track A)

- **Label and help-text changes** in `admincp/mirrors/khotab/show.blade.php:34,64`, `edit.blade.php:43`, and the
  `backupme` checkbox on the Khotab, Anasheed and Telawah mirror forms. For example:
  - «نقل الوصلة إلى مادة أخرى (لا يتم نسخ الملف)»;
  - «إضافة الوصلة لقائمة النسخ الاحتياطي (برنامج الأرشفة)».

  ⚑ The final wording, and confirmation of which option the reviewers meant (screenshot).
- **Optional parity:** restore OLD's lock of the link field for non-super-admins once `down = 1` ⚑.
- **Risk:** none for labels; low for the lock.

### F10: custom Khotab thumbnail (Track C)

**Data facts (CONFIRMED, local 2025 copy, `nuke_islamic_khotab`, 292,592 rows):**

| Field | Value |
|---|---|
| `frame = 1` | 152,006 |
| `frame = 0` and visible | 140,551 (21,151 with id > 250,000) |
| `build_thumb = 1` | **0** |
| `thumbnail` non-empty | **6,467**, all archive.org URLs, ids 896–32,180 (legacy data; **must not be overwritten**) |

**Design:**

- **Upload.** «رفع صورة مخصصة للمادة» on the Khotab item admin page.
  - Accepts JPG, PNG and WebP, MIME-sniffed; ≤ 2 MiB ⚑.
  - Converted to JPEG with GD, which strips any payload.
  - Written to `media/khotab_frames/{b}/{id}.jpg`, then `frame = 1`, so **every existing display path picks it up with
    no view change**.
- **Protection.** A new Laravel-owned table `khotab_custom_frames(item_id unique, uploaded_by_admin_id, uploaded_at,
  previous_frame_backup_path nullable)`.
  - Chosen over a marker file (auditable, queryable) and over `build_thumb` / `thumbnail` (legacy columns; no
    redefinition).
  - `LeechKhotabItemImagesAction`, `KhotabArchiveImages` and `InsertKhotabItemAction:159` re-fetch **skip** items listed
    there, unless the admin chooses «استعادة صورة الأرشيف», which deletes the row and re-fetches.
  - The previous archive frame is copied to `khotab_frames/{b}/{id}.archive.jpg` before the first custom overwrite, so
    restore works without a re-fetch.
- **Cache busting.** Append `?v={mtime}` where frame URLs are built (`ContentSidebarWidget:1062-1090`,
  `KhotabItem::socialImageUrl()`).
- **Permission:** `khotab.edititem`, or a new `khotab.uploadthumb` ⚑. Audited.
- **Anasheed scope:** ⚑ not included unless requested.
- **Risk:** low to medium (the re-fetch paths must honour the guard).

---

## 3. Dependencies

| Item | Depends on |
|---|---|
| F01 | `HomeAd` model; the media root config; `AdminAuditRecorder`; the permission seeder; category and author lookups for banners_ads filters; a modal for pos 12 (native `<dialog>`); the player dialog for pos 11 (to locate) |
| F02 | `AdminGuard`; `SyncLegacyAdminPermissionsAction`; `AdminStaffController`; `PermissionController`; cache lock store; default-connection migration |
| F04 | `ArchiveOrgLink::normalize()`; the Blade button components; Playwright (dev dependency, local only); per-module increment methods |
| F05 | `ArchiveOrgLink`, `MediaPlayerService` |
| F06 | `UploadKhotabAuthorImageAction`, `Author::displayImageUrl()`, `MediaUrl` |
| F08 | Anasheed controllers and services listed in F08.1; cache keys; `AnasheedItemControls` |
| F10 | GD extension (UNVERIFIED in production; `php -m`); frame leech actions; default-connection migration |
| F01 ↔ F08 | the ad slot on `var-item` (pos 4) must not render on a 404 hidden page (trivially true) |
| F05 ↔ F07 | shared normalisation |
| F06 ↔ F10 | the same upload convention (path + `?v=mtime`) |

## 4. Database and compatibility implications

| Item | Shared legacy tables written | New Laravel tables | Legacy compatibility |
|---|---|---|---|
| F01 | `nuke_ads`, `nuke_banners_ads`, `nuke_report_for_bad_ads`, only by admin action; `nuke_ads.num_view`/`num_click`/`show` by public rendering **only when the switch is on** | none | same columns and value shapes (absolute image URL, Y-m-d dates); OLD and Gen 2 renderers keep working |
| F02 | `nuke_authors.uid`, `pwd`, `password` (deactivate); `uid`, `password` (reactivate) | `admin_account_states` | Gen 2 and OLD logins are refused for deactivated admins; already-open Gen 2 sessions persist (F02.5 R1) |
| F04 | none (option A); counter increments as today | none | legacy routes unaffected |
| F05, F07 | none | none | — |
| F06 | `nuke_islamic_authors.author_image`, only on upload | none | Gen 2 `author.php:104` also prefers `author_image`, so Gen 2 shows the new image too |
| F08 | `nuke_anasheed_anasheed.hidden` (toggle) | none | OLD honours `hidden`; Gen 2 public does **not** (F08.4) |
| F09 | none | none | — |
| F10 | `nuke_islamic_khotab.frame` (= 1 on upload) | `khotab_custom_frames` | legacy displays read the same frame file |

Both new migrations are **additive**, on the default connection, and have reversible `down()` methods. No column is
added to any `nuke_*` table.

## 5. Security review

| Item | Concern | Control |
|---|---|---|
| F01 | upload, XSS (type 0), SQLi, CSRF, open redirect, click inflation | §F01.5 |
| F02 | privilege persistence across systems; last-admin lock-out; race; credential handling | §F02.2: guard enforcement, sync skip, uid and marker, lock, no stored hashes, audit |
| F02 | UI exposure | controls rendered only for super-admins; routes remain `admin.role:super-admin` (defence in depth) |
| F04 | the JS must fetch only server-provided archive.org URLs (no user-supplied URL); the filename is sanitised server-side | allow-list host check in PHP before emitting `data-direct-url` |
| F06, F10 | image upload | MIME sniff, re-encode (F10), size limit, fixed target name (no user-controlled path) |
| F08 | info leak of hidden items | 404 for visitors; admin view gated by permission |

## 6. Regression-test plan

**Feature tests (PHPUnit), per batch:**

| ID | Tests |
|---|---|
| F01 | CRUD per field; validation (bad extension, SVG, HTML disguised as `.jpg` rejected; end < start rejected); toggle is POST-only; delete writes the audit snapshot; banners set replacement is transactional (failure leaves the old set); report list and delete; permissions (403 without; super-admin passes); **switch off ⇒ zero queries and no output on every public page**; switch on: eligibility (start day per decision, end day, not-started, ended auto-hide, numview limit), default-image decision, `num_view` +1 per render, click route +1 then 302 to the stored link, throttle; type-0 authoring refused for non-super-admin (if A7a) |
| F02 | deactivate as super-admin; refused for non-super-admin (403), self (422), the last active super-admin (422); **an existing Laravel session loses access on the next request** (401); login refused; sync run leaves the deactivated admin inert and keeps grants; `nuke_authors.uid = 0` and both password columns replaced; `aid`/`name`/`permissions` unchanged; the OLD bridge lookup by the original uid finds nothing; Gen 2 verifier branches (bcrypt, md5, sha1, plain) all reject any input, including the empty string; reactivation restores the uid and requires a new password; audit entries for both; concurrent deactivation of two super-admins leaves ≥ 1 active; partial legacy failure is retried idempotently; the staff list hides controls from non-super-admins |
| F04 | Playwright (B7a list); PHP: `data-direct-url` emitted only for archive.org hosts; fallback `href` unchanged; counter endpoint (if chosen) increments once |
| F05 | normalisation cases (investigation §6) plus the non-playable message |
| F06 | upload sets `author_image` with a version; the previous value is in the audit; the author page, preacher card and chat-room pages show the new URL; the pages in option (b) per decision; the Fatwa author page unchanged unless chosen; no file deleted |
| F08 | hidden item absent from the group, news (pinned and newest), home trending and latest, sidebar department lists, related and top-10; direct URL 404 for a visitor and 200 with a banner for an admin; send-to-friend and comments 404; player and downloads refused (existing); toggle requires the permission; audit; the cache key is forgotten. **Inventory test:** every `nuke_anasheed_anasheed` read in `app/Domain/Content` is on an allow-list of reviewed call sites (fails when a new unreviewed query appears) |
| F09 | rendered labels |
| F10 | upload writes the frame and `frame = 1` plus the custom row; a re-fetch skips; restore deletes the row and restores the archive copy; `?v=` present |
| All | full existing suite, visitor-parity suites, PHPStan, Pint; **no diff** in `config/downloads.php`, `DownloadCapacityGuard`, `ProxiedDownloadStreamer`, or the telemetry classes (checked in review) |

## 7. Implementation batches

Each batch is a separate change set for owner review. None starts before approval of this plan.

| Batch | Content | Track | Prerequisites | Risk |
|---|---|---|---|---|
| **B1** | F06 (incl. option-b decision), F05-1, F02-UI (hide links, menu), F09 labels | A | owner decisions on F06 (b) and F09 wording | Low |
| **B2** | F03, F05-2, F07 | A′ | owner evidence (series id, item and mirror ids, URL) | Low |
| **B3** | F02 deactivation (migration, guard, sync, actions, UI, audit; PermissionController audit) | C | owner acknowledgement of R1–R4 | Medium |
| **B4** | F08 hide (filters, 404, toggle, audit, cache) | C | owner confirmation of Gen 2 public routing (F08.4) | Medium |
| **B5** | F10 custom thumbnail | C | owner decisions on permission and size; GD available | Low–Medium |
| **B6a** | F01 `ads` admin (CRUD, toggle, upload, reports) and permissions | B | decisions A7 and size limit | Low (admin-only) |
| **B6b** | F01 `banners_ads` and `report_ads` admin | B | — | Low |
| **B6c** | F01 public rendering behind `ads.rendering_enabled = false` (B6c-1 foundation closed locally; B6c-2 placement and activation decisions open) | B | decisions P2, P4, P6, P9, P11, P13, pos 9 | Medium (deployed **off**) |
| **B7a** | F04 local browser spike (Playwright) | D | — | none (local) |
| **B7b** | F04 client-side save plus fallback (plus counter path per decision) | D | B7a passes in all three engines; counter decision | Low |

**Not planned:** the F04 option B proxy, unless the owner supplies the arrival-rate evidence and approves a capacity
change separately.

## 8. Deployment risk classification and rollback

| Batch | Classification | Rollback |
|---|---|---|
| B1 | Low; code only, plus `author_image` writes on new uploads | revert the code; restore `author_image` from the audit metadata if needed |
| B2 | Low | revert the code |
| B3 | Medium; an additive migration plus shared-table writes on use only | revert the code: the guard stops enforcing. **Deactivated admins stay locked out of legacy** (uid 0, marker passwords) until reactivated via the old code path or a manual restore from `admin_account_states.original_uid` plus a new password. Keep the table (do not run `down()`) to retain history |
| B4 | Medium; listings change | revert the code; `hidden` values set by toggles remain (they were already honoured by the player and downloads) |
| B5 | Low–Medium | revert the code; custom frames remain as the current frame files; archive copies are kept at `*.archive.jpg` for a manual restore |
| B6a/B6b | Low; admin-only | revert the code; seeded permissions are harmless if left |
| B6c | Medium; deployed off | set `ads.rendering_enabled = false` (instant), or revert |
| B7b | Low | revert the JS: the button falls back to today's `href` |

All batches avoid the Thursday–Saturday deployment freeze. The owner controls every commit and deploy.

## 9. Remaining owner decisions (⚑) and missing evidence

**Decisions:**

| # | Item | Decision | Recommendation |
|---|---|---|---|
| 1 | F01 A7 | type-0 code ads: restricted raw (a) or AdSense allow-list (b) | (a) |
| 2 | F01 A6 | ad image size limit | 2 MiB |
| 3 | F01 P2 | include the start day | yes (fix) |
| 4 | F01 P4 | keep the public auto-hide write-back | yes |
| 5 | F01 P6 | default images when no ad | render nothing |
| 6 | F01 P9 | keep `num_view` +1 per render | yes (parity), with the performance note |
| 7 | F01 P11 | port pos 12 popup | **RESOLVED 2026-11-14: port it** (behind the switch) |
| 8 | F01 P13 | port and place the home banner strip | **RESOLVED 2026-11-14: port and place it** (behind the switch) |
| 9 | F01 pos 9 | lift the W2 deferral | owner |
| 10 | F01.3 | permission assignment: manual (a) or sync mapping (b) | (a) |
| 11 | F01 A8 | reports as tables (no charts) | yes |
| 12 | F02 | deactivation-state cache | no cache |
| 13 | F02 | reactivation requires a new password | accept |
| 14 | F02 R1 | accept the Gen 2 open-session window, or provide a legacy patch / session purge | owner |
| 15 | F02 R4 | block the backup API key for deactivated admins | yes |
| 16 | F02 | add an audit entry to permission edits | yes |
| 17 | F04 | the size cap for client-side save | 300 MiB |
| 18 | F04 | the counter path for client-side saves | POST counted endpoint |
| 19 | F04 | the option C header on `vars.way2allah.com` (host action) | optional |
| 20 | F05-2 | the message wording | **resolved 2026-11-16: Option B** (decision-log #144) |
| 21 | F06 | (a) partial, or (b) switch the 4 Gen-2-parity pages to `displayImageUrl()` | (b), plus whether Fatwa «by-authors» is included |
| 22 | F07 | the save-time warning | optional |
| 23 | F08 | exclude hidden from visitor-facing group counts | yes |
| 24 | F09 | the wording; restore the post-backup link lock | — |
| 25 | F10 | the permission key; the size limit; the Anasheed scope | — |
| 26 | F03 | which permission sees empty and hidden series | — |

**Missing evidence:**

| # | Item | Evidence |
|---|---|---|
| E1 | F03 | the series id, plus the read-only production queries |
| E2 | F05 | `khotab-item-{id}` and the mirror id |
| E3 | F07 | the exact URL and item |
| E4 | F09 | a screenshot |
| E5 | F02 | the reviewer's role; production `session.gc_maxlifetime` for Gen 2; whether production runs the HMAC bridge (R2); whether the Laravel default connection and `main` are the same database |
| E6 | F08 | the production rewrite rules for `var-*` and `anasheed/*` (is Gen 2 public Anasheed still reachable?) |
| E7 | F04 | the exact page where «حفظ المادة» played; the per-module download arrival rates (only needed if option B is ever considered); the mobile download hosts (`nuke_downloads_downloads.url`) |
| E8 | F01 | whether `media/bann/` contains non-image files; whether `media/banner/{b}/{id}.gif` files exist; the location of the Laravel player dialog for pos 11 |
| E9 | F06 | `admin_khotab.media_root` equals the public media root in production |
| E10 | F10 | GD available in the production PHP |
