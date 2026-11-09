# Decision Needed: Website Poll Administration

- **Project:** Old administration migration to the new AdminCP — Phase 4
- **Status:** Paused, awaiting a business decision *(status when shared on 13 October 2026)*
- **Date prepared:** 13 October 2026

> **Decision update — 14 October 2026: APPROVED — POLL ADMINISTRATION REQUIRED.**
> The business approved building Poll Administration in the new AdminCP. The owner added that the migration
> should match how the old system really worked ("parity first"); changes beyond that are handled later. The report
> below is kept as it was shared, so the history of the decision stays visible. The outcome is in Section 11.

---

## 1. Executive Summary

The website has two different features that have both been called "surveys":

1. **Custom Surveys** (questionnaires). These are **already fully managed in the new AdminCP**. Nothing about them
   is missing, and they are not part of this decision.
2. **Website Polls** (short public votes: one question with several choices). Visitors on the new website can
   already view Polls, see results and vote. **However, staff currently have no way to create, edit or remove
   Polls in the new AdminCP.** That work could only be done in the old administration system.

Phase 4 was planned as a small "finishing touches" task for surveys. During the work we found that the missing
pieces belong to Polls, not to Custom Surveys, and that they are a whole feature area rather than a few buttons.
We paused so that we do not build something the business may no longer need.

**We need one decision: do staff still need to create and manage website Polls?**

- **If yes:** we build a complete Poll Administration area in the new AdminCP.
- **If no:** we retire the old Poll administration. Existing Poll data stays preserved, and what happens to the
  public Poll pages is decided separately.

A one-page decision form is in Section 10.

---

## 2. What We Discovered

The old platform used the word "survey" for two unrelated systems:

| | Custom Surveys | Website Polls |
|---|---|---|
| What it is | Multi-question questionnaires, with collected answers and statistics | A single question with up to 12 choices; visitors vote and see results |
| Public website | Not public-facing | Visible on the website (Poll pages, results, homepage Poll box) |
| Managed in the new AdminCP? | **Yes — already migrated** | **No — management still exists only in the old system** |

The earlier migration review grouped them together. That is why Phase 4 was expected to be small.

The investigation also found **three old "category" links** on the old Poll screen: *Add Category*, *Edit
Category* and *Delete Category*. They do not lead to any working function. They were left over from an old News
feature, and nothing in the old system responds to them. **We recommend treating them as retired and not migrating
them.** No decision is needed on these unless the business knows of a reason they are still used.

---

## 3. What Is Already Migrated

- **Custom Surveys, fully:** creating surveys, managing questions, removing surveys, viewing statistics and
  individual responses.
- **Public Polls (visitor side):**
  - list of Polls;
  - viewing a Poll;
  - voting, with the same repeat-vote protection as before;
  - viewing results;
  - the homepage Poll box.

---

## 4. What Is Missing

Nothing in the new AdminCP manages Website Polls. In the old administration system, staff could:

- create a new Poll, with a question and up to 12 choices;
- choose an existing Poll to edit;
- edit a Poll's question and choices;
- remove a Poll;
- remove comments that visitors had posted on a Poll;
- when creating a Poll, optionally publish it together with a News article.

**About Poll comments:** the old website let visitors comment on Polls. The new public website does **not**
currently show Poll comment pages. The homepage Poll box only indicates whether comments exist. Moderating
comments therefore only makes sense if Poll comments are still wanted as a product feature.

---

## 5. Why Engineering Stopped

The original plan for Phase 4 listed two isolated items: a "choose a Poll to edit" screen and a "remove a Poll
comment" action. On their own, these would not be useful:

- a "choose a Poll to edit" screen is pointless without an edit screen behind it;
- a "remove comment" action has little value while the new website does not show Poll comments.

Building them in isolation would create half a feature. Building the whole Poll administration area is a larger
piece of work that is only worthwhile if the business still uses Polls. That is a business question, so we
stopped and are asking it.

---

## 6. Options

### Option A — Keep Poll Administration

Choose this if staff still need to publish or maintain Polls.

The new AdminCP would gain one coherent **Poll Administration** area.

**Required: matching what the old system did**

- see the existing Polls and choose one to work on;
- create a Poll (question plus up to 12 choices);
- edit a Poll's question and choices;
- remove a Poll;
- remove Poll comments, if comments are kept (see question 3 below).

**Standard for every new AdminCP feature**

- access limited to staff who are given the Poll administration permission;
- a record of who changed what and when.

**Optional improvements: only if requested**

- searching or filtering the Poll list;
- a results view inside the AdminCP. Results are already visible on the public site.

Two follow-up questions apply only to Option A.

**Should a Poll still be attachable to a News article?**
When creating a Poll, the old system could also publish a News article and link the two. If the answer is
**yes**, that link is kept. If **no**, Polls are created on their own, which is simpler.

**Are Poll comments still required?**
If **yes**, comments stay part of the product and staff get a way to moderate them. If **no**, Poll comments are
retired and no moderation is built. Existing comments stay stored and are not deleted.

### Option B — Retire Poll Administration

Choose this if staff no longer create or maintain these Polls.

**What retirement means**

- No Poll administration is built in the new AdminCP.
- The old Poll administration screens are listed for switch-off when the old system is decommissioned.

**What retirement does not mean**

