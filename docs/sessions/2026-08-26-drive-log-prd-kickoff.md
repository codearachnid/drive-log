---
title: "Drive Log PRD kickoff"
type: session-log
date: 2026-08-26
product: drive-log
participants: [Tim Wood, Claude]
status: complete
tags: [session, prd, kickoff, drive-log, laravel, livewire, flux]
related:
  - "[[PRD-drive-log]]"
  - "[[ADR-001-phone-first-magic-link-auth]]"
  - "[[ADR-002-per-logbook-membership-authorization]]"
  - "[[ADR-003-dual-time-classification]]"
  - "[[ADR-004-attestation-immutability]]"
  - "[[ADR-005-livewire-flux-ui-layer]]"
  - "[[ADR-006-report-snapshot-and-pdf]]"
---

# Session log: Drive Log PRD kickoff

## Objective

Produce a PRD and supporting ADRs for a Laravel application that logs a teen driver's supervised practice hours, supports SMS magic-link access with no account creation, allows sharing to supervising adults by phone number, captures per-entry sign-off from whoever was in the passenger seat, and produces a printable certification report.

## Inputs

- Verbal requirements: magic link by phone, share by phone number, associated-record picker, owner role, ownership transfer, in-progress data persistence, start/stop timing plus manual entry, morning/afternoon/evening/night tracking, share types of parent / instructor / other observer, per-entry attestation, printable report with initials, script signature, contact details, and relationship.
- Follow-up constraint: Livewire and Flux UI for the UI layer.
- External research: Virginia DMV requirement of 45 supervised hours including 15 after sunset, certified by parent or guardian; under-18 permit holders restricted from driving midnight to 4:00 a.m.; Virginia publishes no official log form.

## Decisions made

| # | Decision | Record |
| --- | --- | --- |
| 1 | Authenticate by phone number with single-use hashed SMS magic links, no passwords; one token primitive serves login, invite, and transfer | [ADR-001: Phone-first magic link auth](../adr/ADR-001-phone-first-magic-link-auth.md) |
| 2 | Authorization via per-log-book membership rows and Laravel policies, not Spatie RBAC, because roles are relationship properties not person properties | [ADR-002: Per-log-book membership authorization](../adr/ADR-002-per-logbook-membership-authorization.md) |
| 3 | Store two independent time classifications: clock-based daypart for display, sunset-derived day/night minutes for compliance; compute once at completion and persist | [ADR-003: Dual time classification](../adr/ADR-003-dual-time-classification.md) |
| 4 | Attestations are append-only with snapshotted signer contact details; driver can never self-attest; editing a signed drive voids signatures and notifies signers | [ADR-004: Attestation immutability](../adr/ADR-004-attestation-immutability.md) |
| 5 | Livewire 3 full-page components with Flux UI Pro; drive timer is server-anchored and client-computed so it survives signal loss | [ADR-005: Livewire and Flux UI layer](../adr/ADR-005-livewire-flux-ui-layer.md) |
| 6 | Certification report writes an immutable JSON snapshot with a content hash; DomPDF renders the same Blade template used for print | [ADR-006: Report snapshot and PDF](../adr/ADR-006-report-snapshot-and-pdf.md) |

## Key reasoning worth retaining

**Sunset math is the product.** A fixed clock rule for "night" is wrong by up to three hours depending on season. In Richmond, sunset moves from roughly 8:40pm in late June to roughly 4:50pm in early December. Any clock-based night rule either discards most winter night hours or credits summer daylight as night. This single insight is why the application beats a spreadsheet.

**The attestation layer carries the integrity weight, not the timer.** Client-supplied end timestamps are trusted, which is a real gap in a system where the driver benefits from longer entries. It is bounded because a supervising adult must sign each entry. That is why self-attestation is prohibited in the enum itself rather than as a configurable permission.

**Zero-friction access is a correctness requirement, not a UX preference.** If a supervising grandparent cannot sign within a minute of receiving a text, the entry gets signed later by a parent who was not in the car, and the record becomes fiction. This drove phone-first auth, deep-linked invites, and the rejection of any native or PWA-first approach.

**Snapshotting appears three times for the same reason.** Attestation contact details, report payloads, and time classification are all captured at the moment of the event rather than derived at read time. The record is evidentiary; nothing a person has signed may change underneath them.

## Open items

1. Confirm whether "after sunset" is interpreted as sunset-to-sunrise or sunset-to-midnight. Sunset-to-sunrise is assumed. Verify with a driving school or the DMV before the report language hardens. Resolved: sunset to sunrise.
2. Confirm whether behind-the-wheel hours with a licensed instructor count toward the 45. Resolved: they count, and totals are uncapped running tallies.
3. Select an embedding-permissive script typeface for signature rendering. Resolved: Dancing Script, with Inter for the body, both OFL.
4. Verify current Flux Pro availability of `flux:table` and `flux:date-picker`.
5. Begin A2P 10DLC brand and campaign registration. External lead time, no engineering workaround, will block launch if started late.
6. Decide whether the driver can originate a log book or only a parent. Resolved: only the parent.
7. Settle the product name. Resolved: Drive Log. Mile Marker was the working placeholder.

## Next actions

- [ ] Resolve open items 1 and 2 with a phone call to a local driving school
- [ ] Start 10DLC registration
- [ ] Scaffold M1: schema, prefixed ULID models, policies, factories, phone auth with log SMS driver
- [ ] Write the day/night classification test matrix before writing the classifier
