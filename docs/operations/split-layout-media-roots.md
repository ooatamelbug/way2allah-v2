# Split layout & media roots (P0) — operations reference

Added 2026-11-09 (plan register row N5, `decision-log.md` #135). **Status (2026-11-09, `decision-log.md` #136): CLOSED
LOCALLY — OWNER-ACCEPTED — PRODUCTION VERIFICATION PENDING** (previously: implemented and tested locally). Nothing in
this document has been run against production. Accepted limitations and the full prerequisite list: §12.

## 1. The layout

```text
/home/w2acp/
├── newapp/        web root (document root) — front controller, assets, media
└── laravel-app/   Laravel application (base_path) — app/, config/, storage/, vendor/ …
```

Recorded from production on 2026-09-10 (`progress/10-09-2026.md` §7). The production values below must be re-verified
before use; this document does not infer production state from local paths.

## 2. `public_path()` differs between HTTP and CLI

| Context | `public_path()` | Why |
|---|---|---|
| HTTP request | `/home/w2acp/newapp` | `newapp/index.php` calls `$app->usePublicPath(__DIR__)` before handling the request |
| `php artisan …`, queue workers, scheduler | `/home/w2acp/laravel-app/public` (does not exist) | `artisan` never sets a public path; Laravel falls back to `basePath('public')` |
| `config:cache` — from the CLI **or** from the deployment installer's HTTP request | `laravel-app/public` | `ConfigCacheCommand` builds a *fresh* application and re-applies only the storage path |

Consequence: any configuration default computed with `public_path()` is baked into the config cache with the
`laravel-app/public` value, and every later HTTP request uses it. Runtime `public_path()` calls inside HTTP code
(thumbnails, album ZIPs, gallery downloads, comment flags) are unaffected.

`bootstrap/app.php` and `public/index.php` are deliberately unchanged (owner decision D1).

## 3. Required production variables (all five, absolute)

```dotenv
KHOTAB_MEDIA_ROOT=/home/w2acp/newapp/media                 # verify before use
ANASHEED_MEDIA_ROOT=/home/w2acp/newapp/media
W2ACD_MEDIA_ROOT=/home/w2acp/newapp/media
W2ACD_IMAGE_ROOT=/home/w2acp/newapp/images/cds_image2
AUTOCOMPLETE_ROOT=/home/w2acp/newapp/w2a_autocomplete
```

The template is `.env.production.example` (placeholders `/absolute/path/to/…`, no secrets). The configuration keys
and variable names are unchanged: `admin_khotab.media_root`, `admin_anasheed.media_root`, `admin_cds.media_root`,
`admin_cds.image_root`, `media.autocomplete_root`. Without them, AdminCP uploads (Khotab archive images, frames and
banners, author images, Anasheed icons and frames, W2ACD images, banners and group icons) and the autocomplete
lists are written under `laravel-app/public/…` — not served — and the header's author / channel lists read empty.

**Config cache dependency (D6):** `config:cache` stays, provided the five values are set first. After any `.env`
change: `php artisan config:clear` → `php artisan config:cache` → `php artisan media:check-roots --expect-web-root=…`.

## 4. Required directories

| Directory (under the web root) | Read by | Written by |
|---|---|---|
| `media/` (incl. `khotab_gifs/`, `khotab_frames/`, `authors/`, `authors/sq/`, `locations/`, `banner/`, `anasheed/…`, `w2acd/…`, `albums/`) | visitor pages, thumbnails, ZIPs | AdminCP (Khotab, Anasheed, W2ACD) |
| `images/cds_image2/` | W2ACD pages | AdminCP W2ACD releases |
| `w2a_autocomplete/` (`authors.txt`, `channels.txt`, `masjids.txt`) | every page's header search | AdminCP author edit / delete |

Writable by the **web PHP user**: the three roots and the AdminCP subfolders (writers create missing bucket
subdirectories with `0775`). The no-SSH guide expects real directories, not symlinks; `media:check-roots` reports
symlinks and their canonical targets. Under `laravel-app/`: `storage/` and `bootstrap/cache/` writable (unchanged).

## 5. Visitor readers now use the configured root (G2)

`Author::fallbackImageUrl()`, `Location::photoUrl()`, `HomeController::bucketedThumb()` and
`ContentSidebarWidget::topitemsThumb()` check file existence through `MediaPathResolver::absolute()`
(`admin_khotab.media_root`) instead of `public_path('media/…')` — the same directory their AdminCP writers use.
URLs, file naming and fallbacks are unchanged; by default (uncached HTTP) the configured root equals
`public_path('media')`, so local behaviour is identical.

## 6. Security fixes

- **S1 — CD images:** the stored extension comes from the server-detected type (`getimagesize()` and content
  finfo must agree on JPEG / PNG / GIF), never from the client file name; anything else is refused with a
  controlled AdminCP error. A `.jpeg` JPEG is now stored as `.jpg` (new uploads only; existing files untouched).
- **S2 — gallery downloads:** `/albumimg-download-{id}.htm` serves a database path only when its canonical
  location is a readable file inside the public root or the resolved target of `public/media` (decision #107);
  traversal and other symlinks out of the root return 404. Routes, permissions and download names are unchanged.

## 7. Read-only audits

- `php artisan media:check-roots [--json] [--expect-web-root=/home/w2acp/newapp]` — per root: path, absolute,
  exists, readable, writable (for **this** process's user), symlink, canonical target; config-cache state; whether
  the five agree on one `<web root>/{media, images/cds_image2, w2a_autocomplete}` structure; split-layout hazard
  (roots inside a missing `laravel-app/public`); a stray `laravel-app/public/` hint. Exit 0 clean / 1 findings /
  64 usage. Never creates, changes, copies, moves or deletes anything and prints no other environment values.
- `php artisan deploy:verify-package <dir|zip> [--full] [--json]` — see §8.

## 8. Packaging restrictions (enforced by `deploy:verify-package`)

Run on the staged directory or the finished ZIP **before** upload — `--full` for a full release; exit 0 required
(1 violations, 2 unreadable, 64 usage). Read-only.

1. `newapp/index.php`, when shipped, must be byte-identical to `deploy/split-layout/newapp-index.php`. Never map
   `public/index.php` (the co-located local variant) into `newapp/`.
2. No file under `newapp/w2a_autocomplete/` — the lists are regenerated on the server; `public/w2a_autocomplete/*.txt`
   are tracked in git (despite `.gitignore:31`) and must be excluded.
3. No file under `laravel-app/bootstrap/cache/` except `.gitignore` (`config.php`, `routes-*.php`, `events.php`,
   `packages.php`, `services.php` are environment-specific; Laravel regenerates them in the writable directory).
4. Nothing under `laravel-app/public/`; only `newapp/` and `laravel-app/` at the top level (extract at `/home/w2acp/`).

The manual, owner-controlled procedure in `progress/10-09-2026.md` §9 stays the procedure; its dated note adds the
mapping exclusions and the verification step.

**Correction 2026-11-09 (owner-approved; final acceptance review concern 3, defects D1 / D2 / D4):**

- **Strict entry names (`unsafe_entry_name`).** Every entry — files and directory markers — is validated *before*
  the rules above. Rejected, never normalised: a leading `/` (absolute path), a backslash, a NUL byte, an empty
  segment (`//`), a `.` or `..` segment (leading or embedded), and any top level other than the `newapp/` and
  `laravel-app/` directories. A single trailing slash is accepted only on a genuine directory entry (in a ZIP, not
  when the Unix mode bits say "regular file"). Directory markers are checked against the protected paths too (a
  marker for a protected directory itself is harmless; anything inside it is refused). ZIP names are read as raw
  bytes from the central directory and cross-checked with libzip, which silently rewrites some names (a NUL
  becomes a space) — any difference is refused. ZIP64 archives and archives whose layout does not add up
  (prepended data, hidden bytes, truncation) are reported as unreadable (exit 2), never partially checked.
- **Duplicate entries (`duplicate_entry`).** The same path twice is refused — an extractor writes entries in order,
  so the last copy would win. ZIP entries are inspected and read by index (`getFromIndex`), never by name.
- **Symlinks (`symlink_entry`).** ZIP entries whose Unix file-type bits mark a symbolic link, and any symbolic link in
  a staged directory (file or directory), are refused. Links are never followed or read through; a symlinked
  directory is reported as one entry instead of silently disappearing from the check. A package path that is
  itself a symlink is unreadable.
- **Modes.** Default (incremental): `newapp/index.php` may be absent; if present it must match the template.
  **`--full` is mandatory for every full release:** `newapp/index.php` must be present and match
  (`front_controller_missing` otherwise). Both modes enforce every protected-path rule.
- Read-only sanity run (2026-11-09) against the existing 2026-09-14 full package (7 036 entries): no unsafe,
  duplicate or symlink findings; it is refused for 3 autocomplete lists, 2 bootstrap cache files and a front
  controller that differs from the template — i.e. it must be rebuilt before any use. **That package is REJECTED FOR
  FUTURE DEPLOYMENT (`decision-log.md` #136)**; it is kept unmodified, and no replacement has been generated.

## 9. Production verification checklist (all OPEN)

- [ ] `newapp/index.php` is the split variant (`../laravel-app/…`, `usePublicPath(__DIR__)`).
- [ ] Whether `laravel-app/bootstrap/cache/config.php` exists, and the five root values inside it (read-only).
- [ ] Whether the five variables are set in `laravel-app/.env`.
- [ ] **Whether `/home/w2acp/laravel-app/public/` exists, and its contents** (misplaced uploads — see §10).
- [ ] Each root: real directory or symlink, canonical target, owner, readable / writable by the web PHP user.
- [ ] PHP `open_basedir`; the PHP handler extension mappings under `newapp/`.
- [ ] `QUEUE_CONNECTION` and any cPanel cron entries running `artisan`.
- [ ] Shape of Khotab / mirror `link` values (URL vs local path; aggregate counts only).
- [ ] After setting the variables and re-caching: `media:check-roots --expect-web-root=/home/w2acp/newapp` exits 0,
      run as the web PHP user where possible.
- [ ] Smoke (read-only): header author / channel lists, thumbnails, a Khotab banner and author image, a W2ACD image,
      an Anasheed icon. An AdminCP upload test only with explicit owner approval.

## 10. Misplaced-file reconciliation (owner approval required — D3)

If `laravel-app/public/` exists on production, AdminCP uploads made while a config cache held the
`laravel-app/public` defaults may be there and missing from `newapp/`. Nothing is moved, copied, deleted or
overwritten automatically — not by the application, not by either command. Reconciliation is an owner-approved
manual step: inventory, compare against `newapp/` (never overwrite), copy, verify, and only then decide about the
originals.

## 11. Remaining production unknowns

Whether a config cache currently exists; whether misplaced files exist; whether the media directories are real or
symlinked and who owns them; the web PHP user versus the CLI user; `open_basedir`; handler mappings; queue / cron
usage; the shape of local-path `link` values; whether earlier packages overwrote `newapp/w2a_autocomplete/`.

## 12. Closure (2026-11-09) — accepted limitations and production prerequisites

**CLOSED LOCALLY — OWNER-ACCEPTED — PRODUCTION VERIFICATION PENDING** (`decision-log.md` #136).

Accepted limitations:

- ZIP64 archives are unsupported (reported as unreadable, exit 2).
- Extractor behaviour (cPanel File Manager) is not production-verified.
- Non-NUL control characters remain allowed in package entry names (escaped in output).
- The CD image MIME-agreement branch (`getimagesize()` vs finfo) has no deterministic regression test.
- Visitor page-level and writer-to-reader coverage gaps remain (readers are tested directly).
- CLI `public_path()` is intentionally not unified with HTTP `public_path()` (D1, Option A).

Production prerequisites (all OPEN):

- [ ] Verify the five absolute media roots (`KHOTAB_MEDIA_ROOT`, `ANASHEED_MEDIA_ROOT`, `W2ACD_MEDIA_ROOT`, `W2ACD_IMAGE_ROOT`, `AUTOCOMPLETE_ROOT`).
- [ ] Verify directory ownership and permissions for the web PHP user.
- [ ] Verify the active split front controller (`newapp/index.php`).
- [ ] Inspect for misplaced media files (`laravel-app/public/`).
- [ ] Verify the config cache values.
- [ ] Run `php artisan media:check-roots --expect-web-root=…` (exit 0).
- [ ] Require `php artisan deploy:verify-package` for every new deployment package.
- [ ] Require `--full` for full releases.
- [ ] Confirm the extraction procedure (location `/home/USER/`, extractor behaviour).
- [ ] Reconcile any misplaced files only after explicit owner approval.
