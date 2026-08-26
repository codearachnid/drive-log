---
id: ADR-004
title: "Attestation immutability, contact snapshotting, and signature voiding"
status: proposed
date: 2026-08-26
product: drive-log
deciders: [Tim Wood]
tags: [adr, domain-model, integrity, audit, compliance]
supersedes: null
superseded_by: null
---

# ADR-004: Attestation immutability, contact snapshotting, and signature voiding

## Context

A parent signs the Virginia completion certificate under penalty of perjury. The log backing that signature is therefore not a personal productivity artifact. It is evidence, and it needs the properties of evidence: a signature must mean something specific, it must be attributable to a person who can be contacted, and it must not be possible to alter the thing that was signed without that being visible.

Three specific failure modes drive this decision.

**The self-signature problem.** If the driver can confirm their own hours, every number in the system is self-reported and the attestation layer is theater.

**The mutable-record problem.** If a drive can be edited after it is signed, a 20-minute drive can become a two-hour drive while carrying a grandmother's signature. The signature would then attest to something that never happened, without her knowledge.

**The stale-contact problem.** The report must list each signer's name, relationship, and contact details for follow-up. If those render from the live `users` row, then a person who changes their name, updates their phone, or is removed from the log book causes historical signatures to render incorrectly or disappear. The report would then misrepresent who certified what.

## Decision

Treat `attestations` as an append-only ledger with snapshotted identity.

**Immutability**

- Attestation rows are written once. Application code has no update path except setting `voided_at` and `voided_reason`.
- Nothing is ever deleted. Voided attestations persist and appear in the audit trail.
- A drive holding at least one live attestation is read-only. Edit controls are not rendered and the policy denies the action regardless.

**Snapshotting**

Signing copies onto the attestation row: `name_snapshot`, `phone_snapshot`, `email_snapshot`, `relationship_snapshot`, and the `statement_version` of the exact certification language shown. The report renders exclusively from these columns and never joins to `users` for display.

**Self-attestation prohibition**

The `driver` role returns false for attestation capability in the enum itself. It is not a column, not a policy override, and not configurable. Additionally, the attestation write path asserts `attestation.user_id !== drive.driver_user_id` as a defense in depth check.

**Correction path**

Before a drive is signed, any member whose role permits editing may change it, with each edit and its before and after values in the activity log. After it is signed, corrections are possible but never silent:

1. Only the owner can unsign an attested drive. The UI verb is Unsign; this record calls the same operation an unlock.
2. Unlocking requires a reason, captured as free text.
3. All live attestations on that drive are voided in the same transaction, stamped with `voided_at` and the reason.
4. The drive returns to `pending_attestation`.
5. Every voided signer is notified by SMS that their signature was removed and why.
6. The unlock, the edits that follow, and the re-signing are written to the activity log. None of it appears on the certification report, which renders live attestations only.

**Capture completeness**

Each attestation records `attested_at`, `signature_method` (`drawn` when a canvas signature was captured, `typed` when the canvas was left blank), the typed `signature_name`, derived `signature_initials`, the drawn signature as `signature_svg` when present, `request_ip`, and the statement version. Multiple people may attest the same drive; all live attestations appear on the report.

## Consequences

**Good**

- A signature on the printed report provably refers to a specific, unaltered drive record and a specific person reachable at a specific number.
- The self-attestation prohibition is the property that makes the entire log meaningful rather than self-reported.
- Snapshotting means revoking a member's access, which is a normal and expected administrative act, does not corrupt history. It also avoids leaking a member's current contact details to books they no longer belong to.
- Voiding rather than deleting means an attempt to quietly inflate hours leaves a visible trail: a voided signature, a stated reason, and a notified signer.
- Statement versioning means a future change to the certification language does not misrepresent what an earlier signer actually agreed to.

**Bad**

- Contact details are duplicated across many rows. A person who corrects a typo in their name will see the old spelling on previously signed entries. This is correct behavior for an evidentiary record and confusing behavior for a normal application, so the UI needs to explain it where it surfaces.
- The correction flow is heavier than an inline edit. A parent fixing a mistyped end time triggers SMS notifications to signers. Mitigation: allow correction without voiding for a strictly bounded set of non-material fields, specifically notes, weather, and road type, none of which the attestation statement refers to.
- Owners can void, which means the owner is a trusted party by construction. Acceptable: the owner is the person signing the DMV certificate and bearing the legal exposure.

**Neutral**

- Cryptographic signing of attestation payloads was considered and deferred. The threat model is a family log, not an adversarial one, and the content hash on the report snapshot per [ADR-006: Report snapshot and PDF](ADR-006-report-snapshot-and-pdf.md) provides proportionate tamper evidence.

## Alternatives considered

- **Editable entries with an audit log.** Standard, cheap, and insufficient. An audit log records that a change happened; it does not prevent a signature from silently coming to mean something new.
- **Live joins to `users` for report rendering.** Simpler schema, but breaks on revocation, rename, and account deletion, in a document whose purpose is contactability.
- **Immutable drives with no correction path at all.** Maximally rigorous and unusable. People mistype times, and a system with no correction path will be worked around by creating duplicate entries, which is worse than a controlled unlock.
- **Allowing the driver to attest with a parent counter-signature.** Adds states and complexity to defend a capability with no legitimate use.

## Scope & Tenancy Impact

**Scope:** the `attestations` and `drives` tables, the signing and unlock paths, the notification layer, and the certification report.

**Tenancy:** row-scoped by `log_book_id`. Attestations inherit isolation transitively through `drives` and are additionally constrained by `member_id`, so a signature can only ever exist where an accepted membership existed at signing time.

Two constraints matter at the boundary:

- Foreign keys from `attestations` to `drives` and to `log_book_members` use `ON DELETE RESTRICT`. Removing a member or a drive must fail while signatures exist, rather than cascading signed history out of the database.
- Membership revocation sets `revoked_at` and never deletes the row, precisely so the restrict constraint is never contended and historical attribution survives.

Because contact details are snapshotted rather than joined, an attestation is fully self-contained within its log book. No cross-book read of the `users` table is required to render a report, which keeps the one globally scoped table out of the reporting path entirely.
