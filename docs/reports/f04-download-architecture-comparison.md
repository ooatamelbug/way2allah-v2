# F04 «حفظ المادة»: download architecture comparison (research only)

**Date:** 2026-11-14.

**Status:** **RESEARCH ONLY.** No implementation, no infrastructure change, no production access.

**Owner position (given):**

- the B7a browser-Blob approach is **not approved**;
- **expanding the existing PHP proxy is not approved at this stage**.

**Sources:**

- the production topology documented in this repository (`docs/operations/download-capacity.md`,
  `download-production-discovery.md`, `split-layout-media-roots.md`, `download-external-media-policy.md`);
- the code of `ProxiedDownloadStreamer` / `DownloadCapacityGuard`;
- the local lab (`docs/reports/download-local-fastcgi-validation.md`);
- B7a (`docs/reports/admincp-b7a-download-validation.md`);
- read-only `SELECT`s on the local 2025 DB copy;
- the archive.org header probes of 2026-11-14.

**Confidence labels:**

| Label | Meaning |
|---|---|
| V | verified locally |
| D | documented in this repository |
| I | inferred |
| U | unverified |

---

## 1. Facts the options depend on

### Production

| Fact | Value | Conf. |
|---|---|---|
| Hosting | cPanel/WHM, EasyApache 4 | D |
| Apache MPM | **prefork**, `MaxRequestWorkers` 800 (target 1000), `ServerLimit` 1500 | D |
| PHP handler | `cgi` or `fcgid`, "PHP-CGI processes" in the incident | D/U |
| CloudLinux LVE | unknown | U |
| RAM | unknown | U |
| Reverse proxy / CDN in front of Apache | **unknown** | U |
| Download slots | `DOWNLOAD_CAPACITY_MAX_CONCURRENT`: "current 100 / target 400" vs "400/400 held". Must be preserved | D (conflicting) |

### Today's delivery

| Delivery | Covers | Conf. |
|---|---|---|
| **proxied through PHP**, with `Content-Disposition: attachment`, one slot, one PHP-CGI process and one prefork worker per transfer | Khotab items and mirrors (YouTube/SoundCloud mirrors redirect) | D |
| **302 redirect** to the stored link, so archive.org plays inline | Anasheed, Telawah, Fatwa, lessons, mobile | D |
| Apache static serving | `/media/*` | D |

### Existing PHP proxy (code)

| Property | Detail | Conf. |
|---|---|---|
| Upstream | opened with PHP's stream wrapper, which **follows redirects (PHP default)**; a 206 is refused as "not a file" | V (code) |
| Range / resume | **no client `Range`** support | V (code) |
| Host check | **none**: the stored link is admin-controlled, and redirect hops are **not** host- or IP-validated | V (code) |
| Per-process memory (lab) | about **35–40 MiB unique** per PHP process | V |
| Concurrency (lab) | `mod_fcgid` spawn limits cap it before the slot pool | V |
| Disconnects (lab) | plain CGI kills php-cgi; FastCGI detects the abort | V |
| 400 concurrent | **not validated** | V |

### archive.org (2026-11-14)

| Behaviour | Conf. |
|---|---|
| `/download/{item}/{file}` returns **302** to a storage node (`ia*.us.archive.org`, `dn*.ca.archive.org`; the node varies between requests) | V |
| **No `Content-Disposition`** at either hop, with or without `?download=1` | V |
| Storage nodes support `Range` | V |
| CORS on the final file: **MP3 yes, MP4 no** (0/36 Way2Allah MP4s) | V |
| Throttling or blocking of a single server IP that fetches heavily | U, a real risk for any server-side option |

### Library and traffic (local 2025 copy)

| Measure | Value | Conf. |
|---|---|---|
| archive.org-hosted media | **≈ 56 TiB**: Khotab items 20.1 TiB (avg 74 MiB), mirrors 35.5 TiB (avg 88 MiB), Anasheed 0.19 TiB (avg 24 MiB) | V |
| Lifetime `downcount` | Khotab ≈ 131 M, Anasheed ≈ 25 M. Lifetime span and current rates unknown | V / U |

**Sizing formulas used below:**

