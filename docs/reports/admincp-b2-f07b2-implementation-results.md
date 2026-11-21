# AdminCP B2 / F07-B2: Variety link repair command, GetRight filter, hidden-mirror protection — results

**Date:** 2026-11-16.
**Approval:** "AdminCP Feedback B2 / F07-B2 — APPROVED: Local Implementation and Testing Only".
**Basis:** `admincp-b2-f07b-data-repair-plan.md` and `admincp-b2-f07b-inventory.csv`.
**Decision log:** #148.
**Status:** **implementation CLOSED LOCALLY (2026-11-16) after validation passed; not deployed.**
- **The data repair has NOT been executed** anywhere: not on the shared local database, not on production.
- **The original F07 complaint is not resolved** (item unknown).
- B2 is not closed.

**What was done:**
- No write to any shared database. The command ran only in its default read-only dry run against the local copy
  (§5): `admin_audit_log` row count and `CHECKSUM TABLE` of both Variety tables were identical before and after, and no
  backup directory was created.
- No migration, production access, request to any Way2Allah host, Gen 2 / OLD change, commit, push or deployment.

---

## 1. Exact repair candidates: 46 records (35 items, 11 mirrors)

Every approved candidate had an unambiguous old and new value, so **none was excluded for ambiguity**. The values below
are the local copy's stored bytes, JSON-encoded so spaces show. The manifest keeps the old value as **hex**
(`F07LinkRepair::MANIFEST`), generated mechanically from `HEX(link)`.

