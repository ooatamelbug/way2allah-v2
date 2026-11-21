# AdminCP B2 / F07: Variety (Anasheed) playback — resolution proposal

**Date:** 2026-11-16. **Phase:** final investigation and proposal only.
**Basis:** `docs/reports/admincp-b2-investigation.md` §3 (refined and corrected here),
`admincp-production-feedback-investigation.md` §F07 and `admincp-b2-f05-2-part2-results.md`.

**Scope of what was done:**
- No code, schema or data change.
- Database: `SELECT`s on the local copy only. The Variety tables are small (about 16 k items and 19 k mirrors), so
  each classification is a single pass; Khotab was touched by one bounded count.
- **No request to any Way2Allah host.** `vars.way2allah.com` was **not** contacted, so its files remain
  **unverified**.
- **Three HEAD requests to `archive.org`** (public, third-party; no download) to test host-less `/download/` paths.
- The URL generator was evaluated in `tinker` (pure computation, no database) to show where malformed links redirect.

---

## 0. Summary

| | |
|---|---|
| Original complaint | "Variety video on `khotab-group-{id}` fails; the player appears but fails". **No item or URL was ever supplied; it cannot be matched to a confirmed item** (§10) |
| Malformed Variety links (visible) | **8 concatenated file names** and **1 junk-prefixed URL** (all `.mp4`, so a `<video>` appears and then fails, matching "appears but fails"); **2 empty**; **202 host-less paths** (175 `/cartoon/amin2|3/…`, 27 `/download/…`) |
| Malformed Variety mirrors (visible) | **7** (`16`, bare file names, concatenations, one empty, one lecture title stored as the link) |
| F05-2 Part 2 coverage | Variety **already** gets the unsupported-format explanation (RMVB, WMV, RM, AVI, FLV, WMA), with a download offered only when the redirect target is a real https URL. It does **not** cover malformed `.mp4` links, empty links, or RAM / 3GP / MPG / OGV / M4V / OGG |
| Download routes | no validation; malformed links redirect **to paths on this site** (404) or, when empty, to the **home page**. Never to an unexpected external host (only admin-stored absolute URLs leave the site). The page's own «حفظ المادة» / «تحميل» buttons stay visible for broken links |
| Recommendation | **Data corrections first** (evidence per row, owner-approved); two small, optional application safeguards; a **warn-only** editor check; format decisions per §2 |

## 1. Confirmed affected items

### 1.1 Variety items (`nuke_anasheed_anasheed`, all visible, `vedio = 1`)

**Correction to B2:**
- **Item 14823 does not start with `?`.** It starts with the Arabic letter **«ر»** (`رhttp://vars.way2allah.com/…`).
  The `?` came from an earlier query run without UTF-8.
- B2 also listed "9 malformed". The correct figure is **8 concatenated + 1 junk prefix**. 12195 is concatenated,
  not merely "similar".

