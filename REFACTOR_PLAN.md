# SEB Refactor Plan

This is an audit-derived proposal only. No refactor is authorized by this document. Existing behavior, URLs, database objects, and API contracts must remain unchanged unless a separate task explicitly approves a behavior change.

## Baseline and Safety

- Repository branch: `master`.
- Repository has no commits and the working tree contains untracked project files. This baseline must be preserved before any refactor branch/work begins.
- The application is connected to a local SQL Server/XAMPP environment; database changes are out of scope for this audit.
- Stored procedure definitions are not in the repository.
- Any future architectural change should use a separate branch and a focused logical change.

## P0 - Critical Risks

### P0.1 Borrow status and stock are not one atomic operation

- Change: first map the current status/stock workflow and design a transaction boundary around status plus inventory changes.
- Owner: `Page/admin_borrows.php` with `components/seb_db.php` status helpers.
- Direct dependencies: admin borrow forms, dashboard statistics, recent borrow readers.
- Database impact: `PhieuMuon`, `Kho`, status lookup table, possible triggers/procedures.
- Risk: changing transaction order can alter stock behavior; do not implement without a reproducible test matrix.
- Verification: approve, reject, double-approve, return, double-return, insufficient stock, and simulated failure.

### P0.2 Concurrent approval can produce incorrect stock

- Change: evaluate atomic conditional stock update/locking using the existing SQL Server contract.
- Owner: admin borrow workflow.
- Database impact: direct SQL and transaction isolation; no schema change assumed.
- Risk: concurrency behavior is production-sensitive.
- Verification: concurrent approval test and invariant `stock >= 0`.

### P0.3 Authentication behavior is implemented in three places

- Change: audit and define a compatibility-preserving authentication owner before extraction.
- Owner candidates: existing shared session/auth helpers, but no owner should be chosen without comparing legacy/API/admin behavior.
- Risk: password fallback, redirects, pending/rejected handling, session regeneration, and return URLs differ.
- Verification: public login, admin login, logout, expired session, invalid password, pending/rejected account, and direct legacy routes.

## P1 - High Coupling / Duplicate Business Logic

### P1.1 Centralize borrow status interpretation

- Current: aliases and status checks are in `components/seb_db.php` and `Page/admin_borrows.php`.
- Target: one shared compatibility helper while preserving current labels and numeric/text schema support.
- Database impact: read/write compatibility only; no schema change.
- Verification: all status IDs/labels and every borrow transition.

### P1.2 Centralize device availability/status mapping

- Current: shared helper, homepage, admin pages, and legacy JavaScript interpret status separately.
- Target: one server-owned availability contract consumed by both clients.
- Risk: legacy labels and FK-valid values differ.
- Verification: ready, maintenance, liquidated, zero quantity, borrowed, and unknown status cases.

### P1.3 Remove fallback success responses only after product decision

- Current: `src/services/api.js` returns hard-coded successful fallback data for several failed requests.
- Target: distinguish offline/demo fallback from real backend failure.
- Risk: visible behavior changes and possible loss of graceful degradation.
- Verification: SQL outage/API 500 and normal empty database responses.

### P1.4 Define database object contracts

- Current: stored procedures are external and result shapes are inferred dynamically.
- Target: documented, versioned contracts or a verified external database inventory.
- Blocker: procedure definitions are not present in repository.
- Stop condition: do not modify procedure contracts without database owner confirmation.

## P2 - Medium Maintainability

### P2.1 Reduce duplicated authentication helpers

- Candidate files: `Page/admin_login.php`, `Page/admin_auth.php`, `Page/tai-khoan.php`, API login branch.
- Preserve: all current URLs, redirect behavior, session fields, password compatibility during migration.
- Verify: full auth matrix before and after.

### P2.2 Document and isolate router working-directory behavior

- Candidate: `index.php` and legacy relative includes.
- Do not replace `chdir()` until all direct and routed PHP entry points are tested.

### P2.3 Establish one client API contract

- Current: React `src/services/api.js` and legacy `Javascript/seb_api.js` duplicate wrappers.
- Target: shared response/error contract, not a frontend rewrite.
- Risk: legacy pages and React pages have different fallback assumptions.

### P2.4 Separate generated build verification from source changes

- Current: production serves `dist/` while source is under `src/`.
- Target: documented build verification and deployment check without changing runtime behavior.

## P3 - Low Priority

- Remove or archive unreachable legacy homepage code only after proving no deployment path depends on it.
- Consolidate duplicate static routing rules only after testing Apache and PHP built-in server separately.
- Improve comments/encoding consistency without broad formatting changes.

## Proposed Execution Order

1. Capture database schema/procedure inventory and current behavior tests.
2. Add non-destructive verification tests for authentication, devices, borrowing, and admin transitions.
3. Address borrow status/stock atomicity after database owner review.
4. Centralize status/availability interpretation with compatibility tests.
5. Consolidate authentication implementations incrementally.
6. Reduce frontend/API duplication only after server contract is stable.

## Explicitly Out Of Scope Until Approved

- Database schema changes.
- Stored procedure or trigger changes.
- URL/API contract changes.
- Framework or language migration.
- Deleting legacy files.
- Removing plaintext password fallback.
- Changing registration approval policy.
