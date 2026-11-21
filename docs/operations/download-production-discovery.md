# Download System: No-SSH Production Discovery Checklist (owner-executed)

**Purpose:** establish the production facts the download release depends on, without SSH. It uses cPanel, WHM,
hosting support, and a few low-impact HTTP observations. **Documentation only:** nothing here was executed against
production by the assistant.

**Use with:**

- `docs/operations/download-production-runbook.md`: this checklist feeds its Phase 1 and gates G1–G10;
- `docs/reports/download-local-fastcgi-validation.md`: the local CGI and FastCGI evidence it refers to.

**Evidence rule:** for every item, save a screenshot or a copy of the text, with the date, the account or role used, and
the exact page. "Documented" (written in this repository) is **not** "verified" (seen on production by the owner).

---

## 1. Hosting environment

**Access levels:**

| Level | Who has it |
|---|---|
| **cPanel** | the account user |
| **WHM** | root, or a reseller with privileges |
| **Support** | the hosting provider's ticket desk |

If an item needs WHM and only cPanel is available, ask hosting support (§1.10 template).

### 1.1 PHP handler used by the Laravel application (the most important fact)

| | |
|---|---|
| Where | **WHM » Software » MultiPHP Manager »** the **PHP Handlers** tab: the handler per PHP version (`cgi`, `suphp`, `fcgid`, `lsapi`, `fpm`). The **System PHP-FPM** / per-domain **PHP-FPM** toggle shows FPM per domain. In **cPanel » MultiPHP Manager**, the PHP version (e.g. `ea-php83`) of the domain that serves the Laravel app |
| Permissions | WHM (root/reseller) for the handler; cPanel for the domain's PHP version |
| Evidence | the domain → its PHP version (`ea-phpXY`) → the handler for that version; whether PHP-FPM is on for that domain |
| If unavailable | ask support (template §1.10). **Optional, owner-controlled:** a temporary `phpinfo()` file is the most direct evidence (`Server API`: `CGI/FastCGI` means `cgi` or `fcgid`; `FPM/FastCGI` means FPM; `LiteSpeed V…` means LSAPI). It **is** a production change: give it an unguessable name, keep it for less than a minute, delete it, and capture only the `Server API`, `PHP Version` and `Loaded Configuration File` lines. If `Server API` reads `CGI/FastCGI`, `cgi` versus `fcgid` still needs MultiPHP Manager or support |

Why it matters: it decides `Content-Length` delivery, how disconnects appear in telemetry, and what actually caps
concurrency (§3, §4). See the local validation report §2.2, §3 and §5.

### 1.2 Apache MPM

| | |
|---|---|
| Where | **WHM » Software » EasyApache 4 »** the currently installed profile, **Apache Modules**: one of `mod_mpm_prefork`, `mod_mpm_event`, `mod_mpm_worker`. Also **WHM » Server Status » Apache Status**, whose header shows `Server MPM:` |
| Permissions | WHM |
| Evidence | the MPM name, plus the Apache version (`Server Version`) |
| If unavailable | support. The repository **documents** prefork (`download-capacity.md`), not verified here |

### 1.3 MaxRequestWorkers and ServerLimit

| | |
|---|---|
| Where | **WHM » Service Configuration » Apache Configuration » Global Configuration**: *Max Request Workers*, *Server Limit*, *Start Servers*, *Max Connections Per Child*. **Apache Status** shows current busy and idle workers |
| Permissions | WHM |
| Evidence | all four values, and one Apache Status capture taken at a normal busy hour (busy / idle / total) |
| If unavailable | support. **Documented:** `MaxRequestWorkers` 800 (target 1000), `ServerLimit` 1500. Unverified |

### 1.4 PHP process limits (the PHP-side ceiling)

