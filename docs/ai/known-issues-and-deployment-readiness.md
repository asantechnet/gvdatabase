# Known issues and deployment readiness

> Historical review of the revisions named below, not a current deployment guarantee. For maintained PWA behavior and manual deployment requirements, use [PWA facts](../facts/pwa.md). The old statement that a version mismatch alone raises an update toast has been superseded by worker readiness verification.

- **Repository:** `razavidev/educational`
- **Branch reviewed:** `main` code state at `3628908` (in sync with `origin/main` at review time; this document is committed on top of that state)
- **Last updated:** 2026-10-18
- **Status:** deployable for a manual test deployment; no known runtime blocker
- **Scope:** open issues, verification gaps and deploy caveats observed while shipping the PWA, HR payroll/off-days/audit and per-service stage work.

This file is a working record, not a specification. Each issue lists what was actually observed, how to reproduce it, the likely cause and a suggested fix. Items marked **test-only** do not affect the deployed application.

## Summary

| ID | Area | Severity | Status | Affects production? |
|----|------|----------|--------|---------------------|
| ISSUE-1 | Contract tests use the pre-versioning payload | High (test coverage) | Open | No |
| ISSUE-2 | Audit flag schema memo breaks after a schema rebuild | High (dev/test) | Open | No (single-process migrate is safe) |
| ISSUE-3 | Stale `deploy.yaml` still describes a production deploy on `main` pushes | Medium | Open (documented as unused) | No while manual deploy is used |
| ISSUE-4 | PWA version identity lags the deployed commit | Medium | By design | Depends on deploy method |
| ISSUE-5 | Applications list shows the case-level mirrored stage | Medium | Open | Cosmetic/consistency |
| ISSUE-6 | Full PHP suite exceeds the 10-minute command budget | Low | Open | No |
| ISSUE-7 | No browser/E2E coverage for pipeline, stage editor or offline mode | Low | Open | Verification gap |

## Repository state at review time

- `main` = `3628908`, identical to `origin/main`; working tree clean.
- `git branch --merged main` → `main` only. `feature/google-drive-storage` (`7b2b511`) and `feature/multi-tenancy-per-office` (`e031967`) are unmerged and contain no files in `main`'s tree.
- No stashes, one worktree.
- The three `2026_10_18_*` migrations (off-days, HR/payroll columns, audit heartbeat flags) are committed.
- Build/worker assets are committed: `public/build/**`, `public/sw.js`, `public/version.json`.

## ISSUE-1 — Contract tests still send the pre-versioning payload

**Severity:** High for test coverage, test-only for the app.
**Status:** Open.

### Symptom

```
Tests\Feature\ApplicantContractStageTest   5 failed, 1 passed
Tests\Feature\SimpleEducationalWorkflowTest 2 failed, 2 passed
```

Failures happen inside the shared contract helper, e.g.:

```
Expected response status code [201] but received 422.
{"errors":{"start_date":["validation.prohibited"]}}
{"errors":{"university_fee":["validation.prohibited"]}}
{"errors":{"contract_template_version_id":["validation.required"]}}
```

### Reproduction

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --filter="ApplicantContractStageTest"
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --filter="SimpleEducationalWorkflowTest"
```

### Cause

Built-in contracts moved to versioned generation. `StoreServiceContractRequest` now requires `contract_template_version_id` and prohibits client-supplied `start_date` (and fields such as `university_fee` on this path), but these tests still post the old shape:

- `tests/Feature/ApplicantContractStageTest.php` — its own `createContract()` helper posts `start_date` and omits `contract_template_version_id`, so all five workflow tests fail in setup.
- `tests/Feature/SimpleEducationalWorkflowTest.php:31` — passes `university_fee` to `createBuiltInContract('private', ...)`.
- `tests/Feature/SimpleEducationalWorkflowTest.php:89` — passes `start_date` to `createBuiltInContract('academy', ...)`.

The helper itself (`tests/Concerns/CreatesBuiltInContracts.php`) is correct; the tests override it with prohibited fields.

### Impact

The contract-line stage workflow (the newest area) is not covered by its five tests, so a regression there would go unnoticed. The application behaviour is not affected.

### Suggested fix

- Prefer `Tests\Concerns\CreatesBuiltInContracts` in `ApplicantContractStageTest` and read the created line from `ApplicantContract` (the helper already creates the template version).
- In `SimpleEducationalWorkflowTest`, create the contracts without `start_date`/`university_fee`, then set `contracts.start_date` with `DB::table(...)->update(...)` when testing date filters, and pass `university_fee` through the applicant detail or a supported contract field.

## ISSUE-2 — `unread_audit_logs` memo breaks after a schema rebuild

**Severity:** High for developer/test ergonomics; not a production blocker.
**Status:** Open.

### Symptom

In a broad batch run, some tests fail with:

```
SQLSTATE[HY000]: General error: 1 no such column: unread_audit_logs
update "users" set "unread_audit_logs" = 1 ...
at app/Models/AuditLog.php:50
```

The same tests pass when their class runs alone.

### Reproduction (deterministic)

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: \
  php artisan test --filter="ApplicantContractStageTest|ApplicantServiceMigrationTest"
# 6 failed, 1 passed — ApplicantServiceMigrationTest::cutover... throws the error above
```

