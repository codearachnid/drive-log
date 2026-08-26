---
title: "Drive Log PRD"
type: prd
status: draft
version: 0.2.0
product: drive-log
owner: Tim Wood
created: 2026-08-26
updated: 2026-08-26
jurisdiction: US-VA
stack: [laravel-13, php-8.5, livewire-4, flux-ui-pro, tailwind-4, database-agnostic]
tags: [prd, laravel, livewire, flux, driving-log, attestation, magic-link]
related:
  - "[[ADR-001-phone-first-magic-link-auth]]"
  - "[[ADR-002-per-logbook-membership-authorization]]"
  - "[[ADR-003-dual-time-classification]]"
  - "[[ADR-004-attestation-immutability]]"
  - "[[ADR-005-livewire-flux-ui-layer]]"
  - "[[ADR-006-report-snapshot-and-pdf]]"
  - "[[ADR-007-sms-keyword-timer-control]]"
  - "[[ADR-008-database-agnostic-schema]]"
---

# Drive Log

## 1. Problem

A teen driver in Virginia must accumulate 45 hours of supervised practice, at least 15 of which occur after sunset, before a parent or guardian signs the DMV completion certificate under penalty of perjury. The state does not supply a log form, so families track this on paper, in a notes app, or in a spreadsheet.

Three things break in practice:

1. **The night hours get under-counted.** "After sunset" is a moving target that shifts by roughly two and a half hours between June and December in Richmond. A clock-based rule of thumb like "after 8pm" silently discards legitimate winter night hours and over-credits summer evening hours.
2. **The supervising adult is not always the record keeper.** A grandmother, an aunt, or a driving instructor rides along, then the log entry gets reconstructed days later by a parent who was not in the car. The certification is only as good as the memory of someone who was not there.
3. **Nothing is verifiable at the end.** The parent signs one certificate attesting to 45 hours with no underlying record of who supervised what.

Drive Log fixes all three by making the person in the passenger seat sign each entry at the time it happened, from their own phone, with no account setup.

## 2. Goals

- **G1.** A driver can start and stop a drive in two taps, and the timer survives a dead app, a locked screen, and a tunnel with no signal.
- **G2.** Day and night minutes are computed from actual sunset and sunrise at the log book's location, never guessed.
- **G3.** Any adult can be given access by phone number alone and attest to an entry within 60 seconds of receiving a text, with no app install and no password.
- **G4.** The finished log prints to a single document that a DMV clerk or driving school would accept without follow-up questions.
- **G5.** A signed entry cannot be silently altered after the fact.

### Non-goals

- Not a GPS tracker. Drive Log does not follow the vehicle or score driving behavior.
- Not a driver education curriculum or skills checklist.
- Not multi-tenant SaaS in V1. No organization layer, no billing, no driving school admin console.
- Not an official DMV integration. The output is a human-readable document, not an electronic filing.

## 3. Users and roles

Roles are scoped to a single log book, not to the application. The same person can be an owner on one log book and a supervisor on another.

| Role | Who | View log | Create/edit entries | Attest entries | Manage members | Transfer ownership |
| --- | --- | --- | --- | --- | --- | --- |
| `owner` | The certifying parent or guardian | Yes | Yes | Yes | Yes | Yes |
| `driver` | The teen accumulating hours | Yes | Own entries only, while unsigned | **No** | No | No |
| `parent_guardian` | Second parent, step-parent | Yes | Yes | Yes | Yes | No |
| `instructor` | Licensed driving instructor | Yes | Yes | Yes | No | No |
| `supervisor` | Grandmother, aunt, adult sibling, family friend | Yes | Unsigned entries only | Yes | No | No |
| `viewer` | Anyone given read-only access | Yes | No | No | No | No |

Create/edit applies to unsigned drives only. An attested drive is read-only until the owner unsigns it, per FR-6.7.

**Hard rule:** the driver can never attest their own entries. This is the single integrity property that makes the whole record meaningful. See [ADR-004: Attestation immutability](../adr/ADR-004-attestation-immutability.md).

