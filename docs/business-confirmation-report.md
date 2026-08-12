# Business Confirmation Report — Way2Allah Platform Migration

**Prepared for:** Product Owner / Business Stakeholder
**Prepared by:** Platform Migration Team
**Date:** 7 August 2026
**Open items:** 9 requiring a decision, 1 minor, 2 resourcing

---

**Status update (2026-08-07): 9 of the 10 items below (#1-#9, #13) are now resolved.** The Business Owner answered all of them directly, plus 3 follow-up questions on details this report deliberately left as pending questions rather than assumptions (`ChatRoomAdminController` keep/retire, #6's dashboard identity, #2's banner-asset source). This report is kept as-is below — a historical record of the questions as originally posed — rather than edited in place, per this project's "visible correction, never silent rewrite" convention. **For the resolutions themselves, see `docs/reviews/wave-6-business-confirmation-reconciliation.md` (full reconciliation, evidence-tier classification) and `decision-log.md` #13 (the authoritative summary entry).** `PROJECT-HANDOFF.md` §4 also reflects current status. Items #10, #11, #12 (Part V infrastructure / resourcing / timeline, not itemized in the table below) remain open, unaffected by this update.

---

**How to read this report:** every item below reflects what our technical review has confirmed so far — it does not include a recommendation on what the business decision should be. Each item ends with the specific question that needs an answer, or the specific low-effort check that would resolve it, before the related engineering work can safely begin.

---

## At a Glance

Nine items require a genuine decision or a quick factual check from the business side. Ordered by the reference numbering used throughout the migration's technical documentation, for cross-reference.

| Ref | Item | Status | The question in one line |
|---|---|---|---|
| #1 | Video Fatwa Library & Advanced Search | Blocked | Is this content still needed? |
| #2 | Ramadan Page & "Share This Site" Banners | Blocked | Still promoted each season? |
| #3 | English-Language Site | Low risk | Still offered, or dormant? |
| #4 | Live Voice Chat Rooms vs. Zoom | Blocked | Which system is actually used today? |
| #5 | Legacy Cross-Login Bridge | Security-adjacent | Is the old account data still meaningful? |
| #6 | Video/Audio Admin Content Tools | Blocked — high priority | Design fresh, copy an old system, or something else? |
| #7 | Content Backup/Booking Tool | Needs verification | Is an external tool still using this? |
| #8 | Relationship to the Older Site | Needs verification | One shared database, or two? |
| #9 | Admin Password Security Audit | Security-sensitive | Audit now, or reset all passwords regardless? |
| #13 | Legacy Web Address Preservation | Needs input | How much search-ranking value is at stake? |

---

## Confirmation #1 — Video Fatwa Library & Advanced Search

**Status:** Blocked
**Affects:** the public video-fatwa browsing section and the site's cross-content advanced search page.

### Why implementation is blocked

Every web address for both features — over twenty distinct page patterns — depends on two internal dispatcher files that no longer exist anywhere in the current website's files. As it stands today, none of these pages can be reached through the site's normal navigation or search-engine links; they return broken pages.

### Evidence gathered

- All fatwa and search web addresses route through two missing files, confirmed absent from a full review of the current codebase. *(Source: routing audit, fatawa.md / advanced-search.md)*
- A live, older version of this same website (`old.way2allah.com`), still running on the same server today, serves this exact content successfully — its fatwa page title matches the current site's own page-title text exactly, character for character. *(Source: old-domain-investigation.md)*
- The older site's own search box submits its results to the *current* site, not to itself — a live, working connection between the two, not an abandoned relic. *(Source: old-domain-investigation.md, Part 2)*
- A confirmed security vulnerability (SQL injection) in both features has already been fixed at the source-code level. This fix is complete and does not depend on the decision below. *(Source: decision-log #11 — completed 7 Aug 2026)*

### Business choices

**Choice A — Still needed:** Content is confirmed reachable and used (via the older site) and should continue to be offered on the new platform.
*Technical impact:* A real, moderate-to-large migration — new working web addresses, and porting roughly sixteen files' worth of business logic, including the cross-content search feature.

**Choice B — No longer needed:** This content/capability has effectively already been retired or superseded and does not need to move to the new platform.
*Technical impact:* None. Formally closed out; no further engineering time spent.

### Recommended next step

Business decision required. The evidence found so far leans toward this content still being live and used — but confirming that, and deciding whether it belongs on the new platform, is a product call, not a technical one.

---

## Confirmation #2 — Ramadan Page & "Share This Site" Banners

**Status:** Blocked
**Affects:** the seasonal Ramadan program page and the "help spread the site" banner-exchange promotional page.

### Why implementation is blocked

Both pages depend on the same missing dispatcher file as Confirmation #1, making them unreachable through the site's normal navigation today.

### Evidence gathered

- The Ramadan page's own production error logs show real visitor traffic and activity during an actual past Ramadan season — direct evidence of genuine historical use, not a page nobody ever saw. *(Source: pages.md §2, production error-log analysis)*
- The "share this site" banner page references roughly twenty-five promotional images, all pointing to a folder that does not exist anywhere in the current website's files — this content is very likely already broken for anyone who does reach it. *(Source: help.md §5)*

### Business choices

**Choice A — Still active:** Ramadan content is promoted each season, and/or the banner-exchange program is still valued.
*Technical impact:* One consolidated Ramadan page replacing three duplicated legacy files; banner-exchange images would need to be re-sourced regardless, since the current ones are missing.

**Choice B — Retired:** Neither page is part of current marketing activity.
*Technical impact:* None — both pages are formally closed out.

### Recommended next step

Business decision required for both pages. Note the two halves may reasonably get different answers — the Ramadan page has real historical evidence of use; the banner page's promotional assets are already confirmed missing regardless of the decision.

---

## Confirmation #3 — English-Language Site

**Status:** Low risk
**Affects:** a completely separate, self-contained English-language version of the website — its own files, its own database, its own accounts, entirely disconnected from the main Arabic site.

### Why implementation is blocked

This migration only has authority over the current Arabic-site codebase. Whether an English offering continues at all is a product decision that sits outside this migration's technical scope either way.

### Evidence gathered

- The only connection found between the two sites, anywhere in the code, is a single "English" link in the main site's navigation menu. No shared code, login system, or data of any kind. *(Source: english.md, vendor integration review)*

### Business choices

**Choice A — Still offered:** An English-language presence is still wanted.
*Technical impact:* None as part of this migration — a genuine, modern English version would be scoped as its own separate future project.

**Choice B — Dormant:** No longer an active part of the offering.
*Technical impact:* A one-line change — remove or repoint the single navigation link.

### Recommended next step

Business decision required, but low urgency — technical risk is minimal under either answer, and no engineering work is currently paused waiting on it.

---

## Confirmation #4 — Live Voice Chat Rooms vs. Zoom

**Status:** Blocked
**Affects:** the site's live, real-time voice chat room feature ("غرفة الهداية — Room of Guidance").

### Why implementation is blocked

Evidence about whether this feature is still used as originally built is genuinely mixed, not simply unconfirmed.

### Evidence gathered

- A page exists in the code that replaces the room with a static Zoom meeting invitation and download link — but this page has no working web address anywhere on the site. No visitor can currently reach it through normal navigation. *(Source: chat_room.md, routing verification)*
- The pages visitors *can* actually reach today still attempt to load the original real-time chat technology (a third-party service embedded via an older integration), unchanged. *(Source: chat_room/chat_rooms.php, chat_room/chat_room.php — direct read)*
- Taken together, the evidence reads as an unfinished switch to Zoom — someone began the move but the live pages were never actually updated to point to it.

### Business choices

**Choice A — Keep the original system:** Live voice rooms via the original technology are still wanted.
*Technical impact:* A moderate rebuild of a real-time chat integration, including confirming the underlying third-party chat service is still operational.

**Choice B — Zoom has already replaced it:** In practice, everyone already uses Zoom for this; the original system is effectively retired.
*Technical impact:* Minimal — simply finish pointing the room page at Zoom and retire the rest.

### Recommended next step

Business decision required — but likely the fastest of all these items to resolve, since it's a simple factual question about current day-to-day practice: does anyone still run sessions through the original system, or has everyone already moved to Zoom?

---

## Confirmation #5 — Legacy Cross-Login Bridge

**Status:** Security-adjacent
**Affects:** an internal bridge connecting the site's forum-based accounts to an older, separate user table used during a past login system.

### Why implementation is blocked

A password-check step in this bridge was found deliberately switched off in the code. Whether the account data it would have checked is still meaningful can't be determined from the code alone.

### Evidence gathered

- A related, more urgent issue in this same bridge — accidentally exposing part of a password hash on every login attempt — was already found and fixed as an emergency measure. This is complete and unrelated to the remaining question. *(Source: 00-blockers.md — fixed 29 Jul 2026)*
- The bridge is confirmed to still be reachable and actively used by real login attempts, but the disabled password check means the linked older account data currently provides no real security value either way. *(Source: admincp.md §4, vendor-vbulletin-forum.md §5)*

### Business choices

**Choice A — Still meaningful:** The older account data is still relevant and its check should be carefully re-enabled on the new platform.
*Technical impact:* A small, careful piece of security engineering, done deliberately rather than by simply flipping the disabled check back on.

**Choice B — Obsolete:** This bridge and its linked account data no longer serve a purpose.
*Technical impact:* None — formally retired, one fewer legacy system carried into the new platform.

### Recommended next step

Business decision required. Not urgent on its own, but should not be left open indefinitely given its security-adjacent nature.

---

## Confirmation #6 — Video/Audio Admin Content Management Tools

**Status:** Blocked — high priority
**Affects:** the internal admin screens content staff would use to add, edit, or remove video sermons (khotab) and Qur'an recitations (telawah).

### Why implementation is blocked

This capability does not currently exist in a working form anywhere in the admin panel — and starting design work before this is resolved risks throwing away real design effort, not just coding time, since the two realistic paths forward produce genuinely different results.

### Evidence gathered

- The admin screens under these two menu items are confirmed to be broken, mislabeled leftover copies of an unrelated feature (the live chat-room admin screen) — not a partial or outdated version of real content-management tools. The menu labels were edited to look like content management; the underlying functionality was not. *(Source: wave-6-analysis.md, wave-6-verification-review.md — direct code comparison)*
- The permission structure for a full content-management system (roughly thirty distinct controls — add/edit/delete for authors, series, items, alternate download links, and more) already exists in the code, suggesting this was planned for but never built. *(Source: khotab/menu.php, direct read)*
- The older company system (`old.way2allah.com`) has its own working admin login, which may or may not be what content staff actually use for this task today — not confirmed, as this review did not attempt to log in to a system outside its authorized scope. *(Source: old-domain-investigation.md — access intentionally not attempted)*

### Business choices

**Choice A — Design fresh:** Build a new, modern content-management screen as part of the new platform, informed by the existing permission structure.
*Technical impact:* A genuine design-and-build effort, sized independently once scoped.

**Choice B — Model it on the older system:** If staff currently manage content through `old.way2allah.com`'s admin panel, use its design as the reference.
*Technical impact:* Different scope from Choice A — requires first confirming what that system's screens actually look like and do.

**Choice C — Different process entirely:** Content staff may already manage this content some other way not yet identified.
*Technical impact:* Unknown until identified — could significantly change or remove the scope of this item.

### Recommended next step

Business decision required before any design or prototyping work begins here. This is the highest-priority open item in this report — the three choices are not variations of the same work, and starting the wrong one wastes real design effort, not just implementation time.

---

## Confirmation #7 — Content Backup/Booking Tool

**Status:** Needs verification
**Affects:** a separate, working piece of code (outside the admin panel) that appears to serve an automated content-backup or download tool, spanning five different content tables.

### Why implementation is blocked

Real, working code for this exists, but it isn't known who or what currently relies on it.

### Evidence gathered

- Server logs confirm this tool's endpoint received a real request within the past several weeks. *(Source: production request log, 1 Jul 2026)*
- The same log shows the endpoint correctly rejected that request for lacking a valid access key — proving the endpoint is alive and properly gated, but not proving routine, ongoing use by a legitimate automated tool. *(Source: legacy-reference-copy-audit.md §4b)*

### Business choices

**Choice A — Still in use:** An external backup or archival tool still relies on this today.
*Technical impact:* A real, moderate integration effort to preserve this behavior on the new platform.

**Choice B — Abandoned:** This was a one-off or discontinued integration.
*Technical impact:* None — safely left behind.

### Recommended next step

Quick factual verification recommended, not a product judgment call — likely answerable in one conversation with whoever manages server or hosting operations, to confirm whether a scheduled job still calls this endpoint.

---

## Confirmation #8 — Relationship to the Older Site

**Status:** Needs verification
**Affects:** not a feature on its own — a foundational question that directly affects how much confidence to place in the evidence behind Confirmations #1, #4, and #6 above.

### Why implementation is blocked

`old.way2allah.com` is confirmed to be a real, live, older version of this same website, still running today on the same server. Multiple checks suggest its content is closely tied to the current site, but it was not possible to confirm from outside whether the two share one live database or two separately maintained ones.

### Evidence gathered

- Identical branding and matching content on both sites, including one specific item — confirmed uploaded within the last two weeks — appearing correctly on both. *(Source: old-domain-investigation.md, Part 2)*
- A controlled test comparing the two sites' visit counters for the same item showed the counts did *not* move together — evidence against the two being one fully identical, always-in-sync system. *(Source: old-domain-investigation.md, "hit-counter test")*
- Best-supported reading of the combined evidence: an older deployment of the same application, with the exact database relationship still genuinely unclear — not a case of insufficient effort, but of honestly mixed findings. *(Source: ADR-0009)*

### Business choices

**Choice A — Resolve it directly:** Authorize the fastest, lowest-risk method available: simply asking whoever manages hosting or domain infrastructure whether the two sites share a database connection.
*Technical impact:* None — a one-time factual question, not engineering work.

**Choice B — Accept as unresolved:** Treat the current "closely related, exact relationship unconfirmed" finding as final.
*Technical impact:* None directly, but leaves residual uncertainty in the evidence behind three other items in this report.

### Recommended next step

Quick factual verification recommended — a single question to Operations/Hosting would meaningfully strengthen confidence in three other open items above.

---

## Confirmation #9 — Admin Password Security Audit

**Status:** Security-sensitive
**Affects:** the passwords currently stored for staff admin accounts.

### Why implementation is blocked

This can only be answered by directly examining live account data — it cannot be determined by reading source code alone.

### Evidence gathered

- The migration project does have local access to a full, current copy of the production database. No individual account or credential data has been queried from it, deliberately, given the sensitivity of that data and the lack of explicit authorization to do so. *(Source: legacy-reference-copy-audit.md §4a)*
- Separately, the storage method used for admin passwords has already been confirmed weak in places (an outdated encoding method with no modern alternative on parts of the login system) — this is a known, already-documented finding, independent of what specific accounts might be affected. *(Source: 00-master-migration-blueprint.md §16, item 4)*

### Business choices

**Choice A — Audit first:** Authorize a narrow, read-only check of stored admin passwords for known-weak formats, with only a summary — never raw data — reported back.
*Technical impact:* A small, one-time, carefully-scoped data review.

**Choice B — Reset regardless:** Skip the audit and simply require every admin account to set a new password once, as standard practice, during the platform switch — making the underlying question moot.
*Technical impact:* No investigation needed; standard rollout housekeeping only.

### Recommended next step

Business decision required. Choice B is the lower-effort, lower-risk path if a one-time password reset for admin accounts is acceptable to the team managing them.

---

## Confirmation #13 — Legacy Web Address Preservation

**Status:** Needs input
**Affects:** not a feature — the site's existing web addresses. Two hundred and seventeen distinct URL patterns currently exist across the site.

### Why implementation is blocked

Preserving every one of these addresses exactly, rather than letting lower-value ones redirect to a general page, is real, ongoing engineering effort. Whether that effort is worth it depends entirely on how much current search-engine ranking value these specific addresses hold — information outside this technical review's visibility.

### Evidence gathered

None gathered on this item — resolving it requires marketing/SEO data (search console reports, current ranking positions) that sits outside the engineering team's access and expertise.

### Business choices

**Choice A — Full preservation:** Every one of the 217 patterns is preserved exactly.
*Technical impact:* Highest effort, highest fidelity, lowest search-ranking risk.

**Choice B — Reduced set:** Only higher-value patterns are preserved exactly; lower-value ones redirect to a general equivalent.
*Technical impact:* Lower effort, with some search-ranking risk for the patterns not preserved exactly.

### Recommended next step

Input needed from whoever owns SEO/marketing for this site — this determines the effort tradeoff, not the engineering team.

---

## Noted, Lower Urgency

Two further items are tracked but do not currently block any engineering work and are not module-level decisions in the same sense as the nine above.

### Confirmation #10 — Survey Answer Timing Edge Case

A narrow technical question: should someone's submitted survey answer stay frozen exactly as it looked the moment a survey closed, even if the survey's own settings are edited afterward? A safe, working default behavior is already in place and does not depend on this being answered.

*Technical impact if never resolved:* none currently — flagged for completeness only.

### Team Sizing & Project Timeline

Two further open items concern how the remaining migration work is staffed and sequenced — the size/experience level of the implementation team, and how much schedule urgency applies — rather than whether any specific feature should be migrated or retired. These shape planning, not the decisions above, and are better suited to a separate resourcing conversation than this report.

---

*Business Confirmation Report — Way2Allah Platform Migration*
*9 decisions requested · 1 noted · 2 resourcing items outstanding*