| Type | Id | Parent (group / item) | Reason | Exact old value | Approved new value |
|---|---|---|---|---|---|
| item | 4860 | 308 | leading_space | `" http://vars.way2allah.com/cartoon/3/3azoz_2.rmvb"` | `"http://vars.way2allah.com/cartoon/3/3azoz_2.rmvb"` |
| item | 4868 | 304 | leading_space | `" http://vars.way2allah.com/cartoon/3/shahd_10.rmvb"` | `"http://vars.way2allah.com/cartoon/3/shahd_10.rmvb"` |
| item | 4869 | 304 | leading_space | `" http://vars.way2allah.com/cartoon/3/shahd_11.rmvb"` | `"http://vars.way2allah.com/cartoon/3/shahd_11.rmvb"` |
| item | 4892 | 300 | leading_space | `" http://vars.way2allah.com/cartoon/3/haydy_49.rmvb"` | `"http://vars.way2allah.com/cartoon/3/haydy_49.rmvb"` |
| item | 4893 | 300 | leading_space | `" http://vars.way2allah.com/cartoon/3/haydy_50.rmvb"` | `"http://vars.way2allah.com/cartoon/3/haydy_50.rmvb"` |
| item | 8703 | 164 | missing_archive_host | `"/download/TheSummerHolidayHD/The%20summer%20holiday_HD.wmv"` | `"https://archive.org/download/TheSummerHolidayHD/The%20summer%20holiday_HD.wmv"` |
| item | 8870 | 720 | missing_archive_host | `"/download/media-way2Allah-215/hmla_3mrk_alblae_steshn.WMV"` | `"https://archive.org/download/media-way2Allah-215/hmla_3mrk_alblae_steshn.WMV"` |
| item | 8871 | 720 | missing_archive_host | `"/download/media-way2Allah-215/hmla_3mrk_kande_krsh.WMV"` | `"https://archive.org/download/media-way2Allah-215/hmla_3mrk_kande_krsh.WMV"` |
| item | 8872 | 720 | missing_archive_host | `"/download/media-way2Allah-215/hmla_3mrk_la_tg3l_alfesbwk_ede3_3mrk.WMV"` | `"https://archive.org/download/media-way2Allah-215/hmla_3mrk_la_tg3l_alfesbwk_ede3_3mrk.WMV"` |
| item | 9075 | 16 | missing_archive_host | `"/download/media-way2allah-33/kwl_-en_allh_wmlaeakth_eslwn_3le_alnbe...fe_khtba_algm3a-_5954.WMV"` | `"https://archive.org/download/media-way2allah-33/kwl_-en_allh_wmlaeakth_eslwn_3le_alnbe...fe_khtba_algm3a-_5954.WMV"` |
| item | 9106 | 732 | missing_vars_host_exact_copy | `"/cartoon/amin3/tabi3ia-rehlat-bahria-rehla-ela-almohit-alhadi-2-8-2014.WMV"` | `"http://vars.way2allah.com/cartoon/amin3/tabi3ia-rehlat-bahria-rehla-ela-almohit-alhadi-2-8-2014.WMV"` |
| item | 9360 | 750 | missing_archive_host | `"/download/media-way2Allah---8/ben_ta3tk_llh_wta3a_alkhel__--_d._3bdalmhsn_alahmd-516.WMV"` | `"https://archive.org/download/media-way2Allah---8/ben_ta3tk_llh_wta3a_alkhel__--_d._3bdalmhsn_alahmd-516.WMV"` |
| item | 9483 | 16 | missing_archive_host | `"/download/media-way2Allah---32/ashhn_rsedk_fe_rmdan-8__.WMV"` | `"https://archive.org/download/media-way2Allah---32/ashhn_rsedk_fe_rmdan-8__.WMV"` |
| item | 9498 | 16 | missing_archive_host | `"/download/media-way2Allah---33_201408/d3wa_taeab.-73_.WMV"` | `"https://archive.org/download/media-way2Allah---33_201408/d3wa_taeab.-73_.WMV"` |
| item | 9589 | 751 | missing_archive_host | `"/download/media-way2Allah---15/nseha_lsharb_aldkhan_mn_alshekh_alshnkete-3198.WMV"` | `"https://archive.org/download/media-way2Allah---15/nseha_lsharb_aldkhan_mn_alshekh_alshnkete-3198.WMV"` |
| item | 9601 | 751 | missing_archive_host | `"/download/media-way2Allah---16/lmn_ashghlth_amwr_aldnea_mkt3_mosr_gda_llshekh_mhmd_alshnkete-694.WMV"` | `"https://archive.org/download/media-way2Allah---16/lmn_ashghlth_amwr_aldnea_mkt3_mosr_gda_llshekh_mhmd_alshnkete-694.WMV"` |
| item | 9604 | 751 | missing_archive_host | `"/download/media-way2Allah---16/mhba_allh_ll3bd_alshekh_mhmd_alshnkete-218.WMV"` | `"https://archive.org/download/media-way2Allah---16/mhba_allh_ll3bd_alshekh_mhmd_alshnkete-218.WMV"` |
| item | 9642 | 727 | missing_archive_host | `"/download/media-way2Allah---52/6___7__-_kss_w_3br_____nbel_al3wde-120.WMV"` | `"https://archive.org/download/media-way2Allah---52/6___7__-_kss_w_3br_____nbel_al3wde-120.WMV"` |
| item | 9688 | 16 | missing_archive_host | `"/download/media-way2Allah---65/kef_tode_shkr_alewm_wallela-2_114.WMV"` | `"https://archive.org/download/media-way2Allah---65/kef_tode_shkr_alewm_wallela-2_114.WMV"` |
| item | 9724 | 751 | missing_archive_host | `"/download/media-way2Allah---75/algm3_ben_hdes__kl_amte_m3afe__wben_mn_eza_khle_bmharm_allh_anthkha_llshekh_mhmd_alshnkete-1_-.WMV"` | `"https://archive.org/download/media-way2Allah---75/algm3_ben_hdes__kl_amte_m3afe__wben_mn_eza_khle_bmharm_allh_anthkha_llshekh_mhmd_alshnkete-1_-.WMV"` |
| item | 9826 | 16 | missing_archive_host | `"/download/media-way2Allah---97/la_tzhr_alshmata_bakhek...d_3bdalmhsn_alahmd-_3.WMV"` | `"https://archive.org/download/media-way2Allah---97/la_tzhr_alshmata_bakhek...d_3bdalmhsn_alahmd-_3.WMV"` |
| item | 9869 | 772 | missing_archive_host | `"/download/heekmaa702/tagharid-nesha-badry-salom-alsalom.WMV"` | `"https://archive.org/download/heekmaa702/tagharid-nesha-badry-salom-alsalom.WMV"` |
| item | 9870 | 772 | missing_archive_host | `"/download/heekmaa702/tagharid-sa3qra2-ayat-alkorsi.WMV"` | `"https://archive.org/download/heekmaa702/tagharid-sa3qra2-ayat-alkorsi.WMV"` |
| item | 9871 | 772 | missing_archive_host | `"/download/heekmaa702/tagharid-sayarti-alsaghira.WMV"` | `"https://archive.org/download/heekmaa702/tagharid-sayarti-alsaghira.WMV"` |
| item | 9872 | 772 | missing_archive_host | `"/download/heekmaa702/tagharid-shikolata-abd-magid-dosary.WMV"` | `"https://archive.org/download/heekmaa702/tagharid-shikolata-abd-magid-dosary.WMV"` |
| item | 9873 | 772 | missing_archive_host | `"/download/heekmaa702/tagharid-ta2er-alamal-nor-3oda.WMV"` | `"https://archive.org/download/heekmaa702/tagharid-ta2er-alamal-nor-3oda.WMV"` |
| item | 9874 | 772 | missing_archive_host | `"/download/heekmaa702/tagharid-taq-taqia.WMV"` | `"https://archive.org/download/heekmaa702/tagharid-taq-taqia.WMV"` |
| item | 9875 | 772 | missing_archive_host | `"/download/heekmaa702/tagharid-tota-tota-3abir-hegazy.WMV"` | `"https://archive.org/download/heekmaa702/tagharid-tota-tota-3abir-hegazy.WMV"` |
| item | 9876 | 772 | missing_archive_host | `"/download/heekmaa702/tagharid-yala-nolawin.WMV"` | `"https://archive.org/download/heekmaa702/tagharid-yala-nolawin.WMV"` |
| item | 9877 | 772 | missing_archive_host | `"/download/heekmaa702/taghrid-alroda-mohamed-abo-almofalfil.WMV"` | `"https://archive.org/download/heekmaa702/taghrid-alroda-mohamed-abo-almofalfil.WMV"` |
| item | 9955 | 430 | missing_vars_host_exact_copy | `"/cartoon/amin3/tabi3ia-3alam-alhaiwan-alghamid-ghabit-alghizlan-3-9-2014.WMV"` | `"http://vars.way2allah.com/cartoon/amin3/tabi3ia-3alam-alhaiwan-alghamid-ghabit-alghizlan-3-9-2014.WMV"` |
| item | 10467 | 796 | missing_archive_host | `"/download/medi-way2Allah--166/a3zm_alfrh_alfrh_ballh_-_d._hazm_shwman__mosr_gda-4_0.WMV"` | `"https://archive.org/download/medi-way2Allah--166/a3zm_alfrh_alfrh_ballh_-_d._hazm_shwman__mosr_gda-4_0.WMV"` |
| item | 10547 | 796 | missing_archive_host | `"/download/media-way2Allah--168/alkran_gherne_d_hazm_shwman-87_.WMV"` | `"https://archive.org/download/media-way2Allah--168/alkran_gherne_d_hazm_shwman-87_.WMV"` |
| item | 11172 | 420 | missing_archive_host | `"/download/promo_adab_shekh.ahmed_HD/promo_adab_shekh.ahmed_MED.wmv"` | `"https://archive.org/download/promo_adab_shekh.ahmed_HD/promo_adab_shekh.ahmed_MED.wmv"` |
| item | 14823 | 1082 | stray_character | `"رhttp://vars.way2allah.com/cartoon/amin8/cartoon-fariq-alse7a-fawz-ghir-motawaqa3-11-2021_x264.mp4"` | `"http://vars.way2allah.com/cartoon/amin8/cartoon-fariq-alse7a-fawz-ghir-motawaqa3-11-2021_x264.mp4"` |
| mirror | 10659 | 8562 | duplicated_url | `"https://archive.org/download/heekmaa701/mahboba-raw3et-alhejab-sama-osama_PocketPcVga.AVIhttps://archive.org/download/heekmaa701/mahboba-raw3et-alhejab-sama-osama_PocketPcVga.AVI"` | `"https://archive.org/download/heekmaa701/mahboba-raw3et-alhejab-sama-osama_PocketPcVga.AVI"` |
| mirror | 15966 | 12309 | leading_space | `" https://soundcloud.com/way2allahcom/al7ashr"` | `"https://soundcloud.com/way2allahcom/al7ashr"` |
| mirror | 15969 | 12308 | leading_space | `" https://soundcloud.com/way2allahcom/wa7y"` | `"https://soundcloud.com/way2allahcom/wa7y"` |
| mirror | 15970 | 12311 | leading_space | `" https://soundcloud.com/way2allahcom/jom3a"` | `"https://soundcloud.com/way2allahcom/jom3a"` |
| mirror | 15971 | 12312 | leading_space | `" https://soundcloud.com/way2allahcom/zekr-allah"` | `"https://soundcloud.com/way2allahcom/zekr-allah"` |
| mirror | 15978 | 12313 | leading_space | `" https://soundcloud.com/way2allahcom/oqfnebnwgebn"` | `"https://soundcloud.com/way2allahcom/oqfnebnwgebn"` |
| mirror | 15979 | 12314 | leading_space | `" https://soundcloud.com/way2allahcom/wfqm7e8fcriu "` | `"https://soundcloud.com/way2allahcom/wfqm7e8fcriu"` |
| mirror | 15981 | 12318 | leading_space | `" https://soundcloud.com/way2allahcom/nasab-omar "` | `"https://soundcloud.com/way2allahcom/nasab-omar"` |
| mirror | 15986 | 12319 | leading_space | `" https://soundcloud.com/way2allahcom/ahamyat-seera"` | `"https://soundcloud.com/way2allahcom/ahamyat-seera"` |
| mirror | 15987 | 12320 | leading_space | `" https://soundcloud.com/way2allahcom/dawabet-seera"` | `"https://soundcloud.com/way2allahcom/dawabet-seera"` |
| mirror | 15997 | 12323 | leading_space | `" https://soundcloud.com/way2allahcom/manaheg-sira"` | `"https://soundcloud.com/way2allahcom/manaheg-sira"` |