**Second hard rule:** the owner is never the driver. The certifying adult originates the log book and names the driver by phone number. A check constraint on `log_books` rejects a book where the two are the same person.

## 4. Glossary

- **Log Book**: the shareable record for one driver working toward one licensing goal. The unit of ownership and sharing.
- **Drive**: one supervised driving session, whether timed live or entered manually.
- **Attestation**: a supervising adult's signed confirmation that a specific drive happened as recorded.
- **Daypart**: the human-facing bucket for a drive: morning, afternoon, evening, or night.
- **Night minutes**: minutes falling between sunset and sunrise at the log book's location. This is the compliance number and it is independent of daypart.
- **Certification Report**: the printable, immutable snapshot produced at the end.

## 5. Domain model

```mermaid
erDiagram
    USERS ||--o{ LOG_BOOK_MEMBERS : "belongs to"
    USERS ||--o{ MAGIC_LINKS : "authenticates via"
    USERS ||--o{ ATTESTATIONS : signs
    LOG_BOOKS ||--o{ LOG_BOOK_MEMBERS : has
    LOG_BOOKS ||--o{ DRIVES : contains
    LOG_BOOKS ||--o{ OWNERSHIP_TRANSFERS : has
    LOG_BOOKS ||--o{ REPORTS : produces
    LOG_BOOK_MEMBERS ||--o{ DRIVES : "supervises"
    LOG_BOOK_MEMBERS ||--o{ ATTESTATIONS : "signs as"
    DRIVES ||--o{ ATTESTATIONS : "is attested by"

    USERS {
        ulid id PK "usr_"
        string phone_e164 UK
        timestamp phone_verified_at
        string name
        string email "nullable"
        string timezone
    }
    MAGIC_LINKS {
        ulid id PK "mgl_"
        string phone_e164
        string token_hash UK
        string purpose "login|invite|transfer"
        json context
        timestamp expires_at
        timestamp consumed_at
        string request_ip
    }
    LOG_BOOKS {
        ulid id PK "lbk_"
        string label
        ulid owner_user_id FK
        ulid driver_user_id FK
        string jurisdiction "US-VA"
        string timezone
        decimal latitude
        decimal longitude
        date permit_issued_on
        int goal_total_minutes "2700"
        int goal_night_minutes "900"
        string status "active|archived"
    }
    LOG_BOOK_MEMBERS {
        ulid id PK "mbr_"
        ulid log_book_id FK
        ulid user_id FK
        string share_type
        string relationship_label
        boolean can_attest
        timestamp invited_at
        timestamp accepted_at
        timestamp revoked_at
    }
    DRIVES {
        ulid id PK "drv_"
        ulid log_book_id FK
        ulid driver_user_id FK
        ulid supervisor_member_id FK "nullable"
        timestamp started_at
        timestamp ended_at
        string timezone_snapshot
        int duration_minutes
        int day_minutes
        int night_minutes
        string primary_daypart
        timestamp sunset_at
        timestamp sunrise_at
        decimal distance_miles "nullable"
        json conditions
        text notes
        string entry_method "live|manual|sms"
        timestamp next_checkin_at "nullable"
        string status
    }
    ATTESTATIONS {
        ulid id PK "att_"
        ulid drive_id FK
        ulid member_id FK
        ulid user_id FK
        timestamp attested_at
        string signature_method "typed|drawn"
        string signature_name
        string signature_initials
        text signature_svg "nullable"
        string name_snapshot
        string phone_snapshot
        string email_snapshot
        string relationship_snapshot
        string statement_version
        string request_ip
        timestamp voided_at
        string voided_reason
    }
    REPORTS {
        ulid id PK "rpt_"
        ulid log_book_id FK
        ulid generated_by_user_id FK
        date period_start
        date period_end
        json snapshot
        string content_hash
        string pdf_path
        timestamp generated_at
    }
```

Prefixed ULIDs follow the existing `HasReferenceId` trait pattern. All timestamps are stored UTC and rendered in the log book timezone, never the viewer's.

## 6. Functional requirements

### 6.1 Authentication

