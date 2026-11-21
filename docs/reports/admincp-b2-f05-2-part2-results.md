# AdminCP B2 / F05-2 Part 2: unsupported media formats — results

**Date:** 2026-11-16.
**Approval:** "AdminCP Feedback B2 / F05-2 Part 2 — APPROVED FOR LOCAL IMPLEMENTATION", **Option B**. This
resolves plan decision 20.
**Decision log:** #144 (Part 1 is #143).
**Status:** CLOSED LOCALLY (2026-11-16) after validation passed; not deployed. **B2 is not closed** (F03 and F07 remain).

Local code only. No production access, request to any host, data change, migration, legacy-tree change, commit, push
or deployment.

---

## 1. Before

- `MediaPlayerService::renderPlayer()` renders YouTube, `.mp3`, SoundCloud and `.mp4`, and returns `null` for
  everything else. `null` was a deliberate placeholder "pending a product decision".
- The endpoint then answered with an empty body, and the script (`media-player-script`) showed the generic «هذه
  المادة غير متاحة للتشغيل حالياً. يمكنك تجربة جودة أخرى أو إعادة المحاولة.» with a retry button. That is
  misleading for a format that can never play: retrying or trying another quality of the same format never helps.

Local scale of the six formats (visible rows):

| Surface | RMVB | WMV | RM | AVI | FLV | WMA | Total | Link the route cannot serve |
|---|---|---|---|---|---|---|---|---|
| Khotab items | 5,470 | 699 | 8,113 | 18 | 79 | 32 | 14,411 | 17 (not `http(s)`; a file may exist on the server) |
| Khotab mirrors | 2,016 | 4,897 | 13,288 | 79,417 | 294 | 193 | 100,105 | 28 (mostly a leading space, e.g. mirror 830) |
| Variety items | 2,594 | 2,718 | 946 | 18 | 1,489 | — | 7,765 | 202 (host-less `/download/…`, e.g. 9870) |
| Variety mirrors | 160 | 90 | 127 | 10,324 | 203 | 5 | 10,909 | ≈ 4 |

## 2. Behaviour implemented

`MediaPlayerService::play()` now does the following:

1. Resolves the media exactly as before, with the same visibility rules. Hidden content is still refused before
   anything else.
2. Tries the existing player first. Every supported format, F05-2 Part 1 included, renders exactly as before.
3. If no player rendered, the type is Khotab or Variety, and the URL **path** extension is one of
   `rmvb, wmv, rm, avi, flv, wma` (case-insensitive), it returns the explanation:

```html
<div class="w2a-player-status w2a-player-unsupported" role="status">
  <p>هذه الصيغة غير مدعومة للتشغيل المباشر في المتصفح. يمكنك تحميل المادة وتشغيلها باستخدام برنامج يدعم هذه الصيغة.</p>
  <a href="/khotab-mirror-4337-833.htm" download class="w2a-action-btn w2a-action-download">
    <i class="fa fa-download" aria-hidden="true"></i> <span>تحميل المادة</span></a>
</div>
```

   When the download route cannot serve the link:

```html
<div class="w2a-player-status w2a-player-unsupported" role="status">
  <p>هذه الصيغة غير مدعومة للتشغيل المباشر في المتصفح. لا يتوفر رابط تحميل لهذه المادة حالياً.</p>
</div>
```

4. Anything else returns the existing empty response and the generic message: unknown formats, formats outside the
   list, Telawat and Fatawa.

**Rendering details:**
- No `<video>`, `<audio>`, `<embed>`, `<object>` or iframe is ever emitted for these formats, so no player
  initialises.
- The script injects any non-empty response and wires up events only when it finds `video` or `audio`, so **no
  JavaScript change was needed**.
- The markup reuses the existing `.w2a-player-status` (centred, RTL page) and `.w2a-action-btn` styles: **no CSS
  change**.
- The dialog's focus trap already includes `a[href]`.

