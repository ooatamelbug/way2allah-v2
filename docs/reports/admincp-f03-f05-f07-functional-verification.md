# F03, F05, F07: functional verification on real local data

**Date:** 2026-11-17. **Verification only. No application code changed.**

## Environment

- **Database:** `fv_disp`, a full copy of local `olddb` (240 tables, checksum-identical), on `mysql57`, with a
  dedicated restricted user. Dropped afterwards.
- **Repairs:** run **only** there.
- **Shared database:** shared `olddb` was only read. Its checksums and `admin_audit_log` (57 rows) are unchanged.
- **Laravel:** served by `php artisan serve` on 127.0.0.1, with `main` pointed at `fv_disp`, a scratch SQLite for the
  default connection, and cookie sessions. Real public pages and the real `POST /media-player` with a page CSRF token
  were used.
- **Media checks:**
  - read-only range requests of the first 1–4 KB against archive.org (no whole file downloaded);
  - `ffprobe` (headers only);
  - **headless Google Chrome 148** driven over the DevTools protocol: load, then muted play for about 4 s; it records
    decoded video size, time advance or `MediaError`.
  - `*.way2allah.com` hosts were **not contacted** (project rule), so they are marked unverified.

---

## F03: series not visible

**Records:**
- **All 22 approved series** were checked: 15346, 15404, 15495, 15496, 15498, 15503, 15509, 15510, 15561, 15616,
  15625, 15627, 15681, 15743, 15746, 15752, 15756, 15765, 15766, 15884, 15949, 15950.
- **Representatives traced in detail:** 15495 and 15616 (group 1138), 15627 (group 232), 15766 (group 1298), 15346
  (author 215, video, standalone), 15884 (author 22, audio, standalone).
- **Control:** series 17008 in group 1138.

**Relationships:** all 22 have `hidden = 0`, `count` ≤ 0 (0 to −39), and their own visible items present, matching the
baseline exactly (316 in total).

| | Before repair | After repair (disposable copy) |
|---|---|---|
| Group / author page lists the series | **no** for all 6 representatives (the page itself is 200) | **yes** for **22 / 22** |
| Series page | 200; links all own items (30, 15, 24, 3, 25, 1) | 200; links all own items for 22 / 22 |
| Stored `count` | 0 / −15 / 0 / −3 / −1 / −32 … | equals the visible own items (30, 15, 24, 3, 25, 1 …) |
| Control 17008 listed | yes | yes |

**Unrelated data:**
- The other 14,530 series are byte-identical to shared `olddb` (aggregate CRC over all columns).
- `nuke_islamic_khotab` checksum is unchanged.
- Visible series with `count` ≤ 0 went from 377 to 355: exactly −22.

**Resolved locally: yes.** The defect is reproduced on real data, and the approved repair fixes it with no side effect.

**Remaining:**
- The owner runs the repair in production.
- The other 355 visible series with `count` ≤ 0 are outside the approved scope (B2 analysis: mostly other authors'
  items and copies).
- The originally reported series id is still unknown.

---

## F05: medium-quality video

**Population:** 2,034 Khotab mirrors labelled «متوسط».
- RMVB 1,456; WMV 254; MP4 117 (100 of them flagged audio under a video item); RM 71; WMA 28; MP3 17; AVI 15; FLV 5;
  `.rv` 4; MPG 1.
- Almost all on archive.org; a few third-party, malformed-host or empty links.

**Sampled:**
- **one real mirror per class** (22): mirrors 5, 12, 13, 62, 141, 175, 198, 237, 252, 255, 262, 291, 442, 4094, 6872,
  19849, 21387, 21904, 30995, 79104, 111152, 164929;
- **20 random medium MP4 mirrors:** 834, 469, 430, 530, 369, 320, 479, 165864, 179, 520, 420, 396, 371, 328, 192,
  165835, 375, 459, 165348, 404.

**Laravel player output (real endpoint):**

