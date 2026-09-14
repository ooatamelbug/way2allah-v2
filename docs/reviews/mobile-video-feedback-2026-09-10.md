# Mobile QA feedback from the supplied video

Reviewed: 2026-09-10

Source: `/Users/dash/Downloads/way2allah-v2-qa-feedback.mp4`

Duration: 202.79 seconds. Recording dimensions: 396 × 850 pixels, including
Android and browser chrome; these are not the web page's CSS viewport dimensions.
The recording shows the newapp site in mobile Chrome.

## Review method and limits

Feedback was extracted using local Arabic speech recognition, with unclear
passages reprocessed in shorter clips, and checked against sampled video frames.
This is an organized interpretation, not a verbatim transcript. Timestamps below
are approximate ranges for locating the demonstrations and related narration.
Some wording remains uncertain and is explicitly identified below.

These are observations from the supplied recording, not claims that every issue
has been reproduced against the current local checkout. The recording alone
does not establish whether a playback failure comes from layout, JavaScript,
the media source, or a server response. No application code was changed during
the initial review. Implementation and validation are recorded below.

## Extracted feedback

| ID | Approximate time | Feedback and visible evidence | Proposed outcome | Priority |
| --- | --- | --- | --- | --- |
| M01 | 00:10–00:22 | Homepage slider banners do not fit correctly. The reviewer explicitly mentions banner dimensions; artwork containing text is cropped in the mobile hero. | Make banner framing work at narrow widths while keeping artwork, caption, and controls readable. Preserve desktop framing. | Medium |
| M02 | 00:22–00:35 | On a media detail page, sections do not line up consistently. The reviewer describes sections as not being the same size; the recording shows varying card edges and insets. | Use consistent outer gutters and align section edges. Confirm which inner padding differences are intentional. | Medium |
| M03 | 00:30–00:40 | Text is cramped in the popular/downloaded-material listings, especially longer Arabic titles. | Give titles and metadata sufficient line height and separation, without clipping or collisions with thumbnails. | Medium |
| M04 | 00:40–00:50 | Breadcrumbs above the lecture details are crowded: home, video section, preacher, series, and item title wrap into a dense block. The demonstrated item is “فقه الأخلاق (333) أثر العلم في الدعوة إلى الله”. | Improve RTL wrapping, spacing, and hierarchy so navigation remains readable and usable by touch. | Medium |
| M05 | 01:08–01:20 | The app-download area at the bottom is not centered. The store badges and “حمل التطبيق الآن” are visibly shifted toward the right. | Center the heading and badge group within the mobile viewport; allow wrapping when needed. | Medium |
| M06 | 01:23–01:33 | The reviewer questions the alignment of the large fatwa action icons. Watch, save, and send-to-friend actions form an uneven arrangement. This is a design concern, rather than an established functional failure. | Give the actions a balanced layout, consistent sizing, and clear labels. | Medium |
| M07 | 01:32–01:42 | Selecting watch on a fatwa produces a dark overlay with a small red close icon, but no visible usable player. The narration reports that the material does not appear. The audio-related wording is unclear, so this review does not assert that hidden audio is playing. | Display a usable player or an explicit loading/error state, with a clearly accessible close control. Investigate the request and media response before attributing the failure to CSS. | High |
| M08 | 01:49–02:03 | In an exclusive-clip detail page, watch/download controls protrude from the left side of the inner quality card. The shown option is “رابط يوتيوب”, and the clip is “مشهد مهيب” by د. أحمد عبد المنعم. | Keep both actions inside their card at narrow widths and accommodate Arabic text without forcing overflow. | High |
| M09 | 02:04–02:18; 02:44–02:56; 03:09–03:22 | Excessive blank space separates section headings from the first item. The reviewer repeatedly calls out sections such as “أحدث المواد”, “الأكثر تحميلاً”, and “قائمة الجودات المختلفة للمادة”. | Reduce unnecessary heading-to-content gaps and establish consistent vertical spacing across shared listing sections. | Medium |
| M10 | Approximately 02:18–02:30 | The reviewer appears to describe a quality/playback action that does not reveal the expected result. The exact wording and target are unclear. The surrounding sequence shows the YouTube quality option, but does not prove a specific technical failure. | Reproduce the main watch button and the individual YouTube-quality watch button separately. Identify the exact failure before changing behavior. | Needs reproduction |

## Positive feedback to preserve

- Around 02:37–02:45, the reviewer says recitation playback appears to work.
  The recording shows an audio player for “الزمر”.
- Around 02:57–03:08, the reviewer treats audio playback as working/acceptable.
  The recording shows an active player for “ميثاق على الفطرة”.
- These examples are useful regression cases; they do not prove all recitation
  or audio items work.

## Suggested implementation order

1. Reproduce the fatwa overlay failure and the uncertain quality-playback report.
2. Fix quality-card overflow and shared section/gutter alignment.
3. Improve breadcrumbs, long-title spacing, and heading-to-content gaps.
4. Correct mobile banner framing, app-download alignment, and fatwa actions.
5. Verify each affected component at narrow mobile, larger mobile, tablet, and
   desktop widths. Include touch interaction, RTL wrapping, and working audio
   examples in the checks.

Maintain the existing desktop visual quality when changing shared styles.
The earlier instruction to leave “أحدث المواد المفرغة” alone still applies;
this recording does not explicitly request a change to that specific section.