**The watch button:** unchanged on every card (item card «مشاهدة المادة» / «استماع المادة», quality cards
«مشاهدة» / «استماع»). It stays visible and keeps its label. Clicking it opens the player dialog, which now shows the
explanation instead of the generic message.

## 3. How download availability is determined

The button always links to the page's **existing** route, the same URL as the card's own download link. No
Archive.org or other direct URL is generated. Whether that route can serve the link is decided **without any
network access**, by reproducing each route's own rule on the stored link:

| Player type | Route | Offered when |
|---|---|---|
| `khotab` | `/khotab-download-{id}.htm` (proxied) | the **raw** stored link is non-empty and starts with `http://` / `https://`, or is an existing file. This is exactly `ProxiedDownloadStreamer::stream()`'s 404 rule |
| `khotab_mirror` | `/khotab-mirror-{khid}-{id}.htm` (proxied) | the same, or the link is a YouTube / SoundCloud page (`ExternalMediaLink`, which the route redirects) |
| `anasheed` | `/var-download-{id}.htm` (redirect) | the route's redirect target (`normalize(link)`, http → https) is an absolute `https://host…` URL |
| `anasheed_mirror` | `/var-mirror-{khid}-{id}.htm` (redirect) | the same; the player already required the item to be visible, as the route does |

The availability check is evaluated only when the notice is shown. A remote source that fails later (dead host,
missing file) cannot be known without contacting it, and is not checked. The download routes are **not modified**:
authorization, capacity guard, rejection handling, telemetry and URLs are unchanged.

## 4. Files changed

| File | Change |
|---|---|
| `app/Domain/Content/Support/BrowserPlaybackFormat.php` | **new**: `UNSUPPORTED` (closed list), `pathExtension()`, `isUnsupported()` |
| `app/Domain/Content/Services/MediaPlayerService.php` | `play()` notice; a `download` resolver on the four Khotab / Variety branches; `unsupportedFormatNotice()`, `khotabDownloadable()`, `anasheedDownloadable()`; message constants. Part 1's private `linkPathExtension()` moved into the shared helper (same logic) |
| `tests/Feature/Content/UnsupportedPlaybackFormatF052Part2Test.php` | **new**, 101 tests |
| `tests/Feature/Content/MediaPlayerControllerTest.php` | the "null pending a product decision" test now expects the explanation (and `asf` still `null`) |
| `tests/Feature/Content/KhotabPlayerArchiveNormalizationTest.php` | `.rmvb` mirror: no player, now the explanation |
| `tests/Feature/Content/KhotabMirrorMp4PlaybackF052Test.php` | Part 1's "unsupported formats unchanged" assertions updated to the explanation (still never a player) |
| `docs/decision-log.md` | #144 |
| `docs/reports/admincp-feedback-implementation-plan.md` | B2 status (F05-2 Part 2); decision 20 resolved |
| this report | new |

No route, view, JavaScript, CSS, download, model or data change.

## 5. Tests

`UnsupportedPlaybackFormatF052Part2Test.php`: **101 passed (499 assertions)**.

