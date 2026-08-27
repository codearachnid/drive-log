# Working in `docs/`

Instructions for Claude Code sessions operating on this documentation tree. Root `CLAUDE.md` and `AGENTS.md` still apply for application code.

## What this tree is

`docs/` is the design record for drive-log. It is docs-first on purpose: the PRD and ADRs were written before any application code so that implementation sessions have settled decisions to build against rather than re-litigating them per session.

Read order for a cold session:

1. `docs/prd/PRD-drive-log.md` sections 1 through 6, for the problem and the functional requirements, then `docs/prd/UX-principles.md` for how it should feel
2. `docs/adr/README.md` for the decision index
3. Whichever ADRs the task touches
4. `docs/specs/README.md` for what is specified and what is not

## Document types

| Type | Location | Purpose | Mutability |
| --- | --- | --- | --- |
| PRD | `docs/prd/` | Problem, requirements, scope | Amend in place, bump `version` |
| ADR | `docs/adr/` | One architectural decision and its trade-offs | Never edit a decided ADR. Supersede it |
| Spec | `docs/specs/` | Implementation-ready detail for one slice | Living until the slice ships, then frozen |
| Session log | `docs/sessions/` | What was decided in a working session and why | Append-only |

## Invariants

These are load-bearing domain rules established in the ADRs. Do not weaken them, work around them, or make them configurable without superseding the ADR that set them.

1. **The driver can never attest their own drives.** Enforced in the `ShareType` enum, not as a permission column or policy override. `ADR-004`.
2. **Attestations are append-only.** No update path except setting `voided_at` and `voided_reason`. Nothing is deleted. `ADR-004`.
3. **Signer contact details are snapshotted at signing time.** Reports render from the snapshot, never from a join to `users`. `ADR-004`.
4. **Night minutes derive from sunset and sunrise**, computed via `date_sun_info()` against the log book's coordinates. Never from a clock rule. `ADR-003`.
5. **`day_minutes + night_minutes = duration_minutes`**, enforced by a model guard on every driver and a database check constraint where the engine supports it. `ADR-003`, `ADR-008`.
6. **`primary_daypart` is display only.** Never sum it for compliance figures. `ADR-003`.
7. **Classification is computed once at completion and persisted**, never derived at read time. `ADR-003`.
8. **One active drive per log book**, enforced by a unique index on the nullable `drives.active_lock` column. `PRD FR-4.3`, `ADR-008`.
9. **One owner per log book**, non-nullable, transfers run under a row lock inside a transaction. `PRD FR-7.6`.
10. **The drive timer is server-anchored and client-computed.** Do not implement it with `wire:poll`. `ADR-005`.
11. **Authorization is re-checked in every Livewire action method**, not only on mount. Livewire public properties round-trip through the client and are user input. `ADR-005`.
12. **Reports are immutable snapshots** with a content hash. Re-rendering reproduces the original exactly. `ADR-006`.
13. **Unattested drives never contribute to certified totals.** They are listed separately. `ADR-006`.
14. **Magic link tokens are stored hashed and single-use, with lifetime set by purpose:** 10 minutes for `login`, 7 days for `invite`, `sign`, and `correct`, the transfer's own expiry for `transfer`. An expired link is a re-issue button, never an error. `ADR-001`.
15. **The owner is never the driver.** Check constraint on `log_books`. The certifying adult originates the book and names the driver by phone number. `PRD FR-2.3`.
16. **Carrier-reserved SMS words are never commands.** `STOP`, `END`, `START`, and their siblings belong to the opt-out layer. Timer keywords are `BEGIN`/`GO`, `DONE`/`FINISH`, and `CONTINUE`, and every timer message carries an authenticated link as the fallback. `ADR-007`.
17. **A forgotten timer never gets an invented end time.** After 8 hours a drive moves to `needs_correction` with `ended_at` null and waits for a human. `ADR-010`.
18. **The app asks once and reminds once.** One signature request per drive, one reminder after 3 days, one weekly owner digest. No other unsolicited message about a drive, and the words "overdue", "late", and "urgent" appear nowhere. `ADR-009`, `docs/prd/UX-principles.md`.