| Handler | Where | Values to capture |
|---|---|---|
| `fcgid` | EasyApache `mod_fcgid` settings: **WHM » Service Configuration » Apache Configuration » Include Editor**, or support (`/etc/apache2/conf.d/fcgid.conf` or similar) | `FcgidMaxProcesses`, `FcgidMaxProcessesPerClass`, `FcgidOutputBufferSize`, `FcgidIOTimeout`, `FcgidBusyTimeout`, `FcgidIdleTimeout`, spawn-score settings |
| `fpm` | **WHM » MultiPHP Manager »** PHP-FPM **User Domain Settings** for the domain | `pm`, `pm.max_children`, `pm.max_requests`, `request_terminate_timeout` (if exposed) |
| `cgi` / `suphp` | no PHP pool: one process per request, bounded by Apache workers and account limits | — |
| CloudLinux (any handler) | **cPanel » Resource Usage** or **WHM » CloudLinux LVE Manager** | **EP** (entry processes), **NPROC**, **PMEM** / **VMEM**, and current faults. On CloudLinux these are often the real ceiling |

| | |
|---|---|
| Permissions | WHM, or cPanel for Resource Usage |
| Evidence | the values above, and any recent "EP limit reached" or memory faults |
| If unavailable | support |

### 1.5 PHP memory and execution limits

| | |
|---|---|
| Where | **cPanel » MultiPHP INI Editor »** select the domain or its PHP version. *Basic Mode* shows `memory_limit`, `max_execution_time`; *Editor Mode* shows the rest, including `output_buffering`, `zend.exception_ignore_args`, `ignore_user_abort` and `default_socket_timeout` |
| Permissions | cPanel |
| Evidence | each value, separately for the **web** PHP (domain) and the **CLI** PHP that cron uses (if different; ask support which binary `/usr/local/bin/php` maps to) |
| If unavailable | support, or the optional `phpinfo()` (§1.1) |

### 1.6 Server RAM and available memory

