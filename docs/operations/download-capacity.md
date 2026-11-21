# Global download capacity

Operational record (2026-10-22). Replaces the Khotab-only guard (2026-10-18) and its never-deployed Khotab-only
telemetry design. Separate from the Khotab migration work, which stays closed.

## The rule

> **Any endpoint that proxies a large or long-lived file through PHP MUST take a slot from `DownloadCapacityGuard`
> and stream through `ProxiedDownloadStreamer`, and so joins the ONE global capacity pool.**
>
> **Redirect-only endpoints must NOT take a slot.** Static files (`/media/*`) and external links bypass PHP entirely
> and are outside this pool.

The pool protects scarce long-lived server resources, not clicks on routes named "download". A proxied transfer holds
one PHP-CGI process and one Apache worker (prefork) for as long as the bytes flow, often minutes. A redirect frees both
in milliseconds.

## What is in the pool today

| Endpoint | Delivery | In the pool |
|---|---|---|
| `/khotab-download-{id}.htm` | PHP proxies the stored link (`ProxiedDownloadStreamer`) | **yes** |
| `/khotab-mirror-{id}-{m}.htm` | PHP proxies the mirror's link | **yes** |
| `/var-download-*`, `/var-mirror-*`, `/recite-download-*`, `/fatawa-download-*`, `/lesson-download-*` | **F04 (2026-11-17, local, not deployed):** PHP proxies the file through `ProxiedDownloadResponder` (Khotab pattern). Exceptions keep a redirect and take no slot: YouTube / SoundCloud pages, a host listed in `download_save.attachment_query_hosts`, and links the proxy must not fetch (non-http(s), local paths, private addresses) | **yes** |
| `/mobile-file-*`, `/khotab-item-pdf-*` | count, then redirect | no |
| `/albumimg-download-*` | small local image through PHP | no (sub-second) |
| `*.grx` playlists, `POST /media-player` | short generated responses | no |
| `/media/*` (PDF, DOCX, …), `thumbnails.php`, external mirror / CD links | Apache or the remote host | cannot be counted by Laravel |

## Components (`app/Support/Downloads`)

| Class | Role |
|---|---|
| `DownloadCapacityGuard` | Config check, limit, retry interval, `tryAcquire()` (flock slots), active-slot count, `refuse()` → 503. Fails OPEN on slot-infrastructure failure, with the warning `DOWNLOAD_CAPACITY_GUARD_FAIL_OPEN`. |
| `DownloadSlot` | One held `flock`. Released by `release()`, the destructor, request shutdown, or the OS if the process dies. |
| `ProxiedDownloadStreamer` | The only place PHP proxies file bytes. Its `StreamedResponse` callback owns the slot and releases it in `finally`. |
| `DownloadRejectionContext` | What the caller already loaded: module, resource type and id, variant and variant id, link, stored size. |
| `DownloadRejectionRecorder` | Structured warning `DOWNLOAD_CAPACITY_FULL`, plus one `download_rejections` row. Never throws. |
| `DownloadRejection` | Read model for the table. |

Order in a protected endpoint: load the row → visibility checks → `tryAcquire()` → full: `refuse()` (503, nothing
counted) → increment the counter → `ProxiedDownloadStreamer::stream()`. Middleware must never own the slot: the body
of a `StreamedResponse` is sent after the middleware stack has returned.

## Configuration (`config/downloads.php`)

Each generic variable falls back to the earlier Khotab guard's variable, so an existing `.env` keeps working. An empty
value counts as unset.

| Variable (fallback) | Default | Meaning |
|---|---|---|
| `DOWNLOAD_CAPACITY_GUARD` (`KHOTAB_DOWNLOAD_CONCURRENCY_GUARD`) | `false` | Kill switch. Must be `true` in production. |
| `DOWNLOAD_CAPACITY_MAX_CONCURRENT` (`KHOTAB_DOWNLOAD_MAX_CONCURRENT`) | `100` | Simultaneous protected downloads, all endpoints combined. Target 400. |
| `DOWNLOAD_CAPACITY_RETRY_AFTER` (`KHOTAB_DOWNLOAD_RETRY_AFTER`) | `60` | `Retry-After` seconds, also shown in the message. |
| `DOWNLOAD_REJECTION_LOG` (`KHOTAB_DOWNLOAD_REJECTION_LOG`) | `true` | One `download_rejections` row per refusal. |
| `DOWNLOAD_CAPACITY_SLOT_DIRECTORY` (`KHOTAB_DOWNLOAD_SLOT_DIRECTORY`) | `storage/app/download-concurrency/khotab` | Slot lock files (local disk, writable by the PHP user). |

**The slot directory intentionally keeps the "khotab" path.** Downloads still running under the earlier Khotab guard
and new downloads under the generic guard lock the same `slot-NNN.lock` files, so they share one pool during the
transition. Production: `/home/w2acp/laravel-app/storage/app/download-concurrency/khotab` (`w2acp:w2acp`). Renaming
it is a separate change for a controlled maintenance window; done while downloads run, it would briefly allow two
pools' worth of transfers.

