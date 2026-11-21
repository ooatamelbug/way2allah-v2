# Download System: Local Production-like FastCGI / CGI Validation

**Date:** 2026-11-11
**Status:** **LOCAL evidence only.** A disposable Docker lab on a developer workstation. **No production access, no
production data, no production credentials.** Nothing here was deployed or activated.

**Lab definition (reproducible):** `tools/download-lab/`, with the raw result JSON in `tools/download-lab/evidence/`.

## 0. What the repository documents about production (inspection before building)

| Item | Documented | Source |
|---|---|---|
| Web server | cPanel/WHM-managed Apache, **prefork** MPM, `MaxRequestWorkers` 800 (target 1000), `ServerLimit` 1500 | `docs/operations/download-capacity.md` |
| PHP processes | downloads are served by **PHP-CGI** processes (the incident: "400/400 slots held by PHP-CGI processes") | same |
| PHP version | the OLD site runs PHP 8.1 (`application/x-httpd-ea-php81`); the Laravel app requires `^8.3` | `decision-log.md`, `composer.json` |
| **Laravel app's PHP handler** | **not documented**: plain CGI (`mod_cgi`), FastCGI (`mod_fcgid`), FPM or LSAPI | — |
| Download capacity | `DOWNLOAD_CAPACITY_MAX_CONCURRENT` documented as current 100, target 400; the incident had 400 slots held | `download-capacity.md` |
| Production php.ini (`output_buffering`, `max_execution_time` …), `FcgidOutputBufferSize`, spawn limits | **not documented** | — |

Because "PHP-CGI" on cPanel can mean either the plain `cgi` handler or `mod_fcgid`, **both were validated**.

## 1. Environment configuration

| Component | Exact version / image |
|---|---|
| Host | 4 CPUs, 11 GiB RAM, Linux 6.8.0-147, Docker 29.8.0. Load from other work varied (load average 1–38 during the runs) |
| Base image | `ubuntu:24.04`, digest `ubuntu@sha256:fe81560990fae86dc4743202b2cb4d3df98d0bcf949173cecef15ce1127ef852` (Ubuntu 24.04.5 LTS) |
| Web image | `w2a-lab-fcgi:local` (`sha256:e51f6b71…`), built from `tools/download-lab/Dockerfile` |
| Apache | 2.4.58 (Ubuntu), **MPM prefork**, `MaxRequestWorkers`/`ServerLimit` 150 (lab), `Timeout 300` |
| FastCGI | `libapache2-mod-fcgid` 1:2.3.9-4: `FcgidMaxProcesses` 150, `FcgidIOTimeout` 3600, `FcgidBusyTimeout` 7200, `FcgidIdleTimeout` 300; **`FcgidOutputBufferSize` tested at 65536 (the module default) and 0**; spawn-score settings left at their defaults |
| Plain CGI | `mod_cgi` + `Action` → `/usr/lib/cgi-bin/php8.3`. The first behaviour run used `mod_cgid`, with identical results |
| PHP | `php8.3-cgi` 8.3.6-0ubuntu0.24.04.13, SAPI `cgi-fcgi`. Effective: `output_buffering=4096`, `ignore_user_abort=0`, `zend.exception_ignore_args=1`, `memory_limit=256M`, `max_execution_time=30` (CPU time on Linux), `default_socket_timeout=60`, OPcache on |
| Process user | PHP runs as the account user `w2a` (uid 1000), as cPanel suEXEC does; the monitor CLI runs as the same user |
| Database | `mariadb:10.11` (10.11.19), digest `mariadb@sha256:7f22313fc130a377a44999965bcb0a08dd5b21e8502824c1b864f792f9bc66ab`, tmpfs, private network only |
| Upstream source | a scripted PHP server (same image), forking per connection: fast, slow drip, stall, clean early close, RST, HTML, 404 |
| App | a copy of the working tree **without** `.env`, `.git` or `storage` contents; a lab `.env` with a random key and password; the 3 download migrations; legacy tables from the test fixtures; **synthetic items only** |
| App settings | `APP_ENV=production`, `APP_DEBUG=false`, file cache and sessions, **no config cache** (so flags could be switched per scenario), guard on, slot limit per scenario, connect timeout 15 s, **idle timeout 10 s** (production default 60 s, shortened to keep runs quick) |
| Resource caps | web container `--memory=4g --cpus=3`; ports bound to 127.0.0.1 only |

