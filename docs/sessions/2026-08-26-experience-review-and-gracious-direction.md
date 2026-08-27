---
title: "Experience review and the gracious, fun, easy direction"
type: session-log
date: 2026-08-26
product: drive-log
participants: [Tim Wood, Claude]
status: complete
tags: [session, review, ux, attestation, timer, report, decisions]
related:
  - "[[PRD-drive-log]]"
  - "[[UX-principles]]"
  - "[[ADR-001-phone-first-magic-link-auth]]"
  - "[[ADR-007-sms-keyword-timer-control]]"
  - "[[ADR-009-signature-requests-and-gracious-reminders]]"
  - "[[ADR-010-forgiving-drive-lifecycle]]"
  - "[[SPEC-001-data-model-and-migrations]]"
  - "[[2026-08-26-open-questions-and-platform-decisions]]"
---

# Session log: Experience review and the gracious, fun, easy direction

## Objective

Review the whole design record for experience gaps and flow breaks against the end goal, a printable and shareable signed driving log, before any application code exists. Then fold the accepted findings into the record with a deliberate change of tone: the product should be gracious about people's time, easy to the point of having nothing to learn, and a little fun. "Attest within 60 seconds of receiving a text" was the wrong yardstick for a grandparent and it came out.

## Inputs

- The full design record as of the two earlier sessions on this date: the PRD at 0.2.0, ADR-001 through ADR-008, SPEC-001, and both session logs. No application code exists; the repository is a stock Laravel skeleton.
- Five journeys traced end to end: owner sets up a book, driver logs a drive, supervisor signs, owner monitors, owner generates and shares the report.
- Direction from Tim Wood: adopt all findings, loosen the aggressiveness, make it fun and easy. Families have spent enough time in DMV lines with impatient people who only need to check a box.

## Findings

Ranked as they were reported. Every item marked adopted is now in the record; the file column says where.

| # | Finding | Severity | Outcome |
| --- | --- | --- | --- |
| 1 | Nothing told the supervisor a drive was waiting for their signature. The only SMS designed was the invitation | Flow break | Adopted. FR-4.4, FR-6.11, `ADR-009` |
| 2 | A single 10-minute token TTL killed invite links opened at lunch and made 7-day transfers impossible to accept on day 2. No expired-link recovery existed | Flow break | Adopted. FR-1.3, FR-1.7, `ADR-001`, invariant 14 |
| 3 | The "one tap to sign" promise was about nine steps: land on the book, profile name gate, find the drive, type the name again, draw, checkbox, sign | Flow break | Adopted. FR-1.6, FR-3.2, FR-6.3, FR-6.5 |
| 4 | The driver must own a distinct phone, and nobody names them, so the report header could print blank | Flow break | Name adopted in FR-2.3. Phone left open, PRD section 11 |
| 5 | No reminders for unsigned drives | Flow break | Adopted as one gracious reminder plus a weekly owner digest. FR-6.12, `ADR-009` |
| 6 | No way to cancel a mis-tapped Start | Friction | Adopted. FR-4.15, `ADR-010` |
| 7 | Auto-close invented an end time, and "flagged" was not a status | Integrity | Adopted as `needs_correction`. FR-4.5, FR-4.16, `ADR-010`, `SPEC-001`, invariant 17 |
| 8 | Dashboard did not model a drive in progress; the 2-hour check-in went only to the owner | Friction | Adopted. FR-4.3, FR-4.13, `ADR-007` |
| 9 | Overlapping and duplicate drives were unchecked and could double-count a certified total | Integrity | Adopted. FR-4.17, `ADR-010` |
| 10 | Timer clock skew between phone and server | Polish | Adopted. FR-4.2 |
| 11 | No batch signing for instructors and regular supervisors | Friction | Adopted. FR-6.13, `ADR-009` |
| 12 | Pending queue not scoped to the signer's own drives | Friction | Adopted. FR-6.14 |
| 13 | No pre-generation readiness checklist | Report | Adopted. FR-8.8 |
| 14 | Restricted-window drives had no report treatment | Report | Adopted as excluded and listed. FR-4.9, FR-8.7. Confirm with a driving school |
| 15 | No report history | Report | Adopted. FR-8.9 |
| 16 | Sharing was "account or PDF" and the content hash was decorative | Report | Adopted: share sheet PDF, public verify page. FR-8.10, FR-8.11 |
| 17 | Permit number may be needed on the certification; default range undefined when permit date is null | Report | Range adopted in FR-8.2. Permit number deferred until a clerk or driving school says it is needed |
| 18 | PRD offered deletion; schema forbids it | Contradiction | Adopted as archive and scrub. PRD section 9 |
| 19 | ZIP to coordinates had no named source | Contradiction | Adopted as a bundled centroid table. FR-2.5, PRD section 8 |
| 20 | `users.timezone` was collected and never read | Contradiction | Removed from `SPEC-001` and the profile screen |
| 21 | Kickoff log said Livewire 3 | Contradiction | Correction appended to that log |
| 22 | Manual entry had no supervisor picker; `/` had no redirect for a live session | Contradiction | Adopted. PRD section 7, FR-2.6 |

