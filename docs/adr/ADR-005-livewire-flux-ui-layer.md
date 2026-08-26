---
id: ADR-005
title: "Livewire 4 and Flux UI Pro with a server-authoritative drive timer"
status: proposed
date: 2026-08-26
product: drive-log
deciders: [Tim Wood]
tags: [adr, ui, livewire, flux, alpine, offline, laravel]
supersedes: null
superseded_by: null
---

# ADR-005: Livewire 4 and Flux UI Pro with a server-authoritative drive timer

## Context

The usage environment is specific and unforgiving. The primary device is a phone held by a teenager in a parked car, often at dusk, sometimes on a rural road with one bar of signal. The secondary device is a grandmother's phone, opening a texted link for the first time with no context and no patience.

That environment sets the constraints. The first meaningful paint has to be fast on a cold visit. The interface has to work at 375 pixels with one thumb. And the running drive timer has to survive a locked screen, a backgrounded browser, and a tunnel, because a lost timer means a lost log entry and a lost log entry means a family reconstructing times from memory, which is the failure this product exists to prevent.

The application itself is small: a dozen screens, a handful of writes per day, no real-time collaboration, no complex client state.

## Decision

Build the UI as Livewire 4 full-page components styled with Flux UI Pro on Tailwind 4. No separate API, no SPA, no client-side router.

**Component structure**

Full-page Livewire components per route, with nested components only where state is genuinely shared. Form objects for the manual entry and invite flows.

**Draft persistence**

The manual entry form binds with `wire:model.live.debounce.750ms` and writes a `draft` status drive row server-side. Closing the browser mid-entry loses nothing, and the draft is picked up on return. This satisfies the persistence requirement without touching local storage, which keeps the behavior consistent across the device switching that actually happens in this product.

**The timer, which is the load-bearing decision**

The timer is server-authoritative in truth and client-computed in display:

1. Tapping Start writes a `drives` row with a server `started_at` and returns that timestamp to the client.
2. Alpine computes elapsed time locally against that fixed origin. No polling drives the display, so a dropped connection cannot freeze or reset the clock.
3. A low-frequency heartbeat, roughly every 60 seconds, confirms liveness and lets other members see the active drive. Heartbeat failure is silent to the driver.
4. Tapping End captures the client timestamp of the tap, then submits. If the request fails, it retries with backoff and submits the original tap time, not the eventual delivery time.
5. A scheduled job auto-closes any drive active beyond 8 hours, flags it, and surfaces it to the owner for correction rather than discarding it.

Because the drive row exists from the moment Start is tapped, an unrecoverable client failure still leaves a recoverable record with a known start time.

**Flux usage**

Standard Flux primitives: `flux:input`, `flux:field`, `flux:select`, `flux:button`, `flux:modal`, `flux:badge`, `flux:callout`, `flux:card`, `flux:checkbox`, `flux:textarea`. Pro components `flux:table` and `flux:date-picker` are proposed for the drive list and manual entry, pending verification against current Flux docs before the build commits to them. Dark mode is a first-class target rather than an afterthought, since evening use is the norm.

## Consequences

**Good**

- One deployable, one auth surface, one set of validation rules. No API to version, document, or separately secure, which for an application handling a minor's records is a meaningful reduction in attack surface.
- Server-rendered first paint is fast on a cold link open, which is the exact scenario for the highest-value user in the system.
- The timer decision decouples display correctness from network availability. Signal loss during a drive is expected, not exceptional, and the design treats it that way.
- Flux gives a coherent, accessible component set without a design system build, and it is already the house standard so there is no ramp cost.
- Livewire's server-side state means the attestation flow, which must be correct rather than fast, has no client-side logic that could diverge from the policy layer.

**Bad**

- Every interaction beyond the timer is a round trip. On poor signal, tapping End Drive can feel slow. Mitigated by optimistic UI on that specific action, since the tap time is what gets recorded regardless.
- Flux Pro is a paid license, and its availability is a build dependency.
- True offline capability is not delivered. The timer survives connectivity loss, but starting a drive requires a connection. Accepted for V1; a service worker with a queued start event is the V1.1 path.
- Client-supplied end timestamps are trusted. In a system where a teenager benefits from longer recorded drives, this is a real gap. It is bounded by the fact that a supervising adult must attest the entry, which is precisely why the attestation layer per [ADR-004: Attestation immutability](ADR-004-attestation-immutability.md) carries the integrity weight rather than the timer.

**Neutral**

- Alpine is used sparingly, for the timer and small interaction polish. There is no meaningful client-side application state otherwise.
- Livewire 4 adds view-based single-file and multi-file components alongside class components. `SPEC-009` fixes which style each screen uses; nothing in this decision depends on it.

## Alternatives considered

- **Inertia with Vue or React.** Better offline story and a nicer feel for the timer, at the cost of a build pipeline, a second state model, and duplicated validation. Not justified by a twelve-screen application.
- **Native or PWA-first.** Solves the offline case properly and destroys the zero-friction attestation flow, which depends on a texted link opening instantly in whatever browser the recipient already has. The grandmother will not install anything.
- **Polling-driven timer with `wire:poll`.** Simpler to write and fails exactly where the product cannot afford to fail. Rejected on the strength of the environment constraints alone.
- **Filament for the whole application.** Excellent for the owner's admin-shaped views and wrong for the two screens that matter most, which are a phone-sized start/stop control and a one-tap signature. Deliberately not used here despite being house standard.

## Scope & Tenancy Impact

**Scope:** the entire presentation layer, plus the drive lifecycle write paths that the timer depends on.

**Tenancy:** row-scoped. Every Livewire component resolves its log book through route model binding and the policy layer, never from client-supplied state. Component public properties never carry a `log_book_id` that the server has not independently authorized, because Livewire properties round-trip through the client and must be treated as user input on every request.

Two rules follow and should be enforced in review:

- Authorization is re-checked in every Livewire action method, not only on mount. A mounted component is a long-lived client-controlled object and a membership can be revoked mid-session.
- Computed properties that expose member contact details are scoped to the current log book, so the globally scoped `users` table is never rendered beyond the boundary described in [ADR-002: Per-log-book membership authorization](ADR-002-per-logbook-membership-authorization.md).
