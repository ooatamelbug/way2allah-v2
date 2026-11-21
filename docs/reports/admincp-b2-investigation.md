# AdminCP B2 investigation: F03, F05-2, F07

**Date:** 2026-11-16.

**Request:** owner, "AdminCP Feedback B2 Investigation — Investigation Only".

**Status:** **INVESTIGATION COMPLETE. NOTHING IMPLEMENTED.** Awaiting owner approval.

- No application code, schema or data change. No commit, push or deployment. The full suite was not run.
- `7amlat` and `old-project` were read only. The local MySQL copy (`olddb`, data to 2026) was read only.
- **Network disclosure:**
  - read-only `HEAD` requests (no media downloaded) were sent to four public **archive.org** URLs;
  - **and two to `vars.way2allah.com`** (the Variety media host, §3.4). The latter is a Way2Allah host; no further
    requests were made to any Way2Allah host once this was noticed.
  - No production database or admin access.
- **Sources read:** `docs/reports/admincp-production-feedback-investigation.md` (§F03, §F05, §F07, §7), plan §F03,
  §F05, §F07, decision-log #87 / #140 (F05-1 normalisation, implemented in B1).

| Feedback | Classification |
|---|---|
| F03 new series not visible | **PARTIALLY CONFIRMED.** The defect class is reproduced locally, with 45 recent series. The reported series id is still unknown |
| F05-2 medium quality | **CONFIRMED — READY FOR FIX** (mapping part); the non-browser-format part needs the owner's wording |
| F07 Variety playback | **BLOCKED — NEEDS OWNER EXAMPLE** (several concrete data defects found; which one was reported is unknown) |

---

## 1. F03 — newly created Khotab series not visible

### 1.1 Original requirement

"A newly created Khotab series is not visible in Laravel" (production feedback F03).

### 1.2 Current implementation (CONFIRMED)

| Surface | Code | Visitor rule | Admin rule |
|---|---|---|---|
| Group page `khotab-group-{id}` | `KhotabGroupController::show()` :36 → `ContentListingService::seriesByAuthorAndGroup($author, $group, $video)` (no admin flag) | `author_id, group_id, vedio` match, **`count > 0`**, `ser.hidden = 0`, `grp.hidden = 0`, `ORDER BY lastupdate DESC`, no pagination | **the same** (admins see no empty or hidden series here) |
| Author page, standalone series (`group_id = 0`) | `KhotabAuthorController` :252 / :257 | `seriesByAuthorAndGroup($author, 0, …)` | `adminStandaloneSeriesByAuthor()`: hidden **and empty** included |
| Category pages | `seriesByCategoryAndGroup()` | `series_category_index` join, `count > 0`, `hidden = 0` | same |
| Series page `khotab-series-{id}` | `KhotabSeriesController::show()` :53–57 → `khotabItemsDefault($series->author_id, $id, $series->group_id, $series->vedio)` | 404 if hidden; items must match the series' **author, group and vedio** | hidden series and items shown |

**Legacy comparison (CONFIRMED):**
- **OLD `ListSeries()`** (`old-project/modules/Khotab/index.php:519-527`):
  - admins see **every** series of the author and group (hidden and empty);
  - visitors see `hidden = 0 AND count > 0`;
  - **no group-hidden check.**
- **Gen 2 `ListSeries()`** (`7amlat/khotab/functions.php:409-429`): identical to Laravel, including `grp.hidden = 0`.
- **Item lists:** OLD `ListKhotab()` (`:619-629`) and Gen 2 `ListKhotab()` (`:511+`) filter items by
  `author, vedio, ser_id, group_id`, exactly as Laravel `khotabItemsDefault()` does.

**Counter maintenance (CONFIRMED):**
- **OLD:**
  - `ConfirmInsertKhotab` :1110 adds 1;
  - move :1337/:1340 moves −1/+1;
  - detach :1515 subtracts 1;
  - delete :1764 subtracts 1;
  - `RepairCount()` :3790–3816 sets `count` = all rows with `ser_id`, hidden included, and propagates `group_id`
    onto items.
- **Laravel:** `MoveKhotabItemAction`, `DetachKhotabItemAction`, `DeleteKhotabItemAction` and the insert actions
  maintain the counter. `RepairKhotabAuthorCountersAction` / `RepairAllKhotabAuthorCountersAction` («إصلاح العدادات»,
  `khotab.editauthor`) recompute it as OLD did.
- **Gen 2:** no series writer (its admincp `uploader*.php` only reads).
- **Triggers present locally:** `trg_khotab_after_*` and `trg_series_after_*` (category indexes only).