## 2. Test scenarios and results

### 2.1 Download behaviour against the compatibility contract (telemetry off, the deployment default)

18 checks per handler (`evidence/results.json`, `results-behaviour-cgi-modcgi.json`).

| Check | FastCGI | Plain CGI |
|---|---|---|
| GET local mp3 / mp4 / pdf / rar: status, `Content-Type` (`audio/mpeg`, `video/mp4`, `application/pdf`, `application/octet-stream`), `Content-Disposition: attachment`, `nosniff`, exact bytes (SHA-256), `downcount` +1 | ✅ | ✅ |
| HEAD: 200, same headers, no body, no `Content-Length`, no counter | ✅ | ✅ |
| Local mirror: GET +1 `hits`, HEAD +0 | ✅ | ✅ |
| YouTube mirror → `302 https://www.youtube.com/watch?v=abc123&list=PL9` (http upgraded), `no-store`, GET +1, HEAD +0 | ✅ | ✅ |
| SoundCloud mirror → `302 https://soundcloud.com/…`, GET +1, HEAD +0 | ✅ | ✅ |
| Look-alike host mirror (an HTML page) → proxied → 500 | ✅ | ✅ |
| Missing local file → 404 (`downcount` +1, legacy-compatible) | ✅ | ✅ |
| Remote 5 MiB with a signed query string; upstream says `application/x-upstream-says-this` → `audio/mpeg` by path, exact bytes | ✅ (`Content-Length: 5242880`) | ⚠️ exact bytes, but **no `Content-Length`** (see 2.2) |
| Upstream `text/html` / upstream 404 → 500 before any download header | ✅ | ✅ |
| Stalled upstream (512 KiB, then silence) → short body, ends after the idle timeout (10.1 s / 10.6 s), slot freed | ✅ | ✅ |
| Upstream closes early (1 MiB of 10 MiB) / RST after 1 MiB → short body, slot freed (both logged `DOWNLOAD_SOURCE_INCOMPLETE`) | ✅ | ✅ |
| Slow client (8 KiB receive buffer, 4 KiB per 20 ms) on a slow upstream (2 MiB at 64 KiB/s) → complete, exact | ✅ (32.1 s) | ✅ (32.4 s) |
| Slots after the block: 0 with evidence (`probe_visible`) | ✅ | ✅ |

### 2.2 Finding: Apache's CGI handler drops `Content-Length` on streamed bodies

- **What PHP sends:** under plain CGI, PHP emits `Content-Length` (verified by running `php-cgi` directly).
- **What Apache 2.4.58 does:** it **drops it for a streamed body** and answers with `Transfer-Encoding: chunked`
  (HTTP/1.1) or `Connection: close` (HTTP/1.0).
- **Isolated on Apache itself, without PHP:**
  - a minimal shell CGI that emits `Content-Length: 11` with an 11-byte body: header kept;
  - the same with a 5 MiB body: header **dropped**;
  - FastCGI keeps it.
- **Consequence:** under plain CGI, a truncated or stalled transfer cannot be told apart from a complete one **by the
  client**. It ends without error, and download managers get no total size or progress. The application's
  "trustworthy `Content-Length`" protection only reaches clients under FastCGI (or FPM).
- **Scope:** this is platform behaviour. It equally affected the OLD site and the previous Laravel code.
- **Correction (2026-11-11, no-SSH discovery):** an external `curl -D -` check **cannot identify the handler on the
  currently deployed code**. The committed (deployed) streamer sets **no** `Content-Length` at all.
  - **After** the new release, presence of the header *suggests* FastCGI or FPM, and absence *suggests* plain CGI or a
    buffering proxy.
  - Even then it is **not proof**: a proxy, CDN or compression can change it.
  - Identify the handler from WHM MultiPHP Manager or hosting support (`docs/operations/download-production-discovery.md`
    §1.1).

## 3. FastCGI disconnect behaviour (and plain CGI)

