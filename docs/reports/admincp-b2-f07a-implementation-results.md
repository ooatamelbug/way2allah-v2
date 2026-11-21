# AdminCP B2 / F07-A: Variety malformed links, download buttons, editor warning, formats — results

**Date:** 2026-11-16.
**Approval:** "AdminCP Feedback B2 / F07-A — APPROVED FOR LOCAL IMPLEMENTATION".
**Basis:** `docs/reports/admincp-b2-f07-resolution-proposal.md` §6 and §8.
**Decision log:** #147.
**Status:** **F07-A CLOSED LOCALLY (2026-11-16) after validation passed; not deployed.**
- **The original F07 complaint is NOT resolved:** the item was never identified.
- No data correction was made (D1–D9 still open). B2 is not closed.

**What was done:**
- No stored link changed. No database write outside the isolated test database.
- No migration, production access, request to any Way2Allah host, Gen 2 / OLD change, commit, push or deployment.
- The download routes, capacity controls, rejection handling and logging are untouched.
- Local data was only read: two `SELECT` passes over the Variety tables via `tinker` to measure the effect (§6).

---

## 1. Files changed

| File | Change |
|---|---|
| `app/Domain/Content/Support/VarietyMediaLink.php` | **new**: `isPlayable()`, `isDownloadable()` (route-exact), `adminWarning()`, `ADMIN_WARNING_FLASH` |
| `app/Domain/Content/Support/BrowserPlaybackFormat.php` | `UNSUPPORTED` + `asf`, `ram`, `ra`, `mpg` |
| `app/Domain/Content/Services/MediaPlayerService.php` | Variety invalid-link notice (`INVALID_LINK_MESSAGE`); the Variety download rule now delegates to `VarietyMediaLink::isDownloadable()` |
| `resources/views/components/content/media-details-card.blade.php` | «حفظ المادة» rendered only when a download URL is given (Khotab always passes one: unchanged) |
| `resources/views/anasheed/item.blade.php` | passes the download URL only when the route can serve the link |
| `resources/views/components/content/media-quality-list.blade.php` | Variety mirror: title link and «تحميل» only when servable (Khotab branch unchanged) |
| `app/Domain/Admin/Http/Controllers/AnasheedItemAdminController.php` | post-save warning (item edit) |
| `app/Domain/Admin/Http/Controllers/AnasheedGroupAdminController.php` | post-save warning (item create) |
| `app/Domain/Admin/Http/Controllers/AnasheedMirrorController.php` | post-save warning (mirror add / edit) |
| `resources/views/admin/anasheed/_link-warning.blade.php` | **new**: inline warning partial |
| `resources/views/admin/anasheed/items/edit.blade.php`, `admin/anasheed/groups/item-create.blade.php`, `admin/mirrors/anasheed/edit.blade.php`, `admin/mirrors/anasheed/show.blade.php` | include the inline warning under the link field |
| `resources/views/layouts/admin.blade.php` | renders the warning flash (`alert-warning`) after `success` / `error` |
| `resources/views/components/admin/contextual-flash.blade.php` | renders the warning flash on public pages (admin session only) |
| `tests/Feature/Content/VarietyMalformedLinksF07aTest.php` | **new**: 58 tests |
| `tests/Feature/Content/MediaPlayerControllerTest.php`, `UnsupportedPlaybackFormatF052Part2Test.php` | superseded "ASF / MPG / RAM / RA → empty" assertions now expect the F05-2 explanation |

**Incidental:** Pint added a space after `!` in the existing (uncommitted, B4) `setVisibility()` of
`AnasheedItemAdminController`. Formatting only.

## 2. Player behaviour (`anasheed`, `anasheed_mirror` only)

**"Invalid structure" (network-free; `VarietyMediaLink::isPlayable()`):** after trimming surrounding whitespace, the
link does not start with `http://` or `https://` (case-insensitive) followed by a host. Concretely:
- empty or whitespace;
- bare or concatenated file names (`a_x264.mp4b_x264.mp4`);
- relative or root-relative paths (`/download/…`, `/cartoon/…`, `cartoon/…`);
- `http:///…`;
- a scheme-less host;
- any character before the scheme (`رhttp://…`);
- text pasted into the field.

**Not judged:** file names (`21.mp4_x264.mp4`, `x.mp3.mp3`, dotted names), percent-encoded or Arabic paths, query
strings, letter case.

**Behaviour, in order:**

