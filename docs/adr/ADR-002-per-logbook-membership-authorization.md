---
id: ADR-002
title: "Per-log-book membership authorization instead of global RBAC"
status: proposed
date: 2026-08-26
product: drive-log
deciders: [Tim Wood]
tags: [adr, authorization, policies, laravel]
supersedes: null
superseded_by: null
---

# ADR-002: Per-log-book membership authorization instead of global RBAC

## Context

Roles in this application are not properties of a person. They are properties of a relationship between a person and a specific log book. The same phone number can be the certifying owner of one child's log book, a supervising grandparent on a niece's, and the driver on none of them. A global role assignment cannot express that without inventing a scope layer on top of itself.

The house standard is `spatie/laravel-permission` with WorkOS, which is the right call for tenanted products with organizational role hierarchies. This is not that. There are six roles, they never nest, and they only ever mean something in the context of a single row.

## Decision

Model authorization as membership rows plus Laravel policies. No permission package.

- `log_book_members` is the authorization table. It carries `log_book_id`, `user_id`, `share_type`, `relationship_label`, `can_attest`, and the `invited_at` / `accepted_at` / `revoked_at` lifecycle timestamps.
- `share_type` is a native PHP enum: `owner`, `driver`, `parent_guardian`, `instructor`, `supervisor`, `viewer`. The enum owns the capability logic as methods, so there is exactly one place that answers "can this role do that".
- A `LogBookPolicy` and a `DrivePolicy` resolve the acting user's membership for the record under test and delegate to the enum.
- A global query scope constrains every log-book-owned model to books the acting user has an accepted, unrevoked membership on. Authorization failures should manifest as a 404 on someone else's log book, not a 403, since a 403 confirms the record exists.
- `can_attest` is stored as a column rather than derived purely from role, so an owner can grant or withhold signing rights case by case without inventing a new role.
- The `driver` role is denied attestation in the enum itself, unconditionally, with no column able to override it.

## Consequences

**Good**

- The authorization model is legible in a single enum file and two policies. A new engineer can hold all of it in their head.
- Multi-role-per-person across books falls out for free, with no scope gymnastics.
- Revocation is a timestamp, which preserves the historical fact that the person had access when they signed. This matters because attestations must survive the revocation of the access that produced them.
- No package upgrade treadmill, no cached permission tables, no seeded role fixtures to keep in sync.

**Bad**

- Capability checks are hand-rolled, so test coverage of the policy matrix is load-bearing rather than optional. Every role and every ability needs an explicit test.
- If the product later grows a driving-school tenant with staff, cross-book roles, and delegated admin, this will need to be replaced rather than extended. That is an accepted trade for V1 simplicity.
- Filament, if introduced later for admin, expects a permission provider. It will need a shim.

**Neutral**

- The one truly global capability, "is this person an application administrator", does not exist yet and is deliberately deferred.

## Alternatives considered

- **`spatie/laravel-permission` with team scoping.** Its teams feature would technically work by treating each log book as a team, but it stores roles in a way that makes "list every book this user touches, with their role in each" an awkward query, and the picker screen depends on exactly that query.
- **Gates only, no policies.** Fewer files, but loses the model-binding ergonomics and makes the 404-versus-403 discipline harder to apply consistently.
- **Attribute-based policy engine.** Looking Glass could express these rules, and there is an argument for dogfooding it here. Rejected for V1: six static roles do not justify a rules engine, and coupling a personal project to an unreleased dependency adds risk without adding capability. Revisit if jurisdiction rule packs arrive, since those genuinely are data-driven rules.

## Scope & Tenancy Impact

**Scope:** every read and write path that touches a log book, drive, attestation, member, or report.

**Tenancy:** row-scoped multi-user, single-database, single-schema. There is no tenant identifier and no `stancl/tenancy` integration. The log book is the isolation boundary and it is enforced in three places:

1. A global scope on all log-book-owned models filtering by the acting user's accepted memberships.
2. Policy checks on every action, including reads.
3. Foreign keys with `ON DELETE RESTRICT` on attestations and reports, so no cascade can quietly destroy signed history.

The `users` table is intentionally outside the isolation boundary, since identity spans books. Any query joining `users` must therefore project only the columns the acting context is entitled to see. Concretely: a `viewer` on one book must not be able to read the email address a user supplied in the context of a different book. Contact details are snapshotted onto attestations partly for this reason, per [ADR-004: Attestation immutability](ADR-004-attestation-immutability.md).
