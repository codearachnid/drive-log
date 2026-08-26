# Implementation specs

A spec turns a slice of the PRD into something buildable without further design work. One slice per spec, sized so a single session can carry it end to end.

[`SPEC-001`](SPEC-001-data-model-and-migrations.md) is written. Use it as the quality bar for the rest rather than working from the template alone.

## Backlog

| Spec | Title | Milestone | Depends on | Status |
| --- | --- | --- | --- | --- |
| [001](SPEC-001-data-model-and-migrations.md) | Data model and migrations | M1 | ADR-002, ADR-003, ADR-004, ADR-007, ADR-008 | Draft |
| 002 | Phone auth and magic links | M1 | ADR-001 | Not started |
| 003 | Log book lifecycle, membership, and invites | M1 | ADR-002, SPEC-002 | Not started |
| 004 | Drive lifecycle and the timer | M2 | ADR-005, SPEC-001 | Not started |
| 005 | Time classification service | M2 | ADR-003 | Not started |
| 006 | Attestation, correction, and voiding | M3 | ADR-004, SPEC-004 | Not started |
| 007 | Certification report and PDF | M4 | ADR-006, SPEC-006 | Not started |
| 008 | SMS delivery and notifications | M3 | ADR-001, ADR-007 | Not started |
| 009 | UI screens and Flux component inventory | M2 to M4 | ADR-005 | Not started |
| 010 | Ownership transfer | M3 | ADR-002, SPEC-003 | Not started |

No spec is currently blocked. The questions that gated `SPEC-005` and `SPEC-007` were answered on 2026-08-26 and are recorded in PRD section 11.

## What each unwritten spec must answer

**002 Phone auth and magic links**: E.164 normalization and the library doing it, the `magic_links` schema and token minting, hashing and lookup, TTL and single-use enforcement, the `purpose` and `context` payload shape for login versus invite versus transfer, rate limiter keys and thresholds, the identical-response guarantee for unknown numbers, session lifetime and the guard implementation, and the carrier link-prefetch mitigation from ADR-001.

**003 Log book lifecycle, membership, and invites**: creation flow and required fields including coordinate capture from a ZIP code, the `ShareType` enum and its capability methods, the invite path from phone entry through accepted membership, attaching an invite to an existing user, revocation semantics, the owner-is-never-driver rule with the driver named by phone number at creation, and the global scope plus policy implementation from ADR-002.

**004 Drive lifecycle and the timer**: the state machine transitions and what guards each, the start and stop endpoints, the server timestamp handshake and the Alpine elapsed-time computation, heartbeat cadence and silent failure, retry with the original tap time, draft autosave, the `active_lock` unique index for one active drive, the 8-hour auto-close job, and the `next_checkin_at` scheduling of the 2-hour and 45-minute owner check-ins from ADR-007.

**005 Time classification service**: the `date_sun_info()` wrapper and its interface, the window intersection algorithm including drives crossing sunset, sunrise, and midnight, the daypart clock boundaries, persistence at completion, and the test matrix across solstices, equinoxes, both DST transitions, and every boundary-crossing shape.

**006 Attestation, correction, and voiding**: the signing flow and statement versioning, the snapshot write, the self-attestation guard in both the enum and the write path, the owner unsign transaction, void plus notify, the per-role edit rights on unsigned drives with before and after values in the activity log, the `signature_pad` canvas in an Alpine component with SVG validation on write and the typed fallback when the canvas is blank, and the non-material field carve-out that permits editing notes and conditions without voiding.

**007 Certification report and PDF**: the snapshot payload schema and its version field, canonical serialization for the content hash, the Blade template within DomPDF's CSS constraints, page-break and repeating-header behavior, embedding Inter and Dancing Script as static TTF with subsetting, rendering drawn SVG signatures through `<img>` data URIs with the typed-name fallback, the supervisor appendix, and the separation of certified from pending totals.

**008 SMS delivery and notifications**: the `SmsSender` contract, the Twilio implementation, the log driver for local, queued dispatch and retry, message templates with sender identification and STOP handling, the inbound webhook with Twilio signature validation and `MessageSid` idempotency, keyword parsing for `BEGIN`, `GO`, `DONE`, `FINISH`, and `CONTINUE` with the reserved-word list from ADR-007, the authenticated end-drive link in every timer message, the check-in job, and the A2P 10DLC registration checklist.

**009 UI screens and Flux component inventory**: every route and its Livewire component, the verified Flux Pro component list, the 375px layout baseline, dark mode as default for evening use, and the one-tap path from a texted link to a completed signature.

**010 Ownership transfer**: nomination of any accepted member except the driver, the SMS acceptance flow, the atomic swap under `lockForUpdate()` with the `pending_lock` column, the outgoing owner's demotion role, 7-day expiry, cancellation, and activity log entries on both ends.

## Status values

`not started` → `blocked` → `draft` → `ready` → `shipped`

`ready` means an implementation session can build from it with no further questions. If a session has to make a design decision while building, the spec was not ready and should be amended rather than the decision being left in the code.

Use `/new-spec` or copy [`_template.md`](_template.md).