### 1.3 Local database findings (14,552 series)

| Finding | Count | Effect on visitors (all three systems) |
|---|---|---|
| **A. `count = 0` but the series has visible items** | **45, all created 2025** | **invisible** in group, author and category lists (`count > 0`), although its page and items exist |
| B. Truly empty series (no items) | 349 (233 in a group, 139 standalone; 8 in groups created since 2025-01) | not listed (expected). **Admins cannot see the in-group ones on the group page** (OLD admins could) |
| C. `count > 0` but **no visible items** | 25 | listed; opening shows an empty item list |
| D. Visible items excluded from their own series page (item author, group or vedio ≠ series) | 554 visible series; 7,782 author / 4,042 group / 2,223 vedio item mismatches | items missing from the series page; **the same filter in OLD and Gen 2** (inherited) |
| E. Series vedio contradicts all its items | 7 (15368, 15374, 15544, 15546, 15897, 15918, 15919) | listed on the wrong video/audio side and its page shows no items |
| F. Visible, counted series inside a hidden group | 0 | — |
| `count` ≠ actual row count | 1,001 | display drift (the count shown on cards) |

### 1.4 Reproducible examples

| Series | Expected | Actual | Evidence | Path |
|---|---|---|---|---|
| **15627** (author 154, group 232, video) | listed on `khotab-group-232` (24 visible items) | **not listed** for anyone | `count = 0`, `hidden = 0`. 24 items `ser_id = 15627`, added 2025-04-24 → 2025-05-21 by admin `farog` (`nuke_islamic_logs` `add_item`, ids 8533+) | `seriesByAuthorAndGroup()` `count > 0` |
| **15949** (154 / group 1138, video) | listed (13 items) | not listed | `count = 0`, created 2025-08-27 | same |
| **15916–15920** (154 / group 1317, audio) | listed (11, 7, 2, 25 and 5 items) | not listed | `count = 0`, created 2025-08-18. **15918 and 15919 are flagged audio while their items are video**, so even with a correct count their page shows no items (E) | same + `khotabItemsDefault()` vedio filter |
| **15956** (757, standalone, video) | listed on the author page (6 items) | not listed for visitors | `count = 0`, created 2025-08-28 | `seriesByAuthorAndGroup($a, 0, …)` |
| **16717–16723, 16673** (154 / group 232, created 2026-02) | admin sees new empty series on the group page (OLD) | not visible even to admins | no items yet, `count = 0` | group page passes no admin flag |
| **16087, 16088** (3 / group 1282) | — | listed (34 and 11) but **empty** | no items with that `ser_id` | `count > 0` |

### 1.5 Root cause

- **CONFIRMED mechanism (A):** a series is invisible whenever its stored `count` is 0, whatever its items. All 45
  local cases were created in 2025, which matches "newly created".
- **NOT CONFIRMED:** *why* those counters are 0. Every insert, move, detach and delete path in OLD and Laravel
  maintains `count`, and OLD's repair sets it from the rows. The 2025 `add_item` log rows for 15627 come from OLD's
  logger, whose insert path adds 1. Unresolved hypotheses:
  - a later counter reset by a tool not in either tree;
  - direct SQL;
  - an OLD path not present in the local copy.
- **CONFIRMED code difference (B):** admins cannot see empty or hidden series on the group page (OLD could). This
  matches "an admin creates a series and cannot find it".
- Whether the owner's reported series is an A or a B case is **unknown**: no id was supplied.

### 1.6 Recommended fix (two independent parts; owner choice)

1. **A, data:** run the existing per-author «إصلاح العدادات» for the **21** affected authors (5, 78, 103, 120, 133,
   154, 217, 295, 509, 512, 589, 605, 629, 630, 643, 659, 660, 663, 727, 757, 769).
   - **No code is needed.**
   - It also rewrites those authors' other series and group counts, and propagates `group_id` onto items (OLD
     semantics). The full sweep would touch 1,001 counters.
   - **Alternative, code:** list series by the existence of a visible item instead of `count > 0`. This deviates from
     all legacy systems and adds a per-list subquery (`ser_id` has no index locally). **Not recommended.**
2. **B, code:** on `khotab-group-{id}`, pass the admin flag (as the author page does for standalone series) so
   permitted admins see empty and hidden series marked «فارغة» / «مخفية», private response. Visitors are unchanged.
   This is plan §F03 H1 (⚑ which permission: `khotab.editauthor`, as the repair tool, or any admin as the Khotab
   precedent).
3. **C, D and E** are data quality (and D is legacy filter parity). Report only; a data correction needs separate
   approval.

### 1.7 Regression risks

