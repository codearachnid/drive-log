---
id: ADR-NNN
title: "Short imperative statement of the decision"
status: proposed
date: YYYY-MM-DD
product: drive-log
deciders: [Tim Wood]
tags: [adr]
supersedes: null
superseded_by: null
---

# ADR-NNN: Title

## Context

What forces are at play. Lead with the problem, not the solution. Include the constraint that makes the obvious answer wrong, because that constraint is the reason this document exists.

Name the specific failure modes being avoided. Vague context produces vague decisions.

## Decision

State the decision in the active voice. Then the implementation detail a reader needs to build against it: table names, column names, enum values, package choices, enforcement points.

Be specific enough that two people reading this independently would build the same thing.

## Consequences

**Good**

- What this buys, concretely.

**Bad**

- What this costs. Required. A decision with no downside was not a decision.
- Include mitigations inline where they exist.

**Neutral**

- Things a future reader should know that are neither wins nor losses.

## Alternatives considered

- **Alternative.** What it would have done well, and the specific reason it was rejected. Not "too complex", but what complexity and why it was not worth it.

## Scope & Tenancy Impact

**Scope:** which tables, paths, and layers this touches.

**Tenancy:** how data isolation is affected. drive-log is single-tenant and row-scoped by `log_book_id` with no `stancl/tenancy` layer, so most answers are some form of "row-scoped", but state where the boundary is enforced and call out anything that crosses it. The `users` table is the one globally scoped entity; any decision touching it needs an explicit note about what that exposes.