| # | Required | Covered by |
|---|---|---|
| 1 | each unsupported format | 6 formats × 4 surfaces: exact message, exact route href, no player element |
| 2 | Khotab video mirrors | `khotab mirror` surface; item 4337's real `.avi` / `.flv` mirrors (833, 836) |
| 3 | Variety video mirrors | `variety mirror` surface, plus the Variety item page |
| 4 | audio mirrors in unsupported formats | `wma`, `rm` flagged audio × 4 surfaces: same explanation (the format decides) |
| 5 | watch button visible | Khotab item page (item card, video- and audio-flagged quality cards with labels) and the Variety item page |
| 6 | message after click | the `/media-player` response the button triggers: exact markup |
| 7 | download available | Khotab: a real local `.rmvb` file, and `HEAD` on the offered href returns 200. Variety: the offered href redirects to the https Archive.org file |
| 8 | download unavailable | leading-space mirror 830, missing local file, relative path → route `HEAD` 404; host-less Variety item 9870 and a mirror → route redirects into the site. No button, no promise |
| 9 | supported MP4 unchanged | MP4, MP3, YouTube × 4 surfaces; an `.mp4` keeps `<video>` (no codec assumption) |
| 10 | F05-2 Part 1 | mirror 834 still `<video>`; its `.avi` / `.flv` siblings get the explanation. The Part 1 suite (53) still passes |
| 11 | upper-case extensions | `.RMVB`, `.WMV`, `.Flv` × 4 surfaces |
| 12 | misleading query strings | `a.mp4?file=b.wmv` → video; `a.wmv?file=b.mp4` → explanation; `play.php?file=a.rmvb`, `#a.rm`, `f.rmvb/` directory, `aviation` → unchanged empty |
| 13 | visibility | hidden Khotab item and mirror, hidden Variety item, the mirror of a hidden Variety item, a hidden Variety mirror → empty, as before |
| 14 | no direct external URL | the notice contains no `http`, `//` or `archive.org`; only the relative route |
| — | closed list | `3gp, asf, mpg, ogv, ram, ra, rv, mkv, webm, xyz` → unchanged empty; Telawat `.rm` → unchanged |
| — | no writes | rows identical after the notice is served |

**Related existing suites:** Part 2, Part 1, MediaPlayerController, KhotabPlayerArchiveNormalization,
KhotabItemController, AnasheedItemController, AnasheedVisitorParity, `Admin/Contextual`, MirrorAuditPhase1,
`Support/Downloads`, `Feature/Anasheed` and `Architecture`: **931 tests, 893 passed, 38 skipped, 0 failed**.

**Updated existing assertions:** 3 tests (15 cases) asserted the superseded "empty / null, pending a product
decision" response for these formats. They now assert the approved explanation, and still assert that no player is
rendered. No other expectation was changed; the test threshold is untouched.

**Full suite** (`timeout 1500 vendor/bin/pest --log-junit … < /dev/null`): **3,838 tests: 3,799 passed, 39 skipped, 0 failures, 0 errors** (32,034 assertions, ~393 s), exit 0, no hang (3,737 before + the 101 new tests).

**PHPStan:** passed, 0 errors. **Pint:** passed on every changed PHP file.

## 6. Remaining limitations

- **Formats outside the approved list** (locally: Khotab `asf` 750, `3gp` 783, `mpg` 598, `ram` 240, `ra` 164, `rv`
  63, `ogg` / `wav`; Variety `3gp` 1,349, `ogv` 200, `m4v` 4) still show the generic message. Extending the list
  needs an owner decision. `ogv` / `ogg` / `wav` / `m4v` may actually play in some browsers, but have no player
  branch today.
- **Codec:** `.mp4` files whose codec the browser cannot decode still fall back to the existing «تعذّر تحميل الملف»
  after the `<video>` error. There is no codec detection, by design.
- **Download availability is a static rule.** A remote file that is gone shows the button, and the route then fails
  as it does today from the card.
- **Pre-existing download defects, not changed:**
  - the proxied Khotab routes use the raw link, so 28 mirrors stored with a leading space (e.g. 830) cannot be
    downloaded;
  - 202 host-less Variety items redirect into the site.
  The explanation correctly offers no download for them. Fixing the data or route is F07 territory, owner decision.
- **Legacy:** Gen 2 and OLD still embed RealPlayer / WMP / Flash for these formats.

## 7. F05-2 status

- **Part 1** (601 MP4 mirrors flagged audio): CLOSED LOCALLY (#143).
- **Part 2** (unsupported formats, Option B): CLOSED LOCALLY (2026-11-16) after validation passed; not deployed (#144).
- **F05-2 as a whole:** **CLOSED LOCALLY** — both approved parts are closed locally; not deployed. The reported item and mirror ids were never supplied, so *the reported* case is
  covered by class, not confirmed individually.
