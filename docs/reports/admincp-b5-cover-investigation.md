# AdminCP B5 (F10): custom Khotab cover — investigation and architecture review

**Date:** 2026-11-16.

**Request:** owner, "B5: Custom Khotab Cover / Thumbnail — Investigation & Architecture Review ONLY".

**Status:** **INVESTIGATION COMPLETE. NOTHING IMPLEMENTED. Awaiting owner decisions (§14).**

- No application code, migration, schema or data change. No production access. No commit, push or deployment.
- `7amlat` and `old-project` were read only. The local MySQL copy (`olddb`) was read only.
- The full test suite was not run (investigation only).

**Evidence key:**
- **CONFIRMED** means by code reading or the local data copy.
- **UNVERIFIED** means it needs production or network access.
- **INFERRED** means a reasoned assumption.

---

## 1. Executive summary

- **The "Archive.org image" is a local file, not a live Archive.org URL.**
  - When an item is added, or when an admin presses «سحب الصور», Laravel reads the item's Archive.org
    `{identifier}_files.xml`. It picks the **middle** of the "Thumbnail" files and **downloads it once** to
    `media/khotab_frames/{floor(id/1000)}/{id}.jpg`, then sets `nuke_islamic_khotab.frame = 1`.
  - Public pages serve that local file. **Nothing refreshes it automatically**; an Archive.org change appears only
    after an admin re-runs «سحب الصور».
- **The Laravel video player has no poster.** It is a bare `<video preload="metadata">`; the browser shows the first
  video frame. The frame file is used by the **home widgets, sidebar lists, featured cards and the social-share
  image** only. Khotab list pages (group, series, author, category, search) show **no per-item image**.
- **No existing field can safely hold a custom cover.**
  - `thumbnail` holds 6,467 legacy Archive.org URLs, which OLD used as the player poster and gallery.
  - `build_thumb` is OLD `thumb.php`'s work-queue flag.
  - `frame` and `gif` are 0/1 flags that every reader tests as `== 1`.
  - `leeched` is a leech-done flag.
- **A migration is not necessary.** A custom cover can be a **second file beside the archive frame**, with the
  resolver preferring it when it exists. That meets all five required behaviours without a column or table, and it
  can never be overwritten by an Archive.org fetch.
- **Recommendation:** a file-convention custom cover.
  - Store it in `media/khotab_covers/{b}/{id}.jpg`, re-encoded to JPEG.
  - One shared resolver: custom → existing archive logic → existing fallbacks.
  - Audit through the existing `admin_audit_log`.
  - **Migration requirement: NO.** It becomes **CONDITIONAL** if the owner wants the custom-cover state queryable in
    SQL, or wants Gen 2 / OLD pages to show it (§8).

## 2. Current image lifecycle (Khotab video items)

