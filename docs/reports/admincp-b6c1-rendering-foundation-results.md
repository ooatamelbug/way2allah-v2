# AdminCP B6c-1: public advertising rendering foundation (F01)

**Date:** 2026-11-14.

**Approval:** owner, "Owner Approval: B6c-1 Only". Reviewed: "B6c-1 Owner Review" (§9).

**Plan:** `docs/reports/admincp-feedback-implementation-plan.md` §F01 (P1–P13). Builds on B6a
(`admincp-b6a-implementation-results.md`) and B6b (`admincp-b6b-implementation-results.md`).

**Status:** **CLOSED LOCALLY** (2026-11-14, after the owner review in §9). **Not deployed, not activated, not
production-verified.** Placement and activation decisions remain **open for B6c-2** (§9.4).

- Not committed, not pushed, not deployed. No production access.
- No schema change, no data change. The local MySQL copy was read only.
- **Nothing is activated.**
  - `ads.rendering_enabled` and `ads.popup_enabled` default OFF.
  - **No slot is placed on any public page**, so even with the switches on, public pages render no advertisement
    (tested).
- **No writes in either switch state:** no view counter, no click counter, no auto-hide.
- B6c-2 (placement, counters, click route, popup opening) was not started.
- `7amlat` and `old-project` are unmodified.

---

## 1. Legacy advertising mapping

### 1.1 Sources

- **OLD:** `old-project/way2allah_functions.php:423-554`: `show_ads_byposition()`, `switch_on_ads_type()`,
  `hide_ads()`.
- **Gen 2:** the same functions in `7amlat/home_functions.php:437-557`.
- **OLD call sites:** the module `index.php` files (table below), `index.php:345-367` and `:517` for the home page.
- **Click handling:** `js/inc.js:640` (`increaseclick`), `:1094` (`link_this_ads`), and `ads_click.php`.
- **Data:** local copy, read only.
  - `nuke_ads`: 1 row (type 1, position 4, `show = 0`, malformed link `ttps://…`).
  - `nuke_banners`: 2 rows: `montada` and an empty `cds` row.
  - `nuke_banners_ads`: 16 rows (B6b).

### 1.2 The common rule (all positions)

| Aspect | OLD behaviour (Gen 2 identical unless noted) |
|---|---|
| **Pool** | `SELECT * FROM nuke_ads WHERE position = $pos AND show = 1 ORDER BY RAND() LIMIT 1` |
| **Eligibility** | `ads_show_type == 'period'`: shown iff `today > startdate && today <= enddate`, comparing Y-m-d strings. **The start day is excluded; the end day is included.** Any other type (`numview`): shown iff `num_view <= required_num_view` |
| **Ineligible pick** | **Not started:** retry recursively; if it is the only ad, the recursion never ends. **Ended period** or **exhausted numview:** `hide_ads()` sets `show = 0`, then retry. **Start day** (`today == startdate`): matches no branch, so **the slot is blank for that render, with no retry**. With `$return = true`, a retried output is lost |
| **Ordering** | `ORDER BY RAND()`; net effect is one uniformly random eligible ad. **`percentage` (the share) is ignored** by the public renderer; it is used only in the admin reports |
| **Type 0 (code)** | `image_path` echoed verbatim (raw HTML/JS) |
| **Type 1 (image)** | Empty `image_path` → two spaces. With a link: `image_path` rewritten `/images/`→`/media/` then `http://`→`https://`, giving `<a href="LINK" onclick="increaseclick(ID)" target="_blank" rel="nofollow"><img border="0" src="SRC" alt=""/></a>` (**Gen 2 adds `width="auto" height="250"`**). Without a link: `<img border="0" src="SRC" alt="" />` with no rewrite |
| **Views** | **Every render** runs `UPDATE nuke_ads SET num_view = num_view + 1`, a write per page view |
| **Clicks** | `increaseclick(id)` POSTs `ads_id` to `ads_click.php`, which runs `num_click = num_click + 1 WHERE id = $id` (**unauthenticated, SQL-injectable**). Gen 2 has **no `ads_click.php`** (verified), so Gen 2 clicks are not counted |
| **Nothing eligible** | OLD and Gen 2 output default images (`images/ads3.jpg` for pos 1, `images/ads.jpg` for pos 2–3) linking `#`. Other positions output nothing |

### 1.3 Positions