- **FR-1.1** A visitor enters a phone number on the landing page and receives an SMS containing a single-use link.
- **FR-1.2** Following the link establishes an authenticated session and marks the phone verified. No password exists anywhere in the system.
- **FR-1.3** Tokens are single-use, expire in 10 minutes, are stored hashed, and are invalidated when a newer token is issued for the same number.
- **FR-1.4** Requests are rate limited per phone number and per IP. The response is identical whether or not the number is known, so the endpoint cannot be used to enumerate users.
- **FR-1.5** Sessions are long-lived (30 days, sliding) so a supervising adult who signed once in March is still signed in come June.
- **FR-1.6** A user can set a display name and optional email after first login. Name is required before that user can attest anything.

See [ADR-001: Phone-first magic link auth](../adr/ADR-001-phone-first-magic-link-auth.md).

```mermaid
sequenceDiagram
    autonumber
    actor V as Visitor
    participant W as Livewire (Flux form)
    participant A as AuthService
    participant Q as Queue
    participant S as SMS driver
    V->>W: enters +1 804 555 0134
    W->>A: requestLink(phone)
    A->>A: normalize to E.164, rate limit check
    A->>A: mint token, store SHA-256 hash, TTL 10m
    A->>Q: dispatch SendMagicLink
    Q->>S: send SMS with signed URL
    S-->>V: "Tap to open your driving log: ..."
    W-->>V: "Check your phone" + resend timer
    V->>A: GET /auth/{token}
    A->>A: hash lookup, verify unconsumed and unexpired
    A->>A: consume token, find or create user, verify phone
    A-->>V: redirect to log book picker or deep link target
```

### 6.2 Log book selection and persistence of identity

- **FR-2.1** After authenticating, a user with exactly one log book membership is routed straight into it.
- **FR-2.2** A user with more than one membership sees a picker listing each log book with the driver's name, their own role, and progress.
- **FR-2.3** A user with no membership is offered the option to create a log book, becoming its owner. The creator names the driver by phone number at creation and the driver is invited as `driver`. A log book is always originated by the certifying adult, never by the driver, and the owner cannot be the driver.
- **FR-2.4** One owner may hold many log books. This matters directly: five children means five log books over time, and the second one should cost nothing to set up.

### 6.3 Sharing

- **FR-3.1** An owner or parent/guardian enters a phone number, selects a share type, and enters a relationship label such as "Grandmother" or "Driving instructor, Abba".
- **FR-3.2** The invitee receives an SMS with a magic link that authenticates them and drops them directly onto the log book, not onto a generic landing page.
- **FR-3.3** If the phone number already belongs to a user, the invite attaches to that existing account rather than creating a duplicate.
- **FR-3.4** Invites can be revoked. Revocation removes access going forward but never removes or invalidates attestations already made.
- **FR-3.5** The relationship label is required and appears on the printed report next to that person's signature.

```mermaid
sequenceDiagram
    autonumber
    actor O as Owner
    participant L as Log Book
    actor G as Grandmother
    O->>L: invite +1 804 555 0199, type=supervisor, label="Grandmother"
    L->>L: create member (invited_at), mint magic link (purpose=invite)
    L-->>G: SMS deep link
    G->>L: taps link
    L->>L: find or create user, verify phone, set accepted_at
    L-->>G: log book view, pending signatures surfaced first
    G->>L: attest drive drv_01J...
    L->>L: snapshot name, phone, email, relationship onto attestation
    L-->>O: notification "Grandmother signed Tuesday's 47-minute drive"
```

### 6.4 Recording a drive

