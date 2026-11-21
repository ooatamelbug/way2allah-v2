# Download telemetry recorder (Phase 3A)

**Status:** local only, **off by default**. It is active only when both `DOWNLOAD_TELEMETRY_EVENTS=on` and
`DOWNLOAD_TELEMETRY_SPOOL` are set to something other than `off`. Nothing is enabled in production.

## 1. Route inventory

| Route | Controller | Class | Slot (pool 400) | Counter | GET | HEAD | Recorder |
|---|---|---|---|---|---|---|---|
| `khotab-download-{id}.htm` | KhotabItemController@download | **Proxied PHP stream** | yes | `downcount` | opens and validates the source, then streams it | **since Phase 3B:** no slot, no count, no source contact (was: slot + `downcount`) | **integrated** |
| `khotab-mirror-{id}-{m}.htm` | KhotabItemController@downloadMirror | **Proxied PHP stream** (YouTube/SoundCloud mirrors included) | yes | mirror `hits` | same as above; an HTML source (YouTube/SoundCloud page) → 500 `source_rejected` | **since Phase 3B:** no slot, no `hits`, no source contact | **integrated** |
| `khotab-item-pdf-{id}.htm` | @downloadPdf | Redirect | no | `pdf` (+1) | 302 to media | **increments `pdf`** | not integrated (Phase 3B) |
| `lesson-download-{id}.htm` | ChatRoomLessonController@download | Redirect | no | khotab `downcount` | 302 | **increments** | not integrated |
| `recite-download-{id}.htm` | TelawahItemController@download | Redirect | no | `downcount` | 302 | **increments** | not integrated |
| `var-download-{id}.htm` | AnasheedItemController@download | Redirect (https/archive.org-normalized) | no | `downcount` | 302 | **increments** | not integrated |
| `var-mirror-{id}-{m}.htm` | AnasheedItemController@downloadMirror | Redirect | no | mirror `hits` | 302 | **increments** | not integrated |
| `fatawa-download-{id}.htm` | FatwaQuestionController@download | External redirect | no | `num_download` | 302 away | **increments** | not integrated |
| `mobile-file-{lid}.htm` | MobileController@download | External redirect (or a 410 page) | no | `hits` | 302 away | **increments** | not integrated |
| `albumimg-download-{id}.htm` | GalleryController@download | Local file response (image, streamed, no slot) | no | — | streams the local image | no body | not integrated (small local files) |
| `download-album-{id}.htm` | GalleryController@downloadAlbum | **Generated ZIP** (cached) | no | — | builds/serves the ZIP | **builds the ZIP on HEAD** | not integrated (Gallery ZIP excluded) |
| `khotab-series-*.grx`, `var-series-{g}.grx` | CategoryDownItems / AnasheedGroup | Generated text list (GetRight) | no | — | small generated body | body generated | not a file transfer |
| `thumbnails.php` | ThumbnailController@show | Generated image | no | — | resized image | generated | not a download |
| `POST media-player` | MediaPlayerController@show | HTML player with external media links (YouTube/SoundCloud embeds) | no | — | HTML fragment | n/a (POST) | not a download |

Only the two Khotab routes proxy bytes through PHP. They are the only users of `DownloadCapacityGuard` (verified by
grep), so they are the only routes the recorder is integrated with in Phase 3A.

### HEAD — Khotab correction (Phase 3B, owner-approved 2026-11-11)

For `khotab-download-{id}.htm` and `khotab-mirror-{id}-{m}.htm` only, a HEAD request now:

- takes **no** capacity slot, so it gets 200 even while the pool is full (it used to get a 503);
- does **not** increment `downcount` or `hits`;
- never contacts the source; emits only `head_answered` (never `download_accepted`).

The response is unchanged otherwise:

- status 200, with the same `Content-Disposition: attachment; filename=…`;
- no `Content-Length`, as before;
- hidden item → 404 and missing local file → 404, as before.

Implemented in `ProxiedDownloadStreamer::head()`, called by both controller methods before the guard.

Compatibility review:

- **Legacy** `download_khotab()` counted every HEAD, because PHP ran the whole script. Under Apache it also opened the source and
  read the entire file into a discarded body.
- **Laravel, before this change:** counted every HEAD and took a slot, without contacting the source.
- **No dependants found:** there are no HEAD-based probes, health checks or monitors in this repository, its docs or its deploy
  scripts. Production access logs were NOT inspected (no production access).