| Step | Where | Behaviour | Evidence |
|---|---|---|---|
| 1. Archive.org metadata read | `ArchiveOrgMetadata::read()` (`app/Domain/Admin/Actions/Khotab/ArchiveOrgMetadata.php`) | for an `archive.org` link: rewrites `https:` → `http:` (OLD parity), fetches `{identifier}_files.xml` (two URL shapes) with a timeout, collects every file whose `original` is the linked file and `format = Thumbnail` (`images`), picks **the middle one** as `frame`, and the `Animated GIF` as `gif` | CONFIRMED |
| 2a. Download at insert | `InsertKhotabItemAction:159` (group page), `InsertKhotabSeriesItemAction` and `InsertKhotabAuthorItemAction` through `KhotabArchiveImages::store()` | archive.org http(s) URLs only; `Http::get` with timeout; `file_put_contents` **replaces** `media/khotab_frames/{b}/{id}.jpg` (and `khotab_gifs/{b}/{id}.gif`); a success sets `frame = 1` (`gif = 1`) | CONFIRMED |
| 2b. Manual re-download | «سحب الصور»: `POST` `admin.khotab.items.images` → `LeechKhotabItemImagesAction` (`khotab.edititem`, CSRF, audited `leech_images`) | the same store; then `frame` / `gif` set if downloaded, `leeched = 1`. **This is the only way an Archive.org change reaches the site** | CONFIRMED |
| 3. Storage | `admin_khotab.media_root` (`KHOTAB_MEDIA_ROOT`), via `MediaPathResolver::path('khotab_frames', $id, 'jpg')` = `media/khotab_frames/{floor(id/1000)}/{id}.jpg` | local files. Locally: 113,307 frame JPEGs, typically **160×110** (older items) or **480–512×320–384** (newer), 6–45 KB | CONFIRMED (sampled) |
| 4. Public URL | `MediaUrl::asset($relative)` = `media.base_url` + path, **no version suffix** | a replaced file keeps the same URL | CONFIRMED |
| 5. Rendering | §4 consumers; each checks `frame === 1` **and** `file_exists()`, then falls back | | CONFIRMED |
| 6. Automatic refresh | **none:** no scheduled job, console command or queue job touches frames (`routes/console.php` schedules only the stats job) | | CONFIRMED |

**Answers to the investigation questions (§2 of the request):**

| # | Question | Answer |
|---|---|---|
| 1 | Where does the thumbnail or poster come from? | the local file `media/khotab_frames/{b}/{id}.jpg` (and `khotab_gifs/{b}/{id}.gif` on GIF-first surfaces). There is **no poster** in the Laravel player |
| 2 | Retrieved directly from Archive.org? | **no** at render time; only at insert or «سحب الصور» |
| 3 | Is the Archive.org URL generated dynamically? | only during the fetch (from `files.xml`); never stored or rendered |
| 4 | Is the image URL stored in the database? | **no** for the frame (only the `frame` flag). The legacy `thumbnail` column holds pipe-separated Archive.org thumbnail URLs for 6,467 rows (ids 896–32,180), **unused by Laravel** |
| 5 | Downloaded and stored locally? | **yes** |
| 6 | Fallback images? | yes, consumer-specific: `/images/tvnoise.gif` (home), the author photo then `/images/way2_withoutimg.png` (sidebars), Gen 2's default share image (social), none (featured cards are only drawn for `gif = 1 OR frame = 1`) |
| 7 | Does the player use a different source? | the player uses **none** (no `poster`). OLD's `ShowPlayer(…, $Khotab['thumbnail'])` used the `thumbnail` column; Laravel does not |
| 8 | No Archive.org image? | the fetch fails or finds none, so the flag stays 0 and consumers use their fallbacks |
| 9 | Cached? | no application cache of image data. The sidebar widgets cache **rows** (300 s), not image URLs; thumbs are recomputed on read with a live `file_exists()`. HTTP caching of `/media/…` static files depends on the production web server (**UNVERIFIED**). `thumbnails.php` (the resizer, 10-day public cache) is **not used for Khotab** |
| 10 | Can a sync or import overwrite an image? | **yes, all on the same path:** Laravel's three insert actions and «سحب الصور» (replace); OLD `get_gif_jpg.php` (§5); OLD admin `ConfirmInsertKhotab` (append, new ids only) |

## 3. Relevant files and database fields

**Table `nuke_islamic_khotab`** (main connection; 292,592 rows locally). Image-related columns:

| Column | Meaning (CONFIRMED) | Local data | Free for a custom cover? |
|---|---|---|---|
| `frame` | 1 = a frame file was downloaded | visible video items: 151,906 with `frame = 1`, 89,907 with `frame = 0` | **no:** every reader tests `== 1` (PHP and SQL, Laravel and legacy); any other value would hide the image everywhere |
| `gif` | 1 = an animated GIF was downloaded | about 49,840 video items | no |
| `leeched` | 1 = «سحب الصور» or the OLD leecher ran | | no |
| `thumbnail` | pipe-separated Archive.org thumbnail URLs (OLD item gallery `index.php:1369`, OLD player poster `:1517`, OLD admin `:5118`) | 6,467 rows | **no:** legacy data, must not be overwritten |
| `build_thumb` | OLD `thumb.php` work-queue flag (`= 0` selects, then writes 1 or 2) | all 0 | **no:** it has legacy semantics and could be re-run |
| `banner` | 1 = an uploaded banner GIF (`media/banner/…`) | | no (different feature) |