Telemetry enabled. Each case runs one download; the client then disconnects
(`evidence/results-disconnect-buf64k.json`, `results-disconnect-buf0.json`).

| Case | FastCGI, buffer 64 KiB | FastCGI, buffer 0 | Plain CGI |
|---|---|---|---|
| Client leaves a slow remote transfer (512 KiB of 100 MiB) | slot free **1.83 s**; `client_aborted` (`k=shutdown_fallback`); upstream socket closed | slot free **0.59 s**; same | slot free **0.47 s**; upstream closed; **no terminal event** |
| Client leaves a fast local transfer (small receive buffer) | 0.48 s; `client_aborted` (shutdown fallback) | 0.43 s; same | 0.41 s; **no terminal event** |
| Client leaves while the upstream is **stalled** | slot free when the idle timeout fires (~10 s after the stall began); `stalled` | 9.8 s; `stalled` | 9.9 s; `stalled` |
| Slow client leaves after 256 KiB | 1.09 s; `client_aborted` | 0.41 s; same | 0.43 s; **no terminal event** |

**How each handler notices a disconnect:**

- **FastCGI:** Apache logs `mod_fcgid: ap_pass_brigade failed … Broken pipe`. PHP's next write fails, PHP aborts the
  request (`ignore_user_abort=0`), `finally` does not run, and **the shutdown fallback records exactly one
  `client_aborted`**. The upstream socket closes and the slot is freed when the request ends.