- **FR-4.1** The driver taps **Start Drive**, optionally selecting who is supervising from the member list. A `drives` row is written immediately with status `active`.
- **FR-4.2** The running timer is anchored to the server's `started_at`, so elapsed time is computed client-side from a fixed origin and stays correct through refreshes, backgrounding, and network loss.
- **FR-4.3** Only one drive may be active per log book at a time.
- **FR-4.4** The driver taps **End Drive**. If the stop request fails due to connectivity, it is retried and the recorded `ended_at` is the moment of the tap, not the moment the server received it.
- **FR-4.5** A drive left active for more than 8 hours is auto-closed by a scheduled job, flagged, and surfaced to the owner for correction rather than silently discarded.
- **FR-4.6** Manual entry allows a date, start time, and end time to be recorded after the fact, with `entry_method = manual`.
- **FR-4.7** Any in-progress form input is persisted server-side as a `draft` drive within 750ms of the last keystroke. Closing the browser mid-entry loses nothing.
- **FR-4.8** Optional fields: distance, weather, road type, traffic level, free-text notes.
- **FR-4.9** Entries that fall wholly or partly within Virginia's midnight to 4:00 a.m. restricted window for under-18 permit holders are accepted but flagged with a visible warning, since the hours may not be creditable and the drive itself may not have been permitted.
- **FR-4.10** The driver can start and end a drive by text message. `BEGIN` or `GO` sent to the application number starts a drive with `entry_method = sms`; `DONE` or `FINISH` ends it. Keywords are case-insensitive and matched on the first word. The sender's phone number identifies them, and a command acts only when it resolves to exactly one active log book where the sender holds the right role: driver for starting, driver or owner for ending. Any other case replies with an authenticated link instead of guessing.
- **FR-4.11** `START`, `STOP`, `END`, `CANCEL`, `QUIT`, `UNSUBSCRIBE`, `STOPALL`, `YES`, `UNSTOP`, `HELP`, and `INFO` are carrier-reserved and are never commands. Every timer message states the correct keywords. If a driver with an active drive texts an opt-out word anyway, the drive is ended, since the intent is unambiguous, and the owner is told that the driver's number is opted out until it texts `START`.
- **FR-4.12** Every timer message the application sends, whether start confirmation, check-in, or auto-close notice, carries an authenticated deep link to the active drive, so the timer can always be ended by tap when a keyword fails or the sender is ambiguous.
- **FR-4.13** When a drive has been active for 2 hours, the owner is texted a check-in naming the driver and the elapsed time. Replying `CONTINUE` keeps the drive open and schedules another check-in 45 minutes later, for as long as the owner keeps replying. Replying `DONE` ends the drive at the time of the reply. No reply changes nothing until the 8-hour auto-close in FR-4.5.
- **FR-4.14** Any member whose role permits editing (owner, parent/guardian, instructor, supervisor) may modify a drive while it is unsigned. The driver may modify only their own unsigned drives. Every edit is written to the activity log with before and after values.

See [ADR-007: SMS keyword timer control](../adr/ADR-007-sms-keyword-timer-control.md).

### 6.5 Time classification

- **FR-5.1** Every drive stores `day_minutes` and `night_minutes`, computed by intersecting the drive window with the sunset-to-sunrise window derived from the log book's latitude and longitude for the relevant dates.
- **FR-5.2** A drive spanning sunset splits correctly. A 5:40pm to 7:10pm November drive in Richmond records daytime minutes before sunset and night minutes after it, in the same entry.
- **FR-5.3** Every drive also stores a `primary_daypart` of morning, afternoon, evening, or night for human-facing display and filtering. This is clock-based and never used for compliance math.
- **FR-5.4** Both values are computed once at completion and stored. Later changes to classification rules do not retroactively alter signed history.
- **FR-5.5** Progress is displayed as running totals against both goals independently: total hours against 45 and night hours against 15. Goals are thresholds, not caps. Logging continues past them and the totals keep counting, because hours beyond the minimum are evidence, not excess.
- **FR-5.6** Drives supervised by a licensed instructor count toward both totals exactly like any other attested drive. There is no separate bucket and no cap on instructor hours.

See [ADR-003: Dual time classification](../adr/ADR-003-dual-time-classification.md).

### 6.6 Attestation