**Code** (all CONFIRMED):

| Role | File / method |
|---|---|
| Path convention | `app/Domain/Content/Support/MediaPathResolver.php` (`path()`, `absolute()`), `app/Domain/Content/Support/MediaUrl.php` (`asset()`) |
| Model helpers | `KhotabItem::videoFramePath()`, `videoGifPath()`, `socialImageUrl()` (GIF first, then frame, file-checked) |
| Fetch and store | `ArchiveOrgMetadata`, `KhotabArchiveImages`, `InsertKhotabItemAction:155-165`, `InsertKhotabSeriesItemAction`, `InsertKhotabAuthorItemAction`, `LeechKhotabItemImagesAction` |
| Admin | `KhotabItemAdminController::leechImages()` (shows the stored frame), `KhotabItemControls` («سحب الصور» and the banner, PDF and DOCX controls) |
| Comparable uploads | `KhotabItemFiles` (banner, PDF, DOCX: fixed path, flag, audit), `UploadKhotabAuthorImageAction` (F06: fixed path, `?v=mtime`, previous value audited), `UploadAnasheedItemFrameAction` (Variety frame: fixed path, backup and restore on failure, `frame = 1`, audit) |
| Config | `config/admin_khotab.php` (`media_root`, `archive_timeout`) |

## 4. Consumer inventory

| # | Consumer | Current source and resolution | Fallback | Shared logic? | Custom cover effect (proposed) |
|---|---|---|---|---|---|
| C1 | Home: latest videos and dump files (`HomeController::bucketedThumb()`, used through `withVideoThumbs`) | `frame === 1` and file exists → frame | `/images/tvnoise.gif`. With `frame = 0`, the author photo, then tvnoise | private method; the same shape as C2 | yes: custom first |
| C2 | Sidebar lists: «الأكثر تحميلا», «جديد», top items and similar (`ContentSidebarWidget::topitemsThumb()`, about 10 widgets; rendered by `x-content.top-items` and `sidebar-ranking`) | the same check | the author photo (author-scoped lists), then `/images/way2_withoutimg.png` | private method | yes: custom first |
| C3 | Featured cards «مادة مختارة» (`x-content.featured-items`; data from `ContentSidebarWidget::khotabRandomFeatured()`, `WHERE hidden = 0 AND (gif = 1 OR frame = 1)`) | **GIF first** (`gif = 1` → `khotab_gifs`), else frame; **no file check** | none (a missing file shows a broken image) | inline in Blade | ⚑ decision: should custom outrank the GIF? Selection includes only `gif = 1 OR frame = 1`, so a custom-only item is never chosen unless the query changes |
| C4 | Social image `og:image` (`KhotabItem::socialImageUrl()`, item page) | **GIF first**, else frame, file-checked | the layout's default image | model method | ⚑ the same decision as C3 |
| C5 | Video player (`MediaPlayerService::renderPlayer()`) | **no poster** | browser first frame | — | ⚑ adding `poster=` is **new behaviour** (OLD used `thumbnail`) |
| C6 | Khotab item page body | no frame image displayed (only C4 and the banner) | — | — | none unless requested |
| C7 | Group, series, author and category item lists; search results (media) | **no per-item image** (author photo or channel logo) | — | — | none |
| C8 | AdminCP: the «سحب الصور» result (`KhotabItemAdminController::leechImages()`) | shows the stored frame URL | — | — | should show both archive and custom (admin preview) |
| C9 | Gen 2 and OLD public pages (`7amlat/functions.php:161, 1048`, `home_functions.php:32, 374`, `khotab/item.php:82`; OLD modules) | the same frame file path | own fallbacks | — | **not affected** by a side-file design (they keep showing the archive frame) |