**Evidence and verification by group:**
- **27 `missing_archive_host` items:** `https://archive.org` + the stored path, verbatim; all 27 HEAD 200
  `video/x-ms-wmv` in F07-B1.
- **14823 `stray_character`:** the leading «ر» (U+0631) removed.
- **5 items and 10 mirrors `leading_space`:** trimmed. 15979 and 15981 also had a **trailing** space; both ends are
  removed.
- **10659 `duplicated_url`:** the value is exactly the same URL twice; one copy (HEAD 200 `video/x-msvideo`).
- **9106 / 9955 `missing_vars_host_exact_copy`:** the exact absolute URL stored in **item 9851** and **mirror 13857**
  respectively.
- `vars.way2allah.com` was not contacted; those rows are HIGH on local evidence only.

**Excluded, as instructed** (unchanged; asserted by tests):
- the 11 cosmetic trims (items 1427, 1668, 1669, 1670, 1814, 2061, 2062, 1921, 12485, 14856; mirror 116);
- item 16759;
- every MEDIUM row (184) and LOW row (25);
- every legacy-host row (31).

## 2. Command

```text
php artisan anasheed:repair-f07-links
    [--apply]                 write (default: dry run, read-only)
    [--rollback=<file>]       restore from a backup (dry run unless --apply)
    [--skip-conflicts]        with --rollback --apply: restore unchanged rows only
    [--actor=<aid>]           required for any write: super-admin, or anasheed.editanasheed AND anasheed.editmirror
    [--backup-dir=<dir>]      default storage/app/maintenance/anasheed-f07b2 (git-ignored)
    [--json]
```

