# AdminCP Production Feedback: Investigation Report (F01–F10)

**Date:** 2026-11-12
**Status:** investigation only. No code, data, configuration or production change was made.

**Sources read:**

| Source | Location |
|---|---|
| Laravel | `laravel-project/` (uncommitted working tree) |
| Gen 2 legacy | `7amlat/` (read-only) |
| OLD PHP-Nuke | `old-project/` (read-only) |
| Production snapshot | the 2025 SQL dump `7amlat/w2acp_db2025-2.sql` (read-only parsing; this is **not** live production) |
| Project records | the decision log and review documents |

**Evidence labels used below:**

| Label | Meaning |
|---|---|
| **CONFIRMED** | verified in code (file:line) or in the 2025 dump |
| **INFERRED** | follows from confirmed code, not observed on production |
| **MISSING EVIDENCE** | needs an item id, a URL, a screenshot or a live-DB check from the owner |

---

## 1. Executive summary

| ID | Feedback | Classification | One-line finding |
|---|---|---|---|
| F01 | Advertisement management missing | **Actual gap** (+ discoverability for Advfiles) | OLD «الإعلانات» (`nuke_ads` campaigns), «إعلانات بنرات المواد» and «الإبلاغ عن اعلانات غير لائقة» have **no Laravel admin and no Laravel public rendering**. They are a different system from Advfiles, which **exists** in Laravel as «المرئيات» → «ملفات الرئيسية» |
| F02 | Add admins / edit permissions | **Existing functionality + role/discoverability**; one small gap | Add and list exist (`admincp/staff`). The permission editor exists but is **super-admin only** and is reachable only from a per-row link in the staff list. Non-super admins get 403. Deleting or demoting an admin (OLD `mod_authors`) is not implemented |
| F03 | New Khotab series visible in legacy, not Laravel | **Likely behavioural difference (admin view of empty series)**; not confirmed on production | Every public list hides series with `count = 0`. OLD shows admins **all** series; Laravel shows admins empty series only for standalone series, **never inside a group** |
| F04 | «حفظ المادة» opens the video | **Legacy parity, external-host behaviour**; Khotab already downloads | Anasheed, Telawah, Fatwa and lesson «حفظ» are **302 redirects** to the media URL, in OLD, Gen 2 and Laravel alike. The browser plays what archive.org serves inline. Only Khotab is proxied with `Content-Disposition: attachment` |
| F05 | Medium quality fails to play | **Data/format root cause (confirmed in the dump)** + one Laravel gap | 93% of mirrors labelled «متوسط» in the 2025 dump are `.rmvb`/`.wmv`/`.rm`/`.wma`/`.avi`/`.flv` (no browser plays them), and 98.6% are `http://`. Laravel's player normalises archive.org links for **Anasheed but not Khotab** |
| F06 | Author image uploads but does not show | **Confirmed defect (inherited inconsistency)** | The upload writes `media/authors/{id/1000}/{id}.jpg`, which only the admin preview reads. Public pages read the `author_image` column or `media/authors/sq/{id}.png` |
| F07 | Variety video on `khotab-group-{id}` fails | **Same mechanism as F05 (inferred)**; module and URL ambiguous | The player plays only `vedio = 1` + `.mp4` or YouTube. On a Khotab group page the Khotab player uses the raw link (no archive.org normalisation, no https). «منوعات» in this site means **Anasheed**, which conflicts with the reported `khotab-group` URL |
| F08 | Hide a variety clip | **New requirement**; no schema change needed | `nuke_anasheed_anasheed.hidden` exists and is partly honoured, but there is **no admin toggle**, and the group page and home lists ignore `hidden`. `is_published` («نشر علي المنصة») is stored but has no public effect |
| F09 | "Transfer/copy alternate link" option | **Existing legacy-parity feature; explanation only** | Two features match the wording: «نقل الوصلة» (re-parent a mirror to another item) and «إنزال نسخة إحتياطية ضمن أرشيف الموقع» (`backupme`: queue for the volunteer Desktop backup app). Neither moves files or changes playback |
| F10 | Custom material thumbnail | **New requirement** | Thumbnails are the downloaded archive.org still frame at `media/khotab_frames/{id/1000}/{id}.jpg` (`frame = 1`). A custom upload can reuse that path with a "custom" guard; no schema change is strictly required |

**Shared root causes:**

- **R1 (F05, F07, partly F04):** media URLs are stored raw: legacy formats, `http://` links, retired archive.org nodes.
  The Khotab player passes them through unchanged.
- **R2 (F06, F10):** the legacy media-path conventions diverge (OLD's bucketed jpg versus Gen 2's column and `sq/*.png`).
- **R3 (F01, F02):** discoverability and role gates. Navigation shows Advfiles as «ملفات الرئيسية», and the permission
  editor only as a row link that requires super-admin.

---