- **FR-6.1** Every completed drive enters status `pending_attestation`.
- **FR-6.2** Any member with `can_attest` may sign a pending drive. The driver may not, and the control is not rendered for them.
- **FR-6.3** Signing captures a typed full name, auto-derived editable initials, a signature drawn on a canvas, the attestation statement version, a timestamp, and the request IP. The drawn signature is stored as SVG on the attestation row. If the canvas is left blank, the typed name is the signature and `signature_method` records `typed` rather than `drawn`.
- **FR-6.4** Signing snapshots the signer's name, phone, email, and relationship label onto the attestation row. Later profile edits do not rewrite historical signatures.
- **FR-6.5** The signer is shown the drive details and an explicit statement before signing, for example: *"I confirm I was present and supervising this drive on 14 November 2026 from 5:40pm to 7:10pm, a duration of 1 hour 30 minutes."*
- **FR-6.6** A drive with at least one live attestation is `attested` and becomes read-only.
- **FR-6.7** An owner may unsign an attested drive to correct it. Unsigning voids all existing attestations with a recorded reason, returns the drive to `pending_attestation` so it can be edited and signed again, and notifies every voided signer by SMS.
- **FR-6.8** Attestations are never deleted. Voided rows persist with `voided_at` and `voided_reason`.
- **FR-6.9** Multiple people may attest the same drive. All appear on the report.
- **FR-6.10** Unsign, edit, and re-sign events are written to the activity log with who, when, and why. They never print on the certification report, which renders live attestations only, per `ADR-006`.

```mermaid
stateDiagram-v2
    [*] --> Active: driver taps Start
    [*] --> Draft: manual entry begun
    Active --> PendingAttestation: driver taps End
    Active --> Void: auto-closed and discarded by owner
    Draft --> PendingAttestation: submitted
    Draft --> Void: abandoned
    PendingAttestation --> Attested: supervisor signs
    Attested --> PendingAttestation: owner unsigns, signatures voided
    PendingAttestation --> Void: owner voids
    Attested --> Void: owner voids
    Void --> [*]
    Attested --> [*]: included in report
```

### 6.7 Ownership transfer

- **FR-7.1** An owner nominates any accepted member other than the driver as the incoming owner.
- **FR-7.2** The nominee receives an SMS and must explicitly accept. Transfer is not unilateral.
- **FR-7.3** On acceptance, the swap is atomic: the nominee becomes `owner`, the outgoing owner is demoted to a role chosen at nomination time, defaulting to `parent_guardian`.
- **FR-7.4** Pending transfers expire after 7 days and can be cancelled by the initiator.
- **FR-7.5** Both the nomination and the acceptance are written to the activity log.
- **FR-7.6** A log book always has exactly one owner. The transfer runs inside a database transaction with a row lock.

### 6.8 Certification report

- **FR-8.1** Any member with attest rights can preview the report. Only the owner can generate a final one.
- **FR-8.2** The report covers a selectable date range, defaulting to the full permit period.
- **FR-8.3** Contents, in order:
  1. **Header**: driver name, log book label, jurisdiction, permit issue date, date range, report ID.
  2. **Summary**: total hours against the 45-hour goal, night hours against the 15-hour goal, drive count, date of first and last drive.
  3. **Log table**: one row per attested drive: date, start, end, duration, daypart, day/night minute split, conditions, supervisor initials rendered in a script face, and signature timestamp.
  4. **Supervisor appendix**: every person who signed, with name, relationship, phone, email, count of entries signed, and their drawn signature, or their typed name in the script face when no drawing was captured.
  5. **Certification block**: the statutory language and the owner's signature line.
  6. **Footer**: generation timestamp, report ID, page numbers, and a content hash for tamper evidence.
- **FR-8.4** The report renders as a print-optimized HTML page and as a downloadable PDF.
- **FR-8.5** Generation writes an immutable snapshot. Reprinting an existing report reproduces it byte for byte even if the underlying log has changed since.
- **FR-8.6** Drives in `pending_attestation` are excluded from the certified totals and listed separately as unsigned, so nothing silently inflates the number the owner is swearing to.

See [ADR-006: Report snapshot and PDF](../adr/ADR-006-report-snapshot-and-pdf.md).

## 7. UI surface

Livewire 4 full-page components with Flux UI Pro. Mobile-first at 375px, since the primary device is a phone in a parked car at dusk.