**Exit codes:** `0` ready, applied, already applied, or rolled back without conflict. `1` blocked, refused, failed, or
any rollback conflict.

**Row states:**
- `pending`: holds exactly the old bytes;
- `applied`: holds exactly the new value;
- `missing`;
- `parent_changed`: the item's group or the mirror's item changed;
- `conflict`: any other bytes.

**Verdicts:**
- **ready:** at least one pending row and no blocking row. A mix of applied and pending is valid because rows are
  independent; a hand-made fix to exactly the approved value counts as applied.
- **already_applied:** "No repair necessary".
- **blocked:** any missing, parent-changed or conflicting row; nothing is written.

**`--apply`, in order:**
1. Authorise the actor.
2. Plan; it must be `ready`.
3. **Write and verify the backup** (§4).
4. One transaction on `main`:
   - re-plan with every row read `FOR UPDATE`;
   - for each pending row, `UPDATE … SET link = :new WHERE id = :id AND HEX(link) = :old_hex`, which must affect
     exactly 1 row, otherwise everything rolls back;
   - one `admin_audit_log` row per record: `repair_link`, module `anasheed`, entity `item` / `mirror`, metadata
     `{from: <exact old value>, from_hex, to, reason, ticket: b2_f07b2, backup_sha256}`.
5. Post-check after commit: the plan must be `already_applied`.