| Item | Group | Stored link | Class | Evidence for the intended link | Confidence |
|---|---|---|---|---|---|
| 14823 | 1082 | `رhttp://vars.way2allah.com/cartoon/amin8/cartoon-fariq-alse7a-fawz-ghir-motawaqa3-11-2021_x264.mp4` | junk character before the scheme (a stray «ر», the key that shares the `v` position on an Arabic keyboard) | the rest is a complete URL; the file name matches the title «فوز غير متوقع (فريق الصحة)»; siblings 14822 and 14824 use the same host and directory | **High**: drop the first character |
| 16755 | 1273 | `new-new-aby-alhanon_x264.mp4new-new-al3awda_x264.mp4` | **previous item's file name + own file name, no host** | 2nd name = title «العودة»; sibling 16754 = `http://vars.way2allah.com/cartoon/amin8/new-new-aby-alhanon_x264.mp4` | Medium-high: `…/cartoon/amin8/new-new-al3awda_x264.mp4` |
| 16756 | 1273 | `new-new-al3awda_x264.mp4new-new-alaa-aldin_x264.mp4` | same | «علاء الدين» | Medium-high: `…/amin8/new-new-alaa-aldin_x264.mp4` |
| 16757 | 1273 | `new-new-alaa-aldin_x264.mp4new-new-alalwan_x264.mp4` | same | «الالوان» | Medium-high: `…/amin8/new-new-alalwan_x264.mp4` |
| 16758 | 1273 | `new-new-alamana_x264.mp4new-new-alamana_x264.mp4` | own name twice, no host | «الامانة» | Medium-high: `…/amin8/new-new-alamana_x264.mp4` |
| 16759 | 1273 | `new-new-alaser-3arog_x264.mp4new-new-alaser-3arog_x264.mp4` | own name twice, no host | «الاسبر». **Item 16773 «الاسير عروج» stores exactly `http://vars.way2allah.com/cartoon/amin8/new-new-alaser-3arog_x264.mp4` and has the identical `linksize` (181,403,648)** | High for the URL; **16759 is very likely a duplicate of 16773** (owner: fix or hide) |
| 15030 | 405 | `watha2qia-rob3iat-aldaf3-24-5-2021_x264.mp4watha2qia-rob3iat-aldaf3-epi2-24-5-2021_x264.mp4` | previous item's name + own name | sibling 15029 = `…/amin8/watha2qia-rob3iat-aldaf3-24-5-2021_x264.mp4`; the title says «الحلقة 12» but the file name says `epi2` | **Medium-low**: `…/amin8/…-epi2-…_x264.mp4` needs checking |
| 12581 | 1027 | `cartoon-3ala-gizirit-alhasharat-son3-alsabon-3amal-momti3-21-9-2017_x264.mp4` ×2 | own name twice, no host | its own mirror 16293 = `http://vars.way2allah.com/cartoon/amin6/…-21-9-2017.AVI`; sibling 12582 is in `amin6` | Medium-high: `…/cartoon/amin6/…-21-9-2017_x264.mp4` |
| 12195 | 999 | `watha2qia-al3alem-alda2i3-27-8-2016.AVIwatha2qia-alfosol-alhamraa-27-8-2016_x264.mp4` | previous item's AVI name + a name | the 2nd name **is already item 12567's link** («الحلقة 2 (الفصول الحمراء)», a different size). Its mirror 15843 = `http://vars.way2allah.com/cartoon/amin5/watha2qia-alfosol-alhamraa-27-8-2016.AVI` | **Ambiguous**: do not infer; the AVI mirror is the only known source |
| 6485, 10796 | 400, 12 | empty | no link | none | **None** (owner: source or hide) |

**Host-less paths (202 visible items, all WMV / RMVB):**

| Prefix | Items | Evidence | Confidence |
|---|---|---|---|
| `/download/<identifier>/<file>` | **27** (e.g. 9870–9877, 8703, 10547, 11172) | the shape of `archive.org/download/…`. **HEAD-verified** on archive.org: `heekmaa702/tagharid-sa3qra2-ayat-alkorsi.WMV`, `promo_adab_shekh.ahmed_HD/promo_adab_shekh.ahmed_MED.wmv`, `TheSummerHolidayHD/The%20summer%20holiday_HD.wmv`, all **200 `video/x-ms-wmv`** | **High** (3 of 27 verified): prefix `https://archive.org`. Verify each before applying |
| `/cartoon/amin2/…` | **58** (ids 8686–9462) | the same directory holds **853** items stored as `http://vars.way2allah.com/cartoon/amin2/…` in the same id range (7746–9244) | Medium-high: prefix `http://vars.way2allah.com`. **Host not verifiable here** |
| `/cartoon/amin3/…` | **117** (ids 9106–11057) | **658** items in `http://vars.way2allah.com/cartoon/amin3/…`, ids 9091–11162 | same |

- 201 of these 202 items have **absolute mirrors** (229 in total), so a working alternative is usually already on the
  page.
- **Not malformed (verified false positives):** 83 absolute links with "double extensions" are real file names, for
  example `21.mp4_x264.mp4`, `ma3a-al7abeb-fe.ramadan.mp4`, `okobat%20elaak.mp3.mp3`. A validator must not flag
  them (§8).

### 1.2 Variety mirrors (`nuke_anasheed_mirror`, all visible)