## 2. Findings per item

### F01 — Advertisement management

1. **Classification.** An **actual gap** for OLD's ad system. Advfiles is **existing functionality** under another
   name.

2. **Legacy behaviour (CONFIRMED).**

   OLD PHP-Nuke `admin.php` has three ad-related admin modules:

   | Menu label | op | Module |
   |---|---|---|
   | «الإعلانات» | `w2a_ads_main` | `old-project/modules/ads/admin/links.php` |
   | «إعلانات بنرات المواد» | `w2a_banners_ads_main` | `modules/banners_ads/admin/links.php` |
   | «الإبلاغ عن اعلانات غير لائقة» | `w2a_bad_ads_main` | `modules/report_ads/admin/links.php` |

   **«الإعلانات»** (`modules/ads/admin/index.php`):

   | Function | Lines | What it does |
   |---|---|---|
   | `add_ads` | 324 | create an ad: name, image upload/URL, `position`, `percentage` share, `type`, `show`, `link`, start and end dates, Google ad code, `ads_show_type`, view limit |
   | `edit_ads` | 414 | edit an ad |
   | `del_ads` | 573 | delete an ad |
   | `update_show` | 597 | toggle `show` |
   | availability check | 627 | position and percentage collision check per date range |
   | `view_report` | 968 | reports and charts |

   **Table:** `nuke_ads` (`id, name, image_path, position, percentage, type, ads_show_type, required_num_view, show,
   link, startdate, enddate, num_view, num_click, path_type`).

   **Public rendering:** Gen 2 `home_functions.php:437` `show_ads_byposition()`, used at `new_content.php:333` (position
   3) and elsewhere.

   **Related modules:**

   - `banners_ads` bulk-assigns banners to Khotab items by author or category.
   - `report_ads` lists and deletes reports of inappropriate ads.

   **Migration record:** `docs/reviews/old-admin-scope-reset.md:109` lists `ads` / `banners_ads` / `report_ads` as
   "absent … Priority 2 … needs detailed reconciliation". No later closure decision exists.

3. **Laravel behaviour (CONFIRMED).**

   - **No ad admin:** no route, controller or menu entry manages `nuke_ads`.
   - **No public rendering:** `app/Domain/Content/Models/HomeAd.php` maps `nuke_ads`, but **nothing references it**.
   - **Advfiles is separate and exists:**
     - routes `admincp/advfiles` (`routes/admin.php:651-666`);
     - menu «المرئيات» → «ملفات الرئيسية» (`AdminDashboardModules.php:141`);
     - permission `khotab.addadv`; request flow `admincp/advfiles/items/{id}/request` (any admin).

4. **Database.** `nuke_ads`. The 2025 dump holds **1 row**: `show = 0`, position 4, `link = 'ttps://www.youtube.com/…'`
   (malformed), image `media/bann/1742505317.jpg`.

5. **Reproduction.**

   | Check | Result |
   |---|---|
   | Laravel admin menu | no ad entry |
   | `grep HomeAd` | model only, no consumer |
   | Gen 2 homepage | calls `show_ads_byposition(3)` |

6. **Root cause.** CONFIRMED: the module was never migrated, and the scope reconciliation left it open.

7. **Proposed solution.** Owner decision first:

   - **(a)** Retire it: one inactive ad in 2025, and Advfiles covers "featured" content.
   - **(b)** Rebuild a minimal manager: list, add, edit, delete, show toggle and dates on `nuke_ads`, plus public slots
     at the positions Gen 2 renders.
   - **(c)** Add only public rendering, managed elsewhere.

   Also consider a menu-label clarification: «ملفات الرئيسية» is the Advfiles feature.

8. **Regression risk.** Low for (a). Medium for (b): public homepage changes, and the `show` reserved-word quoting
   noted in `HomeAd`.

9. **Owner decision required: YES.** Which of the three systems are still wanted?

### F02 — Administrator and permission management

1. **Classification.** **Existing functionality.** The feedback is explained by **role gating and navigation**. There
   is one **actual gap**: deactivate, delete or demote an admin.

2. **Legacy behaviour (CONFIRMED).**

   - **Gen 2 «المشرفين»** (`7amlat/admincp/authors/menu.php`):
     - «إضافة مشرف» (`addstuff`, from a vBulletin member) and «قائمة المشرفين» (`liststuff`);
     - each row links to `edit_author.php`, which edits the permissions and an md5 password;
     - **no** permission check of its own: any logged-in admin could edit anyone's permissions
       (`edit_author.php:47-69`).
   - **OLD `admin.php?op=mod_authors`** (`old-project/admin/modules/authors.php`):
     - restricted to `radminsuper = 1` **and** specific names (`:20-21`);
     - add, modify (name, email, URL, super flag, password, modules) and delete (`deladmin`, `:688-719`);
     - Gen 2's staff page linked to OLD `modifyadmin` for editing.