## Decisions already closed

Do not reopen these in an implementation session. If one is genuinely wrong, write a superseding ADR first.

- Phone-only magic link auth, no passwords, no Fortify or Breeze
- Laravel policies over `log_book_members`, not `spatie/laravel-permission`
- Livewire 4 with Flux UI Pro, no Inertia, no SPA, no separate API
- Any Laravel-supported relational database with a portable schema and no engine-specific constructs; prefixed ULIDs via the `HasReferenceId` trait
- Hosting is any Laravel-compatible host; no platform is named in the record
- DomPDF for report rendering, not Browsershot
- Single-tenant, row-scoped by `log_book_id`, no `stancl/tenancy`
- Night is sunset to sunrise, not sunset to midnight
- Goals are thresholds, not caps; totals are running tallies and instructor hours count like any other attested drive
- The parent originates the log book; the driver never does
- Drawn signatures in V1 via `signature_pad`, stored as SVG text on the attestation, typed name as the fallback
- Inter for the body and Dancing Script for signatures, both SIL OFL 1.1, embedded as static TTF
- Every accepted member reads the whole log book
- SMS keyword timer control trusts the sender's phone number for start and end only, with an authenticated link in every timer message
- Non-driver members with edit rights modify unsigned drives; the owner unsigns an attested drive to edit and re-sign it; all of it is logged and none of it prints on the report
- Link lifetime follows purpose; login is 10 minutes, invite, sign, and correct are 7 days, transfer matches the transfer
- The signature request goes out when the drive ends, to the chosen supervisor or else the owner, and lands on the signing screen for that drive
- Drives can be discarded from the active screen, ended at a chosen time, and corrected after auto-close through `needs_correction`
- Restricted-window minutes are stored as a flag at completion and excluded from certified totals
- Location comes from a bundled ZIP centroid table; there is no geocoding service
- "Delete my log book" means archive and scrub; signed history is never deleted
- Tone is gracious and a little fun by requirement, per `docs/prd/UX-principles.md`; "within 60 seconds" is not a goal anywhere

## Writing conventions

- Markdown only. YAML frontmatter on every document.
- Mermaid for every diagram. No ASCII art, no image files.
- No em dashes anywhere.
- Problem first. Open with what breaks, then the decision.
- Confident and direct. Explain the reasoning, do not hedge it.
- Every ADR requires a **Scope & Tenancy Impact** section. It is not optional even when the answer is "row-scoped, no tenancy layer", because that answer is itself the record.
- Cross-document links use relative markdown paths so they resolve on GitHub. Frontmatter `related` entries use Obsidian wikilinks so they resolve in the vault. Keep both forms.

## Adding a document

Templates live beside the documents they template: `docs/adr/_template.md`, `docs/specs/_template.md`, `docs/sessions/_template.md`. Copy, do not improvise the structure.

Slash commands are available: `/new-adr`, `/new-spec`, `/log-session`.

Numbering is sequential and never reused. When adding an ADR, also add the row to `docs/adr/README.md`. When completing a spec, update its status in `docs/specs/README.md`.

## When a spec conflicts with an ADR

The ADR wins. Fix the spec. If the ADR is the thing that is wrong, stop and say so rather than quietly diverging in code, because a divergence between the record and the implementation is worse than either being wrong on its own.

## Open questions that gate work

One. Whether a log book may have a driver who has no phone of their own, recorded under "Open" in `docs/prd/PRD-drive-log.md` section 11. It gates the `log_books.driver_user_id` nullability in `SPEC-001` and nothing else. Every other question in section 11 was answered on 2026-08-26 and the answers are recorded there. Do not reopen them in an implementation session; if one is genuinely wrong, say so and write a superseding ADR.