## Initial code locations for reproduction

These are investigation entry points, not confirmed root causes:

- `public/assets/frontend/layout/css/premium-ui.css`: shared responsive layout,
  spacing, quality cards, player overlays, and footer styling.
- `resources/views/components/content/media-quality-list.blade.php`: quality
  option metadata and watch/download controls.
- `resources/views/components/content/media-player-script.blade.php`: shared
  playback request and overlay behavior.
- `resources/views/components/content/media-player-panel.blade.php`: shared
  player panel and close button.
- `resources/views/fatawa/question-all.blade.php`: fatwa actions and its separate
  legacy player markup/script.
- `app/Domain/Content/Services/MediaPlayerService.php`: player markup for media
  types and sources, if playback reproduction points to this layer.

The inaccurate raw speech-recognition output and frames containing unrelated
private messages were not copied into the repository.


## Implemented fixes — 2026-09-10

- **M01:** Small-screen banners now show the full artwork, with the caption and
  navigation below it. The desktop hero retains its existing framing.
- **M02/M03/M04/M09:** Media detail cards share mobile gutters, unnecessary stacked
  gaps are removed, long Arabic list titles use larger type and more line height,
  and breadcrumbs wrap with consistent spacing.
- **M05:** The mobile app-download heading and store badges are centered.
- **M06/M08:** Fatwa actions use a balanced labeled grid. Quality watch/download
  actions remain within their cards and provide at least 44px-high touch targets.
- **M07:** Reproduced a legacy zero-height panel clipping the fatwa video. Fatwas
  now use the shared viewport-bound player, with a visible close button, loading
  and request-error/retry states, scroll locking, keyboard focus handling, and
  inline mobile video playback. Closing removes media and restores focus.
- **M10:** Reproduced a separate YouTube URL parsing bug on
  `/var-item-17346.htm`: the Shorts URL produced an empty embed ID. The parser now
  handles watch, Shorts, embed, live, and short links, strips sharing parameters,
  and rejects malformed IDs. The corrected iframe contains `q1-HyMYFoGQ`.
- Removed the competing legacy player script from the clips detail view and
  corrected the inherited styling that hid modal close glyphs.

The homepage “أحدث المواد المفرغة” section was left unchanged.

## Validation

- **185 focused feature tests passed (637 assertions)** across media-player,
  fatwa, clips, lectures, recitation, chat-room lesson, and shared-layout tests.
- PHP syntax and Pint checks passed for the changed PHP files. PHPStan passed
  for `MediaPlayerService.php` with a 512MB PHP memory limit; the initial run hit
  the default 128MB limit. The shared player JavaScript passed `node --check`.
- Browser checks at **320, 390, 768, 1024, and 1440px** on clips, lectures, fatwas,
  and recitation detail pages found no document horizontal overflow; quality
  controls stayed within their cards. Mobile card widths aligned consistently.
- Homepage checks at 320/390px showed the complete banner artwork. At 1440px,
  the desktop hero and multi-column layout retained their existing presentation.
- Player checks included mobile portrait, 844×390 landscape, loading/error
  handling, offline request retry, Escape/close, focus restoration, and removing
  the media on close. The fatwa friend form opened without submitting it.
- Actual recitation playback on `/recite-item-9231.htm` was verified: the audio
  was playing with readyState 4 and advancing beyond 71 seconds.

### Verification limits and local environment

The fatwa native video and corrected YouTube iframe were visibly present and
properly sized, but uninterrupted external video streaming was not confirmed.
The test browser reported blocked external document requests during embed checks;
this does not establish a source-side failure. A real-device playback check is
still useful for those external videos.

After the checks above, the local Laravel process and Docker runtime became
unavailable. Laravel was restarted, but Docker's socket was absent and launching
Docker timed out. Consequently the final extra visual recheck (including the last
close-glyph and long-title refinements) could not be completed; the preview needs
the existing local database containers running again. No production deployment
or database migration was performed.


## Follow-up verification after Docker was restored

The user restored Docker, and the local site at `http://localhost:8000/index.php`
loaded successfully. The previously blocked final checks are now complete:

- The fatwa video on `/fatawa-all-168.htm` (second answer, 15162) actually played.
  The browser reported readyState 4, paused false, duration 125.64 seconds, and
  playback advancing past 7.38 seconds. The picture was visible inside the mobile
  dialog and the close control remained accessible.
- The friend dialog's close glyph is visible and usable. The form was opened and
  dismissed without submitting anything.
- The full mobile hero artwork, separate caption/navigation, and centered app
  badges were visually confirmed. Long sidebar titles were checked at 320px:
  14px font size, 25.2px line height, no line clamping, no horizontal overflow.
- The YouTube quality action emitted the correct embed URL
  `https://www.youtube.com/embed/q1-HyMYFoGQ?autoplay=1`. Network inspection tied
  that exact document request to `net::ERR_BLOCKED_BY_CLIENT` (blocked reason:
  other). The blank iframe in this test browser therefore remains a browser-side
  verification limitation; YouTube streaming is not claimed as verified.

This supersedes the local-environment blocker and native-video uncertainty above.
No further application changes were required. The earlier 185 passing tests and
static-analysis result remain applicable. Browser viewport overrides were reset.