"Laravel host" is where a slot would go in B6c-2. **No slot is placed in B6c-1.** "Wrapper" is OLD's `<div>` class.
None of the positions is rendered by Laravel today, so the implementation status of every row is **foundation only;
not placed**.

| Pos | OLD label (size) | OLD rendering location | Laravel host (B6c-2 candidate) | Wrapper | Gen 2 live | Ambiguity |
|---|---|---|---|---|---|---|
| 1 | يسار الرئيسية (120×600) | home `index.php:367`, `div.WayAsdxxx.w2a_ads_120_600` | `home.blade.php` | none | no | default image ⚑ (decision 3) |
| 2 | وسط الرئيسية (120×240) | home `:372`, `div.WayAsd2` | `home.blade.php` | none | no | default image ⚑ |
| 3 | يمين الرئيسية (120×240) | home `:429`, `div.WayAsd2` | `home.blade.php` | none | **yes** (`new_content.php:333`, in a section card; Gen 2 markup `height="250"`) | Gen 2 vs OLD markup ⚑ (decision 12) |
| 4 | تفاصيل المادة علوي (728×90) | `Detailes()` in Khotab, Anasheed, w2acd, w2a_cd, Locations and `modules/index.php` | `khotab/item`, `anasheed/item`, `w2acd/show`, `locations.item` | `ads_details` | no | — |
| 5 | تفاصيل المادة جانبي (250×250) | `Top10($has_ads && $details)`: called from Khotab `Detailes()`; Anasheed and w2acd `Top10($has_ads)` | the item sidebars (`x-content.top-items` on `khotab/item`, `anasheed-sidebar-list`, `sidebar-ranking` on `w2acd/show`) | `ads_details_bottom` | no | Laravel sidebars are not OLD's `Top10` ⚑ (decision 11) |
| 6 | دروس ومحاضرات صوتية (250×250) | Khotab and Locations `Top10()` else-branch when `audio && video == 0`: `Department2()` audio pages | `khotab.authors.index` (`khotab-{op}.htm`, audio) | `ads_details_bottom` | no | ⚑ decision 11 |
| 7 | صفحة الشيخ علوي (728×90) | Khotab `Author()`, `Group()`, `Series()`; Locations `Author/Group/Series`; Gen 2 `channels/old.php` (not routed) | `khotab/author`, `khotab/group`, `khotab/series`, `locations.author` | `ads_details_2` | no | — |
| 8 | صفحة الشيخ جانبي (250×250) | Khotab `Top10()` else-branch (not audio): `Author`, `Group`, `Series`, `Thumbs`, and `Department2` (video) | the sidebars on `khotab/author` (`sidebar-ranking`), `khotab/group` and `khotab/series` (`top-items`) | `ads_details_bottom` | no | ⚑ decision 11 |
| 9 | صفحة المنوعات علوي (728×90) | Anasheed, w2acd and w2a_cd `Group()` | `anasheed/group`, `w2acd/group` | `ads_details_2` | no | **owner-deferred** (decision-log #91 W2; decision 10) |
| 10 | صفحة القناة علوي (728×90) | Satellite `w2a_channel_list()`, `w2a_channel_ramadan_list()`, `list_var_channel_list_details()` | `channels/show`, `channels/author` | `ads_details_2` | no | — |
| 11 | عرض الديالوج سفلي (468×60) | Khotab, Anasheed and w2acd `Play()` (`khotab-play-{id}.htm`, a player page shown in a dialog) | **no Laravel equivalent page**: Laravel plays inline (`x-content.media-player-panel`, `POST /media-player`) | `ads_dialog` | no | **placement unresolved** ⚑ (decision 9) |
| 12 | بداية تحميل الصفحة (500×700) | home `index.php:517`: fancybox popup on load (§1.4) | `home.blade.php` (popup) | — | no | §1.4 ⚑ (decision 8) |

### 1.4 The popup (position 12)

**OLD behaviour.** A type-1 ad at position 12 emits a jQuery `ready` script calling
`$.fancybox('<div … onclick="link_this_ads(ID)"><img src="SRC" width=700 height=500/></div>')`.

- `SRC` gets only the `http://`→`https://` rewrite.
- It also emits a hidden `<input id="ads_link_url" value="LINK">`.
- Clicking runs `increaseclick(ID)`, then `window.location = LINK` (same tab).
- It shows on **every** home page load. There is no frequency cap; fancybox's close button dismisses it.
- `num_view` + 1 per render.

**Security.**
- `SRC` is placed inside a JavaScript string inside HTML, and `LINK` raw inside an attribute, so a quote in either
  executes script.
- The page depends on jQuery and fancybox.

### 1.5 The home banner strip

**OLD** (`index.php:345-361`) renders three anchors:
1. Forum banner 1: `<a href='url1'><img src='image1' alt='title1'/></a>`.
2. The middle banner: `<a href='khotab-item-ID.htm' class='w2a_large_banner'><img src="media/banner/{floor(ID/1000)}/ID.gif" alt="TITLE"/></a>`.
3. Forum banner 2.

The parts come from:
- **The middle banner:** one random `nuke_banners_ads.media_id`. OLD does **no** check of existence, visibility or
  banner, so missing items or files rendered broken images.
- **Both forum banners:** one random `nuke_banners` row with `cat = 'montada'`, which supplies `image1`/`url1`/`title1`
  and `image2`/`url2`/`title2`. Images get `http://`→`https://`.

All values are raw in single-quoted attributes. **Gen 2 commented the strip out** (`new_content.php:210`) and replaced
`ORDER BY RAND()` with a non-uniform id-floor pick.

## 2. Implemented foundation

| Component | Behaviour |
|---|---|
| `config/ads.php` | `rendering_enabled` (`ADS_RENDERING_ENABLED`) and `popup_enabled` (`ADS_POPUP_ENABLED`), both **default false** |
| `AdsSwitch` | on only for a **strict boolean `true`** in config (a string `"true"` or `1` set at runtime stays off; the env is parsed with `FILTER_VALIDATE_BOOL`). The popup also needs the global switch |
| `LegacyAdEligibility` | OLD's rule verbatim (§1.2): start day excluded, end day included, NULL dates compared as `''`, numview `<=`, any non-`period` type treated as numview |
| `AdSlotService::select()` | **read-only.** Loads the position's `show = 1` pool once, keeps the eligible ads and picks one uniformly (`Random\Randomizer`). One ad per slot; the share is ignored |
| `AdSlotService::present()` | returns null **before any query** when the switch is off, the position is unknown, or (pos 12) the popup switch is off. Code ads are **withheld**. Image ads get OLD's rewrites, then the image URL and any non-empty link must pass `SafeExternalLink` (B6a/B6b's strict rule), or the ad is withheld |
| `<x-ads.slot :position="n"/>` | OLD's markup and wrapper per position, **without** `onclick`/`increaseclick`, with `rel="nofollow noopener"`. All values escaped |
| Position 12 in the same component | an **inert, closed `<dialog>`** with the image (700×500), the link (same tab, as OLD), and a `method="dialog"` close button. **No `open` attribute and no script, so it is never shown in B6c-1** |
| `HomeBannerStripService` + `<x-ads.banner-strip/>` | read-only. The middle banner is drawn uniformly over selection rows whose item **exists, has `hidden = 0` and has a valid banner**: `banner = 1` and the GIF exists, via the existing `KhotabBanner::for()`, as on the item page. A forum banner is shown only if its image and link pass `SafeExternalLink`. Missing parts are omitted; with nothing left it renders nothing |

