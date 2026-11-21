# AdminCP B5 (F10): custom Khotab cover — implementation results

**Date:** 2026-11-16.

**Approval:** owner, "B5: Custom Khotab Cover Implementation — APPROVED FOR LOCAL IMPLEMENTATION". It is based on
`docs/reports/admincp-b5-cover-investigation.md`.

**Decision log:** #142.

**Status:** **CLOSED LOCALLY** (2026-11-16): implemented, and validation passed. **Not deployed, not production-verified;
production prerequisites are open (§10).**

- Not committed, not pushed, not deployed. No production access.
- **No migration, no schema change, no data change.** The only database write is the audit row.
- `7amlat` and `old-project` are unmodified.
- **Local completion is not production readiness** (§10 lists the deployment prerequisites).

---

## 1. What was implemented

**Storage.** A custom cover for a Khotab **video** item is a file of its own:
`media/khotab_covers/{floor(id/1000)}/{id}.jpg`, under `admin_khotab.media_root`. It uses the same root and bucket
rule as `khotab_frames`, `khotab_gifs`, `banner` and `authors`.

- **The file itself is the only state.** There is no column or table, and `frame`, `gif`, `build_thumb` and
  `thumbnail` are never read or written for covers.
- **Archive.org copies are never touched.** The Archive.org fetchers (`KhotabArchiveImages`, the three insert
  actions, «سحب الصور») write only `khotab_frames` and `khotab_gifs`, so they can never overwrite a cover.

**Resolver.** `App\Domain\Content\Support\KhotabCover::url($id)`:
- returns `/media/khotab_covers/{b}/{id}.jpg?v={mtime}-{inode}` when a non-empty cover file exists, otherwise null;
- makes one filesystem check per call and **no database query**;
- its version changes on every replacement, even within the same second, because each replacement renames a new
  file (a new inode) into place.

**Image priority** on the existing image surfaces (§2): a valid cover first, then the **exact existing resolution
and fallbacks**.

**AdminCP:** «صورة الغلاف» on the Khotab item toolbar, for video items only.
- **The page** (`GET admincp/khotab/items/{id}/cover`) shows the custom cover and the Archive.org frame side by
  side.
- **Upload or replace:** `POST …/cover`.
- **Remove:** `DELETE …/cover`, which returns the item to the Archive.org image.
- **Access:** `khotab.edititem` (an existing key) through route middleware, re-checked in the action; CSRF.
- **Audit:** `upload_cover` / `replace_cover` / `remove_cover`.
- **Leech result page:** «سحب الصور» now notes when a cover overrides the archive frame it shows.

## 2. Image resolution priority (per surface)

| Surface | Code | With a cover | Without a cover (unchanged) |
|---|---|---|---|
| Home widgets (latest videos, dump files) | `HomeController::bucketedThumb()` | cover | frame (file-checked) → `tvnoise.gif`; `frame = 0` → the author photo → `tvnoise.gif` |
| Sidebar widgets (about 10 lists: «الأكثر تحميلا», «جديد», channel and author lists, …) | `ContentSidebarWidget::topitemsThumb()` | cover | frame (file-checked) → the author photo (author lists) → `way2_withoutimg.png` |
| Featured cards «مادة مختارة» | `x-content.featured-items` | cover, **outranking the GIF** | GIF if `gif = 1`, else frame (as before, no file check) |
| Social share image `og:image` | `KhotabItem::socialImageUrl()` | cover, **outranking the GIF** | GIF, else frame, file-checked → the layout default |
| AdminCP preview | `KhotabItemCoverController::show()`, `admin/khotab/items/images` | cover and the archive frame side by side | archive frame |

**Not changed, as instructed:**
- **The video player:** no poster, tested.
- **Featured selection eligibility:** `WHERE hidden = 0 AND (gif = 1 OR frame = 1)` is untouched, so a cover never
  makes an item eligible (tested).
- **Pages without thumbnails** (group, series, author, category, search) gain none.

**Sidebar caching.** The sidebar widgets cache their **rows** for 300 s, but every thumb, the cover included, is
resolved after the cache read. A new or removed cover therefore shows immediately; the same holds for the home
widgets.

## 3. AdminCP workflow, replacement and removal

**Upload / replace** (`KhotabItemCoverAction::upload()`):

