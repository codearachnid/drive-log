---
title: "UX principles: easy, gracious, and a little fun"
type: prd
status: draft
version: 0.1.0
product: drive-log
owner: Tim Wood
created: 2026-08-26
updated: 2026-08-26
tags: [prd, ux, copy, tone, accessibility]
related:
  - "[[PRD-drive-log]]"
  - "[[ADR-005-livewire-flux-ui-layer]]"
  - "[[ADR-009-signature-requests-and-gracious-reminders]]"
  - "[[ADR-010-forgiving-drive-lifecycle]]"
---

# UX principles

The PRD says what the product does. This document says how it should feel, and it is a requirement, not a mood board. `SPEC-009` and every message template in `SPEC-008` are reviewed against it.

## Why this document exists

Every family using Drive Log has already stood in a DMV line. They have been told to fill out the form again, to come back with a different document, to hurry up. The product's rigor lives in the schema and the attestation layer, where it belongs. It never leaks out onto a screen as a warning banner, a red badge, or a deadline. If the app ever feels like a second DMV, the teenager stops logging and the grandmother stops signing, and the integrity machinery underneath has nothing to protect.

The bar is simple: a grandparent who has never seen the app should be able to open a text and sign a drive without a single moment of "what does this want from me". A teenager should open the app on their own because it feels good to watch the ring fill up.

## The principles

### 1. One thing per screen

Every screen has one job and one obvious next action, big enough for a thumb, high enough to reach one-handed on a 375px phone. Secondary actions are visually secondary. If a screen needs a paragraph to explain itself, split it.

### 2. Ask, never nag

The app asks once, reminds once, and then trusts the family. One signature request per drive, one reminder after three days, one weekly owner digest that only sends when something is waiting. No streaks, no countdowns, no red numbers. The words **overdue**, **late**, **urgent**, **warning**, **error**, **invalid**, and **failed** do not appear in the product. See `ADR-009`.

### 3. Mistakes cost one tap

Every common mistake has a fix on the screen where it happens. Started by accident: Discard. Forgot to stop: "I forgot to stop". Wrong supervisor: "That wasn't me". Expired link: "Text me a fresh link". Nobody is sent to a settings page, a help article, or a parent to fix something they can fix themselves. See `ADR-010`.

### 4. Nothing to set up, nothing to remember

No passwords, no profile step, no onboarding tour. A name is asked for on the screen that needs it, once. A ZIP code stands in for coordinates. The supervisor picker remembers last time. The link in a text lands on the exact screen it is about. If a user has to remember something between two sessions, that is a bug.

### 5. Talk like a person

Copy is written the way a helpful relative would say it out loud. Short sentences. Contractions. Names, not roles: "Ask Grandma to sign" beats "Request supervisor attestation". The statement a signer confirms is legal language and stays precise; everything around it is plain.

Examples of the voice:

| Situation | Not this | This |
| --- | --- | --- |
| Signature request | "Attestation required for drive drv_01J…" | "Sam drove for 47 minutes on Tuesday evening with you along. Could you sign it when you get a sec?" |
| Reminder | "REMINDER: 1 attestation overdue" | "No rush, but Tuesday's drive with Sam is still waiting for your signature whenever you have a moment." |
| Timer left running | "Error: drive exceeded maximum duration" | "Looks like Sam's timer from this afternoon kept running. Tap here to set when the drive actually ended." |
| Expired link | "Invalid or expired token" | "That link has expired. Want a fresh one?" with a single button |
| Restricted window | "WARNING: drive violates permit restrictions" | "Part of this drive was between midnight and 4am, which Virginia doesn't count toward the 45 hours. It's still in the log, just not in the total." |
| Empty dashboard | "No drives found" | "Nothing logged yet. The first one's the hardest. Tap Start Drive when you're buckled in." |

### 6. Celebrate progress

Progress is the product's reward and it deserves to feel like one. Two rings, total and night, with equal weight. Milestones get a moment: first drive, first night drive, 10 hours, halfway on either goal, each goal met. The moment is a short animation on the drive summary card and a one-line text to the owner. It is never a modal that has to be dismissed and never a badge system. The teenager should want to see the ring move; the parent should get a text that makes them smile in a meeting.

### 7. Share the good part

Ending a drive produces a summary card with a share button that opens the phone's share sheet with one line: "Sam drove 47 minutes, 20 of them after sunset. 12h 15m of 45 done." Families share it to the group chat; grandparents see it and remember to sign. Sharing is always the user's choice and never automatic.

### 8. Dark by default at night

The app switches to a dark surface after local sunset at the log book's location. The most common signing moment is a driveway at 9pm, and the most common driving moment is dusk. The same sunset math that drives compliance drives the theme, which is a small, satisfying consistency.

### 9. Calm, not silent

Things that matter are shown calmly: a restricted-window badge, a waiting-for-correction card, an unsigned count on the report checklist. They are informative, visually quiet, and always paired with the action that resolves them. The report readiness checklist is the model: everything on it is optional, everything on it explains what happens if you proceed anyway, and the Generate button is never disabled.

### 10. Accessible by default

Touch targets at least 44px. Every control reachable by keyboard and labelled for a screen reader. Colour never the only signal. The signature canvas is optional and the typed name is a full signature, so nobody is blocked by a drawing they cannot make. Text scales with the system setting. Dark and light both meet contrast.

## Interaction rules that follow

- The primary action on any screen is a full-width button at the bottom, above the safe area.
- Destructive actions confirm once, inline, with the consequence in plain words. Never a browser `confirm()`.
- Forms save as the user types where the data model allows it, per `ADR-005`. Nobody loses work to a closed tab.
- Time is always shown in the log book's timezone with the day named: "Tuesday 5:40pm", not "2026-11-14T17:40".
- Durations read as "1h 30m", never "90 minutes" and never "1.5 hours".
- Numbers that matter are big. The elapsed timer, the two rings, and the drive duration on the summary card are the largest text on their screens.
- Empty states say what to do next, in one sentence, with the button to do it.
- Loading states are skeletons, not spinners, and never block a tap that could be optimistic, per `ADR-005`.
- The only modal in the product is the ownership transfer confirmation, because it is the one action that changes who is legally responsible.

## What this rules out

- Gamification beyond milestones: no points, no leaderboards, no streak counters. A streak that breaks is a reason to quit.
- Any notification the user did not cause and cannot stop. Every message the app sends traces to a drive the family logged or a request the owner made.
- Settings screens. If a behaviour needs configuring, the default is wrong. Fix the default.
- Toasts that stack, badges that count, and anything red that is not a Discard button.

## How this is enforced

- `SPEC-009` includes the copy for every screen and state, reviewed against section 5 and the banned-word list in section 2.
- `SPEC-008` includes every SMS template, reviewed the same way, with the sender identification and opt-out language the carriers require folded in without breaking the voice.
- A test asserts that no rendered view or message template contains a banned word. It is a cheap test and it will catch the first regression.
