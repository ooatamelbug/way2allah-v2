# F04 «حفظ المادة»: save-material download — results

**Date:** 2026-11-17.

**Owner requirement:** clicking «حفظ المادة» starts a download to the device instead of opening the file in the
browser, for local and external files, including archive.org.

**Constraints kept:**
- the existing Laravel architecture;
- no PHP proxying of large files (the existing Khotab proxy and the capacity guard are unchanged);
- no external service;
- no OLD or Gen 2 change;
- local only, no shared-database write;
- nothing committed, pushed or deployed.

**Status (2026-11-17, after the owner-approved proxy expansion, §6):** **CODE COMPLETE LOCALLY. NOT DEPLOYED.**

| | |
|---|---|
| **Code complete locally** | every «حفظ المادة» route now downloads the file: Khotab (existing proxy), and Variety items and mirrors, Telawah, Fatwa and chat-room lessons through the **same** protected proxy (§6) |
| **Actually verified** | against a local fixture server only: real bytes, `attachment`, MP3 / MP4, a 302 hop, HTML / 404 / unreachable / stalled sources, the full pool, HEAD, counters. **No request to archive.org or any real host was made**; real archive.org downloads are evidenced by Khotab's existing production behaviour, not re-tested here |
| **Production prerequisites** | §6.5 |
| **Still not downloadable** | §6.6 (YouTube / SoundCloud pages, host-less or local-path links, private addresses, archive.org `/details/` pages) |

§2–§5 below are the earlier state (marker mechanism, then the gateway decision). The gateway decision in §5 is
**no longer needed**: the owner approved reusing the existing proxy instead.
---

## 1. Why a browser can or cannot be made to download

- **The deciding factor:** the browser downloads instead of displaying only when the **file response itself** carries
  `Content-Disposition: attachment`.
- **What does not work cross-origin:** the HTML `download` attribute is ignored for cross-origin URLs, and a 302 to
  another host cannot add headers to that host's response.
- **The consequence:** only two kinds of file can be forced:
  - files whose host we configure;
  - files whose bytes pass through a server we control.

| archive.org fact (verified 2026-11-14, `f04-download-architecture-comparison.md` §1) | Effect |
|---|---|
| no `Content-Disposition` at either hop, even with `?download=1` | redirecting there opens the video or audio |
| MP4 has no CORS (0/36 tested) | no client-side workaround (the browser Blob was rejected anyway) |

## 2. Sources «حفظ المادة» uses, and what each one does now

Counts are from the local 2025 copy, measured read-only.

