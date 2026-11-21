# AdminCP B2 / F05-2: MP4 mirror playback — implementation results

**Date:** 2026-11-16.
**Approval:** "B2 / F05-2 Implementation — APPROVED FOR LOCAL IMPLEMENTATION".
**Basis:** `docs/reports/admincp-b2-investigation.md` §2.
**Decision log:** #143.
**Status:** CLOSED LOCALLY (2026-11-16) after validation passed; not deployed. This closes **only** the F05-2 MP4 playback subtask; **B2 is not closed** (F05-2 (2),
F03 and F07 remain).

Local code only. No schema change, migration, data write, production access or HTTP request to any host. Nothing was
committed, pushed or deployed. `7amlat` and `old-project` are unchanged.

---

## 1. Root cause addressed

- The Khotab mirror player (`MediaPlayerService::fromMirror()`) took video/audio from the mirror's own `vedio` flag.
  `renderPlayer()` emits `<video>` only for `video && .mp4`.
- **601 `.mp4` mirrors belonging to video items are stored with `vedio = 0`** (local `olddb`). All 601 have a
  lower-case `.mp4` path with no query string. **No audio item has an audio-flagged `.mp4` mirror.**
- Result: no player, and the generic «هذه المادة غير متاحة للتشغيل حالياً…» for files that exist and are
  `video/mp4` on Archive.org (B2 investigation, HEAD-checked).
- The quality card used the same flag, so these mirrors showed «استماع» with a headphones icon.
- Gen 2 fails identically (`w2a_mada_play()` audio branch).

## 2. Implementation

One rule, in one place, used by both consumers:

```php
// MediaPlayerService
public static function khotabMirrorPlaysAsVideo(mixed $mirrorVedio, mixed $itemVedio, string $link): bool
{
    if ((bool) $mirrorVedio) {
        return true;                       // unchanged: the mirror's own flag says video
    }

    return (int) $itemVedio === 1 && self::linkPathExtension($link) === 'mp4';
}

private static function linkPathExtension(string $link): string
{
    $path = parse_url(trim($link), PHP_URL_PATH);   // path only: no query, no fragment, no host

    return is_string($path) ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : '';
}
```

- **Format detection:** the extension of the **parsed URL path** only, lower-cased. No substring checks, so a
  query parameter, fragment, host name or a file named `…mp4.wmv` / `mp4_lecture.wmv` never counts. An
  unparseable URL is never promoted.
- **Both conditions are required:** the parent type alone never makes a mirror video, and an `.mp4` under an audio
  (or missing) parent stays as flagged.