3. **Laravel behaviour (CONFIRMED).**

   | Function | Route | Gate | Menu |
   |---|---|---|---|
   | List | `admincp/staff` | `authors.liststuff` | «المشرفين» → «قائمة المشرفين» |
   | Add (vBulletin member) | `admincp/staff/create` | `authors.addstuff` | «المشرفين» → «إضافة مشرف» |
   | Edit permissions + password | `admincp/permissions/{admin}` | **`admin.role:super-admin`** (`routes/admin.php:103`, decision-log #10) | **none**: per-row «تعديل الصلاحيات» in the staff list only |
   | Delete or demote | — | — | not implemented. `authors.deletestuff` is seeded but unused (dead in Gen 2 too, decision-log reconciliation) |

   The staff list shows the «تعديل الصلاحيات» link to **every** viewer of the list. For a non-super admin it leads to
   **403**.

4. **Database.** `nuke_authors` (legacy) and the Spatie `admin` guard tables (roles and permissions).

5. **Reproduction.** By code: a non-super-admin holding `authors.liststuff` sees the link and gets 403. MISSING
   EVIDENCE: the reviewer's role. The likely trigger is "not super-admin".

6. **Root cause.**

   - INFERRED: the reviewer is not a super-admin, or did not find the editor because it has no menu entry.
   - CONFIRMED: deletion and demotion are missing.

7. **Proposed solution.** No rebuild.

   - **(a)** Show «تعديل الصلاحيات» only to super-admins, and explain the restriction on the staff page.
   - **(b)** Optionally add a super-admin-only «الصلاحيات» menu entry.
   - **(c)** If wanted: an "deactivate admin" action (remove the role and permissions; never delete the row), super-admin
     only, audited.

8. **Regression risk.** Low (view and menu only). Medium for (c): authorization semantics.

9. **Owner decision required: YES.**
   - Keep editing super-admin-only (recommended; legacy Gen 2 was over-permissive)?
   - Is deactivate or delete required?

### F03 — Newly created Khotab series not visible in Laravel

1. **Classification.** Most likely a **behavioural difference for admins viewing an empty series**. Not confirmed with
   production data.

2. **Legacy behaviour (CONFIRMED).**

   - **OLD `ListSeries()`** (`old-project/modules/Khotab/index.php:518-527`): an **admin** sees every series of the
     author and group, hidden and **empty** included. Visitors see `hidden = '0' AND count > '0'`.
   - **Gen 2 `ListSeries()`** (`7amlat/khotab/functions.php:409-429`): `count > 0`, with hidden optionally included.
   - **OLD insert** (`old-project/modules/Khotab/admin/index.php:1993`): a new series has `count = 0` (the column
     default) and `lastupdate = 0`.
   - **Counter:** `count` is incremented only when an item is added to the series (`:1110`).

3. **Laravel behaviour (CONFIRMED).**

   | Function | Filters |
   |---|---|
   | `ContentListingService::seriesByAuthorAndGroup()` (`:234`) | always `count > 0`; `hidden` dropped only when `includeHidden` |
   | `adminStandaloneSeriesByAuthor()` (`:262`; used by `KhotabAuthorController:252`) | shows admins hidden and empty series, but **only `group_id = 0`** |
   | The **group page** (`KhotabGroupController:36`) | calls `seriesByAuthorAndGroup()` **without** the admin flag. An admin never sees a new empty series inside a group |
   | Category pages (`seriesByCategoryAndGroup()` `:279`) | require `series_category_index` + `hidden = 0` + `count > 0` |

   Other properties:

   - **Ordering:** `lastupdate DESC`. A new series with `lastupdate = 0` sorts last; there is no pagination on these
     lists.
   - **Cache:** none on series lists. Only the homepage "latest" blocks are cached (300 s).
   - **Category index:** `series_category_index` is maintained by DB triggers (`7amlat/db_migration.sql:355+`).
     Neither OLD nor Gen 2 nor Laravel writes it directly.

4. **Database fields.** `nuke_islamic_series.count`, `hidden`, `group_id`, `vedio`, `author_id`, `lastupdate`; the
   `series_category_index` rows.

5. **Reproduction.** Code-level: create a series in a group with no items, then open `khotab-group-{id}` as an admin.
   OLD shows it; Laravel does not. Not executed against production.

6. **Root cause.** Leading hypothesis H1: an empty series inside a group, seen as an admin in OLD. Alternatives:

   | ID | Alternative |
   |---|---|
   | H2 | `count` drifted to 0 despite items (fixable with Laravel's «إصلاح العدادات», `khotab.editauthor`) |
   | H3 | wrong `vedio` (shown on the audio page instead of video) |
   | H4 | missing production DB triggers, so no category-index row |

   **MISSING EVIDENCE:** the series id. Read-only queries:

   ```sql
   SELECT id, author_id, group_id, vedio, hidden, count, lastupdate
     FROM nuke_islamic_series WHERE id = <id>;
   SELECT COUNT(*) FROM nuke_islamic_khotab WHERE ser_id = <id>;
   SHOW TRIGGERS LIKE 'nuke_islamic_series';
   ```

7. **Proposed solution.**

   - If H1: let an admin see a group's hidden and empty series on the group page, as OLD did (marked «مخفية» / «فارغة»
     in the admin view only). Visitors stay unchanged.
   - If H2: run the existing repair-counts tool.

8. **Regression risk.** Low: admin-only branch, with a test per role.

9. **Owner decision required: YES.** Confirm the series id, and approve the admin-only visibility change.

### F04 — «حفظ المادة» opens the video instead of downloading

1. **Classification.** **Legacy parity**, governed by the external host. **Not** a regression. Khotab's own button
   already downloads.

2. **Legacy behaviour (CONFIRMED).** Redirects everywhere:

   - OLD `getit()`: `Header("Location: $Anasheed[link]")` (`old-project/modules/Anasheed/index.php:626`; Khotab
     `:1350`; Telawah `:470`).
   - Gen 2 `download_anasheed()`: `Header("Location: " . $_link)` (`7amlat/anasheed/functions.php:450-490`). Its older
     proxy and force-download code is commented out.
   - Gen 2 `khotab/item.php:202`: the button links to `khotab-download-{id}.htm`.

3. **Laravel behaviour (CONFIRMED).**

   - **The button:** `resources/views/components/content/media-details-card.blade.php:76`, `<a target="_blank">`, used
     for Khotab, Anasheed, Telawah and chat lessons. Fatwa uses `fatawa/question-all.blade.php:232`.
   - **Per module:**

     | Module | Route | Behaviour |
     |---|---|---|
     | Khotab | `khotab-download-{id}` | **proxied** with `Content-Disposition: attachment`, so it always saves |
     | Anasheed | `var-download` / `var-mirror` | 302 to the normalised https archive.org URL |
     | Telawah | `recite-download` | 302 |
     | Fatwa | `fatawa-download` | 302 |
     | Lessons | `lesson-download` | 302 |
     | Mobile | `mobile-file` | 302 |

   - **Effect of the redirect:** the browser receives archive.org's own response for an `.mp4` or `.mp3`, which modern
     browsers **play inline**.
   - **Unreleased download changes** (HEAD, Content-Type, media redirects) do **not** change the redirect routes' GET
     behaviour.

4. **Fields.** The `link` of each module's item and mirror tables.

5. **Reproduction.** By code and local lab: `var-download` returns 302 to archive.org, and the browser plays the file.
   **MISSING EVIDENCE:** the exact page and button where the reviewer clicked.

6. **Root cause.** CONFIRMED for the redirect modules. For Khotab it is not reproducible (attachment), unless the item
   was a YouTube or SoundCloud mirror: after the release that's a 302 to the page; today it's a 500 or an HTML file.

7. **Proposed solution.** Owner choice:

   - **(a)** Keep the behaviour, and add a hint («للتحميل: اضغط بزر الفأرة الأيمن ثم حفظ باسم»).
   - **(b)** Proxy Anasheed and Telawah downloads through the capacity-guarded streamer. That is a **large capacity and
     cost change**; after the 400-slot incident it is not recommended without the capacity evidence.
   - **(c)** Rename the button for redirect modules («فتح / تحميل المادة»).

   The HTML `download` attribute does not work cross-origin, so it is not a fix.

8. **Regression risk.**
   - (a) and (c): none.
   - (b): high (capacity, bandwidth, and the download infrastructure that is out of scope now).

9. **Owner decision required: YES.**

### F05 — Medium-quality playback failure on `khotab-item-{id}`

1. **Classification.** **Data and format root cause** (confirmed in the dump), plus **one Laravel gap** (no archive.org
   normalisation for Khotab).

2. **Legacy behaviour (CONFIRMED).**

   - **"Qualities":** these are **mirrors** (`nuke_islamic_mirror`), titled by `comment` (for example «جودة متوسطة»).
   - **Gen 2 `w2a_mada_play()`** (`7amlat/functions.php:794-855`):

     | Link | Player |
     |---|---|
     | `.mp4` | `<video>` |
     | `.mp3` | `<audio>` |
     | `.rmvb` / `.avi` / `.wmv` | RealPlayer / Windows Media / Flash embeds, **dead in every modern browser** |

   - **Archive.org normalisation:** Gen 2 never applied `fix_archive_links()` to Khotab item mirrors; only
     `channels/old.php` did.

3. **Laravel behaviour (CONFIRMED).**

   - **The player** (`MediaPlayerService::renderPlayer()` `:254-283`) renders only:
     - YouTube (`vedio = 1`);
     - `.mp3` audio;
     - SoundCloud (`vedio = 0`);
     - `.mp4` video when `vedio = 1`;
     - otherwise **nothing**.
   - **No normalisation for Khotab:** `fromKhotabItem()` and `fromMirror()` (`:123-152`) pass the **raw** link. Anasheed
     (`:165`, `:186`) applies `ArchiveOrgLink::normalize()`, which rewrites retired storage-node URLs
     (`ia…us.archive.org/…/items/…`) to `https://archive.org/download/…` (decision-log #87 verified those nodes no
     longer serve).
   - **Card:** `resources/views/components/content/media-quality-list.blade.php`.

4. **Data (CONFIRMED, 2025 dump, 426,052 mirror rows).**

   **The 2,034 mirrors labelled «متوسط»:**

   | Format | Rows |
   |---|---|
   | `.rmvb` | 1,509 |
   | `.wmv` | 261 |
   | `.mp4` | 118 |
   | `.rm` | 73 |
   | `.wma` | 28 |
   | `.mp3` | 17 |
   | `.avi` | 15 |
   | `.flv` | 5 |

   - **~93% are not browser-playable.**
   - **2,005 (98.6%) use `http://`.**
   - **Medium `.mp4` links mostly point at pinned storage nodes** over http: `ia310821.us.archive.org` 41,
     `ia331413…` 20, `ia310814…` 9.

   **All mirrors:**

   - 126,107 `http://` links, and 70 with a leading space;
   - 604 `.mp4` links flagged `vedio = 0`, which get no video player.

   **Items with both a medium mirror and another `.mp4` mirror:** 59 (examples 76005, 106728, 137681).

5. **Reproduction.** By data and code. **MISSING EVIDENCE:** the exact `khotab-item-{id}` and mirror id.

6. **Root cause.**

   - CONFIRMED class: (i) a non-browser format; (ii) an http link to a retired archive.org node, not normalised for
     Khotab; (iii) a `vedio` flag mismatch.
   - Which one applies to the reported item is MISSING EVIDENCE.

7. **Proposed solution.**

   - **(1)** Apply `ArchiveOrgLink::normalize()` + https to the Khotab item and mirror player sources, exactly as for
     Anasheed. The stored URL stays unchanged.
   - **(2)** For non-playable formats, show «هذه الجودة غير قابلة للتشغيل في المتصفح — يمكنك تحميلها» and a download
     link, instead of a blank or dead player.
   - **(3)** Optionally, an admin report listing mirrors with non-playable formats or mismatched `vedio`.

8. **Regression risk.**
   - (1): low (the same normaliser is in production use for Anasheed); tests per URL shape.
   - (2): UI only.

9. **Owner decision required: YES** for (2) and (3). (1) is a defect fix, needing deployment approval.

### F06 — Khotab author image not displayed

1. **Classification.** **Confirmed defect**: an inconsistency inherited from Gen 2 and faithfully reproduced.

2. **Legacy behaviour (CONFIRMED).**

   - **OLD:** the upload `UploadAuthorImage()` (`old-project/modules/Khotab/admin/index.php:2387`) and the display
     (`modules/Khotab/index.php:1673-1675`) **both** use `media/authors/{floor(id/1000)}/{id}.jpg`.
   - **Gen 2:** the display is `!empty($Author->author_image) ? $Author->author_image : get_author_img($id)`
     (`7amlat/khotab/author.php:104`, `authors.php:77`). `get_author_img()` (`functions.php:1143`) gives
     `media/authors/sq/{id}.png` or `no_author_image.png`. Gen 2 has **no** upload.

3. **Laravel behaviour (CONFIRMED).**

   - **Upload:** `admincp/khotab/authors/{id}/{video|audio}/image` → `UploadKhotabAuthorImageAction` writes **only the
     file** `admin_khotab.media_root/authors/{b}/{id}.jpg`, with no DB row.
   - **Admin preview** (`KhotabAuthorAdminController::edit` `:44-49`): that same file + `?v=mtime`, so it is
     **visible**.
   - **Public pages** (author page `khotab/author.blade.php:89`; listing and preacher cards
     `components/content/preacher-card.blade.php:7`): `Author::displayImageUrl()` reads the `author_image` column, then
     `media/authors/sq/{id}.png`, then the placeholder. They **never read the uploaded jpg**.

4. **Fields.**
   - `nuke_islamic_authors.author_image`;
   - the files `media/authors/{b}/{id}.jpg` and `media/authors/sq/{id}.png`;
   - the config `admin_khotab.media_root`, versus the public media URL root.

5. **Reproduction.** By code: upload and the preview shows it; the public page shows the placeholder or the older image.
   Upload success is **not** public-URL accessibility.

6. **Root cause.** CONFIRMED: the upload path and the display path diverge.

   **Secondary risks (INFERRED):**

   - `admin_khotab.media_root` must be the directory the public media URL serves (see
     `docs/operations/split-layout-media-roots.md`);
   - a replaced file with the same name may be cached by browsers or a CDN.

7. **Proposed solution.** Smallest: on upload, also set `author_image` to the uploaded media path with a version
   (`media/authors/{b}/{id}.jpg?v={mtime}`). `displayImageUrl()` already resolves `media/…` through `MediaUrl`. That
   keeps the legacy files untouched, and the column takes priority, as in Gen 2.

   Alternative: extend the fallback chain to try the bucketed jpg before `sq/*.png`.

8. **Regression risk.** Low. Check the Fatwa author pages that share `Author`, and the existing `author_image` values,
   which must not be overwritten unless an upload happens.

9. **Owner decision required: minor.** Should a new upload override an existing `author_image` value? Recommended: yes.

### F07 — Variety video playback failure on `khotab-group-{id}`

1. **Classification.** **Likely the same mechanism as F05** (INFERRED). The module is ambiguous.

2. **Facts (CONFIRMED).**

   - **«منوعات» means Anasheed:** the site navigation (`layouts/partials/navigation.blade.php:66`) and «لوحة تحكم قسم
     المنوعات» on `var-group-{id}` pages (`anasheed/group.blade.php:29`).
   - **A `khotab-group-{id}` page** lists **Khotab** items (`khotab/group.blade.php:110`). Their play buttons use the
     Khotab player (raw link, F05).
   - **Saving a Khotab item** (`StoreKhotabItemRequest:27,75`): the link is stored **as entered** (trimmed, duplicate
     check only); no format or https validation.
   - **Saving an Anasheed item:** the player normalises archive.org links.
   - **"Player appears but fails"** means a `<video>` was rendered: the row has `vedio = 1` and an `.mp4` link. The
     source then failed. Plausible causes:
     - an `http://` or retired-node link;
     - a file still processing on archive.org after a fresh upload;
     - a codec browsers do not support, such as HEVC.

3. **MISSING EVIDENCE.**
   - The exact URL (`khotab-group` versus `var-group`);
   - the item id and its stored link;
   - whether the file plays when opened directly in the browser.

4. **Proposed solution.**
   - **If Khotab:** F05 (1) fixes the retired-node and http causes.
   - **If Anasheed:** check the stored link (normalisation is already applied) and archive.org processing.
   - **Optional save-time warning** (not a block) when a video link is `http://`, not `.mp4`, or points at
     `/details/`.

5. **Regression risk.** As F05.

6. **Owner decision required: YES.** Confirm the URL and item. The save-time warning is a UI addition.

### F08 — Hide a variety clip from publication

1. **Classification.** A **new requirement**. Smallest implementation: **no schema change**.

2. **Existing fields (CONFIRMED).**

   - **`nuke_anasheed_anasheed.hidden`:** honoured by Laravel on the item page, the player, `var-download`, `var-mirror`
     and some listings. The item page suppresses details, mirrors and comments for **everyone** but still renders with
     200, as OLD parity A6 (`AnasheedItemController:101-104`).
   - **`is_published`** («نشر علي المنصة», `InsertAnasheedItemAction:24`, `UpdateAnasheedItemAction:93`): stored, but
     **read nowhere** in the public code. Its meaning ("the platform", presumably the app or another system) is
     unclear.
   - **No hide toggle** exists in the Laravel Anasheed admin, nor in OLD's Anasheed admin.
   - **Listings ignore `hidden`:**
     - the group page (`AnasheedGroupController` docblock `:33`, legacy parity);
     - home trending and latest (`ContentListingService` around `:2334` and `:2356`).
   - **Listings that filter `hidden`:** category, advanced search, group-download.

3. **Effects of hiding (proposal).**

   | Surface | Current | Proposed |
   |---|---|---|
   | Public listing (group, home, news, related, top-10) | still listed | excluded |
   | Search | already excluded | unchanged |
   | Direct `var-item-{id}` URL | 200 with details suppressed | **owner choice:** 404 for visitors (admin preview kept), or keep 200 |
   | Video playback | refused | unchanged |
   | Download links | 404 | unchanged |
   | Admin visibility | — | visible with a «مخفية» badge, plus a toggle |
   | Related content | may list it | excluded |

4. **Proposed solution.**
   - A «إخفاء/إظهار المادة» toggle (permission `anasheed.editanasheed`, audited) writing `hidden`.
   - Add the `hidden = 0` filter to the listing queries that lack it.
   - Owner choice on the direct URL.

5. **Regression risk.** Medium. Several listing queries change; caches (home latest, 300 s) may show the item for up
   to 5 minutes.

6. **Owner decision required: YES.**
   - The direct-URL behaviour.
   - Whether `is_published` is the intended field instead.

### F09 — "Transfer/copy alternate link" option

1. **Classification.** **Existing legacy-parity features.** Needs documentation and labelling only.

2. **Option A: «نقل الوصلة» / «نقل الوصلة إلى مادة أخرى»** (`admincp/mirrors/khotab/…`: `show.blade.php:34,64`,
   `edit.blade.php:43`).

   - **Code:** `MoveKhotabMirrorAction` (OLD `MoveMirror`, `admin/index.php:342-372`).
   - **Enabled / used:** in one transaction:
     - `nuke_islamic_mirror.khid` becomes the target item id;
     - the target's `nuke_islamic_khotab.mirror` count goes +1, the source's −1;
     - `lastmirror` is untouched.
   - **Not done:** no file is moved or copied; the URL (`link`), `comment` and `hits` stay the same.
   - **Effect:** only **which item** lists the mirror. It disappears from the source item's «الجودات» and appears on
     the target's.
   - **Example:** a mirror (id 500) whose link is `https://archive.org/download/x/lecture_low.mp4`, under item 101.
     Choose «نقل» → open item 202 → «نقل الوصلة هنا». Now `khid = 202`, item 101 `mirror` −1, item 202 `mirror` +1.
     Item 202's page offers that same file. Nothing is copied.
   - **Permission:** `khotab.editmirror`. The clipboard state lives in the session.

3. **Option B: checkbox «إنزال نسخة إحتياطية ضمن أرشيف الموقع»** (`backupme`), on the mirror add and edit forms
   (Khotab, Anasheed, Telawah).

   - **Enabled** (`backupme = 1`): the mirror enters the **volunteer backup queue** served by `backup.php` /
     `BackupApiController`:
     - the Desktop backup App asks for work (`myop=get`; categories 2 and 5 require `backupme = 1`, `down = 2`,
       `trial < max`, `booking = 0`; category 2 also requires `id > 104839`);
     - a volunteer downloads the file into the site's offline archive and reports back (`myop=put`);
     - the row becomes `down = 1`, `linksize` and `online` = size, `downloader`, `checktime`.
   - **Disabled:** never queued.
   - **New mirrors** start at `down = 2` (the column default), so ticking the box queues them at once. Editing resets
     `trial = 0` so a corrected link re-enters the queue (`UpdateKhotabMirrorAction:15-18`).
   - **Not done:** no server-side copy or move, no URL change, no effect on playback or download.
   - **Parity note:** OLD disabled the link field for non-super admins once `down = 1` (`admin/index.php:579`). Laravel
     has no such lock (minor).

4. **Proposed solution.** Clarify the labels; for example «نقل الوصلة إلى مادة أخرى (لا يتم نسخ الملف)» and
   «إضافة الوصلة لقائمة النسخ الاحتياطي (برنامج الأرشفة)», with help text. Optionally restore OLD's link lock after
   backup.

5. **Regression risk.** None for the labels; low for the lock.

6. **Owner decision required: YES.** Which option the reviewers meant (screenshot), and the label wording.

### F10 — Custom material thumbnail

1. **Classification.** A **new requirement**.

2. **Current behaviour (CONFIRMED).**

   - **Source:** on item creation the admin picks an archive.org still frame (`archive_frame`, `InsertKhotabItemAction`
     `:43,159`). It is downloaded to `media/khotab_frames/{id/1000}/{id}.jpg` and `frame = 1` is set; an animated gif
     goes to `khotab_gifs` with `gif = 1`.
   - **Re-fetch:** `LeechKhotabItemImagesAction` and `KhotabArchiveImages` can re-download the frame.
   - **Display:** sidebar widgets (`ContentSidebarWidget:1062-1090`), top and random lists, and the social share image
     (`KhotabItem::socialImageUrl()`); each falls back to the default image when the file is missing.
   - **Unused columns:** `nuke_islamic_khotab.thumbnail` (text) and `build_thumb` are used by neither Gen 2 nor Laravel
     for Khotab. Whether the `thumbnail` column holds production data is **unverified**: the dump parse ran out of
     resources.
   - **Existing upload infrastructure to copy:** the author image upload (JPG checks, audit) and the item banner upload
     (`khotab.uploadbanner`).

3. **Proposed backward-compatible design.**

   - **Upload:** «رفع صورة مخصصة للمادة» (JPG/PNG/WebP, size limit, permission `khotab.edititem` or a new
     `khotab.uploadthumb`, audited).
   - **Storage:** `media/khotab_frames/{b}/{id}.jpg` (converted to JPEG), setting `frame = 1`, so **every existing
     display path picks it up with no change**.
   - **Protection:** keep the archive frame from overwriting a custom image. Either a marker file
     `media/khotab_frames/{b}/{id}.custom`, or a new nullable column (preferred if a schema change is acceptable,
     because it is auditable). Re-fetch actions skip custom images unless the admin chooses «استعادة صورة الأرشيف».
   - **Versioning:** append `?v={mtime}` where a frame URL is built, to defeat caches.

4. **Regression risk.** Low to medium: the re-fetch actions must honour the guard, and caches.

5. **Owner decision required: YES.**
   - A schema flag versus a marker file.
   - The permission key.
   - Whether the same feature is wanted for Anasheed.

---

## 3. Cross-feature review

**F04, F05 and F07: shared media behaviour.**

- **Shared:** all three come from **raw stored media URLs** being handed to the browser:
  - F04: via a redirect;
  - F05 and F07: via a `<video>` source.
- **Common factors:**
  - legacy formats;
  - `http://` links;
  - retired archive.org nodes, with **no normalisation on the Khotab player path**;
  - archive.org serving files inline.
- **Not shared:** F04 is not a player issue. For the redirect modules, the browser simply opens the target.
- **A single fix covers the Khotab half of F05 and F07:** normalise and upgrade the URL at render time (F05 (1)),
  without touching the stored data or the download infrastructure.

**F01 and F02: missing feature or discoverability?**

| Item | Verdict |
|---|---|
| F01, OLD ad system | genuinely missing (admin and public) |
| F01, Advfiles | discoverability: it exists as «ملفات الرئيسية» |
| F02, add, list, edit permissions | exist; a role and discoverability issue (editor super-admin-only, no menu entry, the row link shown to non-super admins) |
| F02, deactivate or delete | a real gap |

**F06 and F10: the same convention problem.** Uploads and displays must agree on one path, plus cache-busting.

---

## 4. Proposed implementation order (after owner approval; outside the Thursday–Saturday freeze)

1. **F06 author image** (confirmed defect, small, isolated).
2. **F05 (1) Khotab player URL normalisation** (confirmed gap, small; also likely fixes some F07 cases).
3. **F02 UI** (hide the row link from non-super admins; optional super-admin menu entry).
4. **F09 labels and help text.**
5. **F03** admin view of empty or hidden series in groups, once H1 is confirmed with the series id.
6. **F05 (2)** "not playable" message.
7. **F08** hide toggle and listing filters, after the business rules are approved.
8. **F10** custom thumbnail, after the design is approved.
9. **F01** the ad system, only if the owner chooses to rebuild it.
10. **F04** as decided (a hint or a rename recommended; no proxy).

## 5. Business decisions requiring owner approval

| ID | Decision |
|---|---|
| F01 | Retire, rebuild, or render-only for `nuke_ads` / `banners_ads` / `report_ads`? |
| F02 | Keep the permission editor super-admin-only? Is deactivation or deletion of admins required? |
| F03 | Should admins see empty and hidden series inside groups (OLD behaviour)? (Plus provide the series id.) |
| F04 | Keep redirects with a hint or rename, or proxy (capacity impact)? |
| F05 | Message for non-playable qualities; an admin report of such mirrors? |
| F06 | Should an upload override an existing `author_image` value? |
| F07 | Which page and item: Khotab group or Anasheed? |
| F08 | The direct-URL behaviour of a hidden clip; `hidden` versus `is_published` semantics |
| F09 | Which option was meant; the label wording; restore OLD's post-backup link lock? |
| F10 | A schema flag versus a marker file; the permission; the Anasheed scope |

## 6. Regression-test recommendations

| ID | Tests |
|---|---|
| F02 | staff list renders «تعديل الصلاحيات» only for super-admins; the route stays 403 for others |
| F03 | group page as admin lists empty and hidden series with badges; as visitor, unchanged (`count > 0`, `hidden = 0`) |
| F05 | player source for a Khotab item and mirror with: an `ia…us.archive.org/…/items/…` http link (normalised https), a plain https link (unchanged), a non-archive link (unchanged), `.rmvb` (message), `.mp4` with `vedio = 0` |
| F06 | after upload: `author_image` set, public author page, listing and preacher card show the new URL with a version; the Fatwa author page unchanged |
| F08 | hidden clip absent from group, home, news and related lists; direct URL per decision; player and download refused; admin sees the toggle and badge |
| F09 | move: counts ±1, link unchanged; `backupme`: queue membership via `myop=get` |
| F10 | the upload writes the frame and `frame = 1`; a re-fetch does not overwrite a custom image; the display picks it up |
| All | existing visitor-parity suites stay green; no change to download routes |

## 7. Evidence still needed from the owner

1. **F03:** the series id (and read-only `SHOW TRIGGERS` on production).
2. **F04:** the exact page URL where «حفظ المادة» opened the video.
3. **F05:** `khotab-item-{id}` and the failing quality's mirror id.
4. **F07:** the exact URL (`khotab-group-{id}` or `var-group-{id}`) and the item.
5. **F09:** a screenshot of the option.
6. **F02:** the reviewer's admin role (super-admin or not).
