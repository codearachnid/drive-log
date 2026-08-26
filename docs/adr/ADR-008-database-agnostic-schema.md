---
id: ADR-008
title: "Database-agnostic schema with portable invariant enforcement"
status: proposed
date: 2026-08-26
product: drive-log
deciders: [Tim Wood]
tags: [adr, database, migrations, portability, laravel]
supersedes: null
superseded_by: null
---

# ADR-008: Database-agnostic schema with portable invariant enforcement

## Context

The application has to run wherever Laravel runs. That means the database is whichever relational engine the host offers: MySQL or MariaDB on most managed PHP hosting, PostgreSQL on others, SQLite for local development, tests, and the smallest single-box deploys. Committing to one engine would turn a hosting choice into a migration project.

The schema as first drafted leaned on PostgreSQL: partial unique indexes for "one active drive per book" and "one pending transfer per book", `jsonb`, `inet`, `timestamptz`, and `ALTER TABLE ... ADD CONSTRAINT ... CHECK`. MySQL has no partial unique indexes, and SQLite cannot add a check constraint to an existing table at all. The invariants those constructs enforced are load-bearing, invariants 5, 8, and 9 in `docs/CLAUDE.md`, and cannot simply be dropped.

## Decision

Write the schema in Laravel's schema builder using only types every supported driver has, and enforce every invariant at the model layer on every driver plus at the database layer wherever the driver allows it.

**Column types**

- Timestamps are UTC `datetime` columns via `dateTime()`. Timezone awareness lives in the application and in the stored `timezone` strings, never in the column type.
- JSON columns use `json()`. No `jsonb`, no JSON path indexes.
- IP addresses are `string(45)`. Coordinates and distances are `decimal`.
- Primary keys are `char(30)` prefixed ULIDs, as before.

**Uniqueness that used to be partial indexes**

- `drives.active_lock` is `char(30)` nullable with a unique index. It equals `log_book_id` while `status = active` and is null otherwise, set and cleared in the same write as `status`. Every supported driver treats NULLs as distinct in a unique index, so this gives the one-active-drive guarantee portably.
- `ownership_transfers.pending_lock` works the same way for one pending transfer per book.

**Check constraints**

- `drives_minutes_balance`, `drives_timestamps_by_status`, `drives_end_after_start`, and `log_books_owner_not_driver` are added with `DB::statement` in the migration when `DB::getDriverName()` is `mysql`, `mariadb`, or `pgsql`. On SQLite they are skipped.
- Every check constraint has a `saving` guard on its model that throws a domain exception on violation. The guard is the enforcement on SQLite and the first line of defense elsewhere. A raw write that bypasses the model is stopped by the database on the engines that can.

**Locking**

- Ownership transfer runs under `lockForUpdate()` inside a transaction. That is `SELECT ... FOR UPDATE` on MySQL and PostgreSQL and a no-op on SQLite, which serializes writers anyway.

**Foreign keys**

- `ON DELETE RESTRICT` everywhere signed history is involved, unchanged. SQLite enforces foreign keys only with `foreign_key_constraints` enabled, which is Laravel's default and must stay on.

**Test discipline**

- The constraint tests in `SPEC-001` run through the model, asserting the domain exception, and through a raw insert, asserting a `QueryException`. The raw variant is skipped on SQLite for check constraints only. The suite runs against SQLite and MySQL at minimum.

## Consequences

**Good**

- Hosting is a deployment decision, not an architecture decision. Any Laravel-compatible host with a relational database works, and moving between them is a dump and restore.
- Local development and tests run on SQLite with no service to start.
- The model guards make the invariants visible in the code, next to the domain logic, rather than only in migration SQL.

**Bad**

- Each invariant has two implementations, model and database, and they can drift. Mitigation: the tests exercise both, and the guard and the constraint carry a comment naming the invariant number so they can be found together.
- On SQLite the database does not independently enforce the check constraints. A raw write that bypasses the model can corrupt a local database. Accepted: SQLite is for development, tests, and single-box deploys where no other writer exists.
- The lock columns are denormalized state that must be maintained in the same write as `status`. The model sets them; a raw status update that forgets the lock column breaks the guarantee. The unique index makes that failure loud rather than silent, since a stale lock blocks the next start.
- Lowest-common-denominator types give up PostgreSQL niceties: no `jsonb` indexing, no `inet` validation, no timezone-aware column. None of them were doing anything the application needs.

**Neutral**

- Nothing in the domain queries by JSON content, so `json` versus `jsonb` has no performance consequence at this scale.
- The `char(30)` ULID keys already avoided auto-increment differences between engines.

## Alternatives considered

- **Stay on PostgreSQL only.** Cleanest schema, and it turns "host this elsewhere" into a schema rewrite. Rejected because hosting flexibility is the requirement.
- **Enforce invariants in the application only, with no database constraints anywhere.** Simplest and portable, and one bug in a service class can then put two active drives on a book with nothing to stop it. Rejected: the constraints cost a few lines per driver and catch exactly the bugs that matter.
- **Emulate check constraints with triggers on SQLite.** SQLite triggers can `RAISE(ABORT)`. Adds a third enforcement mechanism and trigger SQL that differs per engine. Rejected as more surface than the model guard for the same guarantee.
- **A schema abstraction layer such as Doctrine DBAL.** Laravel's builder already abstracts everything the schema needs. Rejected as a dependency with no gap to fill.

## Scope & Tenancy Impact

**Scope:** every migration, the `Drive`, `LogBook`, and `OwnershipTransfer` models, the transfer service, and the constraint tests in `SPEC-001`.

**Tenancy:** unchanged. Row scoping by `log_book_id` is enforced by the global scope and policies per [ADR-002: Per-log-book membership authorization](ADR-002-per-logbook-membership-authorization.md), none of which depends on the database engine. The lock columns carry a `log_book_id` value and inherit its scope. The globally scoped `users` table is untouched by this decision.
