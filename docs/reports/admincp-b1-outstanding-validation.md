# AdminCP B1: outstanding validation checklist

**Date:** 2026-11-14.

**B1 status (owner):** **provisionally accepted as locally implemented.**

**§1 (the test suite) is COMPLETE as of 2026-11-14: green with the original configuration.** §2–§4 remain open.
Results are in `docs/reports/admincp-b1-implementation-results.md` §3.

## 1. Test suite, with original configuration only

**Rule:** no threshold, environment variable or test is changed to obtain a pass.

| # | Step | Precondition | Pass criterion | Status |
|---|---|---|---|---|
| 1.1 | Free disk safely | owner cleaned the npm cache manually; no other cleanup | free space ≥ 5 GiB before starting | **DONE**: `df -h /` 13 G free (92 % used) |
| 1.2 | Rerun the affected test with its **default** config: `vendor/bin/pest tests/Feature/Support/Downloads/Telemetry/SpoolWriterTest.php`. No `DOWNLOAD_TELEMETRY_SPOOL_DISK_RESERVE_BYTES` in the environment, `phpunit.xml` or `.env*`, so the config default of 2 GiB applies | 1.1 | 17/17 pass | **DONE: 17/17 passed** |
| 1.3 | Full suite, default config: `vendor/bin/pest` | 1.2 | 0 failures | **DONE: 3,375 tests, 3,336 passed, 0 failed, 0 errors, 39 skipped** (26,596 assertions, 376 s, exit 0). Confirmed by a second run with a JUnit log: 3,375 / 0 failures / 39 skipped |
| 1.4 | PHPStan and Pint after 1.3 | — | 0 errors / pass | **DONE: PHPStan 0 errors; Pint passed** |

**The 39 skips are opt-in, environment-gated tests**, identical in count to every earlier run:

| Count | File | Skip condition |
|---|---|---|
| 20 | `tests/Feature/Support/Downloads/Telemetry/Aggregation/MariaDbIntegrationTest.php` | needs a disposable MariaDB (`W2A_MARIADB_TEST_*` not set) |
| 9 | `tests/Feature/Support/Downloads/Archive/MariaDbArchiveTest.php` | same |
| 5 | `tests/Feature/Support/Downloads/Monitoring/MariaDbMonitorTest.php` | same |
| 4 | `tests/Feature/Support/Downloads/Telemetry/ShadowBenchmarkTest.php` | runs only with `W2A_SHADOW_BENCH=1` |
| 1 | `tests/Feature/Content/ThumbnailControllerTest.php` | needs the PHP GD extension, which the local CLI PHP lacks (`RUNTIME_BLOCKED_BY_MISSING_GD`) |

None of them is related to B1.

**No threshold or download configuration was changed.** No file under `app/Support/Downloads`, `config/downloads.php`,
`config/download_telemetry.php`, `config/download_rejections.php`, `phpunit.xml`, `tests/Feature/Support/Downloads`
or `.env*` was modified on 2026-11-14 (the newest is from 2026-11-11, earlier-phase work). No environment override
was used for the validation runs. No disk cleanup was performed by me; only the owner's npm-cache cleanup preceded the
run.

## 2. F06 author image: production precondition

| # | Step | Pass criterion | Status |
|---|---|---|---|
| 2.1 | On production (owner or ops, read-only): `php artisan media:check-roots --expect-web-root=<web root>`, run by the web/cron user per `docs/operations/split-layout-media-roots.md` | `KHOTAB_MEDIA_ROOT` resolves to the directory served at `media.base_url`; exists, readable and writable by the web PHP user; "Confirmed findings: none" | **PENDING (blocker for F06 deployment)** |
| 2.2 | Confirm that Gen 2 (if still public) serves `/media` from the same directory | a known `/media/authors/sq/{id}.png` loads on both hosts | PENDING |
| 2.3 | After deployment (when approved): upload an image for one test author, then open the author, group, series and item pages and `/fatawa-by-authers.htm` | the new image with `?v=` shows on all five; the audit row has `previous_author_image` | PENDING |

**If 2.1 fails, do not deploy F06.** Fix the media root first (decision-log #136).

## 3. F05-1: kept separate from the reported issue

| # | Item | Status |
|---|---|---|
| 3.1 | F05-1 (Khotab archive.org URL normalisation) is a **general defect fix**, verified by tests only | done locally |
| 3.2 | The **reported** medium-quality playback failure stays **OPEN**: it needs the real `khotab-item-{id}` and mirror id (plan E2), then a check of the stored link and its format. 93 % of medium mirrors are non-browser formats, which is F05-2 (B2) | **OPEN, evidence needed** |
| 3.3 | Do not report the reviewers' issue as fixed on the strength of F05-1 | standing rule |

## 4. F02-UI

| # | Item | Status |
|---|---|---|
| 4.1 | Presentation-only change. Backend authorisation is unchanged (tests) | done locally |
| 4.2 | Optional manual check after deployment: a non-super-admin with `authors.liststuff` sees no «تعديل الصلاحيات» | PENDING (post-deployment) |

## 5. Release gating (unchanged)

- B1 is not deployed. Commits and deploys are the owner's decision.
- There is no deployment Thursday–Saturday.
- B1 ships only after §1 is green and §2.1 has passed.
