---
id: ADR-003
title: "Dual time classification: display daypart versus compliance night minutes"
status: proposed
date: 2026-08-26
product: drive-log
deciders: [Tim Wood]
tags: [adr, domain-model, compliance, time, database]
supersedes: null
superseded_by: null
---

# ADR-003: Dual time classification, display daypart versus compliance night minutes

## Context

The product needs two different answers to what looks like one question.

The first is a human question: was this a morning drive, an afternoon drive, an evening drive, or a night drive? Families think in these terms, they want to filter by them, and a good log shows variety across them.

The second is a legal question: how many of these minutes occurred after sunset? Virginia requires 45 hours of supervised practice with at least 15 after sunset, certified by a parent or guardian.

These are not the same question and conflating them produces wrong numbers in both directions. Sunset in Richmond swings from roughly 8:40pm in late June to roughly 4:50pm in early December. A fixed clock rule of "night starts at 8pm" over-credits June evenings that are still fully daylight and discards nearly three hours of legitimate December night driving every single evening. Over a permit period spanning a winter, that error is large enough to change whether the family meets the requirement.

There is a further wrinkle: a drive can cross the boundary. A 5:40pm to 7:10pm November drive is partly daylight and partly night. Assigning the whole entry to one bucket is wrong either way.

## Decision

Store both classifications on every drive, computed independently, and never let the display value influence the compliance value.

**Compliance classification**

- `day_minutes` and `night_minutes` are computed by intersecting the drive's actual time window with the sunset-to-sunrise window for the log book's latitude and longitude on the relevant dates.
- Sun times come from PHP's native `date_sun_info()`. No package, no HTTP call, no rate limit, no failure mode during an offline deploy.
- The boundary definition is sunset to sunrise, not sunset to midnight. A 5:30am December drive is night driving under any sensible reading. Confirmed as the product's interpretation on 2026-08-26, recorded in PRD section 11.
- `sunset_at` and `sunrise_at` are stored on the drive row as a computation audit trail, so a disputed classification can be re-derived without recomputing solar position.
- A check constraint asserts `day_minutes + night_minutes = duration_minutes` on engines that support it, and a model guard asserts it everywhere, per [ADR-008: Database-agnostic schema](ADR-008-database-agnostic-schema.md).

**Display classification**

- `primary_daypart` is a clock-based enum computed in the log book's timezone from the drive's start: morning 05:00 to 11:59, afternoon 12:00 to 16:59, evening 17:00 to 20:59, night 21:00 to 04:59.
- Used only for badges, filters, and the variety-of-conditions column on the report. Never summed for compliance.

**Both are computed once at completion and persisted.** They are not derived at read time and not recomputed on display.

## Consequences

**Good**

- The compliance number is correct year-round with no seasonal drift, which is the entire reason the application exists rather than a spreadsheet.
- Boundary-crossing drives split accurately, so a family gets credit for the twenty minutes of a drive that happened after sunset instead of losing them or claiming ninety.
- Persisting rather than deriving means a signed entry's numbers can never change underneath a signature. If the classification rule is later corrected, historical signed records keep the values that were attested to, and only future drives use the new rule.
- Storing the sun times makes the math auditable. A driving school questioning a night total can be shown exactly what sunset was used.

**Bad**

- Two columns that both describe "when" is a schema that invites misuse. Someone will eventually sum `primary_daypart = night` and get a different number than `night_minutes`. Mitigations: name the display column unambiguously, comment the columns in the migration, and never expose the daypart aggregate in the reporting layer.
- Latitude and longitude become required log book fields, which is one more thing to collect at setup. Defaulted from a ZIP code lookup at creation.
- The math needs real tests. Not one test, but a matrix across the solstices, the equinoxes, both DST transitions, drives entirely before sunset, entirely after, crossing sunset, crossing sunrise, and crossing midnight.

**Neutral**

- Location is static per log book rather than per drive. A drive taken on vacation in Colorado is classified using Richmond's sun times. The error is small relative to the requirement and the complexity of per-drive geolocation is not warranted.

## Alternatives considered

- **Clock-based night only.** Simplest, and wrong in exactly the way that matters most. Rejected.
- **Civil twilight instead of sunset.** Arguably a better proxy for when headlights matter, but the statute says sunset and the report needs to match the statute.
- **Compute at read time from stored timestamps.** Keeps the schema thin but allows a rule change to retroactively alter numbers a person has already sworn to. That is disqualifying for an attested record.
- **External sunrise/sunset API.** Adds a network dependency and a failure mode to a calculation PHP performs natively.
- **Per-drive geolocation capture.** More accurate, requires location permission from a teenager's phone, and collects location history about a minor for negligible compliance benefit. Rejected on data minimization grounds.

## Scope & Tenancy Impact

**Scope:** the `drives` table, the drive completion path, the progress dashboard, and the certification report totals.

**Tenancy:** row-scoped. Latitude, longitude, and timezone live on `log_books`, so classification is inherently scoped to a single book and no cross-book computation exists. Two log books in different states compute against different sun times with no shared state.

This also means the jurisdiction rule set, currently the hard-coded Virginia 45/15 goals on `log_books.goal_total_minutes` and `goal_night_minutes`, is already per-book data rather than global configuration. That is the seam a future multi-jurisdiction rule pack would extend, and it requires no tenancy change to do so.
