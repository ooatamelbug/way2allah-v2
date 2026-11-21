# AdminCP B1: implementation results (F06, F05-1, F02-UI)

**Date:** 2026-11-14.

**Approval:** owner approval "B1 + B7a", limited to F06, F05-1 and F02-UI.

**Plan:** `docs/reports/admincp-feedback-implementation-plan.md`.

**Status:** **IMPLEMENTED LOCALLY. Owner-accepted provisionally. Final local validation GREEN (2026-11-14, §3.1).**

Still open before deployment: F06's production media-root check (see `admincp-b1-outstanding-validation.md` §2).

- Not committed, not pushed, not deployed.
- No production access of any kind.
- No database writes. The only database access was read-only `SELECT`s on the local MySQL 5.7 copy, used to
  characterise existing `author_image` values.
- Existing uncommitted work from earlier phases is preserved. Some files touched here already carried uncommitted
  edits from earlier phases; only the hunks described below are B1.
- **Not done** (outside the approval): B2–B6, B7b, F02 deactivation, F09 labels, any F01 code.

---

## 1. Changed files

### Application

| File | Feature | Change |
|---|---|---|
| `app/Domain/Admin/Actions/Khotab/UploadKhotabAuthorImageAction.php` | F06 | After the file is written: sets `author_image` to the new public `publicUrl()` (`/media/authors/{b}/{id}.jpg?v={mtime}`); records `author_image` and `previous_author_image` in the audit metadata; a legacy previous value that contains markup is recorded as `[unrecordable: contained markup]` (the audit refuses markup) instead of aborting after the file is in place. Docblock updated |
| `resources/views/khotab/series.blade.php` | F06 | profile-photo box: `fallbackImageUrl()` → `displayImageUrl()`; comment updated |
| `resources/views/khotab/group.blade.php` | F06 | same |
| `resources/views/khotab/item.blade.php` | F06 | same |
| `resources/views/fatawa/by-authors.blade.php` | F06 | same |
| `app/Domain/Content/Services/ContentListingService.php` | F06 | `fatwaAuthorsWithQuestions()` also selects `nuke_islamic_authors.author_image` and names it in `GROUP BY` (MariaDB ONLY_FULL_GROUP_BY). It is a column of the primary-keyed row, so groups, counts and order are unchanged |
| `app/Domain/Content/Services/MediaPlayerService.php` | F05-1 | `fromKhotabItem()` and `fromMirror()` pass the link through `ArchiveOrgLink::normalize()`, as `fromAnasheedItem()` / `fromAnasheedMirror()` already did |
| `app/Domain/Admin/Http/Controllers/AdminStaffController.php` | F02-UI | `index()` passes `canEditPermissions` (super-admin role) and `canAddStaff` (super-admin or `authors.addstuff`). `store()` redirects a super-admin to the permission editor as before, and anyone else to the staff list (if they hold `authors.liststuff`) or back to the add form, with a message that permissions and password are set by the super administration. New private `viewer()` helper |
| `resources/views/admin/staff/index.blade.php` | F02-UI | «إضافة مشرف» only when `canAddStaff`; the «الصلاحيات» column header and every «تعديل الصلاحيات» link only when `canEditPermissions` |

### Documentation

| File | Change |
|---|---|
| `docs/decision-log.md` | **#140**: the F06 visitor-parity deviation (supersedes G-13-03 for the series, group and item profile boxes and the Fatwa by-authors list) |
| `docs/reports/admincp-feedback-implementation-plan.md` | the status header; F01 scope confirmation (Task 3); a correction note on F04 from B7a |

### Tests

| File | Change |
|---|---|
| `tests/Feature/Admin/KhotabAuthorImageUploadDisplayTest.php` | **new**, 6 tests |
| `tests/Feature/Content/KhotabPlayerArchiveNormalizationTest.php` | **new**, 12 tests including the dataset |
| `tests/Feature/Admin/AdminStaffPermissionControlsTest.php` | **new**, 8 tests |
| `tests/Feature/Content/KhotabItemControllerTest.php` | the G-13-03 item test now expects `author_image` first and the fallback when empty (it previously asserted the old rule) |
| `tests/Feature/Content/KhotabBrowsingControllersTest.php` | the G-13-03 series and group tests, same change |
| `tests/Feature/Admin/AdminGrantSafetyTest.php` | "creates an admin normally": the add-only operator is now redirected to `admin.staff.create`, not into the super-admin-only editor it cannot open. All grant-safety assertions are unchanged |

**Not modified:**

- `DownloadCapacityGuard`, `ProxiedDownloadStreamer`, `config/downloads.php`, slot configuration, the telemetry classes;
- any download route or counter;
- any migration;
- `7amlat`, `old-project`;
- the user's Docker containers.

---

## 2. Before and after

### F06: Khotab author image

**Media-root verification (required before relying on the new path).**

- `php artisan media:check-roots` locally: `KHOTAB_MEDIA_ROOT` = `public/media`, the directory served at
  `media.base_url` (`/media`). Exists, readable, writable. **Confirmed findings: none.** The upload target and the
  public URL agree locally.
