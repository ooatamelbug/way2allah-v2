# Wave 5 Documentation Synchronization — Verification

**Date:** 2026-08-06. **Purpose:** confirm that every amendment `wave-5-gap-reconciliation-proposal.md` proposed was applied correctly, and that Blueprint, Roadmap, Decision Log, module documentation, and review documents all agree — before any Wave 5 code is written.

---

## What changed

| Document | Change |
|---|---|
| `00-implementation-roadmap.md` | Wave 3: new task **3.4** (`surveys/`). Wave 5: new tasks **5.8** (`questionnaire/`), **5.9** (`khotab/uploader(s).php`), **5.10** (`broadcasting/`'s working half). Wave 5's Deliverables line and task 5.6/5.7's text updated to match (5.7 explicitly folded into 5.6; a pre-existing citation error — `broadcasting/`'s dead files wrongly cited under 5.6 — corrected to belong to the new 5.10). |
| `00-master-migration-blueprint.md` | Dated revision-history entry added (2nd entry for 2026-08-06). §18's Wave 3 and Wave 5 Contents cells updated to name the 4 new tasks. No Part II decision changed; no §4/§6/§7 edit needed (see below). |
| `docs/migration/modules/admincp.md` | Status-update note added: `admincp/index.php`'s login mechanics have changed since this doc's reads (now session-based, layered on the old cookie mechanism); every dead-code/security finding re-verified and confirmed still accurate. Not rewritten in place. |
| `docs/migration/modules/surveys.md` | Status-update note added: this module now has Roadmap task 3.4; explicitly distinguished from `admincp/survey/`'s unrelated engine. |
| `docs/reviews/wave-5-admin-domain-analysis.md` | Status line updated — superseded by the reconciliation proposal for its amendment recommendations specifically; its own evidence (§1-§6) stands unchanged. |
| `docs/reviews/wave-5-gap-reconciliation-proposal.md` | Status line updated to Approved; each of its 5 proposed amendments marked ✅ Applied. |
| `docs/decision-log.md` | **No change.** See below for why. |

**Why no Blueprint §4/§6/§7 edit was needed:** every model this round's 4 new tasks require (`Poll`, `PollOption`, `QuestionnaireResponse`, `Uploader`) was already named in §6's plain-Eloquent-model list, and §7's domain mapping already correctly separated `admincp/survey/` from public poll voting before any of this pass's research began. The gap in every one of these cases was a missing Roadmap task, not a missing or incorrect Blueprint decision — confirmed, not assumed, by re-checking §6/§7's text directly against this round's findings.

**Why no Decision Log entry was added:** every finding this round was one of two shapes — (1) a real capability the Blueprint had already architecturally anticipated but the Roadmap's task list hadn't caught up to yet (all 4 new tasks), or (2) a confirmation that an existing Wave 0 decision (`AdminGuard`'s session-based design) still holds against current legacy code, not a new decision. Nothing met the Decision Log's own standing bar — "expected to influence future implementation across multiple modules, or explains why an important architectural choice was made." Documentation synchronization for already-Blueprint-anticipated gaps doesn't qualify, per your own instruction this round.

---

## New or amended Roadmap tasks

| Task | What | Depends on | Effort |
|---|---|---|---|
| 3.4 | `surveys/` — public PHP-Nuke poll voting (`Poll`/`PollOption` models, vote/results/list, `cookiedecode()` crash fixed not ported) | Wave 0/1 only | Small |
| 5.8 | `questionnaire/` — read-only da'wah-caller registration viewer | 5.3 | Small |
| 5.9 | `khotab/uploader(s).php` — upload-team list/sort/recompute/backfill; add-uploader form explicitly excluded pending its own confirmation | Wave 1 khotab models, `vbulletin` connection, 5.3 | Small-Medium |
| 5.10 | `broadcasting/`'s working half — `edit_stream.php` only, reuses the existing `Channel` model | `Channel` model, 5.3 | Small |
| 5.6 (amended) | Text corrected — no longer wrongly cites `broadcasting/`'s dead files; scope unchanged | 5.3 | (unchanged) |
| 5.7 (amended) | Explicitly folded into 5.6's own rebuild rather than a separate pass; scope unchanged | 5.6 | (unchanged) |

**Approved execution order** (unchanged from the reconciliation proposal): 5.1 → 5.2 → 5.3 → 5.4 → 5.8 → 5.10 → 5.5 → 5.9 → 5.6 → 5.7, with 3.4 (`surveys/`) run independently, any time — it has no dependency on and no dependent within the Wave 5 sequence.

---

## Remaining deferred / business-confirmation items

Unchanged by this round — none of the 5 new items introduced a new open confirmation:

- **Business Confirmation #6** (khotab/telawah admin CRUD, design fresh vs. `old.way2allah.com` reference) — still gates the one Admin-domain capability confirmed genuinely absent from this codebase (Pattern B). Not part of Wave 5.
- **Business Confirmation #7** (`nuke_backup_booking`'s active-use status) — still gates whether task 5.6 needs a dedicated backup-booking UI, or just the staff-management consolidation.
- **Business Confirmation #9** (live weak/plaintext `nuke_authors` credentials) — a DB read, relevant to how urgently 5.3/5.6/5.7's security fixes should be treated as urgent vs. routine.
- **New, non-blocking item from task 5.9's scope note:** the intended behavior of `khotab/uploader.php`'s add-uploader form (no backend exists to infer it from) — recommended to clarify before building that specific sub-feature, not before starting 5.9 itself (the list/sort/recompute/backfill parts don't depend on it).
- **Operational note, not a formal confirmation:** `admincp/`'s core files show signs of active, ongoing legacy-side maintenance in parallel with this migration (recent 2026 mtimes) — worth a quick check with the business on who's making these changes before Wave 5 starts touching the same pages for behavioral reference.

---

## Confirmation

Re-checked directly, not assumed: the Roadmap now has exactly 11 numbered tasks across this round's affected waves (3.4, 5.1-5.10) with no duplicate numbers; Blueprint §18's Wave 3 and Wave 5 rows name every task the Roadmap now has; both amended module docs (`admincp.md`, `surveys.md`) carry their status-update notes; both Wave 5 review documents carry accurate status lines reflecting what was approved and applied. No code was touched this round — the full test suite and PHPStan were re-run anyway, as a baseline check: **207/207 tests passing, 0 PHPStan errors**, identical to before this documentation pass, confirming nothing was inadvertently touched outside `docs/`.

**Blueprint, Roadmap, Decision Log, module documentation, and review documents are synchronized. The project is internally consistent and ready for Wave 5 implementation to begin, in the approved execution order.**