## Decisions made

| # | Decision | Record |
| --- | --- | --- |
| 1 | Link lifetime follows purpose: login 10 minutes, invite and sign 7 days, transfer matches the transfer. An expired link is a re-issue button, never an error | `ADR-001` amended, PRD FR-1.3 and FR-1.7, invariant 14 |
| 2 | The signature request goes out when the drive ends, to the chosen supervisor or else the owner, landing on the signing screen. One reminder after 3 days, one weekly owner digest, then nothing. Batch signing writes one row per drive | [ADR-009: Signature requests and gracious reminders](../adr/ADR-009-signature-requests-and-gracious-reminders.md) |
| 3 | A forgotten timer moves to `needs_correction` with no end time and waits for a human. Discard and "I forgot to stop" live on the active screen. Overlaps are surfaced and blocked against signed drives | [ADR-010: Forgiving drive lifecycle](../adr/ADR-010-forgiving-drive-lifecycle.md) |
| 4 | The 2-hour check-in goes to the driver and the owner; `DONE` from either ends the drive | `ADR-007` amended |
| 5 | Restricted-window minutes are stored as a flag at completion and excluded from certified totals, listed in their own section | PRD FR-4.9 and FR-8.7, `SPEC-001` |
| 6 | Tone is a requirement. Gracious, easy, a little fun; banned words; milestones; the share card | [UX principles](../prd/UX-principles.md), PRD G6, invariant 18 |
| 7 | "Delete my log book" means archive and scrub; signed history is never deleted | PRD section 9 |
| 8 | Location comes from a bundled ZIP centroid table with no network call | PRD FR-2.5 and section 8 |
| 9 | No timezone on the user; every time renders in the log book's timezone | `SPEC-001` |
| 10 | The owner names the driver at creation and that name seeds the driver's profile | PRD FR-2.3 |

## Key reasoning worth retaining

**"Within 60 seconds" measured the wrong thing.** The goal was never speed; it was that the person in the car is the person who signs. A grandparent who signs Tuesday's drive on Thursday from the text she was sent is the product working perfectly. What the product has to guarantee is that she was asked, that the link still works when she gets to it, and that she is not asked again in a way that makes her mute the number. Speed follows from ease; it is not a target.

**Nagging is an integrity risk, not just a tone problem.** A muted number means no signature request arrives, which means the parent signs later from memory, which is the failure the whole attestation layer exists to prevent. Ask once and remind once is therefore a correctness decision dressed as a kindness.

**The system must never invent evidence.** The auto-close job was the one place the design would have written a number nobody witnessed into a record someone swears to. `needs_correction` costs one enum case and two constraint exemptions and removes that entirely. Every `ended_at` in the compliance path is now a human's.

**Fun is what makes rigor survivable.** The share card and the milestone moments are the cheapest features in the plan and the only ones that make a teenager open the app unprompted. Every rigorous property underneath depends on the log being kept in the first place.

**Most of the gaps were between people, not inside the schema.** The data model was sound on the first pass. What was missing was every moment where one person's action had to reach another person's phone: drive ended, link expired, signature waiting, timer forgotten. That is the pattern to watch for in the unwritten specs.

## Open items

1. **A driver without their own phone.** Gates the nullability of `log_books.driver_user_id` in `SPEC-001` and nothing else. Decide before the M1 migration is written. Recorded in PRD section 11 under Open.
2. **Restricted-window exclusion.** Excluded from certified totals as the conservative reading. Confirm with a driving school before the report language hardens, alongside the earlier open item on Flux Pro component availability.
3. **Permit number on the report.** Deferred. Add if a clerk or driving school asks for it; it is a nullable column and a header line.
4. **Bundled ZIP centroid data.** Choose the dataset and its licence when `SPEC-003` is written. Public domain sources exist for US ZIP centroids.

## Next actions

- [ ] Decide open item 1 and update `SPEC-001` accordingly
- [ ] Write `SPEC-002` through `SPEC-004` against the amended PRD, using the updated "must answer" lists in `docs/specs/README.md`
- [ ] Write every SMS template and screen copy string against `docs/prd/UX-principles.md` when `SPEC-008` and `SPEC-009` are written
- [ ] Add the banned-word test to the M1 test plan so it exists before the first template does
- [ ] Carry forward: Flux Pro component verification, A2P 10DLC registration, static TTF download and `fsType` check
