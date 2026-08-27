# Architecture Decision Records

One decision per file. Numbered sequentially, never reused, never renumbered.

## Index

| ADR | Title | Status | Date | Supersedes |
| --- | --- | --- | --- | --- |
| [001](ADR-001-phone-first-magic-link-auth.md) | Phone-first magic link authentication | Proposed | 2026-08-26 | |
| [002](ADR-002-per-logbook-membership-authorization.md) | Per-log-book membership authorization instead of global RBAC | Proposed | 2026-08-26 | |
| [003](ADR-003-dual-time-classification.md) | Dual time classification, display daypart versus compliance night minutes | Proposed | 2026-08-26 | |
| [004](ADR-004-attestation-immutability.md) | Attestation immutability, contact snapshotting, and signature voiding | Proposed | 2026-08-26 | |
| [005](ADR-005-livewire-flux-ui-layer.md) | Livewire 4 and Flux UI Pro with a server-authoritative drive timer | Proposed | 2026-08-26 | |
| [006](ADR-006-report-snapshot-and-pdf.md) | Certification report as an immutable snapshot with PDF rendering | Proposed | 2026-08-26 | |
| [007](ADR-007-sms-keyword-timer-control.md) | SMS keyword control of the drive timer with an authenticated link fallback | Proposed | 2026-08-26 | |
| [008](ADR-008-database-agnostic-schema.md) | Database-agnostic schema with portable invariant enforcement | Proposed | 2026-08-26 | |
| [009](ADR-009-signature-requests-and-gracious-reminders.md) | Signature requests at drive end, one gracious reminder, batch signing | Proposed | 2026-08-26 | |
| [010](ADR-010-forgiving-drive-lifecycle.md) | Forgiving drive lifecycle: discard, end at a chosen time, needs-correction | Proposed | 2026-08-26 | |

## Status values

- **proposed**: written, not yet built against
- **accepted**: implementation has started or shipped under this decision
- **superseded**: replaced. Set `superseded_by` and leave the file in place
- **rejected**: considered and declined. Keep it, the reasoning is the value

## Rules

1. A decided ADR is not edited. If the decision changes, write a new one and set `supersedes` and `superseded_by` on both.
2. Every ADR carries a **Scope & Tenancy Impact** section, even when the answer is that everything is row-scoped with no tenancy layer. That answer is part of the record.
3. Alternatives considered are mandatory and must include why each was rejected. An ADR with no alternatives is a note, not a decision.
4. Consequences are split into good, bad, and neutral. If there are no bad consequences, the decision has not been thought through.
5. Adding an ADR means adding its row to this table in the same commit.

Use `/new-adr` or copy [`_template.md`](_template.md).