- **`fromMirror()`:** visibility is decided exactly as before (the mirror's `hidden = 0` lookup). The parent's
  `vedio` is read **only** when the mirror is audio-flagged **and** its path is `.mp4`: one primary-key `value()`
  query, without a `hidden` filter, so no new refusal or exposure. Every other mirror costs no extra query.
- **Quality card** (`media-quality-list`): a new optional prop `parentVedio` (default `null`). The Khotab item
  page passes `$khotabItem->vedio`. The Khotab branch derives `$isAudio` from the same helper. The Anasheed branch
  (`isAudioLike()`) is untouched.
- **Played source:** unchanged, the F05-1 `ArchiveOrgLink::normalize()` URL. `renderPlayer()` is unchanged.

### Files changed

| File | Change |
|---|---|
| `app/Domain/Content/Services/MediaPlayerService.php` | `fromMirror()` uses the rule; new `khotabMirrorPlaysAsVideo()` and private `linkPathExtension()` |
| `resources/views/components/content/media-quality-list.blade.php` | optional `parentVedio` prop; the Khotab icon/label follows the rule |
| `resources/views/khotab/item.blade.php` | passes `:parent-vedio="$khotabItem->vedio"` |
| `tests/Feature/Content/KhotabMirrorMp4PlaybackF052Test.php` | new, 53 tests (with datasets) |
| `docs/decision-log.md` | #143 |
| `docs/reports/admincp-feedback-implementation-plan.md` | B2 status: the F05-2 (1) subtask only |
| this report | new |

## 3. Preserved behaviour

| Area | Result |
|---|---|
| Correctly flagged video mirrors | unchanged (flag wins first) |
| Genuine audio mirrors (`.mp3`, SoundCloud) of video items | unchanged: `<audio>` / SoundCloud |
| Audio items | unchanged, including their `.mp4` mirrors (none exist locally) |
| RMVB / WMV / RM / AVI / FLV / WMA | unchanged: still no player and the generic message, whatever the flags |
| YouTube mirrors | unchanged: decided by the mirror flag, as before |
| URL generation / normalisation | unchanged |
| Download route (`khotab-mirror-{item}-{mirror}.htm`) | unchanged: it never reads `vedio`; same bytes, type, hits, capacity guard |
| Visibility | unchanged: hidden mirror refused; the endpoint still does not consult the parent's `hidden` (the item page 404s) |
| Mirror ordering, numbering, titles, format badges, sizes, hit counts | unchanged |
| Stored data | no write: `vedio` flags stay as they are |
| Anasheed (Variety) mirrors | untouched |

## 4. Regression tests

`tests/Feature/Content/KhotabMirrorMp4PlaybackF052Test.php`: **53 passed (170 assertions)**. The fixtures copy the
real rows, including item 4337's eight mirrors in order.

| # | Required case | Tests |
|---|---|---|
| 1 | Video item + MP4 mirror flagged audio | mirrors 834 / 4337, 564 / 3441, 799 / 4282: `<video>` with the normalised https source; no flag write |
| 2 | Video item + MP4 mirror flagged video | unchanged, under a video and an audio parent |
| 3 | Audio item + MP4 mirror | no player, card «استماع»; also a missing parent row |
| 4 | Video item + genuine audio mirror | `.mp3` → `<audio>`; SoundCloud → embed; never `<video>` |
| 5 | Video item + unsupported format | rmvb, wmv, rm, avi, flv, wma × flagged audio / video → no player (unchanged) |
| 6 | MP4 with query parameters | `a.mp4?download=1&x=2` → `<video>`, query kept in the source |
| 7 | Upper-case extension | `LECTURE.MP4`, `Lecture.Mp4` |
| 8 | `mp4` elsewhere | query, query-only, fragment, `a.mp4.wmv`, `mp4_lecture.wmv`, directory `f.mp4/…avi`, host `mp4.example.com`, `lecturemp4` → not video, card «استماع» |
| 9 | Mirror ordering | item 4337: order 830 … 9505 and numbering 1–8 unchanged |
| 10 | Quality labels | titles and badges unchanged; only 834 and 838 switch to «مشاهدة» / `fa-play-circle`; `.rm`, `.avi`, `.flv` stay «استماع»; card and player agree |
| 11 | Download | `khotab-mirror-4337-834.htm`: same bytes, `video/mp4`, hits +1, `vedio` still 0 |
| 12 | Visibility | hidden mirror refused; hidden parent answers exactly as a correctly flagged mirror; the item page still 404s |
| — | Helper rule | 10 direct cases (leading-space real link, relative path, string flags from the DB, YouTube, unparseable URL) |
| — | Query cost | the parent is read only for audio-flagged `.mp4` mirrors (1 query), never otherwise (0) |
| — | Variety | an Anasheed mirror card is unaffected by `parentVedio` |

**Related existing tests:**
- MediaPlayerController, KhotabPlayerArchiveNormalization, KhotabItemController, ContextualKhotabItem,
  MirrorAuditPhase1, `tests/Feature/Support/Downloads`, `tests/Feature/Anasheed` and `tests/Architecture`:
  **627 tests, 589 passed, 38 skipped, 0 failed**.
- AnasheedItemController and AnasheedVisitorParity: **53 passed**.

## 5. Full suite, PHPStan, Pint

- **Full suite** (`timeout 1500 vendor/bin/pest --log-junit … < /dev/null`, stdin closed, bounded):
  **3,737 tests: 3,698 passed, 39 skipped, 0 failures, 0 errors** (31,488 assertions, ~374 s), exit 0, no hang.
- **PHPStan:** passed, 0 errors (whole project).
- **Pint:** passed on the changed PHP files (the new test file was formatted with Pint: imports only).

## 6. Remaining F05-2 issues

- **F05-2 (2), not implemented:** 1,895 medium mirrors (and others) are RMVB / WMV / RM / AVI / FLV / WMA and still
  show the generic «غير متاحة للتشغيل حالياً». The message wording and whether these keep a play button are plan
  decision 20, not approved.
- **Reported case:** the reported item and mirror ids are still unknown. The fix covers every mirror of the confirmed
  class, but *the reported* case is not claimed fixed without them (plan §F05).
- **Codec:** an `.mp4` file encoded with a codec the browser cannot decode (for example HEVC on some browsers) still
  fails in the `<video>` element. This cannot be verified without downloading the files (not done).
- **Data:** the 601 stored flags remain wrong. Optional later: an admin report or an owner-run correction. Not part
  of this task.

## 7. Compatibility risks

- **Gen 2 / OLD:** still fail for these mirrors while they serve item pages (the B4 routing blockers apply). Laravel
  now intentionally differs (decision-log #143).
- **Parent `vedio = 1` with a non-video `.mp4`:** an audio-only MP4 (AAC in an MP4 container) under a video item
  would now open in `<video>`. Browsers play the audio track with an empty picture, which is still playable. Locally,
  all 601 sampled as video by Archive.org content type (3 HEAD-checked during the investigation).
- **Other module views:** the component's new prop is optional, so no other caller changes. Only the Khotab item
  page passes it.
- **Cached pages:** the item page has no response cache on this path; the player endpoint is uncached.