- **B:** low; an admin-only branch, visitors untouched.
- **A by repair:** data change on shared tables (visible to Gen 2 / OLD too); count and group propagation on the
  affected authors' rows; audited.

### 1.8 Required tests

- **B:**
  - visitor group page unchanged (empty, hidden and count-0 series not listed);
  - permitted admin sees empty and hidden series with markers, response `no-store`;
  - a non-permitted admin sees the visitor view;
  - card order (`lastupdate DESC`) unchanged.
- **A (if code):** a series with `count = 0` and visible items is listed, and one with only hidden items is not.

### 1.9 Owner input needed

**YES:**
- the reported series id (to say whether it is A or B);
- approval of the repair run (A) and/or the admin view (B), and which permission.

---

## 2. F05-2 — medium video quality

### 2.1 Original requirement

"Medium-quality playback fails on `khotab-item-{id}`" (production feedback F05). F05-1, archive.org normalisation,
was implemented in B1.

### 2.2 Current implementation (CONFIRMED)

- **Qualities** are admin-entered **mirrors** (`nuke_islamic_mirror`: `comment` label, `link`, `vedio`). There is
  **no automatic discovery** of Archive.org derivatives; `ArchiveOrgMetadata` only reads size and duration.
- **Card:** `resources/views/components/content/media-quality-list.blade.php`. Every mirror gets a play button
  (`w2a_play(id, 'khotab_mirror')`); its icon and label (مشاهدة / استماع) come from `mirror.vedio`, and its download
  link is `khotab-mirror-{item}-{mirror}.htm`.
- **Player:** `MediaPlayerService::fromMirror()` returns `normalize(link)` and `video = mirror.vedio`.
  `renderPlayer()`:
  - YouTube when video;
  - `<audio>` for `.mp3`;
  - SoundCloud when audio;
  - **`<video>` only when `video && .mp4`**;
  - otherwise `null`.
- **Empty response:** the script (`media-player-script.blade.php` :76) shows «هذه المادة غير متاحة للتشغيل حالياً.
  يمكنك تجربة جودة أخرى أو إعادة المحاولة.» That is generic, and misleading for a format that can never play.
- **Download** (`khotab-mirror-…`) is unaffected.
- **Gen 2** (`7amlat/functions.php:870-895`, `w2a_mada_play()`): `vedio = 0` routes to the audio branch, which plays
  only `.mp3` / SoundCloud. So **Gen 2 fails identically** for `.mp4` mirrors flagged audio; for RMVB, WMV and AVI it
  emitted RealPlayer / WMP embeds, dead in modern browsers. OLD used the same mirror model.

### 2.3 Local database findings

**2,034 mirrors labelled «متوسط»** (none hidden):

| Format | `vedio = 1` | `vedio = 0` | Browser-playable in Laravel today? |
|---|---|---|---|
| `.rmvb` | 1,494 | 15 | no (format) |
| `.wmv` | 217 | 44 | no (format) |
| `.rm` / `.rv` | 36 | 41 | no (format) |
| `.avi` / `.flv` | 5 | 11 | no (format) |
| `.wma` | — | 28 | no (format) |
| **`.mp4`** | 18 | **100** | **only the 18**; the 100 flagged audio get the generic message |
| `.mp3` | 13 | 4 | yes (audio) |

- **Across all mirrors:** **601 `.mp4` mirrors have `vedio = 0`, and all 601 belong to video items.**
- **Labels:** the medium `.mp4` ones are mostly «للجوال بجودة متوسطة» (87), «جودة متوسطة للجوال», «جوال متوسطه»,
  and similar.
- **Hosts:** the medium `.mp4` links are mostly retired `ia3xxxxx.us.archive.org` nodes over http (41 + 20 + 9 + …).
  These are now normalised (B1).

### 2.4 Reproducible examples

| Item / mirror | Stored link | After normalisation | Archive.org (`HEAD`) | Laravel today |
|---|---|---|---|---|
| `khotab-item-4337`, mirror **834** «للجوال بجودة متوسطة», `vedio = 0` (item `vedio = 1`) | `http://ia360703.us.archive.org/11/items/Mirror1_way2allah/834_2_-rabt-gwal-4-mtwstt.mp4` | `https://archive.org/download/Mirror1_way2allah/834_2_…mp4` | **200 `video/mp4`** | «غير متاحة للتشغيل حالياً» |
| `khotab-item-3441`, mirror **564** | `http://ia331433…/islamicmedia1/564_2_….mp4` | `https://archive.org/download/islamicmedia1/564_2_….mp4` | **200 `video/mp4`** | same |
| `khotab-item-4282`, mirror **799** | `…/Mirror1_way2allah/799_2_-llgwal-bgwdt-mtwstt.mp4` | normalised | **200 `video/mp4`** | same |
| `khotab-item-106318`, mirror **158479** «متوسط» `.rmvb` | `https://archive.org/download/new___1_341/Maghamsy_2-1-2015.rmvb` | unchanged | 200 `audio/x-pn-realaudio` | same generic message; **no browser can play RMVB** |