- **No data is deleted.** Existing Polls, votes and comments remain preserved.
- **The public Poll pages are not switched off automatically.** We ask you to choose separately what visitors
  should see:
  - keep current Polls and voting as they are for now;
  - keep Polls and results visible, but stop new voting;
  - retire the public Poll pages as well.

  Any change to the public side would be its own small, separately approved step.

---

## 7. Impact of Each Decision

| | Option A — Keep | Option B — Retire |
|---|---|---|
| Staff can manage Polls in the new AdminCP | Yes | No |
| Dependence on the old system for Polls | Removed | Removed, because the old screens are retired |
| Existing Poll data | Preserved | Preserved |
| Public Poll pages | Unchanged | Unchanged unless you choose otherwise (question 4) |
| Further work | Build the Poll Administration area | Small: record the retirement and any public-side choice |
| Risk if the choice is wrong | Effort spent on an unused feature | Staff lose a capability they still need. Mitigation: confirm with the content team before choosing. |

---

## 8. Recommendation

This recommendation follows from how we migrate, not from a view on how the business should use Polls.

- **If Polls are actively used, choose Option A.** Build Poll Administration properly, as one complete capability.
- **If Polls are no longer actively managed, choose Option B.** Retire the administration rather than spend
  effort recreating an unused legacy feature.
- **In either case, do not migrate the isolated items** ("choose a Poll to edit", "remove a Poll comment") on
  their own. Without the rest of Poll administration they are incomplete and give staff no real capability.
- **The three dead "category" links should be retired** in either case.

If you are unsure whether Polls are still used, the people who publish website content are best placed to say.

---

## 9. What Happens Next

**After "YES — Build Poll Administration"**

1. Engineering defines the exact scope from your answers to questions 2 and 3.
2. The Poll Administration area is built in the new AdminCP, with access control and change history.
3. It is tested and verified, then deployed with the other migration phases.
4. The old Poll administration screens are listed for switch-off.

**After "NO — Retire"**

1. The decision is recorded, and Phase 4 is closed as "retired by business decision".
2. The old Poll administration screens are added to the old-system switch-off list.
3. Your answer to question 4 is recorded. Any public-side change is planned as a separate, approved step.
4. No data is deleted.

**After "Not sure"**

Phase 4 stays paused. Work on other phases continues and is not affected.

---

## 10. Business Decision

1. Do we still need staff to create and manage website Polls?
   [ ] YES — Build Poll Administration in Laravel
   [ ] NO — Retire old Poll Administration

2. If YES: should Polls still be attachable to News/Articles?
   [ ] YES
   [ ] NO
   [ ] Not sure / needs discussion

3. If YES: do we still need Poll comments and comment moderation?
   [ ] YES
   [ ] NO
   [ ] Not sure / needs discussion

4. If Poll Administration is retired, what should happen to existing public Polls?
   [ ] Keep current public Polls/voting for now
   [ ] Keep historical Polls/results but stop new voting
   [ ] Retire public Poll functionality too
   [ ] Needs discussion

5. Notes:
   _______________________________________________

6. Decision made by:
   _______________________________________________

7. Date:
   _______________________________________________

---

## 11. Decision Record (added 14 October 2026)

| Question | Outcome |
|---|---|
| 1. Do staff still need to create and manage Polls? | **YES — Build Poll Administration.** Approved. |
| How the details were settled | The owner asked for **parity with the old system's real behavior**, not a redesign. Questions 2 and 3 were therefore answered by checking what actually worked in the old system. |
| 2. Attach Polls to News/Articles? | **Not built.** The old "announce with a News article" option never actually linked the article to the Poll. It only posted a separate story into a News section that no longer exists on the site. There was no working link to preserve. |
| 3. Poll comments and moderation? | **Built.** Removing a comment also removes its replies, as before. One correction: the old system sometimes lowered a Poll's comment count by more than the number of comments removed, so the count is now kept accurate. |
| 4. If retired, public Polls? | Not applicable: administration was approved. Public Polls are unchanged. |
| Old "category" links | Retired, as recommended. They never worked. |

**Status after the decision:** Poll Administration is built and verified locally. Production deployment is
pending, together with the other migration phases.

---

## Appendix — Technical Reference

For the engineering team; not needed to make the decision.

| Item | Reference |
|---|---|
| Custom Surveys (migrated) | Laravel `Admin\SurveyController` — ports `admincp/survey/` (`nuke_survey*` tables) |
| Website Polls, public (migrated) | Laravel `Engagement\PollController` (`nuke_poll_desc`, `nuke_poll_data`) |
| Website Polls, administration (not migrated) | OLD `modules/Surveys/admin/` — ops `create`, `createPosted`, `polledit_select`, `polledit`, `savepoll`, `ChangePoll`, `remove`, `removePosted` |
| Poll comment removal (not migrated) | OLD `RemovePollComment` (`admin/modules/comments.php`); table `nuke_pollcomments` |
| Poll ↔ article link | OLD `createPosted` (News article creation alongside the Poll) |
| Dead category links | `AddCategory` / `EditCategory` / `DelCategory` — linked from the old Poll screen, no handler anywhere in OLD |
| Engineering record | `docs/reviews/old-admin-master-execution-plan.md` §22 (stop), §23 (implementation) |
