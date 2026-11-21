# AdminCP B7a: browser download validation («حفظ المادة»)

**Date:** 2026-11-14.

**Approval:** owner approval "B1 + B7a". This is B7a, validation only.

**Plan:** `docs/reports/admincp-feedback-implementation-plan.md` §F04.

**Status:** **VALIDATION COMPLETE (LOCAL). B7b NOT IMPLEMENTED.**

- No production download behaviour, endpoint, counter, telemetry or capacity setting was created or changed.
- `DownloadCapacityGuard`, `ProxiedDownloadStreamer`, slot configuration and Khotab streaming are untouched.
- Nothing was committed, pushed or deployed.
- The prototype exists only inside the lab page `tools/download-browser-lab/page.html`.

---

## 1. Verdict

| Question | Answer |
|---|---|
| Does the proposed client-side save (`fetch` → Blob → `<a download>`) work when the source sends CORS? | **Yes.** In Chromium 130, Firefox 131 and WebKit 18, for MP3 and MP4, small, 50 MiB and 400 MiB: correct filename, byte-exact (SHA-256 / md5). |
| Does it work for the reported case, **archive.org video (MP4)**? | **No.** archive.org storage nodes send **no `Access-Control-Allow-Origin` for `video/mp4`**, so the browser blocks the fetch. The prototype falls back to today's link, and **the player opens**. That is a fallback, not a forced download. |
| archive.org audio (MP3)? | **Yes.** The storage nodes send CORS for `audio/mpeg`; real archive.org MP3 saved byte-exact in all three browsers. |
| Is playback affected? | **No.** The play button behaves identically with and without the save module, and a save never paused or navigated a playing player (§5). |
| Memory? | The whole file is held in the browser during the transfer: about **2×** the file size in Chromium, **3.5–4×** in WebKit, **5–6×** in Firefox at the peak (§4). 400 MiB in Firefox peaked at about **2.6 GB**. |

**Consequence for F04.** Option A cannot satisfy the requirement for archive.org MP4. Measured on the site's own data,
**0 of 36** sampled MP4 links allow it. Option A would fix only MP3, which also plays inline today.

The remaining routes to a guaranteed save for archive.org video are **server-side**:

- option B, the proxy, with the capacity cost in plan §F04.3;
- or a host or CDN in front of the media that adds `Content-Disposition`.

These are owner decisions and outside B7a. **No B7b work should start on option A as written.**

---

## 2. Environment and method