| Link | Response |
|---|---|
| hidden item / mirror / mirror of a hidden item | refused, as before |
| invalid structure, format **not** in the unsupported list | **`<div class="w2a-player-status w2a-player-invalid-link" role="status"><p>رابط تشغيل هذه المادة غير صالح حاليًا. يرجى إبلاغ إدارة الموقع بالمشكلة.</p></div>`**: no `<video>`, `<audio>`, `<source>` or iframe; the existing status styles |
| invalid structure, unsupported format (e.g. host-less WMV, bare `.AVI` names) | **unchanged F05-2 explanation**, the no-download variant (already approved, still accurate) |
| valid structure | unchanged: `<video>` / `<audio>` / YouTube / SoundCloud, or the F05-2 explanation |

- The watch / listen buttons are unchanged everywhere.
- **Khotab is untouched** (tested: a host-less Khotab `.mp4` keeps its existing player), except for the shared format
  list below.

**Formats:** `BrowserPlaybackFormat::UNSUPPORTED` now also holds **ASF, RAM, RA, MPG**. They get the F05-2
explanation on Khotab and Variety, with the unchanged download-availability rules. **3GP, OGV, M4V and OGG are not
added** (tested: still the existing empty response).

## 3. Download-button behaviour

**Rule** (`VarietyMediaLink::isDownloadable()`): it computes what `/var-download-{id}` and `/var-mirror-{id}-{m}`
actually do, `str_replace('http:', 'https:', ArchiveOrgLink::normalize($link))`. It then asks **Laravel's own URL
generator** (`isValidUrl()`) whether the redirect leaves the site for an absolute URL.
- **This replaces the F05-2 rule.** That rule trimmed the value first, so it would have offered downloads for
  non-archive.org links with a **leading space**, which the route actually redirects into this site.
- The route was not modified.

**On `var-item-{id}`:**
- «حفظ المادة» is rendered only when the item's link is servable.
- For each mirror card, the title's download link and the «تحميل» button are rendered only when the mirror's link is
  servable. Otherwise the title is plain text and no download action appears.
- The play buttons always stay.
- No direct external URL is ever generated: the buttons keep pointing at the existing routes.

## 4. AdminCP warning behaviour (Variety only)

- **Rule:** the **saved** link (re-read from the database after the save) fails `isPlayable()`.
- **Message:** «تنبيه: وصلة المادة #ID المحفوظة ليست رابطاً كاملاً يبدأ بـ http:// أو https:// ويتضمن اسم الموقع،
  لذلك لن تعمل المشاهدة أو التحميل للزوار. تم الحفظ دون أي تعديل على الوصلة؛ يرجى مراجعتها.» For a mirror,
  «وصلة الوصلة #ID».
- **Item edit** (`PUT admin.anasheed.items.update`) and **item create** (`POST admin.anasheed.groups.items.store`):
  the flash is shown on the public page the admin lands on (`var-item` / `var-group`, via
  `<x-admin.contextual-flash>`, alongside «تم تعديل المادة»). This also works when the admin returns to an
  `anasheed_without_authors` page.
- **Mirror add / edit:** the flash is shown on the AdminCP page (admin layout, `alert-warning`).
- **Inline, under the link field**, on the item edit, item create, mirror edit and mirror add forms:
  - computed from `old(...)`, so it is still shown when the form is redisplayed after a validation error;
  - otherwise computed from the stored value;
  - it says saving is still possible.