| Class | Player | Mirrors |
|---|---|---|
| RMVB / WMV / RM / WMA / AVI / FLV / MPG | F05-2 notice + «تحميل المادة» | 5, 13, 62, 141, 198, 252, 442, 4094, 6872, 19849, 21904, 30995, 79104, 111152 |
| MP4 (incl. the 17 audio-flagged ones in the random set) | `<video>` with the normalised `https://archive.org/download/…` URL | 12, 164929, 834, 469, 430, 530, 369, 320, 479, 165864, 179, 520, 420, 396, 371, 328, 192, 165835, 375, 459, 165348, 404 |
| MP3 | `<audio>` | 291, 21387 |
| `.rv` (RealVideo) | empty response → the page's generic «هذه المادة غير متاحة للتشغيل حالياً…» (`.rv` is not in the approved unsupported list) | 175, 237 |
| Empty stored link | empty response → the same generic message | 255, 262 |

**Real sources (range requests) and real browser (Chrome 148):**

| Outcome | Mirrors | Cause |
|---|---|---|
| **Plays with picture** (H.264 Constrained Baseline + AAC) | 164929 (380×240), 165864 (640×480), 165835 (640×480), 165348 (576×360) | valid |
| **Plays sound only, no picture** (time advances, decoded width 0): MPEG-4 Part 2 Simple Profile 160×120 + AAC | 12, 834, 469, 430, 530, 479, 179, 520, 420, 192, 459, 404 (**12 of 21 MP4s**) | **browser limitation of the encoded files**: browsers do not decode MPEG-4 Part 2 video. Likely the reported "medium quality does not play" |
| **Fails**: `MediaError 4`; the file's first 4 KB are zeros; ffprobe "Invalid data" | 369, 320, 396, 371, 328, 375 | **broken files at the source** (archive.org) |
| MP3 plays | 291, 21387 | valid |

Non-browser formats are a browser limitation by design; they show the notice.

**Defect found (reproducible, code; reported for approval, NOT fixed):** **Khotab download routes stream the RAW
stored link.**
- **What happens:**
  - `/khotab-download-*` and `/khotab-mirror-*` (and, since F04, `/recite-download-*` and `/lesson-download-*`) pass
    the stored link to the proxy without `ArchiveOrgLink::normalize()`. The player (F05-1), Variety and Fatwa do
    normalise.
  - Links pinned to retired archive.org storage nodes (`ia…us.archive.org/N/items/…`) answer **500 HTML**, so the
    route returns Laravel's 500 page.
- **Reproduced:** `GET /khotab-mirror-2432-5.htm` → 500. The same file normalised →
  `https://archive.org/download/al-hekmah-tv-1/hakekah-1.WMV` → 206 `video/x-ms-wmv`. Also mirror 6872.
- **Sample of 30 random pinned mirrors:**
  - **26 fail as stored**;
  - **25 of those work when normalised**;
  - 1 fails both ways (13440);
  - 4 work raw.
- **Exposure (local copy):**
  - 10,973 visible Khotab mirrors and 3,868 visible items are pinned (about 87 % failure by the sample);
  - 131 Telawah items and 208 lesson items are pinned.
- **Effect:** for most pinned links, «حفظ المادة» and the F05-2 notice's «تحميل المادة» fail, although the player
  works. **This corrects the F04 statement that "Khotab already downloads".**
- **Proposed fix (needs approval):** apply `ArchiveOrgLink::normalize()` (and `http:` → `https:` for archive.org) to
  the link handed to the proxy in `KhotabItemController::download` and `downloadMirror`, `TelawahItemController` and
  `ChatRoomLessonController`, as Variety and the player already do. Stored data is unchanged; tests to be added.

**Resolved locally: partly.**
- The code paths behave as designed: valid H.264 / MP3 play; non-browser formats get the notice; the F05-2 MP4-as-video
  rule works.
- **The likely reported symptom remains for MPEG-4 Part 2 files** (sound without picture): a source-encoding
  limitation that code cannot fix.
- Separately, the download fallback is broken for pinned links (defect above).