### Cause

`app/Models/AuditLog.php` memoizes the presence check in a process-wide static:

```php
private static ?bool $hasUnreadAuditColumn = null;

if (self::$hasUnreadAuditColumn !== true) {
    self::$hasUnreadAuditColumn = Schema::hasColumn('users', 'unread_audit_logs');
    ...
}
```

`false` is re-checked, but `true` sticks for the whole PHP process. Migration tests such as `tests/Feature/ApplicantServiceMigrationTest.php` use `DatabaseTruncation` and reset `RefreshDatabaseState::$migrated`, which rebuilds the schema mid-process. An audited model written while the schema is being rebuilt (before the audit-flag migration is applied) then hits the memoized `true` and issues the `UPDATE` against a `users` table that does not yet have the column.

### Impact

- Test-suite results depend on class order; a green class can fail when run after another class.
- Any long-lived process that rebuilds the schema (`migrate:fresh`, some test helpers) can hit it. A normal deploy (`php artisan migrate --force` once, then serving requests) does not rebuild the schema, so production is not affected.

### Suggested fix (choose one)

- Drop the memo and call `Schema::hasColumn()` per audit write (correct but adds one schema query per audited create), or
- Key the memo by connection + database name and reset it when the schema is rebuilt (e.g. expose `AuditLog::forgetUnreadColumnCache()` and call it from tests that reset `RefreshDatabaseState`, or listen to the `DatabaseRefreshed`/`migrate:fresh` event), or
- In the schema-rebuilding tests, reset the memo in `setUp()`/`tearDown()` alongside `RefreshDatabaseState::$migrated = false`.

## ISSUE-3 — Stale production deploy workflow

**Severity:** Medium.
**Status:** Open (owner confirmed the pipeline is unused; deployment is manual).

`.github/workflows/deploy.yaml` still triggers on:

```yaml
on:
  push:
    branches: [main]
  workflow_dispatch:
```

It builds with pnpm, rsyncs a release to `DEPLOY_HOST`, runs `php artisan migrate --force`, `php artisan optimize` and `queue:restart`. Because it is `main`-only, pushing `feature/*` branches never triggers it.

### Risk

If deploy secrets are added later (or were never removed), the next push to `main` will deploy production automatically, contrary to the intended manual flow.

### Suggested fix

Delete the workflow, or reduce it to `workflow_dispatch` only, and document the manual deploy steps (below) in the repository.

## ISSUE-4 — PWA version identity lags the deployed commit

**Severity:** Medium (by design).
**Status:** Accepted design; verify per deploy.

`__APP_VERSION__` is `package.json` version + the short SHA of the commit the build was made *from*. A commit cannot contain its own SHA, so the committed build was produced from the content commit:

```
package.json         9.5.0
content commit       4e9447e
deployable branch    3628908  (generated-assets commit)
public/version.json  9.5.0+4e9447e
```

Consequences:

- Deploying the committed files as-is reports `9.5.0+4e9447e`. It differs from the previously deployed `9.5.0+054272d`, so installed clients get the update toast and old-version caches are purged. Correct.
- Rebuilding on the server after checking out `3628908` bakes `9.5.0+3628908` and is also correct.
- Do **not** re-run `npm run build` after every docs-only commit expecting the version to match `HEAD`; the identity is always the commit the build was made from.