### 2.5 Root cause (classified as the owner asked)

| Class | Finding |
|---|---|
| **Incorrect application mapping** — **CONFIRMED** | player type comes from the mirror's `vedio` flag; 601 `.mp4` mirrors of video items are flagged audio, so they get no `<video>`. The files exist (200 `video/mp4`) |
| **Playback incompatibility** — **CONFIRMED** | 1,895 medium mirrors are RMVB / WMV / RM / AVI / FLV / WMA; not playable in any browser |
| **External availability** — **mitigated** | retired storage-node URLs are normalised since B1; the sampled files answer 200. A file's codec (for example HEVC) cannot be verified without downloading |
| **Missing source media** | **not found** in the sample |

### 2.6 Recommended fix

1. **(Ready.)** In `MediaPlayerService::fromMirror()`, treat a `.mp4` mirror as video **when its parent item is a
   video item** (`item.vedio = 1`), whatever the mirror flag. The quality card's icon and label should follow the same
   rule.
   - Render-time only; the stored `vedio` is untouched.
   - This **deviates from Gen 2**, which fails the same way, so it needs a decision-log entry.
   - Scope: Khotab mirrors; Anasheed mirrors have no such mismatch locally.
2. **(Owner wording, plan decision 20.)** For non-browser formats, the endpoint returns a specific message: «هذه
   الجودة غير قابلة للتشغيل في المتصفح، يمكنك تحميلها» with the existing download link. Optionally the card shows
   «تحميل فقط» instead of a play button.
3. **No data change.** Optional later: an admin report of `.mp4` mirrors flagged audio.

### 2.7 Regression risks

Low. The player output changes only for `.mp4` mirrors of video items flagged audio (601 rows), and for the message
on non-browser formats. Audio items' `.mp4` mirrors stay as they are under (1).

### 2.8 Required tests

- mirror `.mp4` + `vedio = 0` + item video → `<video>` with the normalised https source;
- the same mirror under an **audio** item → unchanged;
- `.mp4` + `vedio = 1` → unchanged;
- `.mp3` → `<audio>`;
- `.rmvb` / `.wmv` → the specific message (2) and no player;
- hidden mirror or hidden item → refused, as before;
- the card shows the video icon and label for case (1);
- the download route is unchanged.

### 2.9 Owner input needed

- **Part (1):** no evidence is needed (data-confirmed); the owner approves the Gen 2 deviation.
- **Part (2):** the wording, and whether non-browser qualities show a play button at all.
- Confirmation of the reported item and mirror is still useful (plan §F05: do not claim *the reported* case fixed
  without it).

---

## 3. F07 — Variety (Anasheed) playback

### 3.1 Original requirement

"Variety video playback fails" (reported on a `khotab-group-{id}` page per the feedback; «منوعات» means Anasheed;
production investigation §F07).

### 3.2 Current implementation (CONFIRMED)

| Aspect | Behaviour |
|---|---|
| Player | `MediaPlayerService::fromAnasheedItem()` / `fromAnasheedMirror()`: hidden refused (B4); `link` → `ArchiveOrgLink::normalize()` (archive.org http and storage nodes, including `dn…ca.archive.org` → `https://archive.org/download/…`); `video = vedio`; then the same `renderPlayer()` as Khotab |
| Formats | `<video>` only for `.mp4` + video; `.mp3` audio; YouTube; nothing else |
| Non-archive hosts | **untouched**, `vars.way2allah.com` included (stored `http://`) |
| Relative or malformed links | passed through as-is; a host-less value becomes a URL relative to the Laravel page |
| Download | `var-download-{id}` redirects to `str_replace('http:', 'https:', normalize(link))` |
| Gen 2 | `w2a_mada_play()` by `vedio`, no normalisation; OLD `Play()` used `fix_archive_links()` |

### 3.3 Local database findings (visible Variety items with `vedio = 1`)