| Mirror | Item | Stored link | Class |
|---|---|---|---|
| 156 | 270 | `16` | garbage |
| 9311 | 7318 | `watha2qia-khat-anabib-alaska-9-12-2013.AVI` | bare file name, no host |
| 9575 | 7581 | `watha2qia-ma3na-lebnan-1-1-2014.AVI` | bare file name, no host |
| 9590 | 7600 | `cartoon-bit-sisan-lenegarib-al3ad-6-1-2014.AVI` ×2 | concatenated |
| 11604 | 9198 | empty | empty |
| 15842 | 12194 | `watha2qia-al3alem-alda2i3-27-8-2016_x264.mp4watha2qia-al3alem-alda2i3-27-8-2016.AVI` | concatenated (the same session as item 12195) |
| 18732 | 16769 | `هل يمكن لغير الزوج عمل وليمة الزواج؟ \| د. أحمد سيف` | **a title pasted into the link field** |

Also not media at all: 1 `.pdf` mirror, 1 `.gif` mirror, and 2 `.iso` items (audio flag).

## 2. Root causes

1. **Data entry, CONFIRMED by pattern.** In each concatenation session (16754→16759 on 2023-10-08, 15029→15030,
   12194→12195 and mirror 15842, 12581), every row holds the **previous** row's file name followed by its own, with
   the host prefix missing. This looks like a link field that kept part of the previous value. The tool or form used
   at the time is unknown (OLD / Gen 2 admin, 2016–2023). **Not a Laravel defect.**
2. **Host-less legacy paths (historical, CONFIRMED by evidence above):** the 202 `/download/…` and `/cartoon/…` paths
   were presumably resolved by the host they were served from in the past. They resolve to this site's paths now.
3. **Format:** 7,571 Variety video items are in formats no browser plays (the F05-2 class, already handled).
4. **No save-time validation** in any system (OLD, Gen 2, Laravel), so all of the above could be entered.

## 3. Current playback behaviour (Laravel, verified in code)

`MediaPlayerService` `anasheed` / `anasheed_mirror`:
1. hidden refused (B4);
2. `ArchiveOrgLink::normalize()` (archive.org only; other hosts and relative values unchanged);
3. `renderPlayer()`;
4. F05-2 Part 2 notice.

| Link | What the user gets |
|---|---|
| absolute `.mp4` (8,174 items) | `<video>`; plays if the host serves a browser codec |
| absolute RMVB / WMV / RM / AVI / FLV / WMA | **F05-2 explanation + «تحميل المادة»** to the existing route |
| host-less `/download/…` or `/cartoon/…` (WMV / RMVB) | **F05-2 explanation, no download**: «لا يتوفر رابط تحميل لهذه المادة حالياً» |
| **concatenated `.mp4` (8) and 14823** | **`<video>` with a relative `src`**: the player opens, the request 404s on this site, then «تعذّر تحميل الملف. جرّب جودة أخرى أو أعد المحاولة.» This is **"the player appears but fails"** |
| empty (2) | generic «هذه المادة غير متاحة للتشغيل حالياً…» |
| RAM / 3GP / MPG / OGV / M4V / OGG / ISO | generic «غير متاحة…» with retry (not in the closed list) |
| YouTube / `.mp3` | play |

**Format recommendations** (Variety and Khotab counts; no format was added):

| Format | Variety | Khotab | Nature | Recommendation |
|---|---|---|---|---|
| **ASF** | 0 | 750 (items 16, mirrors 734) | Windows Media container; external software | **add** to the unsupported list (same class as WMV) |
| **RAM** | 13 items | 240 | RealMedia *metafile* pointing at a stream; RealPlayer only | **add** |
| **RA** | 0 | 164 | RealAudio; RealPlayer only | **add** |
| **MPG** | 1 item, 5 mirrors | 598 mirrors | MPEG-1/2 program stream; not played by the mainstream desktop browsers' `<video>` | **add** (low risk; confirm on target browsers) |
| **3GP / 3GPP** | 1 item, 1,349 mirrors | 783 mirrors | mobile container; **codec-dependent** (H.264/AAC may play in Chromium and Safari, H.263/AMR does not) | **do not add**: keep the generic message, or attempt `<video>` with the existing error fallback (owner) |
| **OGV** | 200 mirrors | — | Theora/Vorbis; **browser-dependent** (Firefox yes, Safari no, Chromium support reduced); verify | **do not add**; same option as 3GP |
| **M4V** | 4 items | — | MP4 variant, normally H.264 | **optional**: treat like `.mp4` (attempt `<video>`) |
| **OGG** (audio) | 12 mirrors | small | Vorbis audio; browser-dependent (Safari limited) | **optional**: attempt `<audio>` with the error fallback |
| **ISO / PDF / GIF** | 2 / 1 / 1 | — | not playable media | data issue; leave (or hide) |