## ISSUE-5 — Applications list shows the case-level mirrored stage

**Severity:** Medium (consistency).
**Status:** Known limitation accepted for this pass.

The pipeline, the case workflow card and the per-service report use the `applicant_contract` line's stage. The Applications list (`resources/ts/pages/applicants.vue`) still renders the applicant-level `current_stage` mirror in its Stage column and stage tabs. When a case buys a second service, the list can show a different stage than the selected service line.

### Why it was left

`ApplicantController@index` loads service lines only for users with `view_applicant_financials`, and the row presenter used by the list is built from the applicant model, not from a selected line. Showing the line stage needs the list query to load `services.currentStage` with a selected-service context and the table to render it.

### Suggested follow-up

When `filters.service` is set, eager-load that service's line and render its stage in the Stage column; keep the mirror for the unfiltered view.

## ISSUE-6 — Full PHP suite exceeds the command budget

**Severity:** Low.
**Status:** Open.

A full `php artisan test` run did not complete within 10 minutes in this environment, while the focused suites finish in roughly 25–100 seconds each. `brianium/paratest` is installed (`composer.json`), but the documented workflow does not run `php artisan test --parallel`, so the suite is effectively serial.

### Suggested fix

Run the suite with `php artisan test --parallel` (with a dedicated test database per worker), or split CI into focused serial suites (educational, contracts/commissions, HR, PWA) and keep a nightly full run.

## ISSUE-7 — No browser/E2E coverage for the new surfaces

**Severity:** Low (verification gap).
**Status:** Open.

The per-service pipeline, stage editor, case stage panel and PWA offline flow are covered by feature tests and a successful production build, but were not exercised in a browser here (the configured MySQL server is unreachable; tests run on in-memory SQLite).

### Suggested fix

Add a Playwright smoke flow: login → Applications → Pipeline (switch service, drag/open a card) → case page (move a line stage) → Options → Contract Stages (edit a stage) → offline reload (service worker) → `/api/version` mismatch toast.

## Manual deployment checklist (current owner flow)

1. `git checkout main` and confirm `git status` is clean and `git log -1` is the intended commit.
2. Back up the production database.
3. Upload/checkout the release (exclude `.env`, `storage`, `node_modules`, `vendor`; keep the existing shared `.env` and `storage`).
4. `composer install --no-dev --prefer-dist --optimize-autoloader`
5. `php artisan migrate --force` (expect the three `2026_10_18_*` migrations on the first deploy of this branch).
6. Build assets (`pnpm install --frozen-lockfile && pnpm run build`) **or** serve the committed `public/build`, `public/sw.js`, `public/version.json`.
7. `php artisan optimize` and `php artisan queue:restart`.
8. Smoke-test: login, Applications, Pipeline (service switch + card move), a case's per-service workflow card, Options → Contract Stages, HR off-days, `/api/version`, `/manifest.webmanifest`, `/sw.js`.
9. Expect installed clients to offer the "new version" reload toast once; after reload, old `api-*` caches are gone.

## Verification commands used

```bash
# Repository state
git status -sb
git branch -vv
git branch --merged main
git merge-base --is-ancestor feature/google-drive-storage main   # exit 1 = safe
git merge-base --is-ancestor feature/multi-tenancy-per-office main

# Frontend
npm run typecheck
npm run build
npm run build:icons

# Backend (in-memory SQLite)
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --filter="ApplicantPipelineTest|ApplicantStageCatalogTest|ApplicantServiceDetailsTest|ApplicantWorkflowTest|ApplicantTaskIntegrationTest|EducationalDashboardTest|EducationalReportTest|EducationalWorkflowIntegrationTest|SearchTest|CommissionLifecycleTest"
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --filter="PwaManifestTest"
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --filter="ApplicantContractStageTest"       # fails, ISSUE-1
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --filter="SimpleEducationalWorkflowTest"    # fails, ISSUE-1
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --filter="ApplicantContractStageTest|ApplicantServiceMigrationTest"  # reproduces ISSUE-2
```

## Revision history

- 2026-10-18 — created from the review of `main` at `3628908`; reproduced ISSUE-1 and ISSUE-2 with the exact commands above; confirmed experimental branches are not merged and no experimental files exist in `main`.
