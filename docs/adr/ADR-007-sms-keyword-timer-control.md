---
id: ADR-007
title: "SMS keyword control of the drive timer with an authenticated link fallback"
status: proposed
date: 2026-08-26
product: drive-log
deciders: [Tim Wood]
tags: [adr, sms, timer, twilio, security]
supersedes: null
superseded_by: null
---

# ADR-007: SMS keyword control of the drive timer with an authenticated link fallback

## Context

The timer is the most frequent interaction in the product and it happens in a car. Opening a browser, waiting for a magic link, and tapping Start is three steps too many for a teenager at a stop sign; a text message is one. The requirement is that the driver can start and end a drive by texting a keyword, and that a drive left running gets a parent's confirmation rather than silently accumulating hours.

Two constraints make the obvious design wrong.

**Carrier-reserved keywords.** US application-to-person messaging reserves `STOP`, `STOPALL`, `UNSUBSCRIBE`, `CANCEL`, `END`, and `QUIT` as opt-out commands, `START`, `YES`, and `UNSTOP` as opt-in, and `HELP` and `INFO` for help. Twilio intercepts these before the application sees them and blocks every further outbound message to a number that opts out. A driver who texts `STOP` to end a drive would unsubscribe from every magic link and notification the product sends, and would not know it. The natural vocabulary for a timer is exactly the vocabulary that is reserved.

**Sender identity is unauthenticated.** An inbound SMS proves only that someone can send from that number. Spoofing is rare on US long codes but real. Treating the sender number as a login would be wrong for anything with consequences. Starting and ending a timer has few: the drive still has to be signed by a supervising adult per [ADR-004: Attestation immutability](ADR-004-attestation-immutability.md), and that signature is what carries the integrity weight.

## Decision

Accept timer commands by SMS using non-reserved keywords, trust the sender's phone number for those two actions only, and put an authenticated link in every timer message so that a tap always works when a keyword does not.

**Keywords**

- Start: `BEGIN` or `GO`. End: `DONE` or `FINISH`. Check-in confirmation: `CONTINUE`.
- Matching is case-insensitive on the first word of the message, trailing punctuation ignored.
- Reserved words are never commands. If a driver with an active drive sends an opt-out word anyway, the drive is ended, because the intent is unambiguous, and the owner is notified that the driver's number is opted out until it texts `START`. The application sends nothing to that number until then, since the carrier layer would drop it.
- Every message the application sends about the timer names the correct keywords. A user who has to guess will guess `STOP`.

**Resolution**

- The sender's `phone_e164` resolves to a user, then to that user's accepted, unrevoked memberships on log books in `active` status.
- `BEGIN` and `GO` act only when the sender is the `driver` on exactly one such book. `DONE` and `FINISH` act when the sender is the driver or the owner of exactly one book with an active drive. `CONTINUE` acts only for the owner.
- Any other case, including no membership, ambiguity across books, and a keyword with nothing to act on, replies with an authenticated deep link rather than guessing.
- A drive started by SMS has `entry_method = sms` and no `supervisor_member_id`; the supervisor is established by whoever attests.
- `ended_at` for an SMS end is the time the webhook received the message.

**The link fallback**

- Every timer message, whether the start confirmation, the check-in, or the needs-correction notice, carries a magic link with `purpose = login` and a `context` deep link to the active drive. Tapping it authenticates per [ADR-001: Phone-first magic link auth](ADR-001-phone-first-magic-link-auth.md) and lands on the drive with the end control visible. This is the path when keywords fail, when the sender is ambiguous, and for anyone who prefers tapping.

**Long-drive check-in**

- `drives.next_checkin_at` is set to `started_at + 2 hours` on start.
- A scheduled job, every minute, sends a check-in to the driver and to the owner for each active drive whose `next_checkin_at` has passed, then nulls the column while awaiting a reply. The driver is included because the person who forgot the timer is usually the one holding the phone. The message names the driver and the elapsed time, states the keywords that apply to the recipient, and carries the link. It is worded as a question, never as a warning.
- `CONTINUE` from the owner sets `next_checkin_at = now + 45 minutes`. The cycle repeats for as long as the owner keeps replying.
- `DONE` from the driver or the owner ends the drive at the time of the reply.
- No reply leaves the drive running until the 8-hour move to `needs_correction` from PRD FR-4.5 and `ADR-010`. The check-in is a nudge, not a deadline.

**Webhook**

- Inbound messages arrive at a single webhook. The `X-Twilio-Signature` header is validated against the account auth token on every request; an invalid signature is a 403 and is logged. This is the trust boundary for the whole feature and is not optional in any environment other than the local log driver.
- The webhook is idempotent on the message SID, since the provider retries on non-2xx responses.

## Consequences

**Good**

- Starting a drive costs one text message with nothing to open. This is the difference between a log that gets kept and one that gets reconstructed on Sunday night.
- The parent check-in catches the forgotten timer at two hours instead of eight, through a channel the parent already reads.
- The link in every message means the feature degrades to the existing authenticated flow rather than to a dead end.
- Non-reserved keywords keep the product's own SMS channel healthy. Nobody can opt out by using the product as intended.

**Bad**

- Sender identity is trusted for start and end. A spoofed message could start or end someone's drive. Bounded: a fabricated drive still needs an adult's signature, and an ended drive is visible immediately to the driver, who can start another. Not bounded: it is annoying. Accepted for V1 given the rarity of long-code spoofing and the low value of the action.
- Keyword commands carry no supervisor. Every SMS-started drive shows an empty supervisor until signed. Acceptable, since the signature is the record of who supervised.
- Every inbound message costs money and hits a webhook. Volume is a few messages per day per family; irrelevant at this scale.
- A check-in every 45 minutes on a genuinely long drive is nagging. The owner chose it by replying `CONTINUE`; a road trip can be logged manually instead.

**Neutral**

- Reserved-word handling depends on the provider's default opt-out management staying enabled. Turning it off would make `STOP` reach the application as an ordinary word and would also breach the carrier requirements the number was registered under, so it stays on.
- The webhook handles replies without conversation state. The sender's memberships and the presence of an active drive are the only context, which is why ambiguity resolves to a link instead of a follow-up question.

## Alternatives considered

- **Use `START` and `STOP` and disable the provider's opt-out handling.** Gives the natural vocabulary, breaches CTIA and 10DLC requirements, and makes the product responsible for implementing opt-out correctly itself. Rejected.
- **Link-only, no keywords.** Every timer message is a magic link and there is no command parsing or sender trust. Safer and two taps slower. Rejected as the primary path because the whole point is zero taps; retained as the fallback in every message.
- **Require a per-drive code in the keyword, such as `DONE 4821`.** Defeats spoofing at the cost of the driver reading a code off a previous text. Rejected for V1; revisit if spoofing is ever observed.
- **Missed-call or voice control.** Works on any phone with no messaging cost. Adds a voice product surface for a marginal gain over SMS. Rejected.

## Scope & Tenancy Impact

**Scope:** an inbound SMS webhook and controller, a keyword parser, the drive start and end services already specified for the app, `drives.next_checkin_at`, a scheduled check-in job, and the message templates in `SPEC-008`.

**Tenancy:** row-scoped. Command resolution starts from the sender's `users` row, which is globally scoped, and immediately narrows to that user's accepted memberships. A command never acts across books: ambiguity between two books resolves to a link, not to a guess. The webhook is unauthenticated by design and is the one public write surface in the application besides the magic link request form, so the provider signature check is the boundary and is enforced before any lookup occurs.
