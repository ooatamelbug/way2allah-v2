# AdminCP B2 / F07-B1: Variety media URL data-repair plan

**Date:** 2026-11-16. **Phase:** investigation and repair preparation only. **Nothing was repaired.**
**Basis:** `admincp-b2-f07-resolution-proposal.md`, `admincp-b2-f07a-implementation-results.md` (F07-A closed
locally).
**Companion file:** **`docs/reports/admincp-b2-f07b-inventory.csv`**, the exact per-record inventory (298 rows; the
column list is in §1).

**Scope of what was done:**
- **Database:** `SELECT` only, on the local copy. The Variety tables hold about 16 k items and 19 k mirrors, so each
  query is a bounded pass; no Khotab table was scanned.
- **No request to any Way2Allah host.** `vars.way2allah.com` and `www.way2allah.com` were **not** contacted.
- **Network: 30 HEAD requests to `archive.org`** (public, third-party, no download) to verify archive candidates. The
  legacy third-party hosts (`sub4arab.com`, etc.) were **not** contacted.
- No code, schema, Gen 2 / OLD or data change; nothing committed, pushed or deployed.
- **Documentation fix:** one inaccurate sentence of the F07-A results report was corrected (§1.4 below).

---

## 0. Summary

| | Items | Mirrors | Total |
|---|---|---|---|
| Records needing a repair decision | 235 | 32 | **267** |
| HIGH (exact URL from local evidence, or externally verified) | **46** (27 HEAD-verified) | **12** (1 HEAD-verified) | **58** |
| MEDIUM (plausible; needs the owner to open the URL) | **179** | **5** | **184** |
| LOW (intended URL not determinable) | **10** | **15** | **25** |
| Legacy third-party hosts (not malformed; redirect-safety review) | 4 | 27 | 31 |

- **Hidden records affected:** none. All 298 inventory rows are visible.
- **Recommended next step:** approve the HIGH set, then implement the narrowly scoped repair command (§5) with the
  HIGH rows as its first manifest. MEDIUM rows follow only after the owner spot-checks the `vars.way2allah.com` URLs.
  LOW rows stay manual.

## 1. Exact affected-record inventory

