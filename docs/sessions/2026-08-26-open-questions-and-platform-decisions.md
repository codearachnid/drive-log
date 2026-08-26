---
title: "Open questions resolved, SMS timer control, and platform decisions"
type: session-log
date: 2026-08-26
product: drive-log
participants: [Tim Wood, Claude]
status: complete
tags: [session, decisions, sms, signatures, fonts, database, livewire]
related:
  - "[[PRD-drive-log]]"
  - "[[ADR-003-dual-time-classification]]"
  - "[[ADR-004-attestation-immutability]]"
  - "[[ADR-005-livewire-flux-ui-layer]]"
  - "[[ADR-006-report-snapshot-and-pdf]]"
  - "[[ADR-007-sms-keyword-timer-control]]"
  - "[[ADR-008-database-agnostic-schema]]"
  - "[[2026-08-26-drive-log-prd-kickoff]]"
---

# Session log: Open questions resolved, SMS timer control, and platform decisions

## Objective

Close the six open questions from PRD section 11 so `SPEC-005` and `SPEC-007` unblock, and record the requirements and platform constraints raised in the same session: SMS keyword control of the timer with a parent check-in, edit and unsign rules, a database-agnostic schema, Livewire 4, PHP 8.5, and brand-free hosting.

## Inputs

- Answers from Tim Wood to each open question, recorded in the decisions table.
- New requirements: text keywords to start and end the timer; a check-in to the parent at 2 hours with `CONTINUE` re-checking every 45 minutes; an authenticated link to end the timer in every message; non-driver members edit unsigned entries; the owner unsigns, edits, and re-signs, logged but never reported.
- Platform constraints: any Laravel-compatible host, any Laravel-supported relational database, Livewire 4, PHP 8.5, no hosting brand names in the record.
- Carrier fact: `STOP`, `STOPALL`, `UNSUBSCRIBE`, `CANCEL`, `END`, and `QUIT` are opt-out keywords; `START`, `YES`, and `UNSTOP` are opt-in; `HELP` and `INFO` are help. Twilio intercepts these before the application webhook by default. Source: Twilio's opt-out management documentation and the CTIA Messaging Principles and Best Practices.
- Licensing fact: SIL OFL 1.1 permits embedding in documents. Inter and Dancing Script are both OFL 1.1. DomPDF embeds static TrueType only, not variable fonts.
- Database fact: MySQL has no partial unique indexes; SQLite cannot add a check constraint after table creation; every supported driver treats NULLs as distinct in a unique index.

## Decisions made

| # | Decision | Record |
| --- | --- | --- |
| 1 | Night is sunset to sunrise | PRD section 11, `ADR-003` |
| 2 | Instructor hours count toward totals; totals are uncapped running tallies shown against the 45 and 15 hour thresholds | PRD FR-5.5, FR-5.6, `ADR-006` |
| 3 | The parent originates the log book; the owner is never the driver, enforced by a check constraint | PRD FR-2.3, `SPEC-001` |
| 4 | Drawn signatures in V1 via `signature_pad`, stored as SVG text on the attestation, typed name as fallback | PRD FR-6.3, `ADR-004`, `ADR-006` |
| 5 | Inter for the body and Dancing Script for signatures, both OFL 1.1, static TTF, subsetting on | `ADR-006` |
| 6 | Every accepted member reads the whole log book | PRD section 3, unchanged |
| 7 | SMS keyword timer control with non-reserved words, sender-number trust for start and end only, an authenticated link in every timer message, and the 2-hour then 45-minute owner check-in | [ADR-007: SMS keyword timer control](../adr/ADR-007-sms-keyword-timer-control.md), PRD FR-4.10 to FR-4.13 |
| 8 | Non-driver members with edit rights modify unsigned drives; the owner unsigns to edit and re-sign; all of it logged, none of it on the report | PRD FR-4.14, FR-6.7, FR-6.10, `ADR-004` |
| 9 | Database-agnostic schema: portable types, unique-on-nullable lock columns instead of partial indexes, check constraints where supported plus model guards everywhere | [ADR-008: Database-agnostic schema](../adr/ADR-008-database-agnostic-schema.md), `SPEC-001` |
| 10 | Livewire 4, PHP 8.5, Laravel 13; hosting is any Laravel-compatible host with no platform named | `ADR-005`, PRD section 8 |

## Key reasoning worth retaining

**The timer vocabulary everyone reaches for is the vocabulary the carriers reserve.** `START`, `STOP`, and `END` are opt-in and opt-out commands intercepted before the application sees them. A driver ending a drive with `STOP` would silently unsubscribe from every magic link. That is why the keywords are `BEGIN`/`GO` and `DONE`/`FINISH`, why every timer message names them, and why every timer message also carries an authenticated link: the link is the path that cannot be broken by a wrong word.

**Trusting the sender's number is acceptable only because the attestation layer exists.** A spoofed start or end creates or truncates an unsigned drive, and an unsigned drive contributes nothing until an adult signs it. The same reasoning that let ADR-005 trust client end timestamps covers SMS.

**Storing signatures as SVG text keeps the record self-contained.** An attestation row with the drawing inside it needs no file storage, and the report snapshot stays complete without file references. The cost is that client-supplied SVG is an XSS vector if inlined, so it is validated on write and only ever rendered through an `<img>` data URI.

**The owner-not-driver constraint is what "parent creates" actually means.** There is no way to know a new user is a teenager. What can be enforced is that the person who originates the book, and is therefore its owner, is not the person whose drives it records.

**Goals are thresholds.** Capping the tally at 45 hours would discard the strongest evidence a family has. The tally runs; the goals are the lines it is shown against.

**Portability costs one denormalized column per invariant, not the invariant.** A unique index on a nullable lock column is the partial unique index every engine supports. Check constraints go where the engine allows and the model guard covers the rest, which also makes the invariants visible in the code.

## Open items

1. Verify current Flux Pro availability of `flux:table` and `flux:date-picker`. Carried from the kickoff.
2. Begin A2P 10DLC brand and campaign registration. Carried from the kickoff; still the long-lead item.
3. Fix the Livewire 4 component authoring style per screen when `SPEC-009` is written.
4. Bump `composer.json` to `"php": "^8.5"` once the local toolchain is on 8.5.

## Next actions

- [ ] Write `SPEC-005` and `SPEC-007`, now unblocked
- [ ] Fold ADR-007 into `SPEC-004` and `SPEC-008` when they are written
- [ ] Fold ADR-008 into the `SPEC-001` migrations before M1 starts
- [ ] Download static TTF instances of Inter and Dancing Script and confirm the `fsType` embedding flag is installable