## 4. Existing F05-2 coverage

- **Part 1** (`.mp4` mirrors flagged audio) is **Khotab-only by design**. Variety mirrors had no such mismatch.
- **Part 2 covers Variety fully for the six formats**, items and mirrors:
  - the explanation;
  - the download only when `/var-download` / `/var-mirror` would redirect to an absolute https URL, so host-less
    links get the no-download wording (tested in `UnsupportedPlaybackFormatF052Part2Test`).
- **Not covered:** malformed links whose path ends in `.mp4` (a `<video>` is still attempted), empty links, and the
  formats in §3 outside the list.

## 5. Download-route risks (`AnasheedItemController::download` / `downloadMirror`, unchanged)

| Question | Finding |
|---|---|
| Reject malformed links? | **No.** Only "visible item" and "mirror belongs to item" are checked; the counter is incremented, then `redirect(str_replace('http:', 'https:', normalize(link)))` |
| Redirect to local site paths? | **Yes** (URL generator, verified): concatenated → `https://<site>/new-new-…mp4`; 14823 → `https://<site>/رhttps://vars…`; `/cartoon/…` and `/download/…` → `https://<site>/cartoon/…`; mirror `16` → `/16`; **empty → the site's home page**. All but the home page are 404s on Laravel (unless the web server still serves such legacy paths: unverified) |
| Unexpected external destinations? | **No new risk.** Only absolute URLs stored by admins leave the site (no protocol-relative or other-scheme values exist: 0 rows). Note: `str_replace('http:', …)` rewrites every occurrence, not only the scheme. Harmless in current data |
| Misleading buttons? | **Yes.** The item card's «حفظ المادة» and each quality card's «تحميل» are always shown, so they lead to a 404 or the home page for 213 items and 7 mirrors |
| Counter side effect | a GET on a broken link still increments `downcount` / mirror `hits` (inflated counts; minor) |
| Does F05-2 protect the user? | **Only inside the player dialog** (no misleading download offered there). The page's own buttons are not covered |

## 6. Proposed minimal application fixes (each needs approval; none implemented)

1. **(Recommended) Player guard for unusable links.**
   - **Where:** `MediaPlayerService`, Variety types (Khotab optional, after its own review; Khotab has 165 visible
     items and 210 mirrors with non-absolute links, some possibly local server paths).
   - **When:** after normalisation, the link is not an absolute `http(s)://host…` URL.
   - **What:** return a specific notice instead of rendering `<video>` / `<audio>`, for example «وصلة هذه المادة
     غير صالحة حالياً، يرجى إبلاغ إدارة الموقع.» (wording ⚑). It reuses the F05-2 markup, so no JS or CSS change.
   - **Effect:** removes "the player appears but fails" for the 9 malformed `.mp4` items and any future one.
     Everything absolute is unchanged.
2. **(Optional) Hide misleading download buttons.**
   - On `var-item`, do not render «حفظ المادة» / «تحميل» when the same rule F05-2 uses (`anasheedDownloadable`)
     says the route cannot serve the link.
   - The download routes themselves stay unchanged, as required.