**Only `link` is written.** Every other column of every row is untouched; tests compare full snapshots. No discovery:
only manifest ids are ever read or written.

## 3. Database-specific comparison behaviour

- `link` is `TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci` (MySQL 8 names it `utf8mb3_unicode_ci`). It is
  **case-insensitive and PAD SPACE**: on MySQL, `link = 'x'` matches `'x '` and `'X'`. This was **proven on MySQL 5.7
  and 8.0** in `AnasheedF07LinkRepairMysqlTest`.
- **Every comparison uses `HEX(link)`**, the stored bytes:
  - **reads:** `SELECT HEX(link)` → `hex2bin`, so no driver or collation trims or folds;
  - **guards:** `WHERE HEX(link) = ?`.

  `HEX()` is byte-exact on MySQL, MariaDB and SQLite alike, so the SQLite flow tests and the production SQL are the
  same statements. `BINARY link = ?` would be MySQL-only.
- **Concurrent modification:**
  - the rows are locked `FOR UPDATE` and re-validated inside the transaction;
  - each `UPDATE` repeats the byte guard, so a value changed between the read and the write affects 0 rows, which
    aborts the transaction;
  - rollback uses the same guard against the repaired value.
- **Exact originals are preserved** in the audit metadata (`from` and `from_hex`) and in the backup (`old_hex`).
  Leading and trailing spaces and the Arabic «ر» survive the round trip; the rollback test restores them byte-exactly.

**MySQL / MariaDB test** (`tests/Feature/Admin/AnasheedF07LinkRepairMysqlTest.php`):
- **Gating:** env `W2A_MYSQL_BYTE_TEST_*`; skipped otherwise (as the existing `W2A_MARIADB_TEST_*` pattern).
- **Isolation:** it creates **only session-private `TEMPORARY` tables** (`w2a_f07b2_tmp_*`, never a real table name,
  invisible to other sessions, dropped with the session) with the production column definition.
- **What it runs:**
  - the command's own `plan()`, `storedBytes()` and `replaceExact()`;
  - not `apply()` / `rollback()`, which audit to the real `admin_audit_log`; their flow is covered on SQLite.
- **Results:**
  - **13 / 13 on MySQL 5.7.44** (`mysql57`, port 3307) and **13 / 13 on MySQL 8.0.41** (`mysql8-test`, port 3308);
  - no temporary table left (`information_schema` checked); `olddb` untouched.
- **What it shows:**
  - the hazard itself (plain equality matches a trailing-space or case variant, `HEX()` does not);
  - all 46 old values read back byte-exactly;
  - `replaceExact()` refuses 5 near-miss variants and writes exactly once;
  - `plan()` → apply primitive → `already_applied` → reverse primitive restores the exact bytes;
  - `plan()` blocks on collation-equal but byte-different values.
- **MariaDB:** no disposable MariaDB server is available locally (`maria-final` is not running), so it was not run on
  MariaDB. The same test runs there when `W2A_MYSQL_BYTE_TEST_*` points at one. MariaDB's `utf8_unicode_ci` is also
  PAD SPACE and case-insensitive, and `HEX()` is byte-based there too.

## 4. Backup and rollback procedure

- **Backup:** written **before** the transaction to `storage/app/maintenance/anasheed-f07b2/anasheed-links-<UTC>-<rand>.json`
  (directory 0750, file 0640, git-ignored).
  - **Content:** format, version, UTC time, actor, database, `manifest_sha256`, and every manifest row with the exact
    current bytes (`old_hex`), the new value and its state; plus a SHA-256 of the canonical JSON.
  - **Write:** temporary file → `fflush` + `fsync` → `rename` → read back and verify.
  - **On failure:** the command aborts before any write.