- **Plain CGI:** Apache **terminates the php-cgi process** when the client is gone. The kernel releases the flock (slot
  freed in about 0.4 s) and closes the upstream socket. But no PHP code runs, so **telemetry records
  `download_accepted` and `transfer_started` without a `transfer_finished`**. Interrupted transfers are visible only
  as started minus finished (the rollup's metric), not as `client_aborted`.

**Effect of `FcgidOutputBufferSize` (FastCGI only):**

- With the 64 KiB default, mod_fcgid holds up to 64 KiB before passing it on. In the stalled-upstream case the client
  received the last 512 KiB block only when the transfer ended (about 10 s later); with 0 it arrived at once.
- Disconnect detection is slightly slower with buffering: 1.8 s versus 0.6 s.

**While the upstream is silent**, no handler can notice a departed client: nothing is written. The idle timeout
(production default 60 s) bounds that case, as designed.

## 4. Slot-release evidence

**Slot visibility.** Slot locks were visible in the kernel lock table in every run (`visibility=slot_locks_visible`
while held, `probe_visible` when idle), under both handlers, with PHP as the account user.

**Release after every scenario:**

| Scenario | Slots freed |
|---|---|
| Normal completion | yes |
| 404 / 500 | yes |
| Truncated, RST or stalled upstream | yes |
| Slow client | yes |
| Client disconnect | yes |
| Process killed (plain CGI) | yes |
| Capacity rejection | yes |
| After every concurrency level | within 0.5–1.6 s (one 7.5 s outlier under the overloaded 100-client fast-ramp run) |

No slot ever stayed held after its request ended.

**Telemetry and slots.** No configuration of telemetry changed slot behaviour (§6).

## 5. Concurrency results

Each client streams a slow upstream (100 MiB at 128 KiB/s) for its hold time, then disconnects. The slot limit is set
5 below the client count, so 5 rejections are expected whenever every client is inside PHP at the same moment.

### 5.1 Fast ramp (50 clients per second, 12 s hold): `evidence/results-concurrency.json`

| Handler | Clients / limit | 200 | 503 (rows) | No response within 12 s | Peak slots | php-cgi | php-cgi RSS | Container |
|---|---|---|---|---|---|---|---|---|
| FastCGI | 10 / 5 | 5 | 5 (5) | 0 | 5 | 8 | 498 MiB | 313 MiB |
| FastCGI | 25 / 20 | 20 | 5 (5) | 0 | 20 | 23 | 1,441 MiB | 899 MiB |
| FastCGI | 50 / 45 | 44 | 0 | 6 | 37 | 38 | 2,334 MiB | 1.45 GiB |
| FastCGI | 100 / 95 | 53 | 0 | 47 | 51 | 52 | 3,148 MiB | 1.93 GiB |
| FastCGI | 140 / 135 | 82 | 0 | 58 | (20 at sample) | 93 | 5,447 MiB | 3.23 GiB |

### 5.2 Gentle ramp (5 clients per second, 40 s hold): `results-concurrency-fcgid-slow-ramp.json`, `results-concurrency-cgi.json`

| Handler | Clients / limit | 200 | 503 (rows) | Peak slots | php-cgi | Container memory | Slots free after |
|---|---|---|---|---|---|---|---|
| FastCGI | 50 / 45 | 45 | 5 (5) | 29 at sample | 30 | 1.25 GiB | 1.36 s |
| FastCGI | 100 / 95 | 100 | 0 | 76 | 76 | 2.90 GiB | 0.53 s |
| FastCGI | 140 / 135 | 140 | 0 | 110 | 112 | **3.83 / 4 GiB** (escalation stopped) | 0.59 s |
| Plain CGI | 10 / 5 | 5 | 5 (5) | 5 | 5 | 0.21 GiB | 0.56 s |
| Plain CGI | 25 / 20 | 20 | 5 (5) | 20 | 20 | 0.83 GiB | 0.70 s |
| Plain CGI | 50 / 45 | 45 | 5 (5) | 45 | 45 | 1.80 GiB | 0.47 s |
| Plain CGI | 100 / 95 | 100 | 0 | 93 | 93 | 3.48 GiB (escalation stopped) | 0.58 s |

### 5.3 What this shows (local)

1. **The slot pool works under both real handlers:**
   - the limit is enforced exactly;
   - each refusal is an immediate `503` + `Retry-After` with its row;
   - occupancy is visible;
   - every slot is released after disconnect.
2. **`mod_fcgid` bounds concurrency before the slot pool does.** It spawns php-cgi processes gradually (spawn-score
   throttling) and **queues** further requests inside Apache.
   - Under a burst, clients waited with no response, 47 of 100 within 12 s, rather than receiving a 503.
   - Under a gentle ramp they were all served, but at most 76 or 110 were inside PHP at once.
   - So under FastCGI, `FcgidMaxProcesses` and the spawn settings, not `DOWNLOAD_CAPACITY_MAX_CONCURRENT`, set the
     real ceiling.
   - Plain CGI spawned one process per request without that throttle, and filled the pool exactly.
3. **Memory per PHP process:** about 59–65 MiB RSS each, about 35–40 MiB unique once shared pages are excluded (container
   memory ÷ processes). **Local extrapolation only:** 400 concurrent downloads ≈ 14–16 GiB for PHP alone on this
   build; production's build, OPcache and page sharing differ.
4. **FastCGI keeps idle processes** (300 s idle timeout here): 124 idle php-cgi processes stayed resident after the
   burst runs.
5. **CPU:** the 4-core host saturated (load average up to 38) at 100–140 concurrent requests. Under that starvation, the
   source server itself stalled more than 10 s twice, and the idle timeout correctly ended those transfers.

**Not validated: 400 concurrent workers.** The maximum actually exercised was **140 concurrent clients (FastCGI, 112
php-cgi processes)** and **100 (plain CGI, 93 processes)**, limited by the lab's 4 GiB memory cap and the 4-CPU host.

## 6. Telemetry failure isolation

Under each handler, the same workload ran in six states (`evidence/results-isolation.json`). The workload:

- GET local, GET remote with a signed URL, GET missing, GET upstream HTML;
- HEAD;
- the YouTube mirror;
- GET at capacity (limit 1 held by a slow transfer);
- a client disconnect.

| State | FastCGI | Plain CGI | Telemetry actually did |
|---|---|---|---|
| telemetry disabled (baseline) | — | — | nothing written |
| telemetry enabled | **identical** | **identical** | 3 spool files of events, 0 drops |
| spool unavailable (root is a file) | **identical** | **identical** | drops could not be recorded → `W2A_TELEMETRY_UNRECORDED_DROPS` in Apache's error log (`mod_fcgid: stderr:` / `AH01215:`) |
| spool full (`max_files` reached) | **identical** | **identical** | 36 events dropped and counted (`file_count`) |
| breaker open | **identical** | **identical** | 36 events dropped and counted (`breaker_open`) |
| archive enabled (not run) | **identical** | **identical** | no effect on the download path |

- **"Identical"** means the same status, the same headers (excluding `Date` and `Set-Cookie`), the same body SHA-256 and
  length, and the same counter deltas.
- **Slots:** freed after the disconnect in every state (0.35–1.57 s), with no leaks.
- **Statuses in every state:** 200, 200, 404, 500, 200, 302, 503.
- **Log privacy:** the planted signed-URL secret appears in **no** application or Apache log. `DOWNLOAD_SOURCE_*` lines
  show host and path only.

## 7. Remaining production-only unknowns

| # | Unknown | Why it matters | How to resolve (no SSH needed where noted) |
|---|---|---|---|
| U1 | **The Laravel app's PHP handler** (plain CGI / `mod_fcgid` / FPM / LSAPI) | it decides `Content-Length` delivery (§2.2), whether disconnects produce `client_aborted` or only started-without-finished (§3), and whether spawn throttling caps concurrency (§5) | **no SSH:** WHM » MultiPHP Manager » PHP Handlers, or hosting support (discovery checklist §1.1). The external `curl` check is only indicative, and only after the new release (§2.2 correction) |
| U2 | `FcgidOutputBufferSize`, `FcgidMaxProcesses`, spawn settings (if FastCGI) | buffering delays bytes to clients when a source stalls; process limits cap concurrency | WHM / EasyApache configuration (owner or host) |
| U3 | Production `php.ini`: `output_buffering`, `max_execution_time`, `memory_limit`, `zend.exception_ignore_args` | buffering and limits; trace privacy | cPanel MultiPHP INI Editor (no SSH) |
| U4 | `/proc/locks` visibility for the account user (CloudLinux or hardened `/proc` can hide other processes) | slot-occupancy monitoring | `downloads:monitor --json` run by the account's cron, with one download in flight |
| U5 | Memory per PHP process, and server RAM | 400 concurrent downloads ≈ 14–16 GiB of PHP on the lab build | WHM resource view, or the host |
| U6 | Real production behaviour under 400 slots | **not validated locally** (maximum 140) | only observable in production; the monitor's slot check |
| U7 | Web and cron users, and permissions | spool and archive modes | cPanel account (likely the same user under suEXEC) |
| U8 | Production network path (proxies, CDN) in front of Apache | disconnect propagation, `Content-Length` handling | the owner or host |

## 8. GO / NO-GO recommendation

**Code deployment (feature flags off): conditional GO from the local evidence.**

- Under both plausible production handlers, every compatibility check passed: GET/HEAD, counters, Content-Type,
  Content-Disposition, media redirects, missing files, upstream failures, slow clients and capacity rejection. The one
  exception is `Content-Length` under plain CGI, which is a platform limitation that predates this work.
- Slots were released in every scenario, including the incident's failure mode: a stalled upstream ends at the idle
  timeout.
- **Conditions before GO:**
  1. Resolve **U1** (the handler) from WHM or hosting support, and record the result
     (`download-production-discovery.md` §1.1).
  2. Pre-checks 1.x of the runbook.

**Feature activation (telemetry): conditional GO after U1, U4 and U7, with one documented limitation.**

- Telemetry failure isolation is proven under both handlers: identical responses, and no slot leaks.
- **Limitation:** if production uses plain CGI, interrupted downloads appear only as started-without-finished, never
  as `client_aborted`. The monitor's `transfers` disconnect ratio would then under-count. This is acceptable for
  observation, but it must be known.

**Capacity of 400 concurrent downloads: NO-GO as a validated claim.**

- It was **not** exercised: 140 maximum locally.
- Under FastCGI, `mod_fcgid`'s process and spawn limits, not the slot pool, cap concurrency. Raising the slot limit to
  400 is only meaningful if the handler allows 400 concurrent PHP processes and the server has the memory for them
  (U2, U5).
- Decide the production limit from U1, U2 and U5 evidence, starting from the current production value, not from this
  lab.

## 9. Teardown

The lab containers, volumes and network are removed after this run (see `tools/download-lab/README.md`). The
`w2a-lab-fcgi:local` image is kept locally for re-runs.
