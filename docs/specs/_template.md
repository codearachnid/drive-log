---
id: SPEC-NNN
title: "What this slice delivers"
status: draft
milestone: MN
product: drive-log
owner: Tim Wood
created: YYYY-MM-DD
updated: YYYY-MM-DD
implements: [FR-N.N]
decided_by: ["[[ADR-00N-...]]"]
tags: [spec]
---

# SPEC-NNN: Title

## Goal

One paragraph. What works after this ships that did not work before, stated from the user's side rather than the code's.

## In scope

Explicit list. If it is not here, it is not in this spec.

## Out of scope

The things a reader will assume are included. Name them and say which spec owns them.

## Decisions this inherits

| Decision | Source | What it constrains here |
| --- | --- | --- |
| | `ADR-00N` | |

## Data model

Migrations, columns with types and nullability, indexes, constraints, foreign keys with their delete behavior. Mermaid `erDiagram` for anything with more than two related tables.

Call out every constraint enforced at the database level rather than in application code, and why.

## Interfaces

Routes, Livewire components and their public properties, service classes and their method signatures, enums and their cases, events and jobs. Enough that two implementations would agree on names.

## Behavior

Numbered scenarios covering the happy path first, then each branch. Mermaid `stateDiagram-v2` or `sequenceDiagram` where flow is easier seen than read.

## Edge cases

The list that separates a spec from a description. Concurrency, timezone and DST boundaries, network failure mid-write, revoked access mid-session, duplicate submission, and whatever else this slice can get wrong.

## Test plan

What must be covered and at which level. Name the specific cases for anything with boundary math. "Tests the classifier" is not a test plan; a matrix of dates and drive windows is.

## Acceptance criteria

- [ ] Checkable statements. A session can mark this spec `shipped` when every box is ticked and not before.

## Open questions

Anything that needs a human answer. If this list is non-empty, the spec is not `ready`.