- **Rollback:**
  1. `--rollback=<file>`: a dry run listing each row as `restorable` (holds exactly the repaired value), `restored`
     (already the original) or `conflict`.
  2. `--rollback=<file> --apply --actor=<aid>`:
     - one transaction with row locks;
     - each restorable row via `UPDATE … SET link = :old WHERE id = :id AND HEX(link) = HEX(:new)`;
     - audited as `rollback_link` with `to` / `to_hex`;
     - **any conflict blocks everything**, and a later legitimate edit is never overwritten; with `--skip-conflicts`,
       only the unchanged rows are restored.
  - Repeating a rollback is a no-op.
  - A tampered (checksum), foreign or unreadable backup, or one whose rows differ from the manifest, is refused.

## 5. Dry run on the local copy (read-only)

```text
DRY RUN (no database write)
| item   | 4860  | leading_space | pending | " http://vars.way2allah.com/cartoon/3/3azoz_2.rmvb" | "http://vars.way2allah.com/cartoon/3/3azoz_2.rmvb" |
… (46 rows, all pending)
Rows: 46 (pending 46, applied 0). Verdict: ready
```

Every expected old value matched the local stored bytes exactly. The audit row count and both table checksums were
identical before and after, and no backup directory was created.

## 6. GetRight changes (`/var-series-{group}.grx`)

- `AnasheedGroupController::downloadGetright()` skips any item for which `VarietyMediaLink::isDownloadable($item->link)`
  is false: the F07-A rule, which is exactly what `/var-download-{id}` can serve.
- **Unchanged:**
  - the query (`hidden = 0`, `ORDER BY weight`);
  - the entry format (`URL:` / `File: C:\Way2Allah\الأناشيد\…\{title}.{ext}.GetRight`);
  - the headers (`private`, attachment, no application cache);
  - the route.
- **Effect:** broken entries no longer make GetRight save this site's 404 page or home page. On the local data that
  removes the 213 non-servable items (fewer once the repair runs). An all-broken group yields an empty list.

## 7. Mirror visibility changes (`/var-mirror-{item}-{mirror}`)

- **Before:** the route checked that the **item** is visible and that the mirror belongs to it, **not** the mirror's
  own `hidden` flag. The player already refused hidden mirrors. OLD `getmirror()` checked neither.
- **Now:**
  - the route also requires `nuke_anasheed_mirror.hidden = 0` (404 otherwise, GET and HEAD, no counter);
  - the item page's mirror card offers no download link for a hidden mirror, so no link leads to a 404.
- **Legitimate workflows affected: none found.** No code in Laravel, OLD or Gen 2 sets a Variety mirror's `hidden`
  flag, and 0 such rows exist locally. Visible mirrors are unchanged (same redirect and counter).
- The Khotab mirror route is unchanged (tested). Hidden-mirror cards are still listed on the item page (pre-existing;
  0 rows today).

## 8. Redirect safety (documented only; no allowlist)

Unchanged from F07-B1 §8:
- the Variety download routes redirect only to stored URLs, so this is **not an open redirect**;
- about 99.8 % of targets are archive.org, `vars.way2allah.com`, YouTube or SoundCloud;
- **the residual risk is abandoned third-party hosts** that could be re-registered:

| Host | Records |
|---|---|
| `sub4arab.com` | mirrors 118–138 (21) |
| `media.rashed.ws` | mirrors 157, 158, 159 |
| `way2allah.knoz4arab.com` | mirrors 139, 647 |
| `www.snapdrive.net` | mirror 114 |
| `media.majddoc.com` | items 46, 74 |

Plus item 17351 (a `google.com/url` redirector). **No allowlist was introduced** and no legitimate redirect changed.
No confirmed security issue requiring broader changes was found. The recommended owner decision remains to hide or
delete those rows.

## 9. Tests and results

| File | Result |
|---|---|
| `tests/Feature/Admin/AnasheedF07LinkRepairTest.php` (isolated SQLite harness) | **38 passed (197 assertions)** |
| `tests/Feature/Admin/AnasheedF07LinkRepairMysqlTest.php` (env-gated; TEMPORARY tables) | **13 / 13 on MySQL 5.7.44, 13 / 13 on MySQL 8.0.41**; skipped in the normal suite |
| `tests/Feature/Content/VarietyDownloadsF07b2Test.php` | **7 passed (28 assertions)** |