**Remaining (owner decisions):**
1. Approve the download-normalisation fix.
2. MPEG-4 Part 2 MP4s: re-encode or replace the files, **or** treat them like unsupported formats (notice + download).
   Detecting them needs per-file codec data (for example archive.org's file metadata); it cannot be inferred from the
   URL.
3. The 6 zero-filled source files need replacing.
4. Optional: add `.rv` to the unsupported-format list (4 medium, 6 total mirrors).
5. 90 empty-link mirrors and 23 empty-link items are data.

---

## F07: Variety video

**The 46 manifest records:**
- items 4860, 4868, 4869 and others (35 items in total, including 9870–9877, 9955, 9075, 9826, 14823);
- mirrors 15966, 15969, 15970, 15971, 15978, 15979, 15981, 15986, 15987, 15997 (SoundCloud) and 10659.

| | Before repair | After repair (disposable copy) |
|---|---|---|
| 35 items | format notice "no download available"; «حفظ المادة» hidden; the download route redirects into a broken site path (`/download/heekmaa702/…`, `http://site/ https://vars…`, `/Ø±https://…`) | «حفظ المادة» shown on **35 / 35**; the download route reaches the protected proxy (`HEAD` 200 attachment); 34 are WMV / RMVB (format notice with a download link); **14823** now gets a video player |
| 10 SoundCloud mirrors | player iframe; the download redirects into a broken site URL (leading space) | the download redirects to the correct SoundCloud page |
| Mirror 10659 (duplicated URL) | format notice; the route proxied the malformed URL | the corrected AVI URL; format notice with a download link |

**Real sources after repair:**

| Result | Records |
|---|---|
| archive.org 206 | 25 items, mirror 10659 |
| SoundCloud pages 200 | 10 |
| **archive.org 404** (the repaired URL has no file) | **items 9075 and 9826** |
| **not contacted** (`vars.way2allah.com`, including the 14823 player source, served over **`http://`**) | 8 items |

**Unrelated data:** all other Variety items and mirrors are byte-identical to shared `olddb` (link, visibility,
group).

**The 221 remaining records** (inventory confidence classes; not repaired; current behaviour sampled on 17 real
records):

| Class | Records sampled | Current behaviour |
|---|---|---|
| 173 MEDIUM host-less `/cartoon/…` (`vars` host inferred) | 8686, 8687 | format notice, no download; save hidden; the route redirects into the site. **No file reachable** |
| 11 HIGH space-only (not in the manifest) | 1921, 1427, mirror 116 | **already work** (download proxied; the save button shows) |
| LOW truncated / empty / invalid / title text | 8818, 6485, mirrors 10848, 156, 11604, 18732 | invalid-link or format notice; no download |
| MEDIUM concatenated / bare file name | 16755, mirror 9311 | invalid-link or format notice; no download |
| LOW malformed but absolute URLs | 17351 (Google search URL), 6270 (two URLs joined by a comma), 8120 (doubled folder), mirror 10255 (`http://va…`) | **«حفظ المادة» is shown and the proxied GET would fail with a 500** (since F04: an absolute URL is proxyable); the player shows a notice or the generic message |

**Not repaired** (no ambiguous record was touched).

**Resolved locally: yes for the 46 approved records**, except:
- items **9075 and 9826**, whose repaired archive.org URL returns 404;
- the 8 `vars` items, which are unverified.

The original complaint's record is still unidentified. Whether a `vars` file actually plays is **unverified**.

**Remaining:**
1. The owner runs the 46-row repair in production, and decides on 9075 / 9826 (find the right file, or hide them).
2. Decide the 173 `vars` host-less items. Verifying them needs a request to `vars.way2allah.com`, which needs owner
   permission.
3. Decide the LOW records. Optional: stop offering «حفظ المادة» for absolute-but-malformed links (a small UI rule,
   needs approval).
4. Optional: upgrade `http://vars.way2allah.com` player sources to `https` if that host serves https (unverified).

---

## Not verified

- Anything on `*.way2allah.com`.
- SoundCloud widget playback (third-party iframe).
- Browsers other than Chrome 148 (Safari / Firefox codec support for MPEG-4 Part 2 is likewise absent, by public
  documentation, but was not tested here).
- Production data, which may differ from the 2025 local copy.