| Item | Value |
|---|---|
| Browsers | Playwright 1.48.2: **Chromium 130.0.6723.31**, **Firefox 131.0**, **WebKit 18.0** (headless) |
| Where | official image `mcr.microsoft.com/playwright:v1.48.2-jammy` in a disposable container (WebKit's system libraries are not on the host, and nothing was installed on the host); 3.5 GB memory cap; `/work` and `/tmp` on tmpfs; removed after the run |
| Page origin | `http://127.0.0.1:8000`: lab page plus `/route?to=` (a 302, like `var-download` / `recite-download` today) |
| Media origins | `:8001` with `Access-Control-Allow-Origin: *`; `:8002` without CORS. Both send `Content-Type` `audio/mpeg` / `video/mp4`, `Content-Length` and `Accept-Ranges`, and **no `Content-Disposition`** (like archive.org) |
| Fixtures | `small.mp3` (960,932 B, real MP3, ffmpeg) and `small.mp4` (216,905 B, real H.264/AAC); 50 MiB and 400 MiB `.mp3`/`.mp4` deterministic streams (indexed 1 MiB blocks, so reordering, duplication or truncation changes the hash) |
| Real sources | archive.org `letter-to-saint-nicetius/Letter_to_Saint_Nicetius.mp3` (3,207,590 B, md5 `877197a6…`) and `firetruck_202511/Firetruck.ia.mp4` (2,931,539 B, md5 `4a382b83…`): public items, md5 from archive.org metadata |
| Prototype | `page.html`. The button keeps today's `href` (the route). With `data-direct-url` the click is intercepted: `fetch(mode: cors, credentials: omit, cache: no-store)`, stream into chunks, check `received == Content-Length`, then Blob → `URL.createObjectURL` → `<a download>`, revoked after 30 s. On fetch or HTTP error, or over the cap: **fall back to `href`**. On abort or stream error: report failure and save nothing |
| Integrity | every saved file is hashed (SHA-256 for local, md5 for archive.org) and its size and suggested filename checked |
| Memory | PSS summed over all browser processes, sampled every 150 ms (base before the click, peak, and 3 s after the outcome), plus the container cgroup `memory.current` peak (includes the saved file on tmpfs) |

**Outcome classes.** These are kept distinct, as required:

| Class | Meaning |
|---|---|
| `SUCCESS_FORCED_DOWNLOAD` | the prototype's blob download fired; name, size and hash exact |
| `OPENED_MEDIA_PLAYER` | the browser navigated to the media and rendered it inline (today's behaviour) |
| `FALLBACK_EXTERNAL_URL` | the prototype gave up and sent the browser to today's link. The detail says what happened then. **Never counted as success** |
| `FAILED` | no file and no navigation (cancelled, interrupted, short) |
| `NATIVE_DOWNLOAD` | a download not attributable to the prototype |
| `DOWNLOAD_CORRUPT` | a download fired, but the name, size or hash is wrong |

---

## 3. Results matrix

`✅` = matched the expectation. Columns are Chromium / Firefox / WebKit.

| ID | Scenario | Expected | Chromium | Firefox | WebKit |
|---|---|---|---|---|---|
| B1 | **today:** button → 302 → CORS `small.mp3` | player | ✅ OPENED_MEDIA_PLAYER | ✅ OPENED_MEDIA_PLAYER | ✅ OPENED_MEDIA_PLAYER |
| B2 | **today:** → `small.mp4` | player | ✅ player | ✅ player | ✅ player |
| J1 | prototype, CORS `small.mp3` | forced download | ✅ exact 960,932 B | ✅ exact | ✅ exact |
| J2 | prototype, CORS `small.mp4` | forced download | ✅ exact 216,905 B | ✅ exact | ✅ exact |
| J3 | prototype, CORS 50 MiB `.mp3` | forced download | ✅ exact 52,428,800 B (0.65 s) | ✅ exact (0.96 s) | ✅ exact (0.67 s) |
| J4 | prototype, CORS 50 MiB `.mp4` | forced download | ✅ exact (0.64 s) | ✅ exact (0.94 s) | ✅ exact (0.69 s) |
| J5 | prototype, CORS 400 MiB `.mp3`, no cap | forced download | ✅ exact 419,430,400 B (4.1 s) | ✅ exact (8.6 s) | ✅ exact (4.6 s) |
| J6 | prototype, CORS 400 MiB `.mp4`, no cap | forced download | ✅ exact (3.8 s) | ⚠ exact file, classified NATIVE_DOWNLOAD in the shared-browser run (memory cap, below); **✅ SUCCESS in an isolated rerun** (11.0 s) | ✅ exact (4.6 s) |
| J7 | prototype, 400 MiB `.mp4`, cap 300 MiB | fallback | ✅ FALLBACK → player | ✅ FALLBACK → player | ✅ FALLBACK → player |
| N1 | prototype, **no-CORS** `small.mp3` | fallback | ✅ FALLBACK → player | ✅ FALLBACK → player | ✅ FALLBACK → player |
| N2 | prototype, **no-CORS** `small.mp4` | fallback | ✅ FALLBACK → player | ✅ FALLBACK → player | ✅ FALLBACK → player |
| C1 | prototype, cancel a 400 MiB `.mp4` (20 MiB/s) after 2 s | failed, no file | ✅ cancelled, no file | ✅ cancelled, no file | ✅ cancelled, no file |
| I1 | prototype, source cuts a 50 MiB `.mp4` at 20 MiB (Content-Length promised 50) | failed, no file | ✅ interrupted, no file | ✅ interrupted, no file | ✅ interrupted, no file |
| A1 | **today:** → archive.org `.mp3` | player | ✅ player | ✅ player | ✅ player |
| A2 | prototype, **archive.org `.mp3`** | forced download | ✅ exact, md5 match (8.7 s) | ✅ exact (4.8 s) | ✅ exact (6.3 s) |
| A3 | **today:** → archive.org `.mp4` | player | ✅ player | ✅ player | ✅ player |
| A4 | prototype, **archive.org `.mp4`** | forced download (the goal) | ❌ **FALLBACK → player** (CORS blocked) | ❌ **FALLBACK → player** | ❌ **FALLBACK → player** |

**Totals:** 51 scenario runs. 47 met expectations; 3 are the A4 finding; 1 is the Firefox J6 shared-run artefact,
resolved by the isolated rerun.

**Firefox J6, the shared-run artefact.**

- J6 ran in the same Firefox instance right after J5, whose 400 MiB was still held (base PSS 1,186 MiB at J6's start).
- J6 peaked at 3,316 MiB PSS, and the container hit its **3,500 MiB cap**.
- The page's status could not be read within the harness's 1 s, so the runner could not attribute the download to the
  prototype and conservatively labelled it NATIVE_DOWNLOAD.
- **The saved file was byte-exact.**
- **Isolated rerun** (fresh browser per scenario, `results-2026-11-14/firefox-large-fresh-browser.json`): J3, J4, J5
  and J6 all `SUCCESS_FORCED_DOWNLOAD`, exact.

This is recorded as a **real memory-pressure risk** (§4), not hidden as a pass.

### 3.1 The archive.org CORS finding

| Probe (2026-11-14, `Origin: http://127.0.0.1:8000`, 1-byte range GET) | `/download/` 302 has ACAO | Final storage-node response has ACAO |
|---|---|---|
| Way2Allah sample: 40 random visible archive.org links from the local 2025 copy (25 Khotab, 15 Anasheed), normalised like `ArchiveOrgLink` | 40/40 | **MP3: 4/4. MP4: 0/36** |
| Same sample, second run | — | identical, file for file (40/40 stable) |
| Unrelated public items | — | MP3: 4/4; MP4: 0/4 |

All 40 links were reachable (206).

The `ACAO: *` that the plan observed came from the `/download/` redirect and from an MP3. **For video the storage node
omits it.** This is archive.org's behaviour today. It is outside Way2Allah's control and could change either way
without notice.

## 4. Memory observations

PSS of the browser's processes (MiB). Base is before the click; peak is during the transfer; "after" is 3 s after the
outcome, with the blob URL still alive (it is revoked after 30 s by design).

| Size | Browser | Base | Peak | Peak − base | After | Container peak¹ |
|---|---|---|---|---|---|---|
| small (≤ 1 MiB) | Chromium / Firefox / WebKit | 233 / 369–376 / 237 | +7–11 / +11–15 / +10–16 | negligible | ≈ base | — |
| 50 MiB | Chromium | 234 | 344 | **+110** (≈ 2.2×) | 342 | 583–636 |
| 50 MiB | Firefox (isolated) | 310 | 683–697 | **+372–387** (≈ 7.5×) | 665 | 1,157–1,170 |
| 50 MiB | WebKit | 236 | 694–734 | **+458–498** (≈ 9–10×) | 291 | 1,128–1,166 |
| 400 MiB | Chromium | 234 | 1,048–1,049 | **+815** (≈ 2×) | 1,046 | 1,665–1,684 |
| 400 MiB | Firefox (isolated) | 313–316 | 2,268–2,610 | **+1,952–2,297** (≈ 5–6×) | 1,145 | 2,742–3,236 |
| 400 MiB | WebKit | 236 | 1,684–1,836 | **+1,448–1,600** (≈ 3.5–4×) | 867–1,041 | 2,260–2,276 |

¹ The container cgroup, including the saved file on tmpfs (about 1× the file size) and the fixture server.

**Observations:**

- The prototype holds the **entire file in memory** for the whole transfer, then copies it into a Blob. Peaks are
  2–10× the file size depending on the engine.
- Memory is **not returned immediately**. About 1× the file stays referenced until the blob URL is revoked (30 s here).
- Sequential large saves in one tab **accumulate** (the Firefox J5 → J6 case).
- **Local transfer times** (loopback, effectively unlimited bandwidth): 400 MiB in 3.8–14 s. On a real connection the
  memory is held for the whole network transfer: about 14 min for 400 MiB at 4 Mbit/s.
- **Implication:** the plan's proposed 300 MiB cap is too high for low-memory phones. A 50–100 MiB cap would be the
  prudent ceiling **if** option A were pursued at all for MP3.
- Mobile browsers were **not tested** (UNVERIFIED). Headless desktop engines are not a proxy for iOS Safari or Android
  memory limits.

## 5. Playback unaffected

The play button builds the player element the same way the app does (an `<audio>`/`<video>` with the media URL). Each
case ran twice, without and with the save module. In the "with" run, «حفظ المادة» was then clicked while playing.

| Browser | Source | Without save module | With save module | After clicking save during playback |
|---|---|---|---|---|
| Chromium | archive.org MP3 | `<audio>`, plays | `<audio>`, plays | saved; **still playing** (t 1.7 → 11.3 s); page unchanged |
| Chromium | local MP3 | plays (t 4.9 s) | plays (t 4.9 s) | saved; still playing (→ 6.5 s) |
| Chromium | local MP4 (H.264) | `NotSupportedError`² | `NotSupportedError`² (identical) | saved; player state unchanged |
| Firefox | archive.org MP3 | plays | plays | saved; still playing (→ 9.4 s) |
| Firefox | local MP3 / MP4 | plays / plays | plays / plays | saved; still playing |
| WebKit | archive.org MP3 | `<audio>` created, t = 0 at 5 s³ | identical | saved; state identical; page unchanged |
| WebKit | local MP3 / MP4 | plays / plays | plays / plays | saved; still playing |

² Playwright's Chromium build ships without proprietary codecs (H.264/AAC); `canPlayType` returns `""`. This is a lab
limitation; Google Chrome plays H.264. It is identical with and without the module.

³ WebKit had not started the remote MP3 within the 5 s window. There was no error and it was not paused, identically
in both modes. Not attributable to the save module.

**Conclusion:** the save module does not alter playback in any engine.

## 6. What was not verified

| Item | Why |
|---|---|
| iOS Safari, Android Chrome, desktop Safari and Chrome (branded builds) | headless engines only; mobile memory limits and download UX are unverified |
| Download through a CDN or proxy in front of archive.org | none exists for Way2Allah today |
| Long real-world transfers (minutes) and backgrounded-tab behaviour | the local transfers were seconds; archive.org files were 3 MB |
| A source without `Content-Length` (chunked) | the prototype could not detect truncation for such a source. Not exercised; archive.org sends `Content-Length` |
| Range or resume | the prototype has none. An interrupted save restarts from zero |
| Non-archive hosts (Telawah third parties, `vars.way2allah.com`) | their CORS behaviour was not probed; such sources take the no-CORS path (N1/N2: fallback, player) |
| Any change in archive.org CORS policy | it could change at any time; the result is dated 2026-11-14 |

## 7. Files

**Lab** (new; disposable; not application code): `tools/download-browser-lab/`

- `README.md`, `server.mjs`, `page.html`, `run.mjs`, `run.sh`, `archive-files.json`, `archive_cors_probe.py`;
- `results-2026-11-14/matrix-all-browsers.json`, `firefox-large-fresh-browser.json`, `fixture-manifest.json`,
  `archive-cors-sample.json` (40-link sample results; URLs omitted).

**Plan:** `docs/reports/admincp-feedback-implementation-plan.md` §F04.1 now carries a dated correction (archive.org
MP4 has no CORS) that supersedes its earlier CORS statement for video.

**First Chromium attempt:** stopped by me. The runner's post-navigation wait was not bounded (a harness bug, fixed by
a 3 s bound and a per-scenario deadline). Its 16 completed scenarios matched the final run; the file was not kept.

## 8. Environment side effects (disclosed)

- **Docker:**
  - pulled `mcr.microsoft.com/playwright:v1.48.2-jammy` (about 2 GB), **still present** for reproducibility. Remove it
    with `docker rmi mcr.microsoft.com/playwright:v1.48.2-jammy`;
  - the lab container `w2a-b7a-lab` was created and **removed**;
  - none of the existing containers were touched.
- **Host:**
  - Playwright browser downloads to `~/.cache/ms-playwright` (this session) were **removed** after they helped fill the
    nearly full disk. The pre-existing entries were kept;
  - `playwright@1.48.2` npm package in the session scratchpad only; the project's `package.json` is unchanged.
- **Disk:** the root filesystem is at **99 % (about 1.6 GB free)**. It was about 98 % before this session.
- **Network:**
  - anonymous requests to archive.org: public metadata and search;
  - 1-byte range probes of 40 public site-linked files (twice) and 8 unrelated files;
  - full downloads of the two small test files, per browser.

  No credentials or personal data were sent.

## 9. Confirmations

- **No production changes**, and no production access.
- **No commits, pushes or deployments.**
- **Existing download infrastructure unchanged.** No new endpoint, no counter change, no telemetry change; the guard,
  streamer, slots and Khotab streaming are untouched.
- **B7b is not implemented.** No application view, JS or route was changed for F04.

## 10. Decisions now needed from the owner (F04)

| # | Decision | Notes |
|---|---|---|
| 1 | Retire option A as the F04 solution | it cannot save archive.org MP4s. Optionally keep it for MP3 only, with a ≤ 100 MiB cap; MP3 is a minor share of the complaint |
| 2 | Choose a server-side route for video, or accept today's behaviour plus a clearer hint | **Option B** (proxy through the capacity-guarded streamer) needs the arrival-rate evidence and capacity approval in plan §F04.3; the 400-slot limit stays untouched unless separately approved. **A media host or CDN** that adds `Content-Disposition` is infrastructure outside this repo |
| 3 | If B is ever considered | measure real per-module download rates first (plan E7) |