| Module (route) | Delivery | Link hosts (local copy) | Result |
|---|---|---|---|
| **Khotab** item / mirror (`khotab-download-*`) | **existing PHP proxy** with `Content-Disposition: attachment`, capacity guard (unchanged) | archive.org 291,853 items / 421,299 mirrors; others | **downloads** (already; unchanged). YouTube / SoundCloud mirrors redirect to their pages (not files) |
| **Variety** item / mirror (`var-download-*`, `var-mirror-*`) | 302 redirect | **`vars.way2allah.com` 7,808 items / 6,142 mirrors**; archive.org 8,488 / 10,865; 200 host-less paths (F07); a few third-party | **vars:** downloads **once the host rule (§3) is deployed and the host listed**. **archive.org / third-party:** unchanged, opens |
| **Telawah** (`recite-download-*`) | 302 redirect | archive.org 6,967; `way2allah.knoz4arab.com` 1,153; third-party recitation sites ≈ 700 | operated hosts after the rule; **others unsupported** |
| **Fatwa** (`fatawa-download-*`) | 302 redirect | archive.org 10, `media.way2allah.com` 1 (17 rows) | operated hosts after the rule; archive.org unsupported |
| **Chat-room lessons** (`lesson-download-*`) | 302 redirect | Khotab links (archive.org) | operated hosts after the rule; archive.org unsupported (Khotab's own route downloads them) |

## 3. What was implemented (smallest safe solution)

**Laravel** (`App\Support\Downloads\SaveAttachmentLink`):
- **When it acts:** a redirect-delivered save link whose host is **exactly** listed in
  `download_save.attachment_query_hosts` gets `w2a_save=1`.
  - The list is set with the env `DOWNLOAD_ATTACHMENT_QUERY_HOSTS` and is **empty by default**.
  - Path, query and fragment are preserved.
- **Strict checks**, as `ExternalMediaLink` already does:
  - http or https only;
  - exact host, case-insensitive, trailing dot ignored, with no suffix or substring match;
  - no userinfo, no `@` or `\` tricks, default port only;
  - at most 2,048 bytes, no whitespace or control characters.
- **Everything else** is redirected **byte-for-byte as before**.
- **Unchanged:** counters, HEAD handling, hidden-item 404s, the player (playback links never carry the marker), the
  Khotab proxy and `config/downloads.php` (the capacity / worker-protection configuration).

**Host rule.** On each listed host this turns the marker into an attachment. The visitor still fetches the file
directly from the host; PHP is never in the byte path. Apache 2.4 (mod_headers), placed in that host's docroot
`.htaccess` or vhost:

```apache
# F04 «حفظ المادة»: a Way2Allah save link (…?w2a_save=1) downloads instead of opening. Static files only; no PHP.
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{QUERY_STRING} (^|&)w2a_save=1(&|$)
    RewriteRule ^ - [E=W2A_SAVE:1]
</IfModule>
<IfModule mod_headers.c>
    Header set Content-Disposition "attachment" env=W2A_SAVE
</IfModule>
```

**Alternatives:**
- An equivalent `<If "%{QUERY_STRING} =~ /(^|&)w2a_save=1(&|$)/"> Header set Content-Disposition "attachment" </If>`
  (Apache only).
- **nginx:** inside the serving `location`, `if ($arg_w2a_save = "1") { add_header Content-Disposition "attachment"; }`.

**Verified on a local Apache 2.4.58** (both forms, identical results):

| Request | Result |
|---|---|
| no marker | **200, no `Content-Disposition`** (playback unchanged) |
| `?w2a_save=1`, `?x=1&w2a_save=1`, `?w2a_save=1&x=1` | 200 `attachment` |
| lookalikes `?xw2a_save=1`, `?w2a_save=10` | not affected |
| `HEAD` | `attachment` |
| `Range` resume | **206** `attachment` |
| Arabic file name | `attachment` |
| missing file | plain 404 |
| `Content-Type` | unchanged |

**Owner verification, per host, after placing the rule:**

```bash
curl -sI "https://vars.way2allah.com/<an existing file>?w2a_save=1" | grep -i content-disposition   # → attachment
curl -sI "https://vars.way2allah.com/<same file>" | grep -ci content-disposition                     # → 0 (playback)
```

**Then activate:** set `DOWNLOAD_ATTACHMENT_QUERY_HOSTS=vars.way2allah.com` (and any other verified operated host,
for example `way2allah.knoz4arab.com` or `media.way2allah.com` if those are Way2Allah servers: owner to confirm), then
`php artisan config:cache`.

**If a host is listed without its rule:** the file simply opens as today. There is no breakage, but it is not a
download, so **list only verified hosts**.

## 4. Tests (focused only; the full suite was not run)

| Suite | Result |
|---|---|
| `tests/Feature/Content/SaveMaterialDownloadF04Test.php` (new) | **35/35**, 53 assertions |
| F04 + existing download and touched-route suites (16 files): capacity guard, global pool, stream resilience, HEAD / MIME / media, rejection tracking, download-telemetry HTTP integration, Khotab, Variety, Telawah, chat-room lesson, Fatwa, F07-A / F07-B2 downloads, F05-2 formats, B4 visibility | **516/516**, 2,403 assertions |
| PHPStan | **0 errors** |
| Pint (changed files) | passed |
| Host rule on local Apache 2.4.58 | as §3 |

**Coverage of the requested cases:**

| Case | Covered by |
|---|---|
| Local / operated-host files | marker added; Apache rule → `attachment`, Range, HEAD |
| archive.org video | unchanged, no marker |
| Third-party video (alafasy, ghyth), YouTube | unchanged |
| Unsupported formats | F05-2 suites unchanged |
| Missing items / files | 404, not counted; Apache 404 without header |
| Malicious URLs | suffix / prefix lookalikes, userinfo, backslash, other port, `javascript:`, protocol-relative, relative, whitespace, CRLF injection, `ftp:` → never marked, never rebuilt |
| Download headers | Khotab proxy `attachment` (existing tests); host rule (Apache) |
| Capacity protections | guard and pool suites pass; `config/downloads.php` untouched (its snapshot test passes) |

## 5. What remains unsupported, and the decision needed

**Unsupported:**
- archive.org (≈ 56 TiB of the library);
- third-party hosts (recitation sites, `alafasy.me`, `quranrabbi.ghyth.com`, …);
- host-less Variety paths (data, F07).

On «حفظ المادة» these still **open in the browser**, and this is **not** presented as a download.
- **Khotab is the exception:** its proxy already downloads archive.org files.

**Minimal architecture that would make them download (option C, `f04-download-architecture-comparison.md` §2–§4):**

```
browser ──«حفظ المادة»──▶ Laravel (counts, picks source) ──302──▶ dl.way2allah.com/{HMAC-signed, 5-min token}
                                                                       │  nginx (or a small Go service) on its OWN host
                                                                       │  • verify token (secure_link / HMAC)
                                                                       │  • allowlist: archive.org + approved hosts only
                                                                       │  • follow archive.org's 302 per hop, re-validating host
                                                                       │  • pass Range through; concurrency cap
                                                                       ▼  • add Content-Disposition: attachment
                                                               archive.org storage node
```

- **No PHP** in the byte path.
- **The production host and its 400-slot pool are untouched.**

**Bandwidth implications:**
- every byte crosses the gateway **twice** (in from archive.org, out to the visitor);
- at a 60 MiB average, 0.05 downloads/s ≈ **7.5 TiB/month** (≈ 6 concurrent) and 0.5 downloads/s ≈ **75 TiB/month**
  (≈ 63 concurrent);
- the real per-module download rate is unknown (plan E7) and sizes the host;
- archive.org's tolerance of one high-volume fetching IP is unverified.

**Exact owner decision needed (one of):**
1. **Approve option C:** a dedicated gateway host (provider, bandwidth budget, domain `dl.way2allah.com`). This is
   the only way to guarantee archive.org and third-party downloads without loading production.
2. **If production already has nginx in front of Apache** (verify): approve **B2**, `X-Accel-Redirect` to an
   internal nginx `proxy_pass` location on the same host. That is cheaper, but uses production bandwidth.
3. **Accept the limitation:** guaranteed downloads only for Khotab (existing proxy) and Way2Allah-operated hosts
   (§3). archive.org and third-party files open in the browser, and the button could say so.

Separately, and needed in any case: deploy the §3 rule on `vars.way2allah.com` (and confirm which other hosts
Way2Allah operates), then list them.

---

## 6. Proxy expansion (owner-approved 2026-11-17): the implementation

### 6.1 What changed

One shared class, `App\Support\Downloads\ProxiedDownloadResponder`, applies the **Khotab download pattern** to the
five remaining save routes. It reuses `DownloadCapacityGuard`, `DownloadSlot`, `ProxiedDownloadStreamer`,
`ExternalMediaLink`, `DownloadRejectionContext` and `DownloadTelemetryContext` **unchanged**.

| Route | Handler | Visibility (as before / as the page) | Link (existing normalisation kept) |
|---|---|---|---|
| `/var-download-{id}.htm` | `AnasheedItemController::download` | `hidden = 0` | `ArchiveOrgLink::normalize`, `http:` → `https:` |
| `/var-mirror-{id}-{m}.htm` | `AnasheedItemController::downloadMirror` | item `hidden = 0` and mirror `hidden = 0` | same |
| `/recite-download-{id}.htm` | `TelawahItemController::download` | item exists (the page has no filter) | raw |
| `/fatawa-download-{id}.htm` | `FatwaQuestionController::download` | question exists | `fixArchiveLinks()` |
| `/lesson-download-{id}.htm` | `ChatRoomLessonController::download` | **Khotab's download rule**: `hidden = 1` is a 404 unless a contextual admin is viewing (a lesson *is* a Khotab item) | raw |

**Order in every route** (as in Khotab):
1. load and check visibility, so a missing or hidden item is a 404 before any slot, count or file;
2. a **YouTube / SoundCloud** page link: 302, no slot (GET counts once, HEAD never);
3. a host listed in `download_save.attachment_query_hosts` (verified attachment rule, §3): the marker 302, no slot;
4. a link the proxy must not fetch (below): the **previous redirect**, counted as before;
5. **HEAD**: the streamer's header-only answer (`attachment`, no `Location`), no slot, no source contact, no count;
6. **GET**: `tryAcquire()` **before** anything is counted or opened.
   - **Pool full:** 503 + `Retry-After`, the refusal recorded in `download_rejections` (module `anasheed` /
     `telawah` / `fatawa` / `lesson`), nothing counted.
   - **Otherwise:** count, then `ProxiedDownloadStreamer::stream()`, unchanged. It validates the source before
     committing (an HTML, 204/205/206 or failed source is a **500**, never a "file"), releases the DB connection
     before the copy, applies the connect and idle timeouts, and releases the slot on every path.

**The only new restriction, required for security** (`ProxiedDownloadResponder::proxyable()`): for these modules the
proxy fetches only `http` / `https` URLs with no userinfo and no `@` / `\` tricks, and never `localhost` or a
loopback, private, link-local, reserved or disguised (decimal / hex) IP literal.
- **Why:** the streamer reads **local files** for non-URL links (legacy Khotab parity). Extending that to Variety,
  Telawah and Fatwa editors would let a stored `../.env` or `/etc/...` be downloaded. Pointing the server at internal
  addresses is the same class of risk.
- **What happens to such links:** they keep their previous redirect.
- **What is still allowed:** any public host, on any port.
- **Not covered:** DNS names are not resolved and redirect hops are not re-checked, the same as the existing Khotab
  proxy.
- `download_save.proxy_block_private_hosts` (default `true`) exists only so the test suite can use a 127.0.0.1
  fixture server.

### 6.2 Performance and capacity implications

- **The same ONE global pool** (no new pool, limit or timeout). Each proxied save holds one slot, one PHP-CGI process
  (about 35–40 MiB) and one Apache worker for the whole transfer, and every byte crosses the production server twice.
- **Estimated extra demand** (local 2025 lifetime counters weighted by average stored size; current rates are
  unknown): about **6–8 %** more proxy byte-time than Khotab already uses.
  - Variety: 25 M item and 15 M mirror downloads at about 32 / 46 MiB.
  - Telawah: about 9 M.
  - Fatwa and lessons: small.
  - Khotab today: about 259 M at about 74–88 MiB.
  - About 5 % if `vars.way2allah.com` is listed with its header rule (§3), which takes those files off PHP entirely.
- **Under pressure:** the extra demand shows up as more 503s for **all** proxied downloads, Khotab included, never as
  exhausted workers. The guard refuses before any work.

### 6.3 Files changed (this step)

| File | Change |
|---|---|
| `app/Support/Downloads/ProxiedDownloadResponder.php` *(new)* | the shared Khotab-pattern responder |
| `app/Domain/Content/Http/Controllers/AnasheedItemController.php` | `download`, `downloadMirror` |
| `app/Domain/Content/Http/Controllers/TelawahItemController.php` | `download` |
| `app/Domain/Content/Http/Controllers/FatwaQuestionController.php` | `download` (redirect fallback kept as `away()`, as before) |
| `app/Domain/Content/Http/Controllers/ChatRoomLessonController.php` | `download` (+ Khotab's hidden rule) |
| `config/download_save.php` | `proxy_block_private_hosts` (default true) |
| `tests/Support/ProxiedDownloadProbe.php` *(new)* | asserts the link a route would proxy, with the pool full (no network) |
| `tests/Feature/Content/SaveMaterialProxyF04Test.php` *(new)* | 35 tests (local fixture server) |
| `tests/Feature/Content/SaveMaterialDownloadF04Test.php` | three route tests moved from "redirect" to "proxied (HEAD)" |
| Existing tests updated from the old redirect design (intended behaviour change; same links asserted through the probe or HEAD) | `TelawahControllersTest`, `AnasheedItemControllerTest`, `AnasheedVisibilityB4Test`, `VarietyMalformedLinksF07aTest`, `FatwaQuestionControllerTest`, `VarietyDownloadsF07b2Test`, `AnasheedVisitorParityTest`, `ChatRoomLessonControllerTest`, `UnsupportedPlaybackFormatF052Part2Test`, `GlobalDownloadPoolTest`, `DownloadHeadMimeMediaTest` |
| `docs/operations/download-capacity.md` | "What is in the pool today" updated |

**Unchanged:** `ProxiedDownloadStreamer`, `DownloadCapacityGuard`, `DownloadSlot`, `config/downloads.php`, the Khotab
controller, every timeout and limit, OLD and Gen 2.

### 6.4 Tests (focused only; the full Laravel suite was not run)

| Suite | Result |
|---|---|
| `SaveMaterialProxyF04Test` (new) | **35/35**, 142 assertions |
| `SaveMaterialDownloadF04Test` | **35/35** |
| F04 + every suite touching these routes + Khotab + capacity / pool / resilience / HEAD-MIME / rejection / telemetry-HTTP (19 files) | **581/581**, 2,782 assertions |
| `tests/Feature/Content` + `tests/Feature/Anasheed` + `tests/Feature/Support/Downloads` + 2 admin files | **2,138: 2,099 passed, 39 skipped** (MySQL / MariaDB-gated, as always), **0 failed** |
| Re-check of the changed files after Pint | **502/502** |
| PHPStan / Pint | **0 errors** / passed |

**Coverage of the requested cases** (all against `127.0.0.1` fixtures or with the pool full; no real host contacted):

| Case | Covered by |
|---|---|
| Valid MP3 | lesson: bytes, `audio/mpeg`, `attachment` |
| Valid MP4 | Telawah and the Fatwa 302 hop: bytes, `video/mp4` |
| archive.org-style 302 | followed, file downloaded |
| Canonical archive.org link | asserted via the probe for Variety, Fatwa and F05-2 |
| Visibility and missing items | hidden item, mirror of a hidden item, hidden mirror, hidden lesson, missing ids on all five routes: 404, no count, slot untouched |
| Mirrors | own `hits`, `variant = mirror`, parent title recorded |
| HEAD | `attachment`, no `Location`, no slot (works with the pool full), no source contact, no count |
| YouTube / SoundCloud | 302, no slot, GET counted once, HEAD not |
| Full pool | 503, `Retry-After: 60`, nothing counted, refusal rows with module and normalised URL |
| Connection timeouts | refused connection, and a source silent before its headers cut by the connect timeout (< 3.5 s); slot released |
| Never an HTML file | HTML page and source 404 → 500, no `attachment` |
| Counters | exactly one increment per successful GET |
| Slot and DB release | slot free after the transfer; the `main` connection is released before streaming (observed) |
| Security | `../.env`, `/etc/hostname`, `file://`, site paths are never streamed; loopback, private, link-local, IPv6 loopback, decimal-IP, userinfo and loopback-on-another-port are not fetched |
| Existing Khotab behaviour | `KhotabItemControllerTest` and the pool / resilience suites unchanged and passing |

### 6.5 Production prerequisites (owner-operated; nothing was deployed)

1. **Deploy the stalled-source timeout fix first.** It is recorded as "local; not yet deployed" in
   `docs/operations/download-capacity.md`. Without it, a silent source can hold a slot indefinitely, which is the
   400 / 400 incident. These routes add proxy load.
2. **Confirm the pool settings:** `DOWNLOAD_CAPACITY_GUARD=true` and the production slot limit (100 now, 400 target),
   with Apache `MaxRequestWorkers` headroom above it. Watch the 503 rate after release.
3. **`download_rejections` table** (migration `2026_10_22_130000`) in production for refusal rows. Production
   reported 7 migrations run on 2026-09-24. Without the table the recorder only logs (it never throws).
4. **Optional:** deploy the §3 header rule on `vars.way2allah.com` and list it, so about 14 k Variety files bypass
   PHP.
5. **Smoke test after deploy:** one Variety, Telawah, Fatwa and lesson «حفظ المادة» each downloads the file; a hidden
   Variety item is a 404.

**Rollback:** redeploy the previous controllers; the routes go back to redirects. No data or schema change.

### 6.6 Remaining unsupported sources (not presented as downloads)

| Source | Behaviour |
|---|---|
| YouTube / SoundCloud page links | redirect to the page (they are not files), as before |
| Host-less / relative Variety paths (F07 data), local filesystem paths, `file:` | previous redirect; **never** read from disk. F07 data correction is pending |
| `localhost` or private / loopback IP literals | previous redirect (security) |
| archive.org `/details/…` **pages** (local copy: 1 Variety mirror, 1 Fatwa answer; Khotab already behaves this way for its 59) | the page is HTML, so the proxy refuses it (500) instead of redirecting to the page. Data-correction candidates (point them at the `/download/…` file) |
| A source that is down, returns HTML or stalls | 500, never a broken "download" (unchanged streamer behaviour) |

---

## 7. Fix: archive.org normalisation in the download handlers (owner-approved 2026-11-17)

**Defect** (found by `admincp-f03-f05-f07-functional-verification.md`): the Khotab item and mirror, Telawah and lesson
download routes handed the proxy the **raw stored link**. Links pinned to retired archive.org storage nodes
(`ia….us.archive.org/N/items/…`) answer **500 HTML**, so «حفظ المادة» failed, while the player (which normalises,
F05-1) worked. This corrects the earlier statement that Khotab "already downloads".

**Fix:**
- The four handlers now pass `ArchiveOrgLink::normalize()` of the stored link, the same shared component the player and
  Variety use, to `ProxiedDownloadStreamer` (HEAD, stream and the rejection record).
- Only archive.org `…/items/{folder}/{file}` and `…/download/{folder}/{file}` are rewritten, to
  `https://archive.org/download/{folder}/{file}`. Every other link (other hosts, `/details/` pages, local files, empty)
  is unchanged.
- **Unchanged:** the stored values; YouTube / SoundCloud redirects, which are classified on the stored link before
  normalisation; capacity; HEAD; counters; streaming.
- `MediaPlayerService::khotabDownloadable()` now judges the same normalised link, so the F05-2 notice offers the
  download exactly when the route can serve it.

**Files:**
- `app/Domain/Content/Http/Controllers/KhotabItemController.php` (`download`, `downloadMirror`);
- `TelawahItemController.php`, `ChatRoomLessonController.php`;
- `app/Domain/Content/Services/MediaPlayerService.php` (`khotabDownloadable`);
- tests: `tests/Feature/Content/DownloadArchiveNormalizationF04Test.php` *(new, 11)*;
  `UnsupportedPlaybackFormatF052Part2Test.php` and `KhotabMirrorMp4PlaybackF052Test.php` (mirror 830, stored with a
  leading space on a retired node, is now offered and served: an intended change).

**Tests (focused):**

| Suite | Result |
|---|---|
| New suite | 11/11 |
| Download, route and F05-2 suites | 703 run: 665 passed, 38 gated skips, 0 failures |
| Content + Anasheed + Downloads directories | 2,116 run: the only failure (the mirror-830 assertion above) was then updated, and the re-check of the six affected files passed 259/259 |
| PHPStan | 0 errors |
| Pint | passed |

**Real before / after** (disposable copy of the three tables; local Laravel; GET cut after 8 s; stored links verified
unchanged):

| Route | Raw stored link | After the fix |
|---|---|---|
| `/khotab-mirror-2432-5.htm` | **500** | **200**, `attachment; filename=hakekah-1.WMV`, about 4 MB in 8 s |
| Khotab mirrors 2380, 6872, 7744 | 500 | 200 attachment, bytes streaming |
| Khotab items 72039, 71721 | 500 | 200 attachment (`audio/mpeg`) |
| Khotab item 38080 | no answer | 200 attachment |
| Telawah 869 | 500 | 200 attachment |
| Telawah 5879 and lessons 136656, 132014 | already 206 | still 200 attachment (no regression) |

**Remaining failures** (data or source, not code):
- archive.org files that are gone in both forms (e.g. mirror 13440 in the earlier sample);
- `/details/` page links (59 Khotab items, 1 Variety mirror, 1 Fatwa answer: HTML, refused);
- files corrupt at the source (F05: e.g. mirrors 369, 320, 396, 371, 328, 375, which download but cannot play);
- dead third-party hosts;
- Variety items 9075 / 9826 (repaired URL 404).

**F04 status: CODE COMPLETE LOCALLY — NOT DEPLOYED.** The production prerequisites of §6.5 are unchanged.