1. **Authorization.** `khotab.edititem`. Non-video items are refused with «الصورة المخصصة متاحة لمواد الفيديو فقط»,
   and their page has no upload form.
2. **Request validation.** Required file, ≤ 2 MiB, extension `jpg/jpeg/png/webp`, sniffed MIME
   `image/jpeg|png|webp`.
3. **Content validation.**
   - `getimagesize()` must report JPEG, PNG or WebP.
   - Dimensions are checked from the header **before any decode**: at least 160×90 (the smallest Archive.org frames
     are 160×110), at most 6000×6000 and 24 MP (decompression-bomb guard).
   - Then a full GD decode, whose dimensions must match the header.
4. **Re-encode.**
   - **Always to JPEG** (quality 85, progressive). Metadata and any appended payload are gone, and transparency is
     flattened on white.
   - Downscaled to fit 1280×1280 **keeping the aspect ratio**, never upscaled.
   - Why these bounds: Archive.org frames are ≤ 512 px, the largest consumer is the social image, and the existing
     uploads allow 1–2 MiB.
5. **Under one per-item lock** `khotab-cover:{id}` (`Cache::lock`, 30 s TTL; waits `admin_khotab.cover_lock_wait`,
   default 10 s):
   1. write the JPEG to a temporary file in the target directory;
   2. move an existing cover aside to a `.rollback-*` file;
   3. rename the temporary file into place (atomic on one filesystem);
   4. write the audit row in a transaction;
   5. only then delete the rollback file.

   Any failure in steps 2–4 puts the previous cover back, or removes the new one if there was none. The temporary
   file is always removed. **No history is kept.**

**Remove** (`remove()`): under the same lock, the cover is moved aside, the removal is audited, then the file is
deleted; it is restored if the audit fails. With no cover it writes and records nothing. The Archive.org frame is a
different path and is never involved.

**Concurrency.** A second upload or removal on the same item waits for the lock, then refuses with «يجري تعديل صورة
هذه المادة الآن…» and changes nothing. Different items are independent.

## 4. Security protections

- **Content decides.** The name and the declared MIME are only pre-checks. Polyglots, PHP files and SVG renamed
  `.jpg`, truncated or corrupted images, GIF and empty files are rejected (tested). The stored file is a JPEG
  produced by GD.
- **Deterministic paths.** Paths derive from the integer route id (`whereNumber`); no user-supplied name or path is
  used. Temporary and rollback names are `{id}.jpg.upload-{random}` / `.rollback-{random}` in the same directory.
- **Authorization.** Server-side (`admin.permission:khotab.edititem` plus the action re-check), with the super-admin
  bypass. POST / DELETE with CSRF. GET never writes.
- **Audit.** Each audit row records the path, source format, source and stored dimensions, bytes and SHA-256; it
  never contains markup.
- **Output.** Cover URLs are built from integers and escaped by Blade.

## 5. Changed files

**New:**

| File | Role |
|---|---|
| `app/Domain/Content/Support/KhotabCover.php` | path convention + resolver (`url()`) |
| `app/Domain/Admin/Actions/Khotab/KhotabItemCoverAction.php` | upload / replace / remove: validation, re-encode, lock, atomic swap, rollback, audit |
| `app/Domain/Admin/Http/Controllers/KhotabItemCoverController.php` | page, store, destroy |
| `resources/views/admin/khotab/items/cover.blade.php` | AdminCP page (previews, forms) |
| `tests/Feature/Admin/KhotabCoverB5Test.php` | 32 tests |

**Edited:**

| File | Change |
|---|---|
| `app/Domain/Content/Http/Controllers/HomeController.php` | `bucketedThumb()`: cover first |
| `app/Domain/Content/Services/ContentSidebarWidget.php` | `topitemsThumb()`: cover first |
| `app/Domain/Content/Models/KhotabItem.php` | `socialImageUrl()`: cover first |
| `resources/views/components/content/featured-items.blade.php` | cover first, then the existing GIF / frame rule |
| `resources/views/admin/khotab/items/images.blade.php` | note when a cover overrides the archive frame |
| `app/Domain/Admin/Contextual/Controls/KhotabItemControls.php` | toolbar link «صورة الغلاف» (video items) |
| `routes/admin.php` | 3 routes in the existing `khotab.edititem` group |
| `config/admin_khotab.php` | `cover_lock_wait` (`KHOTAB_COVER_LOCK_WAIT`, default 10) |
| `docs/decision-log.md` | #142 |