**No writes anywhere.** The services issue SELECTs only. Ended or exhausted ads are simply not drawn; their `show`
stays as stored (OLD's write-back is deferred, decision 2).

### 2.1 Proposed safe handling for raw code ads (type 0)

**Now (B6c-1):**
- A code ad is **withheld**: never output publicly, whatever its content, even if it looks like a URL (tested).
- Its row is **not modified, deleted or rewritten**. It stays visible and editable in B6a AdminCP, where only
  super-admins may change or delete code ads, and it is never executed there.
- If such an ad is drawn, the slot is blank for that render, so the selection pool and odds are unchanged (decision 6).
- The local copy has **0** code ads; production is unknown (activation prerequisite 3).

**Options for B6c-2 or later:**

| Option | What | Assessment |
|---|---|---|
| **A. Keep withheld** (recommended now) | image ads only; code ads stay stored and inert | zero script risk. Visible change only if production has live code ads |
| **B. AdSense allow-list** (recommended if AdSense is needed) | parse the stored snippet. Accept only the canonical AdSense structure: `adsbygoogle.js` from `pagead2.googlesyndication.com` with `client=ca-pub-<16 digits>`, `<ins class="adsbygoogle">` with `data-ad-client`/`data-ad-slot` digits and an allow-listed `data-ad-format` / `data-full-width-responsive`. Extract only those parameters and render them through a **trusted Blade template**. Anything else stays withheld | no stored markup reaches the page. Needs a CSP entry for Google's domains. Plan A7(b) |
| C. Sandboxed iframe (`srcdoc`, `sandbox="allow-scripts allow-popups"`, no `allow-same-origin`, no top navigation) | runs arbitrary code in an opaque origin | isolates cookies and DOM, but third-party script still runs (malvertising, redirects in the frame), and AdSense does not support it. **Not recommended** |
| D. Raw output (OLD parity) | echo the code | **rejected:** stored XSS by design |

### 2.2 Intended popup behaviour (for B6c-2; not implemented)

- **Placement:** on the home page only, behind both switches.
- **Opening:** a small **static** script, with no ad data inside it and a CSP nonce, calls `showModal()` on
  `dialog.w2a-ad-popup`. **No stored value is ever placed in JavaScript.**
- **Interaction:** Esc and the close button dismiss it, with focus returned to the page. The image links through the
  B6c-2 click route, so the click is counted server-side.
- **Frequency:** once per page load (OLD parity) or a frequency cap. ⚑ decision 8.
- **Behaviour without the script:** without JavaScript the dialog stays closed, so nothing is forced on visitors.

## 3. Changed files

**New:**

| File | Role |
|---|---|
| `config/ads.php` | the two switches (default OFF) |
| `app/Support/Http/SafeExternalUrl.php` | the strict stored-URL rule, moved verbatim into the shared namespace (see below) |
| `app/Domain/Content/Ads/AdsSwitch.php` | strict switch reading |
| `app/Domain/Content/Ads/LegacyAdEligibility.php` | OLD's eligibility |
| `app/Domain/Content/Ads/AdSlotService.php` | selection and safe presentation (positions 1–12) |
| `app/Domain/Content/Ads/HomeBannerStripService.php` | banner strip selection |
| `resources/views/components/ads/slot.blade.php`, `resources/views/components/ads/banner-strip.blade.php` | components (not placed) |
| `tests/Support/AdsRenderingHarness.php` | production-shaped tables (`nuke_banners` from `SHOW CREATE TABLE`), media root, seeded randomizer, query recorder |
| `tests/Feature/Ads/AdsSwitchOffB6c1Test.php`, `AdSlotRenderingB6c1Test.php`, `HomeBannerStripB6c1Test.php` | tests |

**Edited (application):**

| File | Change | Why |
|---|---|---|
| `app/Domain/Admin/Support/SafeExternalLink.php` (B6b) | its body now delegates to `App\Support\Http\SafeExternalUrl::href()`; the API and behaviour are unchanged | the architecture rule "the Content domain must not depend on the Admin domain" (`tests/Architecture/DomainBoundaryTest.php`) failed in the first full run, because the B6c-1 services imported the Admin class. The fix keeps **one** rule rather than a copy. B6a/B6b URL tests pass unchanged |

**Edited (tests only; deliberate, documented):**

| File | Change | Why |
|---|---|---|
| `tests/Feature/Admin/AdsNoPublicOutputB6aTest.php` | the source scan now skips `app/Domain/Content/Ads/` and `components/ads/`. The test "no switch was introduced (B6c not implemented)" now asserts that the switches exist and are **OFF** | B6a's guard asserted B6c's absence; B6c-1 now exists by approval. Both public-page tests (no output, no `nuke_ads` query or write) are **unchanged and pass** |
| `tests/Feature/Admin/AdsB6bAuthorizationTest.php` | its "no public code reads B6b tables" scan skips `app/Domain/Content/Ads/` | the strip foundation must read `nuke_banners_ads`, read-only and switch-gated |

Apart from the one-line `SafeExternalLink` delegation, no B6a or B6b code was changed, and no public view, route or
migration was touched.

## 4. Tests and results

### Targeted (B6c-1)

| File | Tests | Covers |
|---|---|---|
| `AdsSwitchOffB6c1Test` | **12 passed** | defaults OFF; only a strict `true` enables; the popup switch alone enables nothing; with the switch **off**, positions 0–13 and the strip render nothing with **zero queries and zero writes**; home and a shared-layout page show no ad, popup, dialog or strip and run no ad query, **with the switch off and on**; no public view places a component |
| `AdSlotRenderingB6c1Test` | **55 passed** | all 12 positions: positions 1–11 with OLD's wrappers and markup; position 12 only with the popup switch, as a closed `<dialog>` with no `open`, script, fancybox or JS values. Unknown positions (no query); 13 eligibility cases; pool filtering; uniform pick ignoring share; OLD's URL rewrites; unlinked ads; 11 unsafe or malformed URL forms withheld; escaping; code ads withheld at positions 1/4/11/12 and when URL-shaped, row unmodified; code-ad odds kept; **no writes** across 60 renders including ended and exhausted ads |
| `HomeBannerStripB6c1Test` | **6 passed** | OLD's order and markup; missing, hidden, `banner = 0`, `banner = -1` and file-less selections never drawn, and the rows preserved; uniform pick among eligible items; unsafe forum banners omitted; titles escaped; read-only |
| `AdsSafetyReviewB6c1Test` *(owner review)* | **27 passed** | scenarios 1–10 (§9.1), plus the equivalence of B6a's, B6b's and the shared URL rule on 17 URLs |
| **B6c-1 total** | **100 passed** | 73 at implementation + 27 from the owner review |
| `tests/Architecture/DomainBoundaryTest` | **7 passed** (5 + 2 added) | Content does not use Admin. *Added:* `SafeExternalUrl` uses no domain; Admin does not use `Content\Ads`. The new rule was mutation-checked: a deliberate violation fails it |
| B6a and B6b guard tests (adjusted, §3) with B6c-1 | **90 passed** together | |

**Mutation checks.** Each of these, applied alone, makes at least one test fail:
- removing the switch check (from both entry points);
- not withholding code ads;
- not validating the link;
- bypassing eligibility;
- dropping the strip's banner-file check;
- removing the strip's switch check.

The code-ad mutation initially survived, because URL validation also stopped it. A test with a URL-shaped code ad
was added.

### Full validation

| Check | Result |
|---|---|
| **Full suite, final (after the owner review)** | **3,613 tests: 3,574 passed, 0 failed, 0 errors, 39 skipped**; 30,692 assertions; exit 0 (+29: 27 safety-review tests + 2 arch rules). Pint later reformatted only `AdsSafetyReviewB6c1Test.php`; B6c-1 and architecture tests re-run afterwards: 107/107 |
| Full suite at implementation | **3,584 tests: 3,545 passed, 0 failed, 0 errors, 39 skipped**; 30,503 assertions; exit 0 (B6b closed at 3,511: +73). The **first** full run failed one architecture test (Content must not use Admin). It was fixed by the shared `SafeExternalUrl` (§3), and the rerun is green |
| PHPStan | **0 errors** |

**Skipped (39): unchanged, environment-gated, unrelated** (MariaDB 34, benchmarks 4, GD 1).
| Pint `--test` | **passed** (Pint reformatted `HomeBannerStripService.php` after the import change; B6c-1 and architecture tests re-run afterwards: 78/78) |

## 5. Security findings

| # | Finding (OLD / Gen 2) | Handling (B6c-1) |
|---|---|---|
| S1 | Stored XSS: `link` and `image_path` raw in `href`/`src`; the popup puts `SRC` inside a JavaScript string and `LINK` raw in a hidden input; forum banner URLs and titles and the strip's item title are raw | Blade escaping everywhere. URLs must pass `SafeExternalLink` (absolute http(s); no whitespace, controls, quotes, `<>`, `` ` `` or `\`), otherwise the ad or banner is withheld. **No value is placed in JavaScript**; there is no script at all |
| S2 | Type-0 code executed verbatim | withheld; §2.1 |
| S3 | `ads_click.php`: unauthenticated, SQL-injectable (`WHERE id = $_POST['ads_id']`) | not ported; no click route in B6c-1 (route list tested in B6a) |
| S4 | A write per page view (`num_view`), plus a write-back from a public request (`hide_ads`) | none in B6c-1 |
| S5 | Infinite recursion when only not-started ads exist (a page-level DoS) | no recursion; ineligible ads are filtered |
| S6 | `target="_blank"` without `noopener` | `rel="nofollow noopener"` |
| S7 | Strip: broken images for missing or hidden items or files | eligibility as in the owner rule |

**No vulnerability was found in the B6c-1 implementation** during review, testing and mutation checks.

## 6. Differences from OLD

| # | Difference | Why |
|---|---|---|
| D1 | No `num_view` +1, no click counting, no `show = 0` write-back. Ended or exhausted ads are just not drawn, and their `show` stays stored as 1 | B6c-1 scope (no writes); decisions 2, 4 and 5 |
| D2 | One pass: a start-day ad is ineligible (**parity kept**). OLD's side effects are gone: picking a start-day ad no longer blanks the slot, and "only not-started ads" no longer recurses forever | OLD's blank and loop are defects, not rules. The eligibility rule itself is unchanged |
| D3 | Code ads withheld; that render's slot is blank | security; decision 6 |
| D4 | Ads with an unsafe or malformed image URL or link are withheld: relative image paths, `ttps://`, unencoded non-ASCII and the like | security; decision 7 |
| D5 | Nothing eligible → nothing (no `images/ads*.jpg` defaults) | plan P6 recommendation; decision 3 |
| D6 | No `onclick` or `increaseclick`; `rel` adds `noopener` | no counters in B6c-1; security |
| D7 | Popup: a closed, inert `<dialog>` with no fancybox, script or hidden input, and its own switch | owner instruction "do not open popups"; §2.2 |
| D8 | Strip: only eligible selections. Banner `width`/`height` come from the file, as on the item page. Unsafe or missing parts are omitted. The item link is the canonical `route('khotab.item.show')` (same `khotab-item-ID.htm` path, absolute) | owner rule (B6b decisions 3–4); security |
| D9 | OLD markup used, not Gen 2's `width="auto" height="250"` | Gen 2 only rendered position 3; decision 12 |

## 7. Open decisions (as raised at implementation)

The table below is the original wording, kept for traceability. **§9.3 reconciles each item against the owner's
review.**

| # | Decision | B6c-1 default |
|---|---|---|
| 1 | **Start day** (plan P2): keep OLD's exclusion, or include it. AdminCP's status already labels the start day «ساري» (B6a `LegacyAds::status()`, OLD's admin logic), so admin and public disagree in OLD too | **excluded (parity)** |
| 2 | **Auto-hide write-back** (P4): keep `show = 0` from a public request | not done (no writes in B6c-1) |
| 3 | **Default images** (P6) for positions 1–3 when nothing is eligible | render nothing |
| 4 | **View counting** (P9): `num_view` +1 per render, a write per page view per slot. Pages served from cache would not count | not done |
| 5 | **Click counting** (P10): a server-side click route (+1, then a 302 to the stored link), throttled | not done |
| 6 | **Code ads:** strategy (§2.1, A or B), and whether a withheld code ad keeps its share of picks (blank slot, as now) or leaves the pool (image ads shown instead) | withheld; keeps its share |
| 7 | **Unsafe link on an image ad:** withhold the ad (now), or show the image unlinked | withhold |
| 8 | **Popup:** opening behaviour (once per page load as OLD, or capped); keep the separate `ads.popup_enabled` switch | closed, never opened |
| 9 | **Position 11:** Laravel has no `Play` dialog page; place it under the inline player panel, or drop it | not placed |
| 10 | **Position 9:** lift the W2 deferral | not placed |
| 11 | **Positions 5, 6 and 8:** OLD's `Top10()` selection logic versus Laravel's different sidebars (`top-items`, `sidebar-ranking`, `anasheed-sidebar-list`) | not placed |
| 12 | **Position 3 markup:** Gen 2's `height="250"` or OLD's natural size | OLD |
| 13 | **Strip placement** on the home page (plan: "port and place it", owner 2026-11-14), and where | not placed |

## 8. Activation prerequisites

1. The decisions that remain open after the owner review (§9.4).
1a. **Owner rule: view-limited (`numview`) advertisements must not be activated in production until a reliable
    counting mechanism is approved.** With no counter, their `num_view` never moves, so they would show without
    limit. Before activation, enabled `numview` rows must be reviewed (and an admin should set `show = 0` on any that
    must not run).
2. B6c-2 implemented and reviewed: placement, the counter and click policy, and popup opening.
3. **Read-only production review of `nuke_ads`:**
   - any type-0 rows (the code-ad strategy depends on it);
   - image paths that are relative or non-ASCII (they would be withheld);
   - links that would be withheld;
   - the `nuke_banners` `montada` row's URLs.
4. Production `media/banner/{b}/{id}.gif` files present under `admin_khotab.media_root` (plan E8). Without them, the
   strip shows forum banners only.
5. B6a and B6b deployment prerequisites (seeder, media root, `APP_URL`, `media/bann/` not executable).
6. **Activation:** only by the owner. Set `ADS_RENDERING_ENABLED=true` (and `ADS_POPUP_ENABLED=true` for the popup),
   then run `php artisan config:cache`.
7. **Rollback:** set both to `false`, then `config:cache`. This is instant, and no data is involved.

---

## 9. Owner review ("B6c-1 Owner Review")

### 9.1 Final safety review (scenarios 1–10)

All tested in `AdsSafetyReviewB6c1Test`. Every scenario also asserts that the output contains:
- no stored code;
- no `<script`;
- no `on…=` attribute;
- no `javascript:`;
- no open `<dialog>`.

It also asserts **no write**.

| # | Scenario | Result |
|---|---|---|
| 1 | Both flags OFF (and the popup flag alone ON) | nothing rendered; **zero queries**; no write |
| 2 | Main ON, popup OFF | inline positions and the strip render; position 12 renders nothing and costs **no query**; no write |
| 3 | Both ON | as 2, plus position 12 as a **closed** `<dialog>` with no `open` and no script; no write |
| 4 | Raw code ad selected (all 12 positions, even with a link) | empty output; row unchanged; no write |
| 5 | Unsafe stored URL selected (link or image) | withheld; stored values **not rewritten**; no write |
| 6 | No eligible ad (disabled, ended) | empty slot, **no fallback image** (`ads.jpg` / `ads3.jpg`) |
| 7 | View-limited ad **at** its limit | shown (OLD's `<=`), 25 renders; `num_view` stays 10 and `show` stays 1; no write. See prerequisite 1a |
| 8 | Selection with deleted, hidden or bannerless items | never drawn (50 draws: always the one valid item); rows unchanged |
| 9 | Missing banner file | the item is not drawn; with nothing else eligible, the strip renders nothing; rows unchanged |
| 10 | Repeated requests and amplification | **one SELECT per slot render** and **two per strip render**, whatever the pool size (20 mixed ads per position, 40 selections). 50 full rounds of 12 slots + the strip give exactly 50 × 14 queries: no N+1, no retry loop, no write. The disabled popup costs nothing |

**Confirmed:**
- No scenario executes stored advertising code or writes an advertisement counter.
- Existing public pages contain **no advertising placement**. Home and the shared-layout page are tested with the
  switches off and on, and a scan finds no `<x-ads.` in any public view.
- No placement was added during the review.

### 9.2 Architecture review

| Check | Result |
|---|---|
| `SafeExternalUrl` is independent of Admin and Content | **yes:** it imports nothing; enforced by a new arch rule (mutation-checked) |
| B6a and B6b keep their validation behaviour | **yes.** B6a's `AdRequest` keeps its own unchanged copy of the rule. B6b's `SafeExternalLink` delegates to the shared rule, with the same verdicts on 17 URLs (tested). The B6a URL, security and administration tests and the B6b report tests pass unchanged |
| No new circular dependency | **none.** Admin → `App\Support\Http\SafeExternalUrl` ← Content; the shared class depends on nothing. Content does not use Admin, and Admin does not use `Content\Ads` (both enforced). The pre-existing `App\Support\Http\AdminViewer` → Domain dependency is unrelated and was not touched |
| Existing authorization and URL validation tests remain valid | **yes:** full suite green (§4) |

**No B6c-1 correctness or security defect remains open.**

### 9.3 Reconciliation of the 13 decisions

| # | Decision (§7) | Owner review | Status |
|---|---|---|---|
| 1 | Start day | "Preserve OLD's existing start-date eligibility behavior for now." | **Resolved for B6c-1:** excluded (parity). The view-limit `<=` is also preserved ("for compatibility") |
| 2 | Auto-hide write-back | "Do not enable … counter-based auto-hide." | **Partly resolved.** Counter-based auto-hide is **not approved**. The **date-based** write-back (OLD's `hide_ads()` for ended `period` ads) was not mentioned, so it **stays open** (§9.4) |
| 3 | Default images | "Do not display fallback advertising images … Render an empty slot." | **Resolved:** empty slot (as implemented) |
| 4 | View counting | "Do not enable view counting … The existing write-on-every-page-view approach is not approved." View-limited ads must not be activated in production until a reliable counting mechanism is approved | **Resolved (not enabled).** Becomes activation prerequisite 1a. A future counting mechanism needs separate approval |
| 5 | Click counting | "Do not reuse OLD's vulnerable click endpoint. A secure replacement requires separate design and approval." | **Resolved for this batch:** none. The future design is a separate approval, not B6c-2 by default |
| 6 | Code ads | "Continue withholding all raw code advertisements … Any future AdSense integration requires separate approval." | **Strategy resolved:** option A (withhold). **Sub-question open:** whether a withheld code ad keeps its share of picks (§9.4) |
| 7 | Unsafe link | "Withhold advertisements whose required destination URL fails strict validation. Do not rewrite legacy database values." | **Resolved:** withhold (as implemented), with no rewriting (tested) |
| 8 | Popup | "Keep popup functionality disabled. Do not automatically open popup windows." | **Resolved for B6c-1:** disabled, never opened. Opening behaviour and the separate switch **stay open** for any future activation (§9.4) |
| 9 | Position 11 | "Keep position 11 unresolved until its missing Laravel player-page dependency is addressed." | **Open by owner instruction** (§9.4) |
| 10 | Position 9 | "Preserve the existing deferral for position 9." | **Deferred** (W2 unchanged) |
| 11 | Positions 5, 6 and 8 | not mentioned. "Do not invent placements for unresolved positions" applies | **Open** (§9.4) |
| 12 | Position 3 markup | not mentioned | **Open** (§9.4) |
| 13 | Strip placement | "Keep the homepage banner strip disabled." | **Placement open** for B6c-2; the strip stays disabled and unplaced (§9.4) |

### 9.4 Remaining open decisions (for B6c-2 or later)

Wording is exactly as raised in §7.

| # | Original wording | Recommendation |
|---|---|---|
| 2 (part) | "**Auto-hide write-back** (P4): keep `show = 0` from a public request" (now only for date-ended `period` ads) | **Do not port.** Ended ads are already filtered at render time, and AdminCP shows them as «منتهي» (B6a `LegacyAds::status()`), so the write adds a public-request write without visible benefit |
| 6 (part) | "whether a withheld code ad keeps its share of picks (blank slot, as now) or leaves the pool (image ads shown instead)" | **Keep the share** (as implemented): it preserves OLD's odds, per "do not silently change … share or weighting". If production has enabled code ads, have an admin set `show = 0` instead of changing the rule in code |
| 8 (part) | "**Popup:** opening behaviour (once per page load as OLD, or capped); keep the separate `ads.popup_enabled` switch" | **Keep the separate switch.** Decide frequency only if the popup is ever approved; then a cap (for example once per session) is recommended over OLD's every-load behaviour |
| 9 | "**Position 11:** Laravel has no `Play` dialog page; place it under the inline player panel, or drop it" | keep unresolved until the player-page dependency is addressed (owner instruction) |
| 10 | "**Position 9:** lift the W2 deferral" | keep deferred (owner instruction) |
| 11 | "**Positions 5, 6 and 8:** OLD's `Top10()` selection logic versus Laravel's different sidebars (`top-items`, `sidebar-ranking`, `anasheed-sidebar-list`)" | in B6c-2, propose one host per page from §1.3 for **per-page owner approval**, following OLD's `Top10()` branch rules. Place nothing until approved |
| 12 | "**Position 3 markup:** Gen 2's `height="250"` or OLD's natural size" | **OLD's natural size** (as implemented): a forced height distorts any image that is not 250 px high |
| 13 | "**Strip placement** on the home page (plan: "port and place it", owner 2026-11-14), and where" | in B6c-2, propose OLD's position (after the main blocks, before the left column) for approval. It stays disabled until then. It also depends on prerequisite 4 (banner files) |

---

## 10. Post-closure test fix (2026-11-15, during B4)

- **Symptom:** the full-suite run for B4 failed one B6c-1 test, "draws only from enabled, eligible ads of the
  requested position".
- **Cause:** a **test** clock dependency. The test passed the fixed date `TODAY` (2026-11-14) to `select()`, but its
  `H::slot()` render used the real clock. Once the wall clock reached 2026-11-15, the fixture's start-day ad (start
  2026-11-14) became eligible, as OLD's rule says it should.
- **Fix:** test-only. `AdsRenderingHarness::setUp()` freezes `now()` at `TODAY`, and Laravel resets it after each
  test. No application code changed.
- **Result:** B6c-1 tests 100/100. The eligibility rule itself was correct throughout.

