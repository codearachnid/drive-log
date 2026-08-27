---
id: ADR-001
title: "Phone-first magic link authentication"
status: proposed
date: 2026-08-26
product: drive-log
deciders: [Tim Wood]
tags: [adr, auth, sms, laravel, security]
supersedes: null
superseded_by: null
---

# ADR-001: Phone-first magic link authentication

## Context

The people who need to use this application most are not the people who will tolerate creating an account. A grandmother who rode along on a Tuesday evening drive needs to confirm that drive happened. If confirming it requires downloading an app, choosing a password, or verifying an email, she will not do it, and the log entry will be signed later by a parent who was not in the car. That single failure mode destroys the integrity of the entire record.

The phone number is also the natural identity here. It is how the family already coordinates, it is unique, it is verifiable by possession, and it is the only contact detail anyone reliably knows for a driving instructor.

## Decision

Authenticate by phone number using single-use magic links delivered over SMS. Store no passwords.

Implementation:

- Phone numbers are normalized to E.164 on input and stored that way. `phone_e164` is the unique key on `users`.
- A login request mints a cryptographically random 32-byte token. Only its SHA-256 hash is persisted in `magic_links`. The plaintext exists in the SMS and nowhere else.
- Tokens are single-use. Lifetime follows purpose: `login` tokens expire in 10 minutes and are invalidated when a newer login token is minted for the same number; `invite`, `sign`, and `correct` tokens live 7 days and are independent of each other and of logins, because an invitation, a signature request, or a needs-correction notice is opened when the recipient gets to it, not when it arrives; `transfer` tokens live as long as the transfer they belong to.
- Tokens carry a `purpose` and a `context` payload, so the same primitive serves login, share invitations, signature requests, and ownership transfer acceptance. Invite links land on the signing screen if a drive is waiting for the invitee, otherwise on the log book. Sign links land on the signing screen for the drive in question. Correct links land on the drive in question with the end time field focused. Nothing lands on a generic dashboard.
- An expired or consumed link is never an error page. It is one sentence and one button that re-issues a token for the same number, purpose, and context. A token consumed seconds earlier by a carrier prefetch is re-issued without the button.
- Consuming a token creates the user if the number is unknown, sets `phone_verified_at`, and establishes a standard Laravel session.
- Sessions last 30 days with sliding expiry.
- Rate limiting is applied on both the phone number and the request IP. The response body and timing are identical for known and unknown numbers.
- Delivery goes through a driver contract (`SmsSender`) with Twilio as the production implementation and a log driver locally. This reuses the pluggable driver pattern already built for Burn After Reading v2.

## Consequences

**Good**

- Zero-friction onboarding for the people whose participation the product depends on.
- No password storage, no reset flow, no credential stuffing surface, no breach liability for hashes.
- Phone verification is a byproduct of login rather than a separate step, and the verified number is exactly what the certification report needs to print for follow-up contact.
- One token primitive covers three flows, which keeps the auth surface small enough to reason about.

**Bad**

- SMS costs money per message and can fail or be delayed. Every login is a paid, asynchronous, best-effort operation.
- US A2P 10DLC brand and campaign registration is required before production long-code traffic will deliver reliably. This is a multi-day external dependency with no engineering workaround.
- SIM swap and shared-device access are real attack vectors. The blast radius is a family driving log, which is proportionate, but it is not nothing.
- Number reassignment means a recycled phone number could reach a stale account. Mitigated by revocable memberships and the 30-day session ceiling.
- Links in SMS are sometimes prefetched by carrier or messaging-app scanners, which can consume a single-use token before the human taps it. Mitigation: on a consumed-but-recently-consumed token, issue a fresh link automatically rather than showing an error.
- Seven-day invite and sign tokens widen the window in which a forwarded or leaked text grants access. Bounded: the link authenticates only the phone number it was sent to, the membership can be revoked, and a signature still records the signer's number and IP. Accepted, because a 10-minute invite is a dead link for most of the people it is sent to.

**Neutral**

- International numbers are out of scope at V1 but the E.164 storage decision leaves that door open.

## Alternatives considered

- **Email magic links.** Cheaper and free of carrier registration, but half the intended supervisors will not check email in a driveway, and phone number is the identifier the family actually shares.
- **One-time numeric codes instead of links.** Immune to link prefetching and works when the SMS is read on a different device. Costs one extra step and a keypad entry. Worth reconsidering if prefetch consumption proves common in practice.
- **WorkOS or a hosted identity provider.** The house standard for tenanted products, but it is heavyweight for an application whose entire identity model is one verified phone number per person.
- **Password auth with optional social login.** Rejected outright. It optimizes for the wrong user.

## Scope & Tenancy Impact

**Scope:** application-wide. Every authenticated path depends on this.

**Tenancy:** Drive Log is single-tenant with row-scoped data. There is no `stancl/tenancy` layer, no schema-per-tenant separation, and no organization entity. Isolation is enforced at the query level by `log_book_id` and by policies over `log_book_members`, per [ADR-002: Per-log-book membership authorization](ADR-002-per-logbook-membership-authorization.md).

A `users` row is global rather than scoped to a log book. This is deliberate: one person is one account across every log book they participate in, which is what makes the log book picker and multi-child support work. Consequence: user identity is the one cross-book join in the system, so any future multi-tenant extraction must treat `users` as a shared directory rather than a tenant-owned table.