- The command reports that the local root is a symlink. That is informational and unchanged.
- **Production is unverified** and is a deployment precondition: run `php artisan media:check-roots
  --expect-web-root=<web root>` (decision-log #136/#140).

**Existing data (local 2025 copy, read-only).** 120 of 736 authors have `author_image`, all absolute
`https://old.way2allah.com/media/authors/…` URLs. These are displayed unchanged and are never rewritten unless the
admin uploads a new image for that author.

| | Before | After |
|---|---|---|
| Upload | writes `media/authors/{b}/{id}.jpg` only | same file, **plus** `author_image = /media/authors/{b}/{id}.jpg?v={mtime}`; the previous value goes to the audit |
| Author page `khotab-{video,audio}-{id}`, authors index and preacher card, chat-room author and lesson | `author_image`, else `sq/{id}.png`, else placeholder | unchanged logic; now shows the upload |
| Series, group and item profile box | `sq/{id}.png`, else placeholder (Gen 2 parity G-13-03) | `displayImageUrl()`: `author_image` first, then the same fallbacks |
| `/fatawa-by-authers.htm` | `sq/{id}.png`, else placeholder | `displayImageUrl()` |
| Legacy files (`sq/*.png`, older jpgs) | — | never deleted or modified by B1 |
| Gen 2 (`author.php:104`, `authors.php:77`) | shows `author_image` if set | receives a root-relative `src` that works on its root-level URLs |

**Out of scope, unchanged:** `chat-room/index` and `channels/author` still use the sq-png path. They were not in the
approved list.

**Note.** Overwriting `media/authors/{b}/{id}.jpg` on each upload is the existing OLD upload behaviour, unchanged by
B1. That file is the upload target, not a legacy image source for these pages.

### F05-1: Khotab archive.org normalisation

| Stored link (example) | Player `src` before | Player `src` after |
|---|---|---|
| `http://ia600700.us.archive.org/12/items/F/a.mp4` | same (raw http, retired node) | `https://archive.org/download/F/a.mp4` |
| `http://www.archive.org/download/F/a.mp3` | raw | `https://archive.org/download/F/a.mp3` |
| `https://archive.org/download/F/a.mp4?x=1` | same | same |
| `http://cdn.example.com/a.mp4`, `http://vars.way2allah.com/…`, look-alike `archive.org.example.com` | same | **same** (other hosts untouched, http stays http) |
| archive.org `.rmvb` | no player | no player (format unchanged) |
| YouTube, SoundCloud | embeds | embeds unchanged |

**Not changed:**

- The stored `link` (test-proven), the download routes, and the Khotab streaming path.
- archive.org URLs outside `/download/` or `/items/` (for example `/details/` pages): left as is, exactly as
  `ArchiveOrgLink` does for Anasheed. They are not media files.

**Not claimed:** that the reviewers' medium-quality failure is resolved. That needs the real item and mirror ids
(plan E2). 93 % of medium mirrors in the 2025 copy are formats no browser plays, which B1 does not change (that is
F05-2, in B2).

### F02-UI: staff permission controls

| Viewer | Before | After |
|---|---|---|
| Super-admin | sees the editor link on every row and «إضافة مشرف»; after adding, lands in the editor | unchanged |
| `authors.liststuff` only | sees «تعديل الصلاحيات» on every row (→ 403) and «إضافة مشرف» (→ 403) | sees the roster only; no permission column, no add link |
| `authors.liststuff` + `authors.addstuff` | as above; after adding, redirected into the editor (→ 403) | sees «إضافة مشرف», no permission controls; after adding, back to the staff list with a success message |
| `authors.addstuff` only | after adding, redirected into the editor (→ 403) | back to the add form with a success message |
| Backend | `permissions/*` = `admin.role:super-admin`; staff routes = their permissions | **unchanged** (test-proven: 403 on GET and PUT editor routes, staff routes 403 without permission) |
| Menu «المشرفين» | children gated by `authors.addstuff` / `authors.liststuff`; the editor has no menu entry | unchanged and now test-proven |

---

## 3. Test results

| Run | Result |
|---|---|
| F06 targeted + affected parity: `KhotabAuthorImageUploadDisplayTest`, `KhotabAuthorPanelParityTest`, `KhotabItemControllerTest`, `KhotabBrowsingControllersTest`, `FatwaByAuthorsControllerTest`, `KhotabPublicParityTest`, `ChannelAuthorLayoutTest`, `KhotabMainPanelParityTest` | **192 passed** (1,168 assertions) |
| F05-1 targeted + player parity: `KhotabPlayerArchiveNormalizationTest`, `MediaPlayerControllerTest` | **48 passed** |
| F02-UI targeted + admin parity: `AdminStaffPermissionControlsTest`, `AdminStaffControllerTest`, `AdminVisualParityTest`, `AdminPageLevelParityTest`, `AdminSidebarActiveStateTest`, `AdminGrantSafetyTest`, `AdminRetiredRoutesTest` | **65 passed** after fixes |
| **Full suite** (`vendor/bin/pest`) | **3,375 tests: 3,335 passed, 39 skipped, 1 failed** (environmental, below) |
| PHPStan (`vendor/bin/phpstan analyse`) | **0 errors** |
| Pint (`vendor/bin/pint --test`) | **passed** |

The first F02-UI run had 2 failures, both fixed:

- **(a) My own test.** It used POST for the password route, which is PUT.
- **(b) `AdminGrantSafetyTest`.** It asserted the old redirect of an add-only operator into the 403 editor, which is
  the defect F02-UI removes. Its expectation was updated, and the grant assertions are untouched.

**The one full-suite failure, and why it is not a B1 regression:**

- **The test:** `SpoolWriterTest` › "reports spool usage against the bound through the status command" (download
  telemetry; not touched by B1).
- **The cause:** the telemetry spool deliberately refuses writes when free disk is below
  `DOWNLOAD_TELEMETRY_SPOOL_DISK_RESERVE_BYTES` (default **2 GiB**). This machine's root filesystem had **1.7 GB
  free**, so the guard dropped the write (`DISK_RESERVE`) and the test saw 0 files.
- **Proof:** rerun with the reserve lowered for that process only (`DOWNLOAD_TELEMETRY_SPOOL_DISK_RESERVE_BYTES=100 MiB`,
  no code or config change): `SpoolWriterTest` **17/17 passed**.

**The disk-space event (disclosed).**

1. The machine's disk was already about 98 % full.
2. Installing the B7a browser tooling filled it.
3. A first full-suite attempt then failed with "No space left on device".
4. I removed only what I had added this session: the Playwright browsers `chromium-1140`, `firefox-1465`,
   `webkit-2083` and `ffmpeg-1010` from `~/.cache/ms-playwright`. The pre-existing `chromium-1243`,
   `chromium_headless_shell-1243` and `ffmpeg-1011` were kept.
5. The suite was then rerun from scratch; the results above are from that rerun.

**Still occupying disk:** the Playwright Docker image (about 2 GB) used for B7a. It can be removed with
`docker rmi mcr.microsoft.com/playwright:v1.48.2-jammy` once B7a is reviewed.

### 3.1 Final validation (2026-11-14, after the owner freed disk space)

Run with the **original configuration**: no threshold, environment variable, test or download configuration changed.

| Check | Result |
|---|---|
| Free disk on `/` | **13 G** (92 % used); the owner cleaned the npm cache manually, and no other cleanup was done |
| `SpoolWriterTest` (the previously failing test), default 2 GiB reserve | **17/17 passed** |
| **Full suite** `vendor/bin/pest` | **3,375 tests: 3,336 passed, 0 failed, 0 errors, 39 skipped**; 26,596 assertions; exit 0 |
| Full suite, repeated with a JUnit log | identical: 3,375 tests, 0 failures, 39 skipped |
| PHPStan | **0 errors** |
| Pint `--test` | **passed** |

**Skipped (39), all opt-in and environment-gated, none B1-related:**

| Count | File | Skip condition |
|---|---|---|
| 20 | `MariaDbIntegrationTest` (telemetry aggregation) | disposable MariaDB, `W2A_MARIADB_TEST_*` |
| 9 | `MariaDbArchiveTest` | same |
| 5 | `MariaDbMonitorTest` | same |
| 4 | `ShadowBenchmarkTest` | `W2A_SHADOW_BENCH=1` |
| 1 | `ThumbnailControllerTest` | PHP GD missing from the local CLI |

**The earlier environmental failure is resolved.** It was `SpoolWriterTest`, hit by the spool's 2 GiB disk reserve
when 1.7 GB was free. With 13 G free it passes unchanged. The earlier lowered-reserve rerun is superseded and was
diagnostic only.

## 4. Remaining risks

| # | Risk |
|---|---|
| 1 | **F06 production media root** is unverified. If `admin_khotab.media_root` is not the directory served at `media.base_url`, the uploaded file is not public and `author_image` then points at a missing file. That is worse than today, which shows the sq png or the placeholder. **Run `media:check-roots` before deploying.** |
| 2 | **F06 Gen 2 hosts.** If Gen 2 is served from a host whose `/media` is not the same directory, its `author_image`-first pages would show a broken image for newly uploaded authors. Existing absolute values are unaffected. |
| 3 | **F06 visitor-parity deviation** on four surfaces (decision-log #140). Intentional and owner-approved. |
| 4 | **F05-1** only removes the http and retired-node cause; it does not prove the reported failure fixed (E2). |
| 5 | **F02-UI** is presentation only. Authorization is unchanged, by design; deactivation (B3) is not implemented. |
| 6 | **Full-suite environment:** disk-guarded tests need more than 2 GiB free on `/`. Resolved for the final run (13 G free); keep it in mind for future runs. |

## 5. Confirmations

- **No production changes.** No production access, configuration, database or file change.
- **No commits, no pushes, no deployments.**
- **Download infrastructure is unchanged:** `DownloadCapacityGuard`, `ProxiedDownloadStreamer`, slot configuration,
  Khotab streaming, download routes, counters and the unreleased telemetry were not modified.
- **No advertisement activated.** F01 is not implemented.
- `7amlat` and `old-project` were not modified. The user's Docker containers were not touched; the local MySQL copy
  was read only.