| | |
|---|---|
| Where | **WHM » Server Status » Server Information** (memory total / used / available). **cPanel** sidebar "Physical Memory Usage" (on CloudLinux: the account's own limit, PMEM) |
| Permissions | WHM, or cPanel for the account view |
| Evidence | total RAM, typical available memory at peak, and the account memory limit if CloudLinux |
| If unavailable | support |

### 1.7 Web and cron execution users

| | |
|---|---|
| Where | **Cron:** cPanel » Cron Jobs always runs as the **cPanel account user**. **Web PHP:** with `cgi`/`fcgid` under suEXEC, `suphp`, `lsapi` and per-account FPM pools, PHP runs as the **account user**; with `mod_php`-style or shared pools it may be `nobody`. MultiPHP Manager / support confirms which |
| Permissions | cPanel (account name), WHM or support (suEXEC / pool user) |
| Evidence | the account username; a written answer to "do web PHP and cron run as the same user?" |
| If unavailable | support. **After code deployment** (no production change needed): the file owner of `storage/framework/sessions/*` or `storage/logs/laravel.log` created by web requests, seen in cPanel **File Manager** (*Owner* column), compared with a file written by a cron job |

### 1.8 Filesystem type (flock on the spool, archive and slot directories)

| | |
|---|---|
| Where | **WHM » Server Status » Server Information** / **Show Current Disk Usage** (mount points), or support |
| Permissions | WHM or support |
| Evidence | the filesystem type and mount for the account's home directory (e.g. `xfs`/`ext4` on `/home`). **Not NFS** is required |
| If unavailable | support; ask explicitly "is `/home/<account>` on a local filesystem (not NFS/CIFS)?" |

### 1.9 MariaDB version and limits

| | |
|---|---|
| Where | **cPanel** home sidebar "Database Version"; **phpMyAdmin** home ("Server version"); **WHM » SQL Services » MySQL/MariaDB Upgrade** (current version); `max_connections` in phpMyAdmin » *Variables* |
| Permissions | cPanel / phpMyAdmin (read-only view) |
| Evidence | the version string, `max_connections`, and whether `GET_LOCK` is usable (needed for the rollup and archive locks). Under phpMyAdmin » SQL: `SELECT GET_LOCK('w2a_probe',0); SELECT RELEASE_LOCK('w2a_probe');` must return `1`, `1`; this is harmless and holds nothing |
| If unavailable | support. **Not documented** in this repository for production: the 10.11 figures in these docs are **local** measurements |

### 1.10 Hosting-support request template (copy, fill and send)

> For cPanel account `<account>` and domain `<domain>` (Laravel app at `<path>`), please confirm, read-only:
>
> 1. PHP version and **PHP handler** (cgi / suphp / fcgid / lsapi / fpm) for that domain, and whether suEXEC is used.
> 2. Apache MPM, MaxRequestWorkers, ServerLimit.
> 3. If fcgid: FcgidMaxProcesses, FcgidMaxProcessesPerClass, FcgidOutputBufferSize, FcgidIOTimeout, FcgidBusyTimeout.
>    If FPM: pm, pm.max_children, request_terminate_timeout.
> 4. CloudLinux LVE limits for the account (EP, NPROC, PMEM), if any.
> 5. Total server RAM, and typical free memory at peak.
> 6. Filesystem type of `/home/<account>` (local, not NFS?).
> 7. Whether web PHP and cron jobs run as the same OS user.
> 8. Whether `/proc` is mounted with `hidepid` (or CloudLinux "SecureLinks/hidepid") for the account.
> 9. MariaDB version and max_connections.
> 10. Any reverse proxy or CDN in front of Apache, and its response buffering.
>
> No changes requested.

---

## 2. Safe external checks (optional, low impact)

**Rules:**

- **One request at a time** from one machine, against **one small file** (≤ 5 MB), at a quiet hour.
- **No load tests, no parallel requests.**
- **Counters:** on the **currently deployed** code, a GET **and a HEAD** each increment the download counter (the old
  HEAD behaviour). Every check below adds +1 to the chosen item's counter. Use one item, and note the counts.

| # | Check | Command | What it tells you | What it does **not** prove |
|---|---|---|---|---|
| E1 | Response headers | `curl -sS -o /dev/null -D - https://<site>/khotab-download-<id>.htm` | status, `Content-Type`, `Content-Disposition`, `Server`, `X-Powered-By` (PHP version, if exposed), proxy or CDN headers (`Via`, `CF-…`, `X-Cache`) | the handler |
| E2 | `Content-Length` | the same output | **Before** the new release, the deployed streamer sets **no** `Content-Length` at all (committed HEAD), so its absence says nothing about the handler. **After** the new release (which sends a trustworthy length), presence suggests FastCGI or FPM, and absence with `Transfer-Encoding: chunked` suggests plain CGI or a buffering proxy | **never definitive**: a proxy or CDN, compression, or an untrusted upstream length (the app omits it) all change the result |
| E3 | HEAD | `curl -sS -I https://<site>/khotab-download-<id>.htm`, then compare the item's counter (admin page or DB) | the current HEAD behaviour, which counts and takes a slot today. After the release: no count | — |
| E4 | Redirects | `curl -sS -I https://<site>/recite-download-<id>.htm` (or `var-download`, `fatawa-download`, `mobile-file`) | status (302) and `Location`; a baseline to compare after the release | — |
| E5 | Download consistency | `curl -sS -o f.bin -w '%{http_code} %{size_download}\n' https://<site>/khotab-download-<id>.htm`, then `sha256sum f.bin`, twice, minutes apart | the same size and hash both times; the size matches the source file | — |
| E6 | Media mirrors (after the release) | `curl -sS -I https://<site>/khotab-mirror-<id>-<yt_mirror>.htm` | `302` to `https://www.youtube.com/…`, `Cache-Control: no-store` | — |

**Record:** date and time (UTC), the exact command, the full header output, the size and SHA-256, and the counter
before and after.

---

## 3. Capacity analysis: how the limits interact

A proxied download holds **all** of these for its whole duration:

1. one **Apache worker** (under prefork: one process per connection);
2. one **PHP process** (php-cgi / fcgid / FPM child);
3. one **download slot** (`DOWNLOAD_CAPACITY_MAX_CONCURRENT`);
4. **memory** for that PHP process. The local lab build measured about 35–40 MiB unique and about 60 MiB RSS per process;
   production differs.

So the number of concurrent downloads is bounded by the **smallest** of:

| Bound | Source |
|---|---|
| Apache workers available to downloads | `MaxRequestWorkers` − workers needed for every other request (pages, assets, admin) |
| PHP processes | fcgid `FcgidMaxProcesses` (per class), FPM `pm.max_children`, CloudLinux EP/NPROC; plain CGI has no pool, only Apache and LVE |
| The download-slot limit | `DOWNLOAD_CAPACITY_MAX_CONCURRENT` |
| Memory | (available RAM, or the LVE PMEM) ÷ (memory per PHP process) |

**What happens at each limit:**

| Limit reached first | Visitor sees | Effect on the rest of the site |
|---|---|---|
| **Slot limit** | an immediate **503 + Retry-After** (logged in `download_rejections`) | none: pages keep their workers and processes. **This is the intended ceiling** |
| **PHP process cap** (fcgid / FPM / EP) | the request **waits in a queue** (fcgid inside an Apache worker; FPM in its listen backlog), then is served, or times out (fcgid busy or IO timeout, an EP 508 on CloudLinux) | queued downloads **hold Apache workers** (fcgid under prefork) and compete with page requests; the site can feel slow before any 503 is sent. Local evidence: under a burst, 47 of 100 clients got no response within 12 s, instead of a 503 |
| **Apache workers** | connections wait in the listen backlog, then time out | **the whole site** stalls |
| **Memory** | swapping, the OOM killer, or LVE memory faults | **the whole site** degrades |

**Therefore:**

- The slot limit protects the site **only if it is the lowest of these bounds**, with headroom left for page traffic.
- The slot limit should be **below** both the PHP process cap and `MaxRequestWorkers`, minus a reserve for
  non-download requests, and within memory ÷ per-process memory.
- **Do not change the production slot limit** (up to 400 or down) until §1.3, §1.4 and §1.6 are evidenced, and the
  current value has been read from production. The repository's figures conflict: `download-capacity.md` lists
  "current 100, target 400", while its incident note says "400/400 slots held".
- Local validation did not exercise 400 concurrent workers (maximum 140); see the validation report §5.

---

## 4. Telemetry accuracy under plain CGI

**What happens.**

- **Plain CGI** (`cgi` / `suphp`): when a visitor disconnects, Apache **terminates the php-cgi process**. The slot is
  freed by the kernel at once (verified locally, about 0.4 s), but **no PHP code runs**. Neither `finally` nor the
  shutdown fallback fires, so the transfer has `download_accepted` + `transfer_started` and **no**
  `transfer_finished`.
- **FastCGI** (`fcgid`) **and FPM:** PHP itself sees the aborted connection, and the shutdown fallback records exactly
  one `transfer_finished st=client_aborted k=shutdown_fallback`. FPM was not tested locally, but it is expected to
  behave like fcgid.

**Impact on metrics:**

- **`completed`** counts stay correct: a completed transfer always ends in PHP.
- **`client_aborted`** is **under-counted under plain CGI**. Disconnects appear as *unterminated transfers*:
  `transfer_started − transfer_finished` per period.
- **Monitor `transfers` check:** the disconnect ratio (`client_disconnect_ratio_warn`) under-reads under plain CGI.
  The failure ratio (stalled, read_failed, incomplete, source errors) is unaffected, because those end inside PHP.
- **A SIGKILL by the host** (OOM, LVE, a manual kill) has the same effect under every handler.

**Reading the data:** under plain CGI, treat *unterminated* (started − finished) as "interrupted, cause unknown, mostly
client disconnects". No telemetry code change is planned; this is a documented limitation.

---

## 5. Evidence matrix

**Confidence key:**

| Level | Meaning |
|---|---|
| **V** | verified on production by the owner (none yet) |
| **D** | documented in this repository, not independently verified |
| **L** | local lab only |
| **U** | unknown |

| Required fact | Current known value | Evidence source | Confidence | Prod. verification | Release impact |
|---|---|---|---|---|---|
| PHP handler for the Laravel app | unknown. The incident narrative says "PHP-CGI processes" (fits `cgi` or `fcgid`) | `download-capacity.md` (narrative) | U (D for "php-cgi") | **pending** (§1.1) | **High**: Content-Length, telemetry disconnects, the real concurrency ceiling |
| PHP version (web, domain) | the app requires `^8.3`; the OLD site is on `ea-php81` | `composer.json`, `decision-log.md` | U for the app domain | pending (§1.1) | **Blocker** if not 8.3 |
| PHP CLI version and `memory_limit` (cron) | unknown | — | U | pending (§1.5) | rollup and archive cron need ≥ 256M |
| Apache MPM | prefork | `download-capacity.md` | D | pending (§1.2) | the capacity model |
| `MaxRequestWorkers` / `ServerLimit` | 800 (target 1000) / 1500 | `download-capacity.md` | D | pending (§1.3) | the capacity ceiling |
| PHP process limits (fcgid / FPM / LVE) | unknown | — | U | pending (§1.4) | **the actual concurrency ceiling** |
| `FcgidOutputBufferSize` (if fcgid) | unknown (module default 64 KiB) | lab: buffering delays bytes on a stalled source | U / L | pending (§1.4) | client-visible latency only |
| Download slot limit (`DOWNLOAD_CAPACITY_MAX_CONCURRENT`) | **conflicting**: "current 100, target 400" vs "400/400 slots held" | `download-capacity.md` | D (inconsistent) | pending (read `.env` via cPanel File Manager, or support) | must be **preserved** at deployment; not changed without §3 evidence |
| `memory_limit`, `output_buffering`, `zend.exception_ignore_args` (web) | unknown | — | U | pending (§1.5) | memory, privacy of traces |
| Server RAM / account memory limit | unknown | — | U | pending (§1.6) | whether high slot counts are survivable (lab: ~35–40 MiB unique per process) |
| Web and cron users | unknown; likely the account user (suEXEC/FPM) | lab assumption only | U | pending (§1.7) | spool and archive modes (0750/0640 vs 0770/0660) |
| Filesystem type of the account home | unknown | — | U | pending (§1.8) | flock-based slots, spool and archive require a local FS (**blocker** if NFS) |
| `/proc/locks` visible to the account (hidepid) | unknown | lab: visible as the account user | U / L | pending (§1.10 Q8; then the monitor after deployment) | slot monitoring reports `unknown` if hidden |
| MariaDB version / `max_connections` / `GET_LOCK` | unknown (code comments cite `max_connections = 300` from an incident) | `ProxiedDownloadStreamer.php` comment | U (D for 300) | pending (§1.9) | instant `ADD COLUMN` needs 10.3+; rollup and archive locks need `GET_LOCK` |
| Reverse proxy / CDN in front | unknown | — | U | pending (E1, §1.10 Q10) | disconnect propagation, `Content-Length` |
| Behaviour under CGI and FastCGI | all contract checks pass; slots always freed | local validation report | L | n/a (local) | supports a conditional GO |

---

## 6. Minimum facts needed before code deployment (flags off)

The code deployment may proceed only with **verified** (V) answers for:

1. **The PHP version of the Laravel domain is 8.3.x** (§1.1). This is a hard requirement of the application.
2. **The PHP handler** (§1.1). It does not block the deployment by itself, but it decides how the post-deployment
   checks and the monitoring are read, and whether `Content-Length` reaches visitors.
3. **The current download settings in production `.env`**: `DOWNLOAD_CAPACITY_GUARD`,
   `DOWNLOAD_CAPACITY_MAX_CONCURRENT` (and their `KHOTAB_DOWNLOAD_*` legacy names), `DOWNLOAD_REMOTE_*`. They are read in
   **cPanel » File Manager** (view only; the file holds secrets, so do not download or share it) and are preserved
   unchanged by the deployment.
4. **MariaDB version ≥ 10.3** and that the deployment user can run the three migrations (§1.9).
5. **The filesystem is local, not NFS** (§1.8): the existing capacity guard already relies on flock.
6. **A working, recent backup** of the database and the application files (hosting backup or cPanel Backup), plus a
   restore path.

**Needed before feature activation** (not before code deployment):

- web and cron users (§1.7);
- `/proc/locks` visibility (§1.10 Q8);
- PHP process limits and RAM (§1.4, §1.6);
- the CLI `memory_limit` (§1.5);
- spool and archive storage (runbook Phase 6);
- alert destinations.

**Needed before any change to the slot limit:**

- §1.3, §1.4 and §1.6 all evidenced;
- the capacity analysis (§3) written down with the real numbers.

Until then the production value stays as found.