| Screen | Route | Notes |
| --- | --- | --- |
| Landing / phone entry | `/` | Single `flux:input` with `tel` inputmode, one `flux:button` |
| Link sent | `/check-phone` | Resend countdown, edit-number affordance |
| Log book picker | `/books` | `flux:card` per book with progress rings |
| Dashboard | `/books/{book}` | Dual progress bars, prominent Start Drive, pending-signature callout |
| Active drive | `/books/{book}/driving` | Large elapsed timer, supervisor picker, End Drive |
| Manual entry | `/books/{book}/drives/create` | `flux:date-picker`, time fields, autosaving draft |
| Drive list | `/books/{book}/drives` | Status via `flux:badge`, filter by daypart and signer |
| Attestation | `/books/{book}/drives/{drive}/sign` | Statement, typed name, signature canvas (`signature_pad`), `flux:checkbox` consent, sign |
| Members | `/books/{book}/members` | Invite form, role and relationship, revoke |
| Transfer ownership | `/books/{book}/transfer` | `flux:modal` with confirmation copy |
| Report | `/books/{book}/report` | Range selection, preview, print, download |
| Profile | `/profile` | Name, email, timezone |

Design notes:

- Night-drive UI should default to a dark surface. The most common moment for a supervisor to sign is sitting in a driveway at 9pm.
- Attestation is the highest-value action for a first-time visitor. It must be reachable in one tap from the link they were texted, with zero onboarding in between.
- Progress against 15 night hours deserves equal visual weight to the 45-hour total. Under-counting night hours is the most common way families fall behind.

Verify current Flux Pro component availability against the Flux docs before committing to `flux:date-picker` and `flux:table` in the build.

See [ADR-005: Livewire and Flux UI layer](../adr/ADR-005-livewire-flux-ui-layer.md).

## 8. Technical architecture

| Layer | Choice | Rationale |
| --- | --- | --- |
| Framework | Laravel 13, PHP 8.5 | House standard |
| UI | Livewire 4 + Flux UI Pro + Tailwind 4 | Server-driven, one deployable, no API surface to secure |
| Database | Any Laravel-supported relational database: MySQL, MariaDB, PostgreSQL, or SQLite | Portable schema so the app runs wherever Laravel does. See [ADR-008: Database-agnostic schema](../adr/ADR-008-database-agnostic-schema.md) |
| Identity | Custom phone guard, no Fortify or Breeze | No password primitives to secure or leak |
| Authorization | Laravel policies over `log_book_members` | Roles are per-record, not global. See [ADR-002: Per-log-book membership authorization](../adr/ADR-002-per-logbook-membership-authorization.md) |
| SMS | Driver contract with Twilio as primary, plus one inbound webhook for timer keywords and check-in replies | Reuse the pluggable driver pattern from Burn After Reading v2. See [ADR-007: SMS keyword timer control](../adr/ADR-007-sms-keyword-timer-control.md) |
| Queue | Database driver at V1 | Redis and Horizon only if SMS volume justifies it |
| Sun times | PHP `date_sun_info()` | Native, no dependency, no external API call, no rate limit |
| Audit | `spatie/laravel-activitylog` | Member changes, drive edits, unsigns, voids, transfers. Never rendered on the report |
| PDF | `barryvdh/laravel-dompdf` with Inter and Dancing Script embedded as static TTF, both SIL OFL 1.1 | Table-heavy document, no headless Chrome on the box |
| Signatures | `signature_pad` (MIT) in an Alpine component, stored as SVG text on the attestation | Vector, no image storage path, renders in DomPDF through an `<img>` data URI |
| Hosting | Any Laravel-compatible host: one PHP 8.5 web process, a queue worker, the scheduler, a relational database, and a private disk | The workload is a handful of writes per day; nothing here needs a specific platform |

### Constraints worth enforcing in the schema