- concurrent transfers = arrival rate × mean transfer time (Little's law);
- monthly volume (TiB) ≈ rate (/s) × mean size (MiB) × 2.63 M s ÷ 1,048,576.

| Example (60 MiB, 4 Mbit/s client ≈ 126 s per transfer) | Concurrent | Volume |
|---|---|---|
| 0.05 downloads/s | ≈ 6 | ≈ 7.5 TiB / month |
| 0.5 downloads/s | ≈ 63 | ≈ 75 TiB / month |

**The real per-module arrival rate is the single most important missing input (plan E7).**

### On X-Sendfile and X-Accel-Redirect

| Mechanism | Remote files? |
|---|---|
| `mod_xsendfile` (Apache) | serves **local files only**; it **cannot** serve a remote URL |
| `X-Accel-Redirect` (**nginx only**) | hands the response to an **internal location**. That location *can* be a `proxy_pass`, so nginx (not PHP) streams a remote file. **Only if nginx fronts the site**, which is unknown in production (U) |

Neither is assumed below.

---

## 2. The four options

### A. Existing PHP proxy (`ProxiedDownloadStreamer` + `DownloadCapacityGuard`)

Route the redirect modules through the same proxy Khotab uses. **Not approved for expansion now**; it is included for
comparison.

| Criterion | Assessment |
|---|---|
| Guarantees attachment | **Yes** (sets `Content-Disposition`), for every reachable source including archive.org MP4 (V) |
| PHP occupancy | **1 PHP-CGI process per transfer, for its whole duration** (minutes) (D/V) |
| Memory | about 35–40 MiB per transfer for PHP, plus a prefork worker (V / U) |
| Bandwidth | **2×** on the web server (in from archive.org, out to the client) |
| Concurrency | bounded by min(slot pool, fcgid / LVE process limits, `MaxRequestWorkers`). It **shares** the pool and workers with Khotab and with every page view. Saturation degrades the **whole site** (D) |
| SSRF / redirects | stored links are admin-controlled, but redirect hops are not host- or IP-checked today (V, code). Expansion would need an allowlist plus private-IP rejection per hop |
| archive.org | works: the wrapper follows the 302 (V) |
| Client cancellation | CGI: process killed; FastCGI: detected (V, lab) |
| Large files | streams, but each holds resources for the full transfer; **no Range / resume** (V, code) |
| Deployment | code only, but **raises the slot or worker budget**, which the owner has frozen |
| Operational complexity | low (exists) |
| Cost | server bandwidth and RAM on the shared host; risk to site availability |
| **Verdict** | technically guarantees the download; **architecturally the wrong place** for long transfers on a prefork, PHP-CGI host |

### B. Web-server-based external media gateway

A path on the web server (for example `/dl/ia/{item}/{file}`) that the web server itself proxies to archive.org,
adding `Content-Disposition`, with no PHP in the byte path. There are two sub-variants, and they differ sharply.

**B1. Apache `mod_proxy` on the production Apache**

| Criterion | Assessment |
|---|---|
| Guarantees attachment | **No, not reliably.** `mod_proxy` **does not follow upstream redirects**: archive.org's 302 would be passed to the client, which then plays from the storage node. Proxying straight to a node is not viable, because the node varies per request (V) |
| PHP occupancy | none |
| Memory | one **prefork Apache worker per transfer** (U; typically far smaller than PHP + worker) |
| Bandwidth | 2× on the web server |
| Concurrency | consumes `MaxRequestWorkers` shared with the site |
| SSRF | a fixed host mapping is safe; `.htaccess` `RewriteRule [P]` with a variable host would be an open proxy, so it is **not** acceptable |
| Deployment | WHM root (EasyApache `mod_proxy`, vhost include) |
| **Verdict** | **does not solve archive.org** |

**B2. nginx gateway** (cPanel's `ea-nginx` reverse proxy in front of Apache if available, or nginx on a separate
host/subdomain)

| Criterion | Assessment |
|---|---|
| Guarantees attachment | **Yes.** nginx can intercept the upstream 302 (`proxy_intercept_errors` + `error_page 301 302 = @follow`) and `proxy_pass` to the `Location` after validating it. It can then `add_header Content-Disposition "attachment; filename*=UTF-8''…"` (I; standard pattern, not lab-tested here) |
| PHP occupancy | **none in the byte path.** Optionally Laravel authorises and counts in milliseconds, then answers `X-Accel-Redirect` to the internal gateway location: PHP is freed at once |
| Memory | event-driven: about **tens of KiB per connection** plus proxy buffers. Set `proxy_buffering off` or `proxy_max_temp_file_size 0` so large files are **not spooled to disk** (I) |
| Bandwidth | 2× on whichever host runs nginx |
| Concurrency | thousands of connections per worker. Bounded by bandwidth, file descriptors and `worker_connections`, not RAM (I) |
| SSRF / redirects | requires an exact host allowlist (`archive.org` and the suffix `.archive.org` with a leading dot), `https` and port 443 only, a bounded hop count (≤ 2), the `Location` re-validated at each hop, private and reserved IPs rejected (or a pinned resolver), an `Accept` type check, and `Range` pass-through. **Never** a raw user-supplied URL: the path encodes only `{item}/{file}`, or a signed token (§3) |
| archive.org | compatible: redirect following plus `Range` |
| Client cancellation | nginx closes the upstream when the client aborts (`proxy_ignore_client_abort off`, the default) (I) |
| Large files | native streaming; `Range` and resume can be passed through |
| Deployment | `ea-nginx` needs **WHM root** plus custom `location` includes; **its availability on this server is U**. A separate host needs DNS (`dl.` subdomain), TLS and a VPS |
| Operational complexity | low–medium (nginx config, logs, monitoring) |
| Cost | if on the same server: **its bandwidth** (shared with the site). If separate: a VPS (§4) |
| **Verdict** | **viable**: the cheapest correct server-side design *if* nginx can front the site or a small separate host is acceptable |

### C. Dedicated download service

A small, separate service, either nginx (B2 on its own host) or a tiny Go service, on `dl.way2allah.com`. Laravel
issues short-lived **signed** links; the service validates them and streams from archive.org with `attachment`.

| Criterion | Assessment |
|---|---|
| Guarantees attachment | **Yes** (I; same mechanism as B2) |
| PHP occupancy | **none.** Laravel only signs the URL when rendering the page or a tiny redirect route |
| Memory | about tens of KiB to a few hundred KiB per connection (event-driven nginx or Go goroutines) (I) |
| Bandwidth | 2× on the **service host**, isolated from the web server. The production site no longer carries the bytes |
| Concurrency | high; set by the host's bandwidth and descriptors. A per-IP and global concurrency cap is enforced in the service |
| SSRF / redirects | the token carries `url` (pre-normalised by `ArchiveOrgLink`), `filename`, `expires` and an HMAC (key shared with Laravel); TTL about 15 min; no IP binding (mobile networks). The service additionally enforces the B2 rules (host allowlist, https, ≤ 2 hops, per-hop re-validation, private-IP rejection with connect-to-resolved-IP, type allowlist) and logs without query strings |
| archive.org | compatible. **Risk:** all traffic leaves from one IP; archive.org may throttle it (U) |
| Client cancellation | the service cancels the upstream on client disconnect (Go `context`; nginx default) |
| Large files | streaming; `Range` and resume pass-through |
| Deployment | a VPS or container host, DNS, TLS (ACME), a secret shared with Laravel, monitoring and alerting. A later Laravel change points «حفظ المادة» at the signed link (a B7b-like batch, not proposed now) |
| Operational complexity | medium: one more service to run and patch |
| Cost | a VPS with generous or unmetered transfer. Indicative list prices **(verify)**: budget VPS ≈ USD 5–40 / month with several to tens of TB included, or unmetered 1 Gbit/s ≈ USD 30–100 / month; hyperscaler egress ≈ USD 50–90 / TB (avoid). At 7.5 TiB/month (0.05/s example) a budget VPS suffices; at 75 TiB/month an unmetered link is needed |
| **Verdict** | **recommended target architecture**: guarantees the download, removes the load from the production host, needs no root on the shared server |

### D. Controlled media storage or mirroring

Copies of the media on storage the project controls (object storage plus CDN, or the site's own server), served with
`Content-Disposition: attachment`: object metadata, or `response-content-disposition` on a presigned GET, or a CDN
rule.

| Criterion | Assessment |
|---|---|
| Guarantees attachment | **Yes** (I; S3-compatible stores support `response-content-disposition` on presigned URLs) |
| PHP occupancy | none. Laravel signs or links only |
| Memory | none on our servers |
| Bandwidth | none on our servers. A CDN or the storage provider serves |
| Concurrency | effectively unbounded (CDN) |
| SSRF | not applicable to downloads (no proxying). The **copy job** fetches from archive.org and needs the same host rules |
| archive.org | removes the dependence on archive.org at download time; archive.org remains the source of the copy |
| Client cancellation | native |
| Large files | native, with `Range` and resume |
| Deployment | a storage account plus CDN, a **bulk copy** (≈ **56 TiB** for everything), a sync job for new uploads, an index mapping item to object, and a Laravel URL switch |
| Operational complexity | **high**: storage lifecycle, sync, consistency, takedowns, two sources of truth |
| Cost | storage dominates. Indicative **(verify)**: ≈ USD 6 / TB-month (B2-class) to ≈ USD 15 / TB-month (R2-class, zero egress) to ≈ USD 23 / TB-month + egress (S3). Full mirror ≈ **USD 340–1,300+ / month** before egress. A **hot-subset mirror** (the most downloaded files, or MP4 only, or cache-on-first-download) could cut this by an order of magnitude, but needs download statistics to size |
| **Verdict** | the most robust delivery, but the highest effort and running cost. Sensible later, as a **cache tier behind C**, not as a first step |

---

## 3. Summary matrix

| Criterion | A. PHP proxy | B1. Apache mod_proxy | B2. nginx gateway | C. Dedicated service | D. Own storage / CDN |
|---|---|---|---|---|---|
| Guaranteed attachment (archive.org MP4) | ✅ | ❌ (302 leaks) | ✅ | ✅ | ✅ |
| PHP worker per transfer | ❌ yes, whole duration | ✅ none | ✅ none | ✅ none | ✅ none |
| Memory per transfer | ≈ 35–40 MiB + worker | 1 prefork worker | KiB | KiB | none on our side |
| Load on the production host | high | high (workers) | high if co-located | **none** | **none** |
| Concurrency | low (shared pool) | low (`MaxRequestWorkers`) | high | high | very high |
| SSRF exposure | redirect hops unchecked today | fixed host only | must be engineered | must be engineered (signed tokens) | copy job only |
| Range / resume | ❌ | partial | ✅ | ✅ | ✅ |
| Client cancellation | ✅ (lab) | ✅ | ✅ | ✅ | ✅ |
| Needs WHM root | no (budget changes) | yes | yes (`ea-nginx`) or a separate host | no (separate host) | no |
| New infrastructure | none | none | maybe | VPS, DNS, TLS | storage, CDN, sync |
| Operational complexity | low | low | low–medium | medium | high |
| Running cost | shared-host bandwidth | same | same, or a VPS | VPS (≈ 5–100 USD/mo by volume) | storage (≈ 340–1,300+ USD/mo full) |
| Depends on archive.org per download | yes | yes | yes | yes (single IP: throttling risk) | no |

## 4. Recommendation (for owner decision; not approved work)

1. **Gather the missing input first** (no code):
   - per-module download arrival rates and bytes per month, from the access logs or the Phase 3A telemetry once
     released (E7);
   - whether production has `ea-nginx` or any proxy or CDN in front of Apache (discovery §1.10 Q10).
2. **Target: option C**, a small dedicated download gateway on its own host, nginx-based or a minimal Go service,
   with HMAC-signed short-lived links, strict archive.org allowlisting and per-hop redirect validation, `Range`
   pass-through and a concurrency cap.
   - It is the only option that **guarantees the save, keeps PHP out of the byte path and keeps the load off the
     shared production host**, without root access there.
   - The production 400-slot configuration and the Khotab proxy stay untouched. Khotab could later move to the same
     gateway, by separate decision.
3. **If production already runs nginx in front of Apache:** B2 with `X-Accel-Redirect` is an equivalent, cheaper
   alternative on the same host, subject to the host's bandwidth headroom.
4. **Later, optional:** D as a **hot-file cache** behind C, once download statistics identify the files worth
   mirroring.
5. **Not recommended:**
   - B1, which cannot follow archive.org's redirect;
   - expanding A, against the owner's freeze and the prefork / PHP-CGI capacity risk;
   - the browser Blob approach (rejected after B7a).

**Interim, no infrastructure:** today's behaviour plus clearer UI guidance is the only zero-cost option. It does
**not** guarantee a download, and is listed only for completeness.

## 5. Unverified items that change the recommendation

| # | Item | Effect if different |
|---|---|---|
| 1 | per-module download rate and volume | sizes C's host, or D's subset |
| 2 | nginx or CDN in front of production Apache | enables B2 on the same host |
| 3 | archive.org tolerance for a single high-volume fetching IP | if it throttles, C needs multiple egress IPs or D |
| 4 | production RAM, LVE limits and PHP handler | affects only A, which is not recommended |
| 5 | current VPS and CDN prices | all cost figures above are indicative list prices, not quotes |

## 6. Confirmations

Analysis and documentation only:

- no code, configuration or infrastructure change;
- no production access;
- no database writes (read-only `SELECT`s on the local copy);
- no commits, pushes or deployments;
- `DownloadCapacityGuard`, `ProxiedDownloadStreamer`, slots, Khotab streaming and telemetry are untouched.