- **Never blocks, never rewrites.** Validation rules are unchanged. The stored value equals what was submitted
  (Laravel's global `TrimStrings` trims surrounding whitespace on every form, as before).
- No network check.
- Khotab forms are unchanged.

## 5. Tests

`tests/Feature/Content/VarietyMalformedLinksF07aTest.php`: **58 passed (261 assertions)**, isolated in-memory
database (harness). The fixtures are the real malformed values.

| # | Requirement | Covered by |
|---|---|---|
| 1 | known malformed items | 16755, 16758, 15030, 12195, 14823, 6485: exact notice, no player element |
| 2 | known malformed mirrors | 156 `16`, 11604 empty, 18732 title text → notice; 9311, 9590, 15842 (AVI) → F05-2 no-download explanation; never a link |
| 3 | valid absolute URLs | `<video>` / `<audio>` / YouTube unchanged |
| 4 | unusual file names | `21.mp4_2_x264.mp4`, `ma3a-al7abeb-fe.ramadan.mp4` |
| 5 | URL-encoded characters | item 12580's percent-encoded Arabic path; query string; upper-case scheme |
| 6–9 | missing host / scheme, concatenations, empty | 9 shape cases plus the real rows |
| 10 | button visibility | item card and mirror cards hidden or shown per link; leading-space non-archive link hidden; play buttons kept |
| 11 | routes unchanged | `var-download` / `var-mirror` targets (local path, home page, archive.org) and counters exactly as before |
| 12 | item edit warning | saved unchanged + flash + shown on `var-item`; none for a valid link; inline warning from the stored link and after a validation redisplay |
| 13 | mirror edit warning | add and edit save unchanged + flash + admin layout alert; inline on the edit form; none when valid |
| 14 | saving possible | every warn case saves (DB asserted), item create included |
| 15 | F05-2 MP4 | Part 1 suite unchanged (in the related run) |
| 16 | F05-2 explanations | download variant and no-download variant unchanged |
| 17 | new formats | ASF, RAM, RA, MPG on Variety and Khotab; 3GP, OGV, M4V, OGG not added |
| 18 | visibility / permissions | hidden item, hidden mirror, mirror of a hidden item refused; hidden page 404; visitors never see the warning; an admin without `anasheed.editanasheed` gets 403 and nothing is saved |

**Related existing suites:** 75 files (all Variety / Anasheed page and admin tests, mirror tests, contextual admin,
player, F05-2 Parts 1 and 2, Khotab item, architecture):
- 1,500 tests, 1,495 passed;
- the 5 failures were the superseded ASF / MPG / RAM / RA "empty response" assertions, updated as listed in §1;
- after the update, all related files pass.

**Full suite** (`timeout 1500 vendor/bin/pest --log-junit … < /dev/null`): **3,940 tests: 3,901 passed, 39 skipped, 0 failures, 0 errors** (33,074 assertions, ~394 s), exit 0, no hang (3,884 before + 58 new − 2 superseded dataset rows).

**PHPStan:** passed, 0 errors. **Pint:** passed.

## 6. Effect on the local data (read-only measurement)

**Visible Variety items (16,493):**
- 11 get the invalid-link notice: 8 concatenations, 14823, 2 empty;
- 202 host-less WMV / RMVB keep the F05-2 no-download explanation;
- **«حفظ المادة» hidden for 221**: the 213 broken links plus 8 links with a leading space on a non-archive host,
  which the route redirects into this site;
- 14 now get the format explanation (RAM 13, MPG 1).

**Visible Variety mirrors (18,805):**
- 3 get the notice (`16`, empty, title text);
- 4 bare or concatenated AVI keep the F05-2 explanation;
- **download links hidden for 25**: the 7 broken mirrors plus 18 with a leading space that the real route redirects
  into this site (`http://<site>/ https://…`).
  - *(Corrected in F07-B1, `admincp-b2-f07b-data-repair-plan.md`: of the 18, **10 are SoundCloud links**, which
    trimming fixes. **8 are truncated archive.org values** (`media-w..._hane_hlme`), and that value answers 404 even
    trimmed.)*
- 5 new-format mirrors (MPG).

## 7. Remaining F07 data defects (not touched; proposal §7, owner approval per row)

- **D1:** 14823 («ر» prefix).
- **D2:** 27 host-less `/download/…` items, prefix `https://archive.org` (3 HEAD-verified).
- **D3:** 175 `/cartoon/amin2|3/…` items, prefix `http://vars.way2allah.com` (unverified host).
- **D4:** 16755–16758, 12581 (candidates).
- **D5:** 16759, a duplicate of 16773.
- **D6, D7:** 15030 and 12195 (ambiguous).
- **D8:** 6485, 10796 (empty).
- **D9:** mirrors 156, 9311, 9575, 9590, 11604, 15842, 18732.
- **New:** 8 items and 18 mirrors whose link starts with a space (their download is hidden now).
  - Trimming the value through «تعديل» fixes the 5 `vars` items and the 10 SoundCloud mirrors (the form trims on
    save).
  - It does **not** fix the 3 items and 8 mirrors holding the truncated `media-w..._hane_hlme` value.
  - Full inventory: `admincp-b2-f07b-data-repair-plan.md`.
- The GetRight group list (`AnasheedGroupController`, `.grx`) still lists `/var-download-{id}` for every item,
  broken ones included (out of scope; unchanged).

## 8. Remaining information to confirm the original F07 feedback

- The page URL (`khotab-group-{id}` as reported, or `var-group-{id}`) and the item clicked.
- Whether the file plays when opened directly.

Until then F07 is **not** claimed resolved. F07-A only removes the failing-player symptom for the confirmed malformed
Variety rows and makes their download state honest.
