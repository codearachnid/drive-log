---
id: ADR-006
title: "Certification report as an immutable snapshot with PDF rendering"
status: proposed
date: 2026-08-26
product: drive-log
deciders: [Tim Wood]
tags: [adr, reporting, pdf, dompdf, integrity, compliance]
supersedes: null
superseded_by: null
---

# ADR-006: Certification report as an immutable snapshot with PDF rendering

## Context

The certification report is the product's actual deliverable. Everything else exists to produce it. It gets handed to a driving school or kept on file behind a DMV certificate that a parent signs under penalty of perjury, so it needs to be complete, verifiable, and stable.

Two properties are non-obvious but necessary.

**Stability.** If the report renders live from the current state of the log book, then the document a driving school received in March will render differently in May after two entries are corrected. There would be no way to reproduce what was handed over, and no way to answer a question about it.

**Honest totals.** The certified total must reflect only attested drives. If unsigned entries are quietly folded into the 45-hour figure, the owner is signing a certificate that overstates verified practice.

There is also a mundane but real constraint: the document is dense, tabular, and multi-page, and it needs embedded typefaces, a body face that matches the app and a script face for typed signatures and initials, plus vector rendering of drawn signatures.

## Decision

Generating a report writes an immutable snapshot; rendering reads only from that snapshot.

**Snapshot**

- Generation serializes the full report payload into `reports.snapshot` as JSON: driver details, period, summary totals, every included drive with its stored day and night minutes, and every live attestation with its snapshotted signer name, initials, relationship, phone, and email.
- A SHA-256 `content_hash` of the canonical payload is stored and printed in the report footer alongside the report ID.
- Re-rendering an existing report reproduces it exactly, regardless of subsequent changes to the log book.
- Generating a new report never mutates an old one. Reports accumulate.
- Only the owner can generate. Any member with attest rights can preview a live, clearly watermarked draft. Every generated report stays listed on the report screen with its ID, range, totals, and hash, and can be printed or downloaded again.
- A public page at `/verify/{report}` shows the generation date, range, certified totals, and content hash for a report ID, and nothing else, so a third party holding a printout can check it without an account. The PDF itself is never reachable by URL; sharing it means the owner sends it, through the phone's share sheet.

**Totals discipline**

- Certified totals include attested drives only.
- Drives in `pending_attestation` are listed in a separate, clearly labeled section with their own subtotal, so the owner can see what is outstanding without it contaminating the number being certified.
- Drives with `restricted_window = true` are likewise excluded from certified totals and listed in their own section, per PRD FR-8.7. Drives in `needs_correction` have no minutes and appear on the readiness checklist rather than in the report.
- Before generation the owner sees a readiness checklist of everything that will be excluded and why, with a reminder action per waiting signer. Nothing on it blocks generation; it exists so the owner is never surprised by their own report.
- Voided attestations are excluded from the report body. The activity log retains them.
- Totals are running tallies. The summary reports logged hours against the 45 and 15 hour requirements and keeps counting past them; nothing caps or truncates logged hours. Instructor-supervised drives count identically to any other attested drive.

**Rendering**

- Primary output is a print-optimized Blade view with a dedicated print stylesheet: page-break control, repeating table headers, and page numbering.
- PDF export uses `barryvdh/laravel-dompdf` against the same Blade template.
- Signatures render from the attestation row: the drawn signature as an `<img>` data URI of the stored SVG when present, otherwise the typed name in the script face. Initials in the log table always render from `signature_initials` in the script face.
- Typefaces are embedded from static TTF files with DomPDF font subsetting on: Inter for the body, matching the Flux UI default, and Dancing Script for signatures and initials. Both are SIL Open Font License 1.1, which permits embedding in documents. DomPDF does not support variable fonts, so the static instances are used. No face under a desktop-only or web-only license is ever embedded.
- The supervisor appendix lists each signer once with full contact details, relationship, and the count of entries they signed.

## Consequences

**Good**

- A report handed to a third party can be reproduced byte for byte months later, which is what makes it useful as a record rather than a printout.
- The content hash gives proportionate tamper evidence: an altered PDF will not match the hash held against the report ID.
- Separating certified from pending totals means the owner cannot accidentally certify unverified hours, which is the specific thing they are legally exposed on.
- One Blade template drives both print and PDF, so the two cannot drift.
- DomPDF requires no headless Chrome, which keeps the server small and the deploy simple.

**Bad**

- DomPDF's CSS support is limited. The template must be built within its constraints from the start rather than styled freely and then fixed, and complex flexbox or grid layout is not available.
- Snapshots duplicate data and grow the database. Negligible at this scale: a completed log book is a few hundred rows and a report snapshot is tens of kilobytes.
- The snapshot must be versioned. A future change to the report structure has to render old snapshots correctly, so the payload carries a schema version and the renderer branches on it.
- Font licensing is a real constraint. Only OFL or Apache licensed faces are embedded, and any substitution must re-check the license and the `fsType` embedding flag in the TTF, because an embedded typeface without embedding rights is a licensing problem shipped inside every generated PDF.
- Drawn signatures are SVG paths from `signature_pad`. DomPDF renders SVG through `php-svg-lib`, which handles paths but not every SVG feature. The stored SVG is constrained to what `signature_pad` emits and validated on write, and the renderer is tested against it.

**Neutral**

- Drawn signatures are in V1 as of 2026-08-26, captured with `signature_pad` and stored as SVG text on the attestation row rather than as image files. No file storage path exists for signatures and the report snapshot stays self-contained.

## Alternatives considered

- **Live rendering with no snapshot.** Simplest and fails the reproducibility requirement outright.
- **Storing only the generated PDF.** Preserves the artifact but loses the structured data, making it impossible to re-render in a new format or answer questions about the underlying numbers.
- **Spatie Browsershot.** Far better CSS fidelity, at the cost of running headless Chrome on the server. Reconsider if the layout proves genuinely infeasible in DomPDF, since layout fidelity on a legal document is worth real operational cost.
- **Client-side PDF generation.** Moves an integrity-critical operation to an untrusted environment. Rejected.
- **Digitally signed PDFs.** Stronger tamper evidence and disproportionate for the threat model. The content hash is the right level.

## Scope & Tenancy Impact

**Scope:** the `reports` table, the generation path, the print and PDF renderers, and the totals aggregation.

**Tenancy:** row-scoped by `log_book_id`. A report is always scoped to exactly one log book and one date range. There is no cross-book aggregation anywhere in the reporting layer, which matters because a household with several children in the system must never produce a document that blends two drivers' hours.

The snapshot design has a useful tenancy property: because it captures attestation contact details that were themselves snapshotted at signing time per [ADR-004: Attestation immutability](ADR-004-attestation-immutability.md), report rendering performs no join against the globally scoped `users` table. The reporting path is therefore fully contained within the log book boundary, and generating a report cannot leak identity data across books even in the presence of a scope bug elsewhere in the application.

Generated PDFs are stored on a private disk with access mediated by policy, never by an unguessable path alone.