The CSV columns are:
- `record_type`, `id`, `group_id` (a mirror's is its item's group), `hidden`, `title` (a mirror's names its item);
- `current_url` and `proposed_url`, both JSON-encoded so leading or trailing spaces are visible;
- `category`, `evidence`, `confidence`, `externally_verified`, `recommended_action`, `owner_approval_required` (yes
  for every row).

### 1.1 HIGH: 58 records

| Category | Records | Proposed | Evidence | Externally verified |
|---|---|---|---|---|
| Missing archive.org host | **27 items** (8703, 8870–8872, 9075, 9360, 9483, 9498, 9589, 9601, 9604, 9642, 9688, 9724, 9826, 9869–9877, 10467, 10547, 11172) | `https://archive.org` + the stored path, **verbatim** | `/download/<identifier>/<file>` shape | **yes: all 27 HEAD 200 `video/x-ms-wmv`** |
| Stray character | item **14823** | drop the leading «ر» | the rest is a complete URL; file name matches the title; siblings use the same host and folder | no (vars) |
| Leading space, non-archive (route redirects into the site) | items **4860, 4868, 4869, 4892, 4893**; mirrors **15966, 15969, 15970, 15971, 15978, 15979, 15981, 15986, 15987, 15997** (SoundCloud) | trimmed value | complete URL after trimming | no |
| Leading space, archive.org (already works) | items 1921, 12485, 14856 | trimmed | cosmetic | no |
| Trailing space only (already works) | items 1427, 1668, 1669, 1670, 1814, 2061, 2062; mirror 116 | trimmed | cosmetic | no |
| URL repeated twice | mirror **10659** | one copy | the value is the same URL twice | **yes: 200 `video/x-msvideo`** |
| Missing vars host, exact copy exists | items **9106, 9955** | `http://vars.way2allah.com` + path | another record stores exactly this absolute URL | no |
| Concatenated; probable duplicate | item **16759** | item 16773's URL | exactly 16773's stored URL **and** the identical `linksize` (181,403,648) | no. **Owner: correct it, or hide 16759 as a duplicate** |

**On "HIGH" without external verification:** the intended URL is established exactly by local evidence (trimming,
removing one stray character, an identical stored copy). That does **not** prove the remote file exists. Per the rules,
none of these is called "verified" because of its host or folder.

### 1.2 MEDIUM: 184 records (each needs the owner to open the URL)

| Category | Records | Proposed | Evidence |
|---|---|---|---|
| Missing vars host | **173 items** under `/cartoon/amin2/…` and `/cartoon/amin3/…` (ids 8686–11057; list in the CSV) | `http://vars.way2allah.com` + path | **172 have a mirror of the same item in the same vars folder**; the folder holds 1,511 absolute vars items in the same id range |
| Concatenated | items **16755, 16756, 16757, 16758** | `…/cartoon/amin8/new-new-{al3awda, alaa-aldin, alalwan, alamana}_x264.mp4` | 2nd file name matches the title; folder from sibling 16754 |
| Concatenated | item **12581** | `…/cartoon/amin6/…-21-9-2017_x264.mp4` | folder from its own AVI mirror 16293 and sibling 12582 |
| Doubled folder, no file | item **17310** (episode 3) | `https://vars.way2allah.com/cartoon/amin8/xcartoon-ahlam-zahabia3.mp4` | siblings 17309 = `…zahabia2.mp4`, 17311 = `…zahabia4.mp4` |
| Bare file name | mirrors **9311, 9575** | `http://vars.way2allah.com/cartoon/amin/<name>.AVI` | parent item's WMV is in `cartoon/amin` with the same base name |
| Concatenated | mirror **9590** | `…/cartoon/amin/cartoon-bit-sisan-lenegarib-al3ad-6-1-2014.AVI` | the same |
| Concatenated | mirror **15842** | `…/cartoon/amin5/watha2qia-al3alem-alda2i3-27-8-2016.AVI` | parent 12194 in `amin5`; mirror 15843 shows the AVI naming |
| File name as host | mirror **10255** (`http://va` + file) | `…/cartoon/amin2/tabi3ia-sayado-alasmak-ghabat-alamazon-13-4-2014.AVI` | parent 8154 is `…/amin2/tabi3ia-…-13-4-2014.WMV` |

### 1.3 LOW: 25 records (manual review; no automatic value)

| Records | Problem | Notes |
|---|---|---|
| items 8818, 8819, 8820; mirrors 10848, 10853, 10854, 10856, 10861, 10862, 10909–10914 | **truncated archive.org value** `https://archive.org/download/media-w..._hane_hlme.{WMV,AVI,mp3}`: a literal `...` in place of the identifier | the value answers **404** on archive.org; the real identifier is unknown |
| item 15030 | concatenated; candidate `…-epi2-…` but the title says episode 12 | owner identifies the file |
| item 12195 | concatenated; the candidate is already item 12567's file (a different episode) | AVI mirror 15843 still works |
| items 6485, 10796 | empty | both have working mirrors (6485: archive.org AVI + MP3; 10796: vars AVI) |
| item 8120 (episode 5) | doubled folder prefix, no file name | AVI mirror 10222 uses a different naming |
| item 6270 | two placeholder URLs joined by a comma (`…/admin.mp4,…/admin.avi`) | correct or hide |
| item 17351 «ماده حديد» | **a Google search-redirect URL**, not media | probably a test entry: delete or hide |
| mirror 156 | `16` | delete |
| mirror 11604 | empty | delete |
| mirror 18732 | the lecture title stored as the link | delete or replace |

### 1.4 Not defects (false-positive guard) and corrections to earlier reports

- **`...` inside archive.org file names is legitimate.** About 45 items and 70 mirrors have names like
  `eamn_dakt_3leh_dneah...abshr-_.mp4` (archive.org's own upload names). A sample answered **200 `video/mp4`**. They
  must never be "fixed". Only `media-w..._hane_hlme`, which has no identifier segment, is truncated.
- **Correction to F07-A §6** (now amended in that report): of the 18 leading-space mirrors whose download F07-A hides,
  **10 are SoundCloud links** (trimming fixes them) and **8 are the truncated archive.org value** (trimming does not).
  F07-A had described all 18 as archive.org links without a folder.
- **New since the F07 proposal:**
  - mirror 10255 (file name as host);
  - items 8120 and 17310 (doubled prefix);
  - mirror 10659 (URL repeated);
  - item 6270 (comma-joined);
  - item 17351 (Google redirect);
  - the 11 truncated archive.org rows.

### 1.5 Legacy third-party hosts: 31 records (not malformed; redirect-safety review only)

| Host | Records |
|---|---|
| `sub4arab.com` | mirrors 118–138 (21) |
| `media.rashed.ws` | mirrors 157, 158, 159 |
| `way2allah.knoz4arab.com` | mirrors 139, 647 |
| `www.snapdrive.net` | mirror 114 |
| `media.majddoc.com` | items 46, 74 |
| `www.way2allah.com/neqab/…` | items 3359, 3360 |

None of these was contacted. Their availability, and current owner, is unknown (§8).

## 2. Proposed corrections

**Rules for a correction:**
- The only column written is `link`.
- `linksize`, `vedio`, `hidden`, titles, counters and every other column are never touched.
- Corrections are never automatic: each row is applied only from an owner-approved manifest (§5).

| Wave | Rows | Condition |
|---|---|---|
| **1** | the 58 HIGH rows (optionally without the 11 cosmetic trims) | owner approval of the list; 16759 needs the "correct vs hide" choice |
| **2** | the 184 MEDIUM rows | after the owner opens a sample of each MEDIUM category, or each URL, on `vars.way2allah.com` and confirms |
| — | the 25 LOW rows | manual, one by one, through «تعديل المادة» / mirror edit (audited); or hide / delete |
| — | the 31 legacy-host rows | owner decision with §8 (keep / hide / delete); no URL change proposed |

## 3. Confidence and evidence

The CSV's `confidence` follows the instructed scale:
- **HIGH:** the exact intended URL is established from reliable local evidence, or verified externally (archive.org
  HEAD).
- **MEDIUM:** plausible, needs external verification.
- **LOW:** not determinable.

No row is "verified" because another row shares its host or folder.

## 4. Records requiring manual review

All 25 LOW rows (§1.3), the duplicate choice for 16759, and every MEDIUM row before wave 2.

## 5. Recommended repair execution design (not implemented)

A dedicated Artisan command modelled on the F03-A repair (`khotab:repair-f03-series-counts`, decision-log #145), for
example **`anasheed:repair-f07-links`**.

**Manifest:** a versioned JSON file in the repository, so the approval is reviewable in a diff, not hard-coded.
- One entry per approved row: `{table: item|mirror, id, expected_old_hex, expected_old_sha256, new_value, wave, approval_ref}`.
- The **old value is stored byte-exact** (hex plus SHA-256). This is required because `link` is `utf8_unicode_ci`
  TEXT, which is **case-insensitive and PAD SPACE**: `link = 'x'` also matches `'x '` and `'X'` (checked locally).
- The manifest is checksummed and refused if edited.

**Modes:**
- **Default: dry run, read-only.** Per row: the current value (JSON-escaped), expected, new, and a state:
  `pending` / `already_applied` / `conflict` / `missing`, plus a summary.
- **`--apply --actor=<aid>`:** super-admin or the Variety edit permission (`anasheed.editanasheed`, and
  `anasheed.editmirror` for mirrors). It:
  1. re-runs the plan; any `conflict` or `missing` blocks the whole run (no partial application by default);
  2. writes and verifies the backup (§6);
  3. opens one transaction on `main`;
  4. `SELECT … FOR UPDATE` the rows by primary key;
  5. re-validates **byte-exactly** (`BINARY link = ?` or `SHA2(link,256) = ?`);
  6. updates each row with `UPDATE … SET link = ? WHERE id = ? AND BINARY link = ?`, which must affect exactly 1 row
     or everything rolls back;
  7. writes one `admin_audit_log` row per record (`repair_link`, module `anasheed`, entity `item` / `mirror`, metadata
     `{from_sha256, to, wave, backup_sha256}`);
  8. runs a post-check after commit.
- **`--wave=1|2`** limits the run to approved waves. No discovery: only manifest ids are ever touched.

**Idempotency:**
- rows already holding `new_value` (byte-exact) count as `already_applied` and are skipped;
- when every row is applied, the command reports "nothing to do";
- a mix of applied and pending rows is allowed **only** when each pending row still holds its exact expected old
  value. That is safe here because each row is independent; this differs from F03-A, where all rows had to move
  together.

**Cache and visibility:** no cache to invalidate (the Variety pages and routes are uncached). Visibility flags are not
touched.

**Alternative:** per-row correction through the existing «تعديل المادة» and mirror edit forms. This is audited and
warns via F07-A, so it is suitable for the 25 LOW rows. It is impractical for 200+ rows and has no byte-exact
conflict guard.

## 6. Backup and rollback design

- **Backup:** written before the transaction. A JSON file in `storage/app/maintenance/anasheed-f07` (git-ignored, mode
  0640), written via temporary file, `fsync`, rename, read-back and checksum verification (as F03-A).
  - It holds, per row, `table`, `id`, `old` (hex and sha256), `new`, the manifest checksum, the actor and a timestamp.
  - A backup failure aborts before any write.
- **Rollback:** `--rollback=<backup> [--apply --actor=…]`:
  - it restores `old` only where the current value is byte-exactly `new`;
  - anything else is a **conflict**, for example an admin edited the link after the repair, which is a legitimate
    later change. A conflict blocks the rollback unless `--skip-conflicts` is given, and then the conflicting rows
    are left untouched;
  - it runs in one transaction, audited as `rollback_link`, and is idempotent.
- **Tests required** (when implemented), as in F03-A plus:
  - byte-exact guard: trailing-space and case differences are conflicts, not matches;
  - hex round-trip of Arabic and percent-encoded values;
  - per-wave filtering;
  - a manifest checksum failure refuses the run;
  - every other column of every row is unchanged.

## 7. GetRight findings (`/var-series-{group}.grx`, `AnasheedGroupController::downloadGetright`)

| Question | Finding |
|---|---|
| How generated | `anasheedItemsForGroupDownload($group)`: items with `group_id = X AND hidden = 0 ORDER BY weight` (OLD `DownSeries()`, visitor parity A8). One `URL: https://<site>/var-download-{id}.htm` + `File: C:\Way2Allah\الأناشيد\{group}\{title}.{ext}.GetRight` entry per item; the extension comes from the raw link |
| Malformed links included? | **Yes.** Every visible item is listed, so broken ones make GetRight fetch this site's 404 page (concatenated, «ر», host-less) or the **home page** (empty link), saved under the item's file name. Local scale: the 213 non-servable items (F07-A §6) |
| Hidden items included? | **No** (`hidden = 0`). Variety groups have no `hidden` column |
| Reuse of F07-A rules | **Yes:** `VarietyMediaLink::isDownloadable($item->link)` is exactly the route's own rule |
| Semantics if filtered | the list is "every visible item of the group that can be downloaded". Entries that can only fail disappear and the count shrinks; the order and every downloadable entry are unchanged |
| Cached? | **No** application cache. The response is `Cache-Control: must-revalidate, post-check=0, pre-check=0, private` (attachment), so no shared cache |

**Proposed minimal correction (not implemented):** in `downloadGetright()`, skip items where
`! VarietyMediaLink::isDownloadable($item->link)`. That is a one-line filter in the loop, with tests for skipped
broken items, unchanged order and entries, and hidden still excluded. The repairs in §2 shrink the skipped set to the
LOW rows.

## 8. Redirect-safety findings (`/var-download-{id}`, `/var-mirror-{id}-{m}`, unchanged)

| Aspect | Finding |
|---|---|
| Who controls the target | **Only stored data.** The routes take numeric ids; no request parameter reaches the redirect. **There is no classic open redirect:** a visitor cannot choose the destination |
| What the target can be | any absolute URL an admin stored, with `http:` → `https:` (all occurrences) after archive.org normalisation. F07-A warns admins about non-absolute links but, by design, does not judge hosts |
| Legitimate hosts | archive.org (all nodes): 8,456 items / 10,865 mirrors; `vars.way2allah.com`: 7,818 / 6,145; YouTube (`youtu.be`, `youtube.com`, `www.youtube.com`): 1,601 mirrors, redirecting to the video page (expected); SoundCloud: 159 mirrors. **About 99.8 %** |
| Residual risk | **stale third-party hosts** (§1.5: `sub4arab.com`, `media.rashed.ws`, `media.majddoc.com`, `snapdrive.net`, `knoz4arab.com`). If such a domain lapsed and was re-registered, the site would forward visitors to whatever it serves now. Also item 17351 redirects through **`google.com/url`** to YouTube (a redirector chain) |
| Authorization / visibility | `var-download`: the item must be visible. `var-mirror`: the **item** must be visible and the mirror must belong to it, **but the mirror's own `hidden` flag is not checked** (the player does check it). 0 hidden mirrors exist locally (latent gap). The 61 mirrors of hidden items are refused |
| Host allowlist? | an allowlist of archive.org (all subdomains), `vars.way2allah.com`, the YouTube hosts and `soundcloud.com` would keep 99.8 % working and stop **≈ 34 rows**: §1.5 plus items 6270 and 17351 and mirror 10255. That breaks no working legacy content among the verified hosts, but the legacy-host rows would stop redirecting even if a host is still alive. **Not implemented (needs approval).** The lower-risk alternative is a data decision on those rows (hide or delete) |

## 9. Production verification prerequisites

1. **Production differs from local until proven otherwise.** Re-run the inventory read-only on production (the same
   queries; an export of the 298 ids with their byte-exact current values) and diff it against the CSV. Any differing
   row drops out of the manifest.
2. **archive.org:** re-check the 28 HEAD-verified URLs shortly before applying (files can be removed).
3. **vars.way2allah.com:** the owner opens a sample of each MEDIUM category, or each URL, and confirms playback or
   download. This was never contacted here.
4. **Backups:** a normal database backup plus the command's own row backup.
5. **Access:** an AdminCP actor with the Variety edit permission; the `storage/app/maintenance` directory writable and
   persistent.
6. **Read-only dry run** on production first; apply wave 1 only after an empty `conflict` / `missing` list.

## 10. Explicit owner decisions required

1. **Approve wave 1** (58 HIGH rows), and whether to include the 11 cosmetic trims (they already work).
2. **16759:** correct it to 16773's URL, or hide it as a duplicate.
3. **Wave 2** (184 MEDIUM): after the vars spot-check, all at once or per category.
4. **The 25 LOW rows:** per-row manual handling (identify, hide or delete), especially the 11 truncated archive.org
   rows and items 6270 / 17351.
5. **The repair mechanism:** approve implementing the manifest-driven command (§5), or the manual editor route.
6. **GetRight:** approve the one-line filter (§7).
7. **Redirect safety:** decide on the 31 legacy-host rows (keep / hide / delete) and whether a host allowlist is
   wanted (§8). Separately, whether the mirror route should also refuse hidden mirrors (latent).
8. **The original F07 complaint:** the page URL and item, still required to confirm it. F07 is **not** claimed
   resolved.