`KhotabArchiveImages`, `LeechKhotabItemImagesAction`, `ArchiveOrgMetadata`, the insert actions and
`MediaPlayerService` are **unchanged**.

## 6. Tests and validation

### Targeted (`KhotabCoverB5Test`, **32 passed**)

Mapped to the owner's list:

| # | Covered by |
|---|---|
| 1, 3 | no cover: sidebar, featured and social show the frame; the GIF item keeps GIF-first on featured and social |
| 2, 4 | a cover wins on the sidebar, featured, social and **home page**, including over the GIF |
| 5 | removal restores frame and GIF-first; the archive frame file is untouched |
| — | a video item without any archive image can get a cover; removal returns to its fallback |
| 6 | audio item refused, no file, no audit; its page shows no upload form; the toolbar offers no link |
| 7 | two kinds of admin without `khotab.edititem` get 403 on view, upload and remove; super-admin passes; the action re-checks (`AuthorizationException`); visitors are sent away; GET never deletes; CSRF fields and multipart present |
| 8 | JPEG, PNG (transparency → white) and WebP accepted and stored as JPEG; 2560×1440 → 1280×720; 900×1800 → 640×1280 |
| 9 | 9 rejections (text or PHP renamed `.jpg`, truncated JPEG, GIF, SVG, too small, 6100-px, > 2 MiB, empty); the current cover byte-identical; no temporary file left |
| 10 | failed replacement (audit write fails): the current cover byte-identical, no temporary file. Failed first upload: no cover. Failed removal: the cover kept |
| 11 | while the item lock is held, upload and remove are refused and change nothing; after release, the upload succeeds |
| 12 | an Archive.org refresh (`KhotabArchiveImages::store`, HTTP faked) rewrites only `khotab_frames`; the cover is unchanged and still wins; after removal the refreshed frame shows |
| 13 | missing or empty cover file → existing image |
| 14 | each replacement changes the `?v=` (two in the same second differ); same path |
| 15 | existing card and widget output with no cover (1 & 3), plus the existing suites below |
| 16 | `POST /media-player`: `<video>` without `poster`, no cover URL |
| 17 | 60 random featured draws: a covered `frame = 0` item never appears; a covered eligible item does |
| 18 | upload, replace and remove: no database write except `admin_audit_log`; `nuke_islamic_khotab` rows identical; main-connection table list identical |
| audit | `upload_cover`, `replace_cover`, `remove_cover` with metadata; a removal without a cover records nothing |

**Mutation checks.** Each of these, applied alone, fails at least one test:
- the sidebar, home, featured or social cover-first branch removed;
- the video-only check removed;
- the lock removed;
- the minimum or maximum dimension checks removed.

**Two survivors are defence in depth:**
- removing the inner rollback alone is still covered by the outer restore;
- removing the temporary-file cleanup alone changes no tested path. On validation failure no temporary file
  exists, and on success it has been renamed. The cleanup matters only if an encode fails mid-write, which cannot
  be reproduced deterministically.

### Related existing suites

`--filter "Khotab|Home|Sidebar|Featured|Contextual|Social|Channel|KhotabItem"`: **1,046 passed, 0 failed.**

### Full validation

| Check | Result |
|---|---|
| **Full suite** `timeout 900 vendor/bin/pest --log-junit … < /dev/null` (stdin closed, bounded, output captured; see note) | **3,684 tests: 3,645 passed, 0 failed, 0 errors, 39 skipped**; 31,315 assertions; exit 0 in about 6 min (B4 closed at 3,652: +32). Skips unchanged and environment-gated (MariaDB 34, benchmarks 4, GD 1) |
| PHPStan | **0 errors** |
| Pint `--test` | **passed** |

**Note:** the full suite is run with `stdin` closed and a hard `timeout`, after the owner reported an earlier
unbounded Pest run stuck polling stdin for hours.

## 7. Legacy compatibility

- **OLD and Gen 2 continue to display their existing Archive.org frame.** They read `khotab_frames/{b}/{id}.jpg`
  directly (`7amlat/functions.php:161, 1048`, `home_functions.php:32, 374`, `khotab/item.php:82`, OLD
  `modules/Khotab`). If those pages still serve visitors, the same item shows the archive image there and the cover
  on Laravel pages. Accepted by the owner (design D).