Lowering the limit while downloads hold higher-numbered slots lets those finish, so the count settles down to the new
limit as they end. Raising it is immediate.

## Capacity targets (independent, separately reversible)

| Setting | Where | Current | Target |
|---|---|---|---|
| `MaxRequestWorkers` (prefork; `ServerLimit` 1500 unchanged) | production Apache (WHM-managed) | 800 | 1000 |
| global download limit | production `.env` | 100 | 400 |

## When the pool is full

- HTTP **503**, `Retry-After: <retry_after>`, `Cache-Control: no-store, private`, `text/plain; charset=UTF-8`:
  «عذرًا، يوجد ضغط حاليًا على خدمة التحميل. يرجى المحاولة مرة أخرى بعد <retry_after> ثانية.»
  No `Refresh`, no automatic retry.
- Before any counter, file or network work. The stored link and size are recorded as already loaded: no HEAD request,
  no remote open. A missing or zero size is stored as NULL / `unknown`.
- `client_ip` is Laravel's `$request->ip()`. No trusted proxy is configured, so it is the direct peer, which may be a
  proxy if one is ever put in front of Apache. The raw `X-Forwarded-For` is kept only as `forwarded_for`.
- If the row cannot be stored, `DOWNLOAD_REJECTION_TELEMETRY_FAILED` is logged (exception class and code only) and the
  503 stands. A broken log channel is ignored too.

## Stalled sources and log volume (2026-11-10, local; not yet deployed)

Incident: 400/400 slots held by PHP-CGI processes whose remote sources had sent nothing for ~50 h. The old loop
(`while (! feof($h)) echo fread($h, 8192);`, no timeouts) never ends on a silent, still-open connection: `fread()`
returns `false` once per socket timeout and `feof()` stays false. Reproduced locally against a real TCP server.

| Variable | Default | Meaning |
|---|---|---|
| `DOWNLOAD_REMOTE_CONNECT_TIMEOUT` | `15` | Remote links. Applied **per operation**: the TCP/TLS connect, and each read while waiting for the response headers, again for every redirect hop (PHP default: up to 20). **Not an end-to-end deadline**: a slowly answering server or a chain of slow redirects can take longer in total, and DNS resolution is not covered. A silent source is bounded. |
| `DOWNLOAD_REMOTE_IDLE_TIMEOUT` | `60` | Longest a source may send no bytes mid-transfer; then the transfer ends and the slot is freed. Over TLS a stall is detected after up to twice this. |
| `DOWNLOAD_REJECTION_LOG_INTERVAL` | `60` | At most one `DOWNLOAD_CAPACITY_FULL` warning per interval, with `suppressed_since_last_log`. `0` = every refusal. The `download_rejections` row is still written for every refusal. |

There is deliberately no total-duration limit: a transfer that keeps receiving bytes runs to the end. Client cancels
are not logged. The source handle is closed and the slot released on every path. The throttle state is
`rejection-log-throttle.state` in the slot directory.

**Source validation (owner-approved, 2026-11-10).** The source is opened and checked in `stream()`, *before* any status
line or header is sent. For a remote source the database connection is released **before** that open (a dead host must
not pin MariaDB connections for its connect timeout), and released again before the first byte in case anything
reconnected; for a local file the release stays just before the first byte.

- Remote: the **final** response after redirects must be 2xx other than 204/205/206, and must not declare
  `Content-Type: text/html`. No MIME allowlist. Otherwise: a real **500** "Error reading file" (legacy's answer), the
  connection closed, the slot freed, one `DOWNLOAD_SOURCE_REJECTED` line (`reason`: `status`, `html`, `no_http_status`).
  An open failure is a real 500 with `DOWNLOAD_SOURCE_OPEN_FAILED`. The counter has already been incremented once by the
  controller, as before (legacy also counted before opening).
- `Content-Length` is sent to the visitor only when trustworthy: remote — exactly one header on the final response, a
  plain non-negative integer, no `Transfer-Encoding`, no `Content-Encoding` other than `identity`; local — `fstat()` of
  the opened regular file. The stored database size (`linksize`) is never used.
- With a declared size, at most that many bytes are sent (extra source bytes are dropped). A source that ends early is
  logged once as `DOWNLOAD_SOURCE_INCOMPLETE`; the visitor's client sees a body shorter than its `Content-Length`, so
  a stalled or cut transfer shows as failed instead of as a complete file. Stalls and read errors are logged as
  `DOWNLOAD_SOURCE_STALLED` / `DOWNLOAD_SOURCE_READ_FAILED`, with `declared_bytes`.
- HEAD is unchanged: the source is not contacted and no `Content-Length` is added.
- Output compression: with `zlib.output_compression` on, PHP does not compress a response that sets `Content-Length`,
  so the header and body stay consistent (verified locally). Apache `mod_deflate`, if it compressed these types, would
  remove the header itself (not verified on production); the download would then behave as before (no declared size).

## Open follow-ups

- No retention policy exists for `download_rejections`; it grows until one is approved.
- While a source is silent nothing is written, so PHP cannot see a gone client; the idle timeout bounds that case.
