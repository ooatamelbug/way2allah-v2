# F01 advertising: placement owner-approval matrix

**Date:** 2026-11-17. **Investigation and documentation only.** No code, data or configuration change; OLD and Gen 2
read-only.

**Sources:**
- OLD `index.php`, `modules/*` and `way2allah_functions.php` (`show_ads_byposition()` call sites);
- Gen 2 `new_content.php` and `home_functions.php`;
- Laravel `LegacyAds::POSITIONS`, `config/ads.php`, `<x-ads.slot>` and `<x-ads.banner-strip>`;
- `admincp-b6c1-rendering-foundation-results.md` §1 and §9, and `admincp-f01-f10-closure-results.md` §2.

## Common display behaviour

This is implemented and identical for every position.

**Eligibility** (OLD's rule, kept verbatim):
- `show = 1`;
- the date window excludes the start day and includes the end day;
- `num_view <= limit` for view-limited ads.

**One pick per position per page**, uniform among the eligible ads.

**Image ads (type 1)** are rendered as `<img>`, linked only if the URL passes `SafeExternalUrl` (new tab,
`rel="nofollow noopener"`), inside OLD's wrapper class where OLD had one.

**Withheld:**
- **code ads (type 0):** they keep their share of picks, so the slot is empty;
- **unsafe URLs:** the ad is withheld; stored values are never rewritten;
- **no eligible ad:** an **empty slot** (no fallback image).

**Not done:** view counting, click counting and auto-hide writes (not approved).

**Switches:**
- `ADS_RENDERING_ENABLED` (default **off**): while off there is no ad query and no output anywhere;
- position 12 additionally needs `ADS_POPUP_ENABLED` (default **off**), and is never opened.

**Placement:** no `<x-ads.slot>` or `<x-ads.banner-strip>` is placed on any Laravel page today (verified: 0 uses
outside the components).

## Matrix

**Implementation status legend:**
- **FOUNDATION ONLY:** the slot renders behind the switch, but is not placed on any page.
- **NO HOST:** OLD's location has no Laravel equivalent yet.
- **DEFERRED:** an earlier owner instruction holds it.

| OLD pos | OLD page / location | Laravel page / location | Expected dimensions | Implementation status | Exact owner decision needed |
|---|---|---|---|---|---|
| **1** | Home, left column `div.WayAsdxxx.w2a_ads_120_600` (`index.php:367`) | `home.blade.php`. **UNCERTAIN:** Laravel's home follows Gen 2's card layout and has **no left column**; Gen 2 never rendered pos 1 | 120 × 600 (skyscraper) | FOUNDATION ONLY, **NO HOST** | Choose a concrete spot in the Laravel home (e.g. a new side card), **or drop position 1** |
| **2** | Home, third block, before «جديد الإسطوانات» `div.WayAsd2` (`index.php:372`) | `home.blade.php`. **UNCERTAIN:** no equivalent block; Gen 2 never rendered pos 2 | 120 × 240 | FOUNDATION ONLY, **NO HOST** | Choose a spot, **or drop position 2** |
| **3** | Home, third block, after the Quran block `div.WayAsd2` (`index.php:429`). **Gen 2 live:** card «إعلان» (width 3) after the albums card (`new_content.php:333`) | `home.blade.php`: **proposed** a `x-home.section-card` titled «إعلان», as Gen 2. The Gen 2 albums card it followed is **not** in Laravel's home, so the row position is uncertain | 120 × 240 (OLD); Gen 2 forced `height="250"` | FOUNDATION ONLY, not placed | (a) Approve adding the «إعلان» card and **where in the card grid**; (b) markup: OLD natural size (recommended) or Gen 2's forced height 250 |
| **4** | Item details, top (`Detailes()`): Khotab, Anasheed, w2acd, w2a_cd, Locations; Gen 2 `channels/old.php:905` (not routed) | `khotab/item`, `anasheed/item`, `w2acd/show`; **no Laravel Locations item view**. **Exact spot UNCERTAIN** (no ad region exists) | 728 × 90 (leaderboard) | FOUNDATION ONLY, not placed | Approve the pages and the **exact spot** (e.g. above or below the details card); decide on the Locations item (no view) |
| **5** | Item details, sidebar `ads_details_bottom` (`Top10($has_ads && $details)`) | Item sidebars: `x-content.top-items` (Khotab), `anasheed-sidebar-list`, `sidebar-ranking` (w2acd). **UNCERTAIN:** the Laravel sidebars differ from OLD's `Top10()` | 250 × 250 | FOUNDATION ONLY, not placed. **Open since B6c-1** (§9.4 #11) | Per-page host approval following OLD's `Top10()` rule, or drop |
| **6** | Audio lessons pages, sidebar (`Top10()` else-branch, audio, `Department2()`) | `khotab.authors.index` (audio, `khotab-{op}.htm`). **UNCERTAIN** sidebar spot | 250 × 250 | FOUNDATION ONLY, not placed. Open (§9.4 #11) | Approve the host and spot, or drop |
| **7** | Sheikh / group / series page, top `ads_details_2` (Khotab `Author/Group/Series`, Locations); Gen 2 `channels/old.php` (not routed) | `khotab/author`, `khotab/group`, `khotab/series`; Locations author (**no Laravel view**). **Exact spot UNCERTAIN** | 728 × 90 | FOUNDATION ONLY, not placed | Approve the pages and the exact spot |
| **8** | Sheikh / group / series sidebar (`Top10()` else-branch, not audio; also `Thumbs`, video `Department2`) | Sidebars on `khotab/author` (`sidebar-ranking`), `khotab/group` and `khotab/series` (`top-items`). **UNCERTAIN** | 250 × 250 | FOUNDATION ONLY, not placed. Open (§9.4 #11) | Approve the host and spot per page, or drop |
| **9** | Variety (Anasheed / w2acd / w2a_cd) group page, top `ads_details_2` | `anasheed/group`, `w2acd/group` | 728 × 90 | **DEFERRED** (decision-log #91 W2; reconfirmed in the B6c-1 review) | Lift the W2 deferral, or keep it |
| **10** | Satellite channel lists, top `ads_details_2` (`modules/Satellite/index.php:63,100,623`) | `channels/show`, `channels/author`. **Exact spot UNCERTAIN** | 728 × 90 | FOUNDATION ONLY, not placed | Approve the pages and the exact spot |
| **11** | Player dialog page (`Play()`, `khotab-play-{id}.htm`), bottom `ads_dialog`; Gen 2 `channels/old.php:1317` (not routed) | **NONE**: Laravel plays inline (`x-content.media-player-panel`), with no dialog page | 468 × 60 | **NO HOST**; unresolved by owner instruction | Place it under the inline player panel, **or drop position 11** |
| **12** | Home, on page load: fancybox **popup** (`index.php:517`), shown on **every** home load, click → same-tab navigation | `home.blade.php`: an inert, **closed** `<dialog>` (never opened) | 700 × 500 image in a popup (labelled 500 × 700 in OLD's admin) | Implemented as a closed dialog; **disabled** (`ADS_POPUP_ENABLED` off); not placed | (a) Approve the popup at all; (b) frequency: every load (OLD) or a cap, **once per session recommended**; (c) placement on the home page |
| **Banner strip** (not a numbered position) | Home `w2a_home_banners` (`index.php:345-361`): forum banner, random featured item banner (`nuke_banners_ads`), forum banner. **Gen 2 commented it out** | `home.blade.php` via `<x-ads.banner-strip>` (existing, visible items with a valid banner only) | 3 banners in a row (item banner `media/banner/{b}/{id}.gif`; forum banners from `nuke_banners` `montada`) | Component implemented; **disabled and not placed** (owner, B6c-1) | (a) Approve showing it (Gen 2 removed it); (b) placement (OLD's spot: after the main blocks, before the left column); (c) depends on the banner files existing in production `media/banner/` |

## Missing or mismatched positions

| Item | Finding |
|---|---|
| No position is **missing** from Laravel's admin | all 12 are editable in AdminCP (`LegacyAds::POSITIONS`, OLD labels and sizes) |
| **No Laravel host** | pos **1** and **2** (no OLD-style columns), pos **11** (no player dialog page), and the Locations item / author pages for pos **4** / **7** |
| **Sidebar mismatch** | pos **5 / 6 / 8**: OLD's `Top10()` selection differs from Laravel's sidebars |
| **Popup size label** | OLD's admin says 500 × 700, but OLD renders 700 × 500 (`width=700 height=500`). Laravel renders 700 × 500, as OLD did |
| **Gen 2 vs OLD** | Gen 2 (the visitor-parity baseline) rendered only pos **3** and no strip. Every other placement adds ads that live Gen 2 does not show today |

## Approval and activation summary

| Already implemented, disabled | Requires owner approval before activation |
|---|---|
| Admin for all 12 positions, banner sets and reports (B6a / B6b) | Every **placement** above (none is placed) |
| `<x-ads.slot>` positions 1–12 behind `ADS_RENDERING_ENABLED` (off) | Popup: approval, frequency, `ADS_POPUP_ENABLED` |
| Position 12 as a closed `<dialog>` behind `ADS_POPUP_ENABLED` (off) | Banner strip: approval and placement |
| `<x-ads.banner-strip>` (not placed) | Position 9 deferral; positions 1 / 2 / 11 keep-or-drop |

**Before any activation (unchanged prerequisites):**
- the permission seeder and key assignment;
- production `media/bann/` and `media/banner/` present and non-executable;
- `APP_URL`;
- `nuke_banners_ads` InnoDB;
- **no view-limited ads** until a counting mechanism is approved;
- code ads stay withheld (AdSense needs separate approval).
