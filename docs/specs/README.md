# Implementation specs

A spec turns a slice of the PRD into something buildable without further design work. One slice per spec, sized so a single session can carry it end to end.

[`SPEC-001`](SPEC-001-data-model-and-migrations.md) is written. Use it as the quality bar for the rest rather than working from the template alone.

## Backlog

| Spec | Title | Milestone | Depends on | Status |
| --- | --- | --- | --- | --- |
| [001](SPEC-001-data-model-and-migrations.md) | Data model and migrations | M1 | ADR-002, ADR-003, ADR-004, ADR-007, ADR-008, ADR-009, ADR-010 | Draft |
| 002 | Phone auth and magic links | M1 | ADR-001 | Not started |
| 003 | Log book lifecycle, membership, and invites | M1 | ADR-002, SPEC-002 | Not started |
| 004 | Drive lifecycle and the timer | M2 | ADR-005, ADR-010, SPEC-001 | Not started |
| 005 | Time classification service | M2 | ADR-003 | Not started |
| 006 | Attestation, correction, and voiding | M3 | ADR-004, ADR-009, SPEC-004 | Not started |
| 007 | Certification report and PDF | M4 | ADR-006, SPEC-006 | Not started |
| 008 | SMS delivery and notifications | M3 | ADR-001, ADR-007, ADR-009 | Not started |
| 009 | UI screens and Flux component inventory | M2 to M4 | ADR-005, UX principles | Not started |
| 010 | Ownership transfer | M3 | ADR-002, SPEC-003 | Not started |

No spec is currently blocked. The questions that gated `SPEC-005` and `SPEC-007` were answered on 2026-08-26 and are recorded in PRD section 11. The experience review of the same day, recorded in [the session log](../sessions/2026-08-26-experience-review-and-gracious-direction.md), added requirements to nearly every unwritten spec; the lists below already include them. One open question, a driver without a phone, touches `SPEC-001` only.

## What each unwritten spec must answer

**002 Phone auth and magic links**: E.164 normalization and the library doing it, the `magic_links` schema and token minting, hashing and lookup, single-use enforcement, the lifetime per purpose from PRD FR-1.3 and which purposes invalidate their predecessors, the `purpose` and `context` payload shape for login, invite, sign, and transfer, the landing rules from FR-3.2 and FR-6.11, the expired-link page with its one re-issue button from FR-1.7, rate limiter keys and thresholds, the identical-response guarantee for unknown numbers, the redirect for an already-authenticated visit to `/` from FR-2.6, session lifetime and the guard implementation, and the carrier link-prefetch mitigation from ADR-001.

**003 Log book lifecycle, membership, and invites**: the creation flow with driver name and phone, ZIP code, and optional permit date, the bundled ZIP centroid table and how it resolves coordinates and timezone with no network call, the driver's name becoming the invited user's initial display name, the `ShareType` enum and its capability methods, the invite path from phone entry through accepted membership, attaching an invite to an existing user, revocation semantics, the owner-is-never-driver rule, archive-and-scrub as the meaning of deletion from PRD section 9, and the global scope plus policy implementation from ADR-002.

**004 Drive lifecycle and the timer**: the state machine transitions including `needs_correction` and what guards each, the start and stop endpoints, the server timestamp handshake with the clock offset from FR-4.2 and the Alpine elapsed-time computation, the dashboard's running-drive state from FR-4.3, the "Who was with you?" prompt at end and the default-to-last-supervisor rule, discard from FR-4.15, "I forgot to stop" from FR-4.16, the overlap check from FR-4.17, the drive summary card and share sheet from FR-4.18, heartbeat cadence and silent failure, retry with the original tap time, draft autosave, the `active_lock` unique index for one active drive, the 8-hour move to `needs_correction` with no invented end time from ADR-010, and the `next_checkin_at` scheduling of the 2-hour and 45-minute check-ins to driver and owner from ADR-007.

**005 Time classification service**: the `date_sun_info()` wrapper and its interface, the window intersection algorithm including drives crossing sunset, sunrise, and midnight, the daypart clock boundaries, the `restricted_window` flag from FR-4.9 computed in the same pass, persistence at completion including for a `needs_correction` drive once its end is set, and the test matrix across solstices, equinoxes, both DST transitions, and every boundary-crossing shape.

**006 Attestation, correction, and voiding**: the signing flow with the name prefilled and captured inline on first signature per FR-1.6, the "Sign and confirm" button with no separate checkbox per FR-6.5, statement versioning, the snapshot write, the self-attestation guard in both the enum and the write path, the batch signing screen from FR-6.13 writing one attestation row per drive, the "Waiting for you" queue ordering from FR-6.14, the "That wasn't me" reroute from FR-6.11, the owner unsign transaction, void plus notify, the per-role edit rights on unsigned drives with before and after values in the activity log, the `signature_pad` canvas in an Alpine component with SVG validation on write and the typed fallback when the canvas is blank, and the non-material field carve-out that permits editing notes and conditions without voiding.

**007 Certification report and PDF**: the readiness checklist from FR-8.8, the default date range rule from FR-8.2, the snapshot payload schema and its version field, canonical serialization for the content hash, the Blade template within DomPDF's CSS constraints, page-break and repeating-header behavior, embedding Inter and Dancing Script as static TTF with subsetting, rendering drawn SVG signatures through `<img>` data URIs with the typed-name fallback, the supervisor appendix, the separation of certified totals from the unsigned and restricted-window sections per FR-8.6 and FR-8.7, the report history list from FR-8.9, the public verify page from FR-8.10 and exactly what it may reveal, and download through the share sheet from FR-8.11.

**008 SMS delivery and notifications**: the `SmsSender` contract, the Twilio implementation, the log driver for local, queued dispatch and retry, every message template written to the voice in the UX principles with sender identification and STOP handling, the signature request at drive end and the single 3-day reminder from ADR-009 with `sign_requested_at` and `sign_reminded_at`, the weekly owner digest that sends only when something is waiting, milestone messages to the owner, the needs-correction note to driver and owner, the inbound webhook with Twilio signature validation and `MessageSid` idempotency, keyword parsing for `BEGIN`, `GO`, `DONE`, `FINISH`, and `CONTINUE` with the reserved-word list from ADR-007, the authenticated link in every timer message, the check-in job addressing driver and owner, and the A2P 10DLC registration checklist.

**009 UI screens and Flux component inventory**: every route in PRD section 7 and its Livewire component, the verified Flux Pro component list, the 375px one-thumb layout baseline, dark mode as default for evening use, the path from a texted link to a completed signature with the name as the only typed field, the drive summary card and milestone moments, the copy for every screen and message checked against `docs/prd/UX-principles.md`, and the empty, waiting, and error states for each screen, since those are where tone is won or lost.

**010 Ownership transfer**: nomination of any accepted member except the driver, the SMS acceptance flow, the atomic swap under `lockForUpdate()` with the `pending_lock` column, the outgoing owner's demotion role, 7-day expiry, cancellation, and activity log entries on both ends.

## Status values

`not started` → `blocked` → `draft` → `ready` → `shipped`

`ready` means an implementation session can build from it with no further questions. If a session has to make a design decision while building, the spec was not ready and should be amended rather than the decision being left in the code.

Use `/new-spec` or copy [`_template.md`](_template.md).