3. **(Optional) Extend the unsupported list (decision #144) with ASF, RAM, RA, MPG.** This is a one-line change to
   the closed list plus tests.
4. **(Owner choice) Browser-dependent formats** (3GP, OGV, M4V, OGG): attempt playback with the existing error
   fallback, or keep the generic message.
5. **Not recommended:** guessing hosts at render time (e.g. treating `/download/…` as archive.org in code). The
   evidence differs per prefix, and a data correction is explicit and reviewable.

## 7. Data corrections requiring owner approval

All are row-level `link` edits on shared tables, so Gen 2 and OLD benefit too. They should run through «تعديل المادة»
(audited) or a reviewed script with a row backup, each row verified first.

| # | Rows | Proposed value | Verification before applying |
|---|---|---|---|
| D1 | 14823 | drop the leading «ر» | open the URL in a browser (`vars` host) |
| D2 | 27 `/download/…` items | prefix `https://archive.org` | HEAD each on archive.org (3 already 200); result is still WMV → F05-2 explanation **with** download |
| D3 | 175 `/cartoon/amin2|3/…` items | prefix `http://vars.way2allah.com` | owner spot-check in a browser (host not contacted here) |
| D4 | 16755–16758, 12581 | the §1.1 candidates | owner opens each URL; title already matches |
| D5 | 16759 | the URL of 16773, **or hide 16759 as a duplicate** | owner decision |
| D6 | 15030 | candidate `…-epi2-…`, title says episode 12 | owner identifies the right file |
| D7 | 12195 | **unknown**: its second name is already 12567's file | owner identifies the file, or relies on the AVI mirror |
| D8 | 6485, 10796 (empty) | source or hide | owner |
| D9 | mirrors 156, 9311, 9575, 9590, 11604, 15842, 18732 | correct or delete (9311 / 9575 likely `http://vars.way2allah.com/cartoon/amin*/…`; directory unknown) | owner |

## 8. Admin validation recommendation: **warn only**

The Variety item editor (`StoreAnasheedItemRequest` `Anasheedfile`, `UpdateAnasheedItemRequest` `Anasheed_link`) and
the mirror forms (`Store`/`UpdateAnasheedMirrorRequest` `link`) only check *required / string / length*; the Khotab
forms are the same. The admin layout renders only `success` and `error` flashes; a warning needs a `warning` flash.

| Check | Catches | Safe to block? |
|---|---|---|
| not an absolute `http(s)://host/…` URL | concatenations, «ر» prefix, bare names, `16`, titles in the link field, host-less paths | **No: warn.** 213 existing items are non-absolute; the edit form re-submits the link, so blocking would stop admins from editing *any* field (title, visibility) of those items |
| whitespace or non-URL characters before the scheme | 14823, 18732 | warn (covered by the rule above) |
| two media extensions with no `/` between and **no host** | concatenations | warn. **Must not** flag absolute names like `21.mp4_x264.mp4` or `x.mp3.mp3` (83 valid rows) |
| extension in the unsupported list | RMVB / WMV / … | **info only** («لن تُشغّل في المتصفح، سيُعرض للزائر رابط تحميل») |
| empty | — | already **blocked** (`required`) on items; the mirror update allows empty (`nullable`): keep |

**Recommendation:**
- a non-blocking **warning** shown after save, listing the issue, on the Variety item and mirror forms (and,
  separately approved, Khotab);
- no new blocking rule, so historical data stays editable;
- **plan decision 22** covers this.

## 9. Tests required (when implemented)

- **Guard:**
  - for each malformed class (concatenated, junk prefix, bare name, title text, root-relative `.mp4`, empty) on
    `anasheed` and `anasheed_mirror`: the notice, no `<video>` / `<audio>`, no download link;
  - absolute `.mp4`, `.mp3`, YouTube unchanged;
  - the F05-2 six-format notice unchanged (download only for absolute https);
  - hidden refused as before.
- **Buttons (if approved):** rendered for absolute links, absent for non-absolute ones, routes untouched (status,
  counter, redirect target identical).
- **Format list (if approved):** ASF / RAM / RA / MPG get the explanation; 3GP / OGV / M4V / OGG follow the chosen
  option; case-insensitive and path-only rules as in F05-2.
- **Warning (if approved):**
  - each warn case shows the warning **and saves**;
  - valid absolute links (including `21.mp4_x264.mp4`) show none;
  - an item with a legacy relative link can still be edited;
  - Khotab untouched unless approved.
- **Data (if approved):** a dry-run listing, per-row before/after, verification evidence attached; no other column
  changed.

## 10. Remaining information needed from the owner

1. **The original F07 item.**
   - The page URL (`khotab-group-{id}` as reported, or `var-group-{id}`) and the item clicked.
   - The symptom "the player appears but fails" **matches** the 9 malformed `.mp4` Variety items (groups 1273, 405,
     1027, 999, 1082), but **no document identifies the reported item**, so F07 **is not claimed resolved**.
   - If it was a Khotab group page, F05-1 / F05-2 already apply.
2. **Approval per data correction** D1–D9, and a browser spot-check of `vars.way2allah.com` for D1, D3, D4 (not
   contacted here).
3. **Application fixes:** the guard (§6.1) and its wording; the buttons (§6.2); the format list (§6.3); the
   browser-dependent formats (§6.4).
4. **Warning (plan decision 22):** warn-only as recommended, and whether Khotab forms are included.