- **Counter impact:** `downcount` and `hits` will grow more slowly by the number of HEAD requests. Those are mostly
  link checkers, crawlers and some download managers that probe before a GET. The owner may want to measure HEAD volume
  in the access logs before deploying.
- **Clients that relied on a 503 from HEAD** to detect a full pool now get 200 and only find out on the GET. None are
  known.
- **Not changed:** HEAD on every other route (the redirects still count on HEAD; `download-album` still builds the ZIP on HEAD).

### Redirect routes — HEAD (Phase 3B.1)

Before 3B.1, every redirect route counted HEAD exactly like GET:

| Route | Counter |
|---|---|
| `khotab-item-pdf` | `pdf` |
| `lesson-download` | khotab `downcount` |
| `recite-download` | `downcount` |
| `var-download` | `downcount` |
| `var-mirror` | mirror `hits` |
| `fatawa-download` | `num_download` |
| `mobile-file` | `hits` |

No route did any transfer work: each loads one row, increments, and redirects. `mobile-file`'s availability check is a
local host comparison.

Now:

- **HEAD:** same status (302) and the same `Location` as GET; the counter is untouched.
- **GET:** unchanged, and counts exactly once.
- **Dependencies:** none found in the repository. Before this change, tests exercised GET only on these routes.

Still unchanged: `download-album-{id}.htm` builds the ZIP on HEAD (Gallery ZIP excluded from this phase).

## Content-Type (Phase 3B.1)

Proxied Khotab downloads (GET and HEAD) send a `Content-Type` from an explicit allowlist (`DownloadMime`). The
allowlist holds exactly the types legacy `download_khotab()` sent:

| Extension | Content-Type |
|---|---|
| `mp3` | `audio/mpeg` |
| `mp4` | `video/mp4` |
| `pdf` | `application/pdf` |
| `doc` | `application/msword` |
| `docx` | `application/vnd.openxmlformats-officedocument.wordprocessingml.document` |
| anything else | `application/octet-stream` |

Every response also carries `X-Content-Type-Options: nosniff`.

- **Where the extension comes from:** for a local file, the real path actually opened (symlinks resolved); for a
  remote link, the stored link's path. The query string and the upstream's own Content-Type are never used.
- **Before:** Laravel sent no Content-Type, so the framework defaulted to `text/html; charset=UTF-8`.
- **Compatibility:**
  - browsers still download, because of `Content-Disposition: attachment`;
  - some players and download managers will now see the correct audio or video type;
  - unknown types are `application/octet-stream`, never HTML;
  - bodies, `Content-Length`, filenames, timeouts and capacity are unchanged;
  - files with other extensions (for example `.rm`, `.wma`, `.amr`) are `application/octet-stream`, exactly as legacy
    sent them.

### YouTube/SoundCloud

On the proxied route, a mirror that points at a YouTube or SoundCloud page is answered with `text/html`. Since the
local streamer fix, that is refused before any header (HTTP 500 "Error reading file") and recorded as
`source_rejected` with `k=html`. It is never `transfer_started` or `completed`. The mirror's `hits` counter is still
incremented before the source is opened, exactly as before. Legacy behaviour streamed the HTML page as the "file". No
redirect semantics were changed.

## 2. Event schema (v1 + `download_accepted`)

Common fields on every recorder event: `t`, `ev`, `id` (random 16-hex), `rq` (request id from `RequestMetrics`),
`mod`, `rt`, `rid`, `var`, `vid` (mirror id), `m` (method), `head`, `rng` (a Range header was sent), `ip` (Laravel
resolved), `xff` (an X-Forwarded-For header was present; its value is not stored), `uah` (HMAC-SHA256 of the
User-Agent keyed with `APP_KEY`, cut to 32 hex), `uaf` (`bot` | `tool` | `browser` | `other` | `none`).

| Event | When | Extra fields | Terminal |
|---|---|---|---|
| `download_accepted` | GET took a slot and the counter was incremented | — | no |
| `head_answered` | HEAD (one event; never `download_accepted`; no slot, no counter) | `st=head` | yes |
| `capacity_rejected` | no free slot (503) | `st=rejected`, `pa` active, `pl` limit | yes |
| `source_open_failed` | connect/open failure, or link missing (404) | `k` = `open_failed`, `exception` or `not_found`; `sh` | yes |
| `source_rejected` | non-2xx/204/205/206, no status line, or `text/html` | `k` = `status`, `no_http_status` or `html`; `ss`; `sh` | yes |
| `transfer_started` | headers are being sent; the copy begins | `sh` host only, `ss`, `dl` trusted length, `ttfb` open ms | no |
| `transfer_finished` | copy ended | `st`, `b` bytes sent, `d` ms | yes |

