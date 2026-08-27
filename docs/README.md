# drive-log documentation

Design record for **Drive Log**, a supervised driving practice log built to satisfy Virginia's requirement of 45 hours of practice including 15 after sunset, certified by a parent or guardian.

Docs are written before code. An implementation session should be able to start from a spec without re-deriving decisions.

## Map

```
docs/
├── CLAUDE.md          agent instructions, invariants, conventions
├── prd/               what we are building and why
├── adr/               decisions, one per file, with trade-offs
├── specs/             implementation detail per slice
└── sessions/          working session logs
```

## Start here

| If you want to | Read |
| --- | --- |
| Understand the product | [PRD](prd/PRD-drive-log.md) sections 1 to 4 |
| Understand how it should feel | [UX principles](prd/UX-principles.md) |
| Understand the data model | [PRD](prd/PRD-drive-log.md) section 5 |
| Understand a decision | [ADR index](adr/README.md) |
| Build something | [Spec index](specs/README.md) |
| Know what changed and why | [Sessions](sessions/) |

## The short version

A teen driver taps start and stop from their phone. The app computes how many of those minutes fell after sunset at the log book's location, because a fixed clock rule is wrong by up to three hours depending on the season and that error is large enough to change whether a family meets the requirement.

Whoever was in the passenger seat is texted a link when the drive ends, authenticates by phone number alone with no account setup, and signs the entry whenever they get to it. The app asks once and reminds once; it never nags. The driver cannot sign their own entries. Mistakes are one tap to fix. At the end, the owner generates an immutable, printable report carrying every signature, each signer's relationship and contact details, and a content hash, and shares it as a PDF.

The product is meant to be easy and a little fun, on purpose. Families have already done their time in the DMV line.

## Architectural decisions

| ADR | Decision | Status |
| --- | --- | --- |
| [001](adr/ADR-001-phone-first-magic-link-auth.md) | Phone-first magic link authentication | Proposed |
| [002](adr/ADR-002-per-logbook-membership-authorization.md) | Per-log-book membership authorization, not global RBAC | Proposed |
| [003](adr/ADR-003-dual-time-classification.md) | Dual time classification, display daypart vs compliance night minutes | Proposed |
| [004](adr/ADR-004-attestation-immutability.md) | Attestation immutability, snapshotting, and voiding | Proposed |
| [005](adr/ADR-005-livewire-flux-ui-layer.md) | Livewire 4 and Flux UI Pro with a server-authoritative timer | Proposed |
| [006](adr/ADR-006-report-snapshot-and-pdf.md) | Certification report as an immutable snapshot | Proposed |
| [007](adr/ADR-007-sms-keyword-timer-control.md) | SMS keyword timer control with an authenticated link fallback | Proposed |
| [008](adr/ADR-008-database-agnostic-schema.md) | Database-agnostic schema with portable invariant enforcement | Proposed |
| [009](adr/ADR-009-signature-requests-and-gracious-reminders.md) | Signature requests at drive end, one gracious reminder, batch signing | Proposed |
| [010](adr/ADR-010-forgiving-drive-lifecycle.md) | Forgiving drive lifecycle: discard, end at a chosen time, needs-correction | Proposed |

## Stack

Laravel 13, PHP 8.5, Livewire 4, Flux UI Pro, Tailwind 4, any Laravel-supported relational database. Prefixed ULIDs via the `HasReferenceId` trait. DomPDF for report output with Inter and Dancing Script embedded. `signature_pad` for drawn signatures. SMS through a driver contract with Twilio in production.

## Wiring this into agent sessions

`docs/CLAUDE.md` is picked up automatically when a session reads files in this tree. To surface it from a cold start, add this to the root `CLAUDE.md` or `AGENTS.md`:

```markdown
## Design record

This project is docs-first. Before implementing anything, read `docs/CLAUDE.md`
for the domain invariants and closed decisions, then the relevant spec in
`docs/specs/`. Do not reopen decisions recorded in `docs/adr/` without writing
a superseding ADR.
```

## Obsidian

This tree mirrors into `open-brain` and is written for both readers. Body links are relative markdown paths so GitHub resolves them; frontmatter `related` entries are wikilinks so the vault graph resolves them. Preserve both forms when editing.