- **No legacy flag or column is changed**, so legacy queries (`frame = 1`, `gif = 1`) behave exactly as before.
- **Preserved risk, not fixed by B5:** OLD `get_gif_jpg.php` is an **unauthenticated GET** that writes Archive.org
  images into `khotab_frames` in **append mode** (`?id=N` for any item, or a self-refreshing batch over
  `leeched = 0` items). It cannot reach `khotab_covers`, so covers are safe from it. The archive frames it writes
  remain exposed to it, as before B5. Its production reachability is part of the B4-style routing verification.

## 8. Known limitations

- **Video only, enforced at upload.** If an item is later edited from video to audio, an existing cover stays and
  still shows on any image surface listing it. Admins can still open «صورة الغلاف» and remove it (removal is
  allowed for any item).
- **No SQL listing of covered items.** The state is the file; listing means a directory scan or the audit log
  (accepted: no table approved).
- **Archive frame URLs stay unversioned** (pre-existing; outside B5 by instruction).
- **`cover_lock_wait`** relies on the configured cache store supporting atomic locks (`file`, `database` and `redis`
  do; `array` is per-process).
- **GD.** JPEG, PNG and WebP are required. WebP support depends on the production GD build (UNVERIFIED). Without
  it, WebP uploads are refused cleanly («تعذرت قراءة الصورة»).

## 9. Storage verification (local)

| Requirement | Finding |
|---|---|
| Compatible with media-root conventions | **yes:** the same `admin_khotab.media_root` (`KHOTAB_MEDIA_ROOT`) and `MediaPathResolver` bucket rule as the existing writers (frames, GIFs, banners, PDF, DOCX, author images); a separate folder |
| Persistent across deployments | locally `public/media` is a symlink to the shared legacy media tree, outside the release. In production, `KHOTAB_MEDIA_ROOT` = `newapp/media` (`docs/operations/split-layout-media-roots.md`). The `deploy:verify-package` rules do not mention `media/`, so whether an extraction can touch it is **not established**. **Production: UNVERIFIED**, the same open prerequisite as every existing media writer |
| Backups | **must include `media/khotab_covers/`** (§10) |
| Public URLs | `MediaUrl::asset()` → `media.base_url` + path, the same as frames; resolves when `media_root` is the directory served at `media.base_url` (`media:check-roots`) |
| No script execution | only GD-produced `.jpg` files are ever written there. Server-side prevention under `media/` is the existing open prerequisite (B6a: `media/bann/`) |

No blocker was found locally; nothing required a new deployment configuration.

## 10. Deployment prerequisites (not performed)

1. **Persistent media storage.** Confirm `KHOTAB_MEDIA_ROOT` is absolute and persistent (`newapp/media`), and run
   `php artisan media:check-roots --expect-web-root=…` (exit 0).
2. **Writable directory.** The web PHP user must be able to create `media/khotab_covers/` and its bucket
   subdirectories (0775) and rename files inside them.
3. **Public serving.** `/media/khotab_covers/{b}/{id}.jpg` must be served as a static image at `media.base_url`.
4. **Backups.** Add `media/khotab_covers/` to the production media backup.
5. **Restore verification.** Restore one cover into a staging copy and confirm it displays.
6. **Script execution prevention.** Confirm the web server does not execute scripts under `media/` (or at least
   `media/khotab_covers/`).
7. **Cache behaviour.** Cover URLs are versioned (`?v=`), so long static caching is safe. Laravel pages are not
   publicly cacheable. Confirm no CDN rewrites query strings away.
8. **GD.** Confirm with `php -m` / `gd_info()` in production that JPEG, PNG and WebP are supported.
9. **Cache store** supports locks (`CACHE_STORE`).
10. **Legacy routing** (B4 blockers): OLD `get_gif_jpg.php` and the legacy pages.

## 11. Deviations from the approved design

None of substance:
- `?v=` uses `{mtime}-{inode}` instead of `{mtime}` alone, so two replacements within one second still bust
  caches.
- `admin_khotab.cover_lock_wait` was added (a configuration key, default 10 s) so the lock wait is bounded and
  testable.
