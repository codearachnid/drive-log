---
id: ADR-009
title: "Signature requests at drive end, one gracious reminder, and batch signing"
status: proposed
date: 2026-08-26
product: drive-log
deciders: [Tim Wood]
tags: [adr, attestation, sms, notifications, ux]
supersedes: null
superseded_by: null
---

# ADR-009: Signature requests at drive end, one gracious reminder, and batch signing

## Context

The product's integrity rests on the person in the passenger seat signing the entry. The original design got that person into the app with no account and no password, which was necessary, and then stopped. Nothing told them a drive was waiting. The only message anyone received was the invitation, so a grandmother who signed drive #1 from the invite link never heard about drives #2 through #6. They got signed on Sunday night by a parent who was not in the car, which is the exact failure the product exists to prevent.

The obvious fix, "text them immediately and expect a signature within a minute", is the wrong fix. The people signing are grandparents, aunts, and instructors between lessons. They will open the text when they open it. A product that measures itself in seconds will start nagging, and a product that nags gets muted, and a muted product gets its entries signed by the wrong person. The families using this have already spent enough time being hurried by a DMV.

There is also a volume problem in the other direction. A driving instructor who does three lessons a week does not want three separate signing ceremonies with three separate drawings. If signing is tedious, it gets deferred, and deferred is the same as forgotten.

## Decision

Ask once, kindly, at the moment the drive ends. Remind once, three days later, as a favour. Then stop. Let a signer clear everything waiting for them in one go.

**The request**

- When a drive enters `pending_attestation`, one SMS goes to the member recorded in `supervisor_member_id`. If that is null, it goes to the owner. It carries a magic link with `purpose = sign` and `context = {"drive_id": ...}` that authenticates and lands on the signing screen for that drive, per `ADR-001`.
- `drives.sign_requested_at` records when it was sent. The send is idempotent on that column.
- The message names the driver, the day, and the duration, and asks. It does not mention deadlines because there are none.
- The signing screen offers "That wasn't me". Tapping it clears `supervisor_member_id`, writes the activity log, and re-sends the request to the owner. No one is asked twice to sign a drive they were not on.

**The reminder**

- A scheduled job sends one reminder to the same recipient when `sign_requested_at` is more than 3 days old, the drive is still `pending_attestation`, and `sign_reminded_at` is null. It sets `sign_reminded_at` and never sends again for that drive.
- The reminder is worded as a favour, carries a fresh `sign` link, and mentions that other drives may be waiting too, so the recipient lands on the batch screen when there is more than one.
- The owner receives a weekly digest, on a fixed weekday, listing drives waiting for a signature grouped by signer, only when the list is non-empty. It offers a "send a reminder" action per signer that reuses the reminder path, respecting `sign_reminded_at`, so a manual nudge cannot become a second automatic one.
- The words "overdue", "late", and "urgent" do not appear in any message or screen. This is a rule in `docs/prd/UX-principles.md` and it is enforced in review, not left to taste.

**Batch signing**

- `/books/{book}/sign` lists every `pending_attestation` drive where the signer is the recorded supervisor, pre-checked, with other unsigned drives available beneath, unchecked and collapsed.
- The statement adapts to the set: one sentence per selected drive, each with its date, times, and duration, under a single "I confirm I was present and supervising each of these drives" heading. `statement_version` identifies the batch wording.
- One typed name, one optional drawing. On confirm, one `attestations` row is written per selected drive inside one transaction, each carrying the same `signature_svg`, `signature_name`, `signature_initials`, snapshot columns, IP, and `statement_version`. Every row is a complete, independent attestation of exactly one drive.
- Invariant 1 holds because the batch screen is not rendered for the `driver` role and the write path asserts `user_id !== drive.driver_user_id` per row. Invariant 2 holds because each row is append-only from the moment it exists.

**Landing rules**

- Invite links land on the signing screen if exactly one drive is waiting for the invitee, on the batch screen if more than one is, and on the log book otherwise.
- Sign links land on the signing screen for their drive, or on the batch screen if that drive has since been signed and others are waiting.

## Consequences

**Good**

- The person who was in the car is asked while the drive is fresh, from a channel they already read, with a link that needs nothing typed but their name. That is the whole integrity story made real.
- A signer is never contacted more than twice about one drive. The product stays welcome in the family's message threads, which is a precondition for everything else working.
- An instructor clears a week of lessons in one screen. Signing stops being the thing that gets deferred.
- "That wasn't me" turns a mis-attributed drive into a one-tap correction instead of a phone call.

**Bad**

- Two messages per drive plus a weekly digest is real SMS spend. At a few drives a week per family it is a few dollars a year. Accepted.
- A signer who ignores both messages is not chased further. The owner's digest and the report readiness checklist are where the gap becomes visible, and the owner can nudge by hand. The alternative, escalating reminders, is what makes people mute the number.
- Batch attestations share one drawing across several rows. Each row is still a complete signature of one drive, and the report renders each independently, so the evidentiary claim per drive is unchanged. The activity log records the batch so the shared origin is visible if anyone asks.
- The request goes to the owner when no supervisor was chosen, which means the owner sometimes gets asked to sign a drive they were not on. Mitigation: the "Who was with you?" prompt at drive end, and the owner's ability to reassign from the signing screen.

**Neutral**

- Reminder timing is a constant, 3 days, not a per-book setting. A family that wants faster nudges can tap "send a reminder" from the digest. Configuration here would be complexity looking for a user.
- The digest weekday is fixed application-wide. Sunday evening, because that is when families plan the week.

## Alternatives considered

- **No automatic request; the signer opens the app when they think of it.** This is the original design. It fails exactly as described in the context, and the failure is silent.
- **Immediate request with escalating reminders at 1, 3, and 7 days.** The standard SaaS pattern. Rejected because the audience is family and the channel is personal SMS. Escalation reads as nagging, and a nagging number gets muted, which is worse than no reminder at all.
- **Push the signature onto the driver's phone in the car, hand the phone over.** One tap, no SMS, and the signature lands on the driver's session, which the driver could then produce alone. Rejected on invariant 1. A variant with a one-time code texted to the supervisor's phone was considered and deferred; it is a reasonable V1.1 addition for the driveway case.
- **One attestation row covering many drives.** Fewer rows, and it breaks the one-drive-one-signature model that the report, the void path, and `ON DELETE RESTRICT` all depend on. Rejected.
- **A configurable reminder cadence per log book.** Rejected as a setting nobody would find, in a product whose whole point is that there is nothing to configure.

## Scope & Tenancy Impact

**Scope:** the drive completion path, `drives.sign_requested_at` and `drives.sign_reminded_at`, the `sign` magic link purpose, two scheduled jobs (the 3-day reminder and the weekly digest), the signing and batch signing screens, and the message templates in `SPEC-008`.

**Tenancy:** row-scoped. A request is addressed from a `log_book_members` row, so it cannot reach anyone who is not an accepted member of that book. The batch screen queries drives by `log_book_id` and the acting member's id and is rendered inside the same policy layer as the single signing screen, so a signer sees only the book they arrived at. The digest is assembled per owner across the books they own, which is a cross-book read of the owner's own memberships only, and it renders driver names and counts, never member contact details. The `sign` link's `context` carries a `drive_id`; consumption re-checks the recipient's membership on that drive's book before landing, so a forwarded link cannot land a non-member on a signing screen.