- A unique index on the nullable `drives.active_lock` column, which equals `log_book_id` only while the drive is active, guarantees one active drive per book on every supported database.
- `log_books.owner_user_id` is non-nullable, and transfer runs under `lockForUpdate()` inside a transaction.
- `attestations` has no `UPDATE` path in application code except setting `voided_at` and `voided_reason`.
- A check constraint asserting `day_minutes + night_minutes = duration_minutes` catches classification bugs at the database boundary on engines that support it, and a model guard asserts the same rule everywhere. See [ADR-008: Database-agnostic schema](../adr/ADR-008-database-agnostic-schema.md).

## 9. Compliance and operational reality

- **Virginia requirement.** 45 hours total with at least 15 after sunset, certified by a parent or guardian. The certification is made under penalty of perjury, which is precisely why per-entry attestation and tamper-evident reporting matter here rather than being over-engineering.
- **Restricted hours.** Under-18 permit holders may not drive between midnight and 4:00 a.m. Flag, do not silently accept.
- **A2P 10DLC.** US application-to-person SMS over long codes requires brand and campaign registration with the carriers. This is a lead-time item, not a code item. Start it before the build finishes or launch will stall on it.
- **Consent language.** The first SMS to any number must identify the sender and include opt-out instructions. Honor STOP. Timer keywords never collide with the reserved opt-out, opt-in, and help words, per FR-4.11.
- **Minor's data.** The driver is a minor. Collect the minimum: name, phone, drive times. No location history beyond the log book's static coordinates, no birthdate unless the report genuinely requires it.
- **Retention.** Offer log book deletion. Default to purging magic link rows after 30 days and archiving log books 12 months after the goal is met.

## 10. Release plan

**M1: Skeleton (week 1)**
Schema, models, prefixed ULIDs, policies, factories, seeders. Phone auth end to end with a log SMS driver. Log book creation and picker.

**M2: The core loop (week 2)**
Start and stop a drive, server-anchored timer, manual entry, draft persistence, sunset-based day/night classification with unit tests across a full solar year.

**M3: Sharing and signing (week 3)**
Invites, deep-linked magic links, role assignment, attestation with snapshotting and the signature canvas, unsign and void, notifications, SMS keyword timer control, and the long-drive owner check-in.

**M4: The deliverable (week 4)**
Report snapshot, print stylesheet, PDF export, signature rendering, supervisor appendix, content hash.

**M5: Hardening**
Rate limits, real SMS driver plus 10DLC registration, auto-close job, activity log surfacing, accessibility pass, dark mode.

## 11. Resolved questions

Answered by Tim Wood on 2026-08-26. Recorded here so the reasoning survives; the requirements above already reflect the answers.

1. **"After sunset" means sunset to sunrise.** A 5:30am winter drive is night driving. `ADR-003`, FR-5.1.
2. **Instructor behind-the-wheel hours count toward the totals,** folded in like any other attested drive. Totals are running tallies with no cap at 45 or 15; the goals are thresholds the tally is shown against. FR-5.5, FR-5.6.
3. **The parent originates the log book.** The creator becomes owner and names the driver by phone number. The owner is never the driver, enforced by a check constraint. FR-2.3, `SPEC-001`.
4. **Drawn signatures ship in V1.** Typed name and initials are captured on every attestation; a `signature_pad` canvas captures the drawn signature as SVG, with the typed name as the fallback when the canvas is blank. FR-6.3, `ADR-004`, `ADR-006`.
5. **Typefaces are Inter for the body and Dancing Script for signatures,** both SIL OFL 1.1, embedded as static TTF with subsetting. Same-license alternatives if the look needs adjusting: Caveat (handwritten, less formal), Allura and Great Vibes (more formal), Sacramento (cleanest, thin strokes, check legibility at table sizes). `ADR-006`.
6. **Supervisors read the whole log book.** Full read for every accepted member, as the role table already states. Revisit only if a driving school ever becomes a member as a vendor.

## 12. Future

- Multi-jurisdiction rule packs, since the 45/15 numbers and the sunset definition are state-specific.
- Weekly nudge SMS when night-hour pace falls behind the calendar.
- Offline-capable timer with a service worker and a queued stop event.
- Export to the log formats specific driving schools request.