| Required | Covered by |
|---|---|
| exact scope | manifest = the independently written 35 + 11 ids; 9 replacement values asserted literally; control rows (cosmetic trims, 16759, a MEDIUM, a LOW, item 9851) never change |
| byte-exact matching; leading / trailing spaces; case | 7 byte-difference conflicts on SQLite (extra or dropped trailing space, tab, letter case, scheme case, Arabic prefix, later edit), and the same class plus the collation hazard on MySQL 5.7 / 8.0 |
| correct replacement | every manifest row equals its new value after apply; the MySQL write path stores the new bytes exactly |
| missing records; changed values; parent changes | blocked with the named problem; nothing written; no backup |
| partial state | 2 rows pre-fixed by hand → 44 applied and audited |
| transaction failure | a trigger aborts the 12th item update → all links unchanged, no audit |
| backup failure | uncreatable directory → refused before any write |
| audit | 46 `repair_link` rows, actor, exact `from` (leading space kept), `from_hex`, `to`; rollback audited |
| rollback; conflicts; repeat | byte-exact restore of all 46 (spaces and «ر» back); 2 later edits block; `--skip-conflicts` restores 44; repeat no-op; tampered / foreign / missing backup refused |
| repeated execution | second apply: nothing to do, no new audit or backup |
| no unrelated change | full snapshots of both tables equal apart from the manifest rows' `link` |
| GetRight | 9 link shapes: only servable entries, weight order, exact output bytes, headers; all-broken → empty; every listed URL leaves the site |
| hidden mirror | 404 on GET / HEAD, counter unchanged; visible mirror unchanged; mirrors of hidden / other items refused; no card link; player still refuses; Khotab mirror route unchanged |
| existing download / F07-A behaviour | related suites below |

**Related suites:** all Variety / Anasheed page and admin tests, F07-A, F05-2, mirror tests, downloads, contextual
admin, architecture and the three new files: **832 tests, 819 passed, 13 skipped** (the env-gated MySQL tests), 0
failed.

**Full suite** (`timeout 1500 vendor/bin/pest --log-junit … < /dev/null`): **3,998 tests: 3,946 passed, 52 skipped (39 pre-existing + the 13 env-gated MySQL byte tests, which passed separately on MySQL 5.7.44 and 8.0.41), 0 failures, 0 errors** (33,309 assertions, ~401 s), exit 0, no hang (3,940 before + 58 new).

**PHPStan:** passed, 0 errors. **Pint:** passed.

## 10. Production prerequisites (owner; nothing executed)

1. Deploy the new classes and the two controller changes. No migration.
2. **Read-only dry run on production:** `php artisan anasheed:repair-f07-links`.
   - It must show 46 rows with no `missing`, `parent_changed` or `conflict`.
   - Rows already `applied` are fine.
   - **Any discrepancy: stop**; the manifest is never edited to fit.
3. Optionally run the MySQL byte test against a **disposable** copy of the production engine (MariaDB if production is
   MariaDB) with `W2A_MYSQL_BYTE_TEST_*`.
4. Re-check the 28 archive.org URLs (27 items and mirror 10659) shortly before applying. The owner opens the vars
   URLs of items 14823, 4860–4893, 9106 and 9955 (not contacted here).
5. A normal database backup, then `--apply --actor=<aid>` at a quiet time.
6. Keep the backup file off the server.
7. Verify with a second dry run (`already_applied`), the Variety pages and the downloads.

## 11. Remaining MEDIUM / LOW cases (unchanged)

- **184 MEDIUM** (F07-B1 §1.2):
  - 173 host-less `/cartoon/amin2|3/…` items;
  - items 16755–16758, 12581, 17310;
  - mirrors 9311, 9575, 9590, 15842, 10255.

  They need the owner's `vars.way2allah.com` spot-check and a separate approval (a second manifest wave).
- **25 LOW** (F07-B1 §1.3):
  - 11 truncated `media-w..._hane_hlme` rows;
  - 15030, 12195, 6485, 10796, 8120, 6270, 17351;
  - mirrors 156, 11604, 18732.

  These need manual decisions.
- **Item 16759** (duplicate decision), the **11 cosmetic trims**, and the **31 legacy-host rows**.