## 5. Archive.org integration findings

- **Metadata:** `{identifier}_files.xml`, fetched only on admin action (insert, «التالي» preview, «سحب الصور»).
  - There is no API key and no sync job.
  - Multiple thumbnail files per video exist (`format = Thumbnail`; the middle one is chosen). Their pixel sizes
    vary by item (sampled local copies: 160×110 and 480–512 wide).
  - Whether Archive.org serves larger sizes on request is **UNVERIFIED** (no network access in this investigation).
- **No proxy:** the site serves its own downloaded copy; no external image URL is rendered.
- **Writers that can replace `khotab_frames/{b}/{id}.jpg`:**
  1. Laravel: `InsertKhotabItemAction`, `InsertKhotabSeriesItemAction`, `InsertKhotabAuthorItemAction` (new items),
     and `LeechKhotabItemImagesAction` (any item, admin `POST`). All replace the file.
  2. **OLD `get_gif_jpg.php`** (CONFIRMED by reading; production reachability UNVERIFIED):
     - **unauthenticated GET**;
     - with `?id=N` (the OLD item page's «سحب» link) it processes that item regardless of `leeched`;
     - without an id it self-refreshes every second over `leeched = 0` items (10 at a time);
     - it writes with **`fopen(…, 'a')` (append)**, so a re-run appends a second JPEG to an existing file.
  3. OLD admin `ConfirmInsertKhotab` (`modules/Khotab/admin/index.php`, append mode, new items only).
  4. OLD `update_gif_jpg.php` only sets flags from file existence; it writes no images.
  5. Gen 2 has no frame writer.
- **Consequence for B5:** any design that **stores the custom cover at the archive frame's path** can be overwritten
  by (1), or appended to by (2). A design that stores it at its **own path** cannot.

## 6. Architecture comparison

| Criterion | A. Existing field | B. New nullable column on `nuke_islamic_khotab` | C. Separate table (`khotab_custom_frames`) | D. Existing file-convention infrastructure (side file) |
|---|---|---|---|---|
| What it means | reuse `thumbnail`, `build_thumb`, or `frame = 2` | e.g. `custom_cover` (path / version) | a Laravel-owned row per customized item | `media/khotab_covers/{b}/{id}.jpg`; its existence **is** the state |
| Schema change | none, but **redefines** a legacy field | **yes, on a shared legacy table** (plan F01–F10 says no column on `nuke_*`) | yes (additive, default connection) | **none** |
| Backward compatibility | **breaks:** `frame = 2` hides the image in every `== 1` reader (Laravel, Gen 2, OLD, SQL `frame = 1`); `thumbnail` / `build_thumb` have live legacy meaning | legacy ignores the column | legacy unaffected | legacy unaffected; legacy keeps the archive frame |
| Query performance | — | free (row already loaded, if selected) | a join or extra lookup per list, or eager loading | one extra `is_file()` per rendered thumbnail; consumers already `file_exists()` the frame. Lists render 6–24 thumbs |
| Storage complexity | — | file + column must agree | file + row must agree | **one artefact** (the file); no dangling reference possible |
| Archive.org fallback | — | resolver | resolver | resolver: no file → today's logic exactly |
| Admin workflow | — | upload / remove | upload / remove | upload / remove (same UI) |
| Replacement and deletion | — | write file, update column | write file, upsert row | atomic rename; delete or rename to remove |
| Data integrity | poor | two sources of truth | two sources of truth | **single source of truth** |
| Backup and restoration | — | DB + files | DB + files | files only (one more media folder) |
| Risk of overwriting existing media | **high** | low (own path) | low (own path) | **none:** no fetch path writes `khotab_covers` |
| SQL-queryable "has custom cover" | — | yes | yes | **no** (a filesystem scan, or the audit log) |
| Long-term maintainability | poor | medium | medium | high (mirrors `KhotabItemFiles`, F06 and the Anasheed frame pattern) |

**Variant considered and rejected: D′, overwrite the archive frame file**, keeping the archive copy as
`{id}.archive.jpg` plus a marker. Its advantage is that Gen 2 and OLD would show the custom cover. But:
- «سحب الصور» and the insert actions would need guards;
- OLD `get_gif_jpg.php?id` (no auth, append mode) would still corrupt it;
- "Archive.org updated while custom exists" would have to be redirected to the backup name.

It is more moving parts for a legacy display that, after B4's routing verification, should not be serving visitors.

## 7. Recommended design (not implemented)

1. **Storage:** `media/khotab_covers/{floor(id/1000)}/{id}.jpg` under `admin_khotab.media_root` (the same root and
   bucket rule as frames, in a separate folder). There are no user-supplied names.
2. **One resolver** (Content domain), e.g. `KhotabItemImage::url($id, $frame, $gif, $context)`:
   - custom file exists → `/media/khotab_covers/{b}/{id}.jpg?v={mtime}`;
   - otherwise → **exactly today's per-consumer logic** (frame or GIF, file check, existing fallback).
3. **Consumers switched to the resolver:** C1, C2 and C8. C3 and C4 per decision Q2. C5 (poster) only if Q1 approves
   new behaviour. C6, C7 and C9 unchanged.
4. **Admin:** on the Khotab item admin page and toolbar:
   - «رفع صورة مخصصة» (upload or replace);
   - «حذف الصورة المخصصة» (return to the archive image), shown only when a custom cover exists;
   - a preview of both the custom cover and the archive frame.
5. **Archive.org unchanged:** «سحب الصور» and the insert actions keep writing only `khotab_frames`. With no custom
   cover, the refreshed archive frame shows; with one, the custom cover stays.
6. **Audit:** `admin_audit_log` rows `upload_cover` / `replace_cover` / `remove_cover` (module `khotab`, entity
   `item`), with bytes, dimensions and a SHA-256 of the stored file.
7. **No `frame`, `gif`, `thumbnail` or other legacy value is written.**

All five required behaviours hold:

| Requirement | How |
|---|---|
| No custom cover → archive image | no side file, so today's logic |
| Valid custom cover → custom | the side file exists |
| Custom removed → archive image again | the side file is gone; the archive frame was never touched |
| Archive.org updates, no custom → updated image | «سحب الصور» replaces the frame as today |
| Archive.org updates while custom exists → keep custom | fetches never write `khotab_covers` |

## 8. Migration requirement

**NO** for the recommended design (D).

**CONDITIONAL:** a migration becomes necessary only if the owner wants:
- (a) to list or filter customized items in SQL (for example an AdminCP filter "has custom cover") — option C, a
  Laravel-owned table, preferred over B;
- (b) Gen 2 / OLD pages to show the custom cover — D′, which needs no migration but carries the overwrite risks
  in §6.

## 9. Upload and storage strategy

| Aspect | Proposal | Existing precedent |
|---|---|---|
| Formats | JPEG, PNG, WebP in; **always re-encoded to JPEG** (GD `imagecreatefrom*` → `imagejpeg`, quality about 85), which strips metadata and polyglot payloads. No GIF (animation) and no SVG ⚑ | B6a ad images (jpg/png/gif/webp, sniffed); F06 author image (jpg only) |
| Size | ≤ 2 MiB upload ⚑. Decoded dimensions ≤ 4000×4000 and ≥ 160×90 (pixel-bomb guard, `getimagesize` before decode) ⚑. Optionally downscale to a maximum width (for example 1280) ⚑ | B6a 2 MiB; F06 < 1 MB |
| Validation | `extensions` + `mimetypes` + `getimagesize` + a successful GD decode; reject on any mismatch | B6a `AdRequest` |
| Filename | fixed: `{id}.jpg` from the integer route id; a temporary file `{id}.upload-{random}.jpg` in the same directory, then `rename()` (atomic on the same filesystem) | Anasheed frame (backup and rename) |
| Location | `media/khotab_covers/{b}/` under `admin_khotab.media_root` | `MediaPathResolver` |
| Public URL | `MediaUrl::asset(...)` + `?v={filemtime}` | F06 `?v=mtime` |
| Replacement | the previous custom file is renamed to `{id}.previous-{random}.jpg`, the new one renamed in, and the audit row written; on audit failure the previous file is restored; on success the previous file is deleted (or kept ⚑) | `UploadAnasheedItemFrameAction` |
| Removal | rename to `{id}.removed-{timestamp}.jpg` (recoverable) ⚑, or delete; audit | — |
| Orphans | only `.upload-*` / `.previous-*` temporaries after a crash; a documented cleanup glob; never touches `khotab_frames` | — |
| Backup | **add `media/khotab_covers/` to the production media backup** (deployment prerequisite) | — |
| Permission | `khotab.edititem` (as «سحب الصور»), or a new seeded `khotab.uploadcover` ⚑; route middleware plus the action re-check; CSRF; `POST` / `DELETE` | `KhotabItemFiles` keys |
| Audit | `admin_audit_log` (no markup in metadata) | `AdminAuditRecorder` |
| Executable safety | the folder only ever receives re-encoded `.jpg`; the web server must not execute scripts under `media/` (the existing B6a prerequisite) | B6a prerequisite |

## 10. Failure handling

| # | Scenario | Recommended behaviour |
|---|---|---|
| 1 | Custom reference exists but the file is missing | not possible as a dangling reference in D: the file **is** the reference. If it disappears, the resolver falls back to the archive logic. (In B or C, the resolver must check the file and fall back the same way) |
| 2 | Archive.org thumbnail unavailable | a custom cover still shows. Without one, today's fallbacks are unchanged |
| 3 | Upload succeeds, DB (audit) update fails | the file is renamed in only inside the try block. On audit failure, restore the previous file (or remove the new one), as in `UploadAnasheedItemFrameAction`. The admin sees an error |
| 4 | DB succeeds, file operation fails | order the work so the file step comes **first** (temp write, validate, re-encode, rename), then audit; a file failure aborts before any record |
| 5 | Two admins replace concurrently | each writes its own temporary file. A per-item `Cache::lock('khotab-cover:{id}')` around backup → rename → audit serializes them: the last writer wins and both are audited, with no half-written file |
| 6 | Admin removes the cover | rename it away (recoverable) and audit. The resolver immediately serves the archive frame |
| 7 | Archive.org refresh after customization | it writes only `khotab_frames`; the custom cover keeps winning; after removal the newer archive frame appears |
| 8 | Cached pages with an older image URL | the custom URL carries `?v=mtime`, so a replacement gets a new URL. Laravel pages are not publicly cacheable (B4 test). Static `/media/` caching headers in production are UNVERIFIED (prerequisite). The archive frame URL is unversioned today (pre-existing; out of scope ⚑) |

## 11. Performance considerations

- **Resolver cost:** one extra `is_file()` per rendered thumbnail. Today each already does one `file_exists()` for the
  frame. A typical page renders 6–24 Khotab thumbnails, so at most about 24 extra `stat` calls, cached by PHP's stat
  cache within a request. **No extra database query.**
- **Lists without images** (group, series, author, category, search) are unaffected.
- **No new indexes or queries.** C3's random selection query is unchanged unless Q8 approves including custom-only
  items, which would need a file scan or a table (CONDITIONAL).
- **Split layout:** `media_root` must be the directory served at `media.base_url`, as already required for F06
  (`media:check-roots`).

## 12. Security considerations

- **Upload:** sniffed type, decoded by GD and re-encoded (no polyglot survives), dimension guard, size limit, fixed
  integer-derived path (no traversal), no user filename, `.jpg` only on disk.
- **Authorization:** server-side permission, `POST` / `DELETE` with CSRF, the action re-checks the permission, audit.
- **Output:** URLs built from integers only; Blade escaping; no stored URL is rendered (unlike `thumbnail`).
- **Legacy:** OLD `get_gif_jpg.php` is an unauthenticated writer into `khotab_frames` (pre-existing). D keeps
  custom covers out of its reach. Its reachability belongs to the B4-style routing verification.
- **GD in production:** UNVERIFIED (`php -m`). Available locally.

## 13. Compatibility risks

| Risk | Impact | Mitigation |
|---|---|---|
| Gen 2 / OLD pages keep the archive frame | if those pages still serve visitors, two different images exist | B4 routing verification; or D′ (§6), not recommended |
| GIF-first surfaces (C3, C4) | a custom cover is not shown there unless Q2 says it outranks the GIF | Q2 |
| Featured selection (C3) only picks `gif = 1 OR frame = 1` | custom-only items (89,907 video items have `frame = 0`) never become featured | Q8 |
| Static file caching | an old custom image could linger in browsers or a CDN without `?v` | `?v=mtime` |
| Backups | covers are lost if the new folder is not backed up | deployment prerequisite |
| Disk | at most about 50–300 KB per cover after re-encode | negligible |

## 14. Questions requiring owner approval

1. **Which surfaces use the custom cover?** Home widgets (C1), sidebars (C2) and admin preview (C8) are recommended
   yes. Should the **video player gain a `poster`** (C5, new behaviour)?
2. On the **GIF-first** featured cards (C3) and social image (C4), should the custom cover **outrank the animated
   GIF**? Recommended: yes, matching "a valid custom cover exists → display the custom cover".
3. **Scope:** video items only, or audio items as well? (The request says video materials.)
4. **Permission:** reuse `khotab.edititem`, or add `khotab.uploadcover` (a seeder change, manual assignment)?
5. **Formats and limits:**
   - JPEG/PNG/WebP in and JPEG out;
   - 2 MiB;
   - dimension bounds;
   - optional downscale width.
6. **Accept that Gen 2 / OLD pages do not show custom covers** (design D)? Alternatively, choose D′ with its
   overwrite risks.
7. **Removal and replacement:** keep a recoverable copy (`.removed-` / `.previous-`) or delete? For how long?
8. **Featured selection:** should items that have only a custom cover become eligible for «مادة مختارة»? That is a
   query change, and needs a table if done efficiently.
9. **Storage folder name** `media/khotab_covers/`, and adding it to production backups.
10. **Pre-existing:** should the archive frame URLs also get `?v=mtime`, so a re-leech busts caches? (Out of B5 unless
    approved.)
11. **Migration:** confirm no SQL listing is needed. Otherwise choose option C.

## 15. Proposed implementation stages (after approval)

| Stage | Scope | Tests |
|---|---|---|
| S1 | resolver + consumers C1, C2 and C8 (+ C3, C4 and C5 per Q1, Q2); read-only, with no upload yet. Covers placed by test fixtures | each consumer: no cover = today's output byte for byte; a cover wins; a missing file falls back; `?v` |
| S2 | upload / replace / remove actions, request validation, GD re-encode, lock, audit, admin UI, permission (Q4) | formats, size and dimension limits, polyglot rejection, path safety, CSRF and methods, authorization, audit, concurrency (lock), failure rollbacks (§10), archive re-leech never touches covers, removal falls back |
| S3 | documentation and deployment checklist: GD in production, folder and permissions, backups, `/media/` cache headers, `media:check-roots`, legacy routing (B4 blockers) | — |

No stage needs a migration under design D.
