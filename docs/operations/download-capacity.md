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
| `/var-download-*`, `/var-mirror-*`, `/recite-download-*`, `/fatawa-download-*`, `/mobile-file-*`, `/lesson-download-*`, `/khotab-item-pdf-*` | count, then redirect | no |
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

## Open follow-ups

- No retention policy exists for `download_rejections`; it grows until one is approved.
- A stalled remote read holds its slot until the next write reveals a gone client, or the socket times out.