| Class | Items | Laravel player |
|---|---|---|
| Absolute `.mp4` | 8,174 | `<video>` (plays if the host serves it) |
| **Non-browser format** (WMV 2,718, RMVB 2,594, FLV 1,489, RM 929, AVI, RAM, …) | **7,571** | generic «غير متاحة» |
| Root-relative (`/cartoon/…`, `/download/…`), none `.mp4` | 202 | generic «غير متاحة» (non-`.mp4`). `/download/…` are host-less archive.org paths |
| **Malformed, no scheme or host** | **9** | broken: a relative URL on the Laravel host, then a load error |
| Empty link | 2 | «غير متاحة» |
| YouTube / `.mp3` | 2 / 28 | play |

**Hosts:** `vars.way2allah.com` holds 7,724 `http://` + 94 `https://` links; archive.org has about 7,300.

### 3.4 Reproducible examples

| Item | Stored link | Problem |
|---|---|---|
| **16755–16759** (group 1273, added 2023-10-08) | for example 16759 `new-new-alaser-3arog_x264.mp4new-new-alaser-3arog_x264.mp4`; 16757 `new-new-alaa-aldin_x264.mp4new-new-alalwan_x264.mp4` | **two file names concatenated, no host**, apparently shifted between rows: **data-entry error**. `<video>` is rendered (`.mp4` suffix) and fails, which matches "player appears but fails" |
| **15030** (group 405) | `watha2qia-rob3iat-aldaf3-24-5-2021_x264.mp4watha2qia-…epi2…mp4` | the same pattern |
| **12581** (1027), **12195** (999) | concatenated names (12195 `…27-8-2016.AVIwatha2qia-…_x264.mp4`) | the same |
| **14823** (group 1082) | `?http://vars.way2allah.com/cartoon/amin8/cartoon-fariq-alse7a-…x264.mp4` | leading `?`, so it is relative and broken |
| **11172**, **10547**, **9870–9877** (group 772), … | `/download/heekmaa702/tagharid-….WMV` | host-less archive.org path **and** WMV |
| **17210, 17211, 11571** | `http://vars.way2allah.com/cartoon/amin8/….mp4` | **works:** the host answers `301 → https`, then `200 video/mp4` (`HEAD`, see the disclosure at the top) |
| **17345, 17346** | `https://dn721605.ca.archive.org/0/items/…/….mp4` | **works** after normalisation (`200 video/mp4`) |

### 3.5 Root cause

**Not confirmed for the reported case**: the owner's URL and item are still missing (§7 of the production
investigation). Confirmed candidate causes, each with ids:
1. malformed links (data);
2. non-browser formats (48 % of video items, the same class as F05-2);
3. host-less `/download/` paths (data).

**Ruled out for the sampled items:** archive.org normalisation (works) and `vars.way2allah.com` http (it redirects to
https and serves `video/mp4`). Codec support (for example HEVC) is **unverified** (no downloads).

Note: a `khotab-group-{id}` page lists **Khotab** items. If the report really came from such a page, F05 applies
rather than Anasheed.

### 3.6 Recommended fix

- **Data:** the 9 malformed and 2 empty links need an admin to enter the correct URL through «تعديل المادة». The
  correct values are not derivable for 16755–16759 because the names are shifted.
- **Code (shared with F05-2 (2)):** the specific non-browser-format message.
- **Optional (plan decision 22):** a save-time **warning** on Khotab and Anasheed item and mirror forms when a video
  link is not absolute http(s), not `.mp4`, or contains two extensions.
- **Not recommended:** guessing hosts for relative links at render time.

### 3.7 Regression risks

Low for the message. The data corrections are manual and per item.

### 3.8 Required tests

- the message for non-browser formats on Anasheed items and mirrors;
- if the warning is approved, the form warning cases;
- `.mp4` absolute links unchanged.

### 3.9 Owner input needed

**YES:**
- the exact page URL and item (and whether it is `var-group` or `khotab-group`);
- whether the 11 broken link rows should be corrected;
- the warning (decision 22).

---

## 4. Cross-system compatibility

- **No fix requires changing `7amlat` or `old-project`.**
- **F03 A (repair)** writes shared rows. OLD and Gen 2 then list the repaired series too, since their visitor rule is
  the same `count > 0`.
- **F05-2 (1)** is a Laravel render-time change. Gen 2 / OLD keep failing those mirrors if still routed (the B4
  routing blockers apply).
- **F07 data corrections** write shared rows (all systems benefit).
- The legacy-routing verification from B4 (§8) applies to any claim that visitors are fixed.

## 5. Order of implementation

1. **F05-2 (1)** first: confirmed by data and HTTP, one method plus the card label, render-time only, no data change.
2. **F05-2 (2) + F07 message**, once the wording is approved.
3. **F03 B** (admin view), once the permission is chosen. **F03 A** as an owner-run data repair.
4. **F07 data corrections**, once the reported item is known.