`transfer_finished.st` can take these values:

| `st` | Meaning |
|---|---|
| `completed` | The whole file was sent. **This is the only completed download.** |
| `incomplete` | The source ended before its declared size: a truncated file. |
| `stalled` | The source read timed out (the idle timeout). |
| `read_failed` | A read from the source failed. |
| `client_aborted` | The visitor disconnected. |
| `interrupted` | The request ended for any other reason without a pump result. |

**Attempt vs completion:** an attempted download is `download_accepted`. A completed transfer is
`transfer_finished` with `st=completed`.

**Exactly one terminal event per transfer:** `TransferTelemetry` emits at most one terminal event. If PHP aborts the
request mid-stream, `finally` does not run, so a shutdown function, registered in `transfer_started`, emits
`client_aborted` (or `interrupted`) with `k=shutdown_fallback`.

**Shutdown fallback, measured:** with PHP's built-in server, a real client that disconnects mid-transfer is
detected by PHP's own write path. PHP aborts the request, `finally` does not run, and the shutdown function records
`transfer_finished st=client_aborted k=shutdown_fallback`. This happened in 5 of 5 runs, with exactly one terminal event and the slot freed (the
descriptor closes at request end). In that mode, `b` is the byte count of the last chunk counted before the abort. It
can exceed what the client actually received, because of socket buffers.

**Validation gap:** production runs PHP-CGI/FastCGI behind Apache. No PHP 8.3 CGI or FPM is installed locally (only
`php-fpm7.4`), and the system Apache was deliberately not touched. Abort detection under FastCGI depends on
mod_fcgid/proxy_fcgi buffering and on when the FastCGI write fails. Both the in-loop `connection_aborted()` path and
the shutdown fallback are implemented, but which one fires, and how quickly, under production's SAPI is **unverified**.
The integration test (`DownloadHttpIntegrationTest`) does not claim production equivalence.

## 3. Integration points

- `KhotabItemController::download` and `downloadMirror` build a `TransferTelemetry` after the slot and the counter,
  call `accepted()`, then pass it to `ProxiedDownloadStreamer::stream()`, which takes a new optional fourth
  parameter.
- `ProxiedDownloadStreamer`:
  - 404 → `source_open_failed(not_found)`;
  - HEAD branch → `head()`;
  - `openValidated` failure → `source_open_failed` or `source_rejected`, emitted **after** the slot is released;
  - callback → `started()` before `pump`, which updates the byte count;
  - `finally` releases the slot **first**, then calls `finished()`.
- `DownloadCapacityGuard::refuse()` emits `capacity_rejected` after the existing database rejection row, inside
  try/catch.
- `DownloadTelemetry` is scoped per request (`AppServiceProvider`). It writes only through `SpoolWriter`, so the
  file cap, file count, disk reserve, lock budget, breaker and drop ledger all apply unchanged.

## 4. Failure isolation

- When disabled, the only cost is one config read. `SpoolConfig` is never built and no file is touched.
- Every `emit` is wrapped in `catch (Throwable)`. The writer itself never throws; it drops and counts.
- Telemetry objects hold neither the slot nor the source handle, so they cannot keep either alive.
- Telemetry runs after slot release on every failure path and at transfer end.
- Timeouts, capacity, headers, filenames, counters and status codes are identical with telemetry on or off.

## 5. Privacy

- Never stored: the raw User-Agent (`ua` is never set), cookies, Authorization headers, tokens, the link's path or
  query string (only the host, in `sh`), the X-Forwarded-For value, or request or response bodies.
- Stored: one raw IP per event. It lives in:
  - spool files (`active/`, `sealed/`; `done/` for 24 h; `quarantine/` until an operator removes it);
  - `download_telemetry_ip_ua_hourly` (retention 14 days, **but pruning is off by default**: until
    `telemetry:prune` is enabled, those rows are kept indefinitely).
- `uah` is keyed with `APP_KEY`. Rotating the key changes every hash, so IP/User-Agent pairs from before and after a
  rotation are not comparable. The hash cannot be reversed without the key, but a known User-Agent string can be
  confirmed by anyone who holds the key.
- An IP plus `uah` pair is a combination, not a person.
