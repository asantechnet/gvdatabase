# Testing workflow and performance

- Maintained test behavior, reviewed 2026-10-29.
- Implementation: [phpunit.xml](../../phpunit.xml), [tests/TestCase.php](../../tests/TestCase.php), [PermissionSeeder.php](../../database/seeders/PermissionSeeder.php), [RolePermissionSeeder.php](../../database/seeders/RolePermissionSeeder.php), [composer.json](../../composer.json).
- This is the canonical testing document. Historical handoffs and plans (including `docs/BASELINE_TEST_FIX_HANDOFF.md`) describe past work and do not override this file.
- Validation: the full suite was measured end to end on the local MySQL 8.4 server in one process — **462 tests, 454 passed, 8 failed, 4,112 assertions, 1,202 s (about 20 minutes)**. Timings below are measured, not estimated. The 8 failures are pre-existing and unrelated to the seeding workflow; each was re-run against the unmodified checkouts: stale contract payloads (`ContractTaskAssignmentTest` ×2, `RealtimeNotificationTest`, `VisaFirmComplianceTest`), list ordering (`VisitorFollowUpTest`), and MySQL-hostile migration round-trips (`CatalogueRetirementMigrationTest`, `FinanceRetirementMigrationTest`'s SQLite `PRAGMA foreign_key_check`, `MarketerRetirementMigrationTest`'s SQLite-style foreign-key drop).

## Test environment

`phpunit.xml` is authoritative for the test environment. It sets `APP_ENV=testing`, `DB_DATABASE=testing`, `CACHE_STORE=array`, `SESSION_DRIVER=array`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`, `BCRYPT_ROUNDS=4` and disables Pulse/Telescope/Nightwatch. There is no `.env.testing`: the root checkout inherits `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_USERNAME` and `DB_PASSWORD` from `.env`.

- **Root checkout:** uses MySQL, ordinarily `127.0.0.1:3306` and the `testing` schema. The connection is direct loopback; warm queries measure about 0.10 ms, so the database link is not the reason a suite feels slow.
- **Multi-tenant worktree:** its `phpunit.xml` forces `DB_CONNECTION=sqlite` and its `tests/TestCase.php` configures separate throwaway `office` and `landlord` SQLite files. Its base class still seeds the same authorization seeders through Eloquent on SQLite; the bulk `seedAuthorizationCatalog()` path described below belongs to the root checkout. Do not copy root-checkout MySQL assumptions into that worktree, and do not point two worktrees at the same `testing` database.
- Never run the suite while another suite, agent or worktree is using the same database. `RefreshDatabase` runs `migrate:fresh` on the configured connection, so concurrent runs collide on DDL and locks.

## Mandatory seeding path (root checkout)

Every root-checkout test extending `Tests\TestCase` calls `seedAuthorizationCatalog()` from `setUp()`. It bulk-writes the canonical catalogue in five statements:

1. `permissions` upsert from `PermissionSeeder::catalog()`.
2. `roles` insert-or-ignore from `RoleHierarchy::LEVELS`.
3. `roles` id lookup.
4. `permissions` id lookup.
5. `role_permissions` insert-or-ignore from `RolePermissionSeeder::grants()`.

`PermissionSeeder::run()` and `RolePermissionSeeder::run()` remain the Eloquent source of truth for production seeding and for tests that deliberately verify seeder behavior after a migration. `RoleSeeder` also remains Eloquent. The bulk test path uses the same catalogues and grant map, so roles, permissions and default grants stay identical to production without paying model-event and per-row query costs for every test.

Rules:

- Do **not** add `$this->seed([PermissionSeeder::class, RolePermissionSeeder::class])` to a test `setUp()`. The base class already seeds the catalogue; re-running the Eloquent seeders costs about 1,100 SELECTs and is redundant.
- Call a seeder in a test only when the test is verifying that seeder or a data migration, or when it needs a different fixture (for example `ApplicantPortalDemoSeederTest` intentionally runs `DatabaseSeeder`).
- The bulk path bypasses Eloquent model events, including audit writes, for the authorization catalogue. Tests must not assert audit rows created by seeding permissions. Business-model audits remain unchanged.
- If the catalogue or grant map changes, change the seeder data, not a copy in `TestCase`. The bulk path reads the seeders directly.
- Never seed the full `DatabaseSeeder` in a shared base class. Heavy demo seeders belong to the specific test that exercises them.

## Measured cost and the result cache

The old per-test setup ran the three Eloquent seeders: **3,252 queries and about 1.95 seconds per test** (1,460 for permissions, 36 for roles, 1,756 for role permissions). With 462 current tests that alone was roughly **15 minutes**, before any test body ran. Most of the excess came from `firstOrNew()`/`firstOrCreate()` issuing a SELECT and an INSERT per row, plus the audit checks on `Permission` creation.

The bulk path reduces setup to five statements per test; observed test times are now about **0.08–0.6 seconds including the test body**. The largest remaining fixed cost is `RefreshDatabase` running `migrate:fresh` **once per PHP process**: 76 migrations and 110 tables take about **41 seconds** on the maintainer machine. Every `php artisan test` invocation pays it once, including a run of a single file. Run files together rather than starting a fresh process for each test, and do not interpret the first test's duration as that test's own cost.

Four migration tests (`ApplicantServiceMigrationTest`, `BuiltInContractTemplateMigrationTest`, `FinanceRetirementMigrationTest`, `CatalogueRetirementMigrationTest`) deliberately use `DatabaseTruncation` and reset `RefreshDatabaseState::$migrated` in both `setUp()` and `tearDown()`, so each of their tests pays another full migration. The `tearDown()` reset also makes the **next file's first test** re-migrate, which is why a full run pays around a minute for unrelated tests such as `CommissionLifecycleTest`'s first case. That is required by their schema round-trips; do not copy the reset into ordinary tests, and run those migration files separately when iterating.

In the measured 1,202 s full run, 13 tests took between 42 s and 100 s each and together accounted for about **1,000 s** of the total. The other ~450 tests finished in roughly 3–4 minutes. When iterating, run the migration files in their own invocation (even better, one file per invocation) so their resets do not tax the remaining files.

`.phpunit.result.cache` timings are misleading for this work: PHPUnit starts its result-cache clock at `testPrepared`, which is emitted after `setUp()`. The cached per-test times therefore exclude all fixture and seeding work. Use wall-clock `time php artisan test ...` to judge the suite. The cache may also contain entries from removed test classes or another worktree; delete it if its contents do not match the checkout.

## Commands

```bash
# Full suite (fixed migrate:fresh cost plus all tests)
php artisan test

# One file or a subset; the process still pays migrate:fresh once
php artisan test tests/Feature/IncomeReceiptTest.php
php artisan test tests/Feature/IncomeReceiptTest.php tests/Feature/RoleBasedAuthorizationTest.php

# Tight loop on a test name
php artisan test --filter=receipt_returns_formal_educational_receipt_payload

# Composer wrapper: clears cached config first, then runs the suite
composer test

# Type checking must use the project script; never bare vue-tsc/tsc
npm run typecheck
```

Parallel execution is available through the installed `brianium/paratest` package (`php artisan test --parallel`). It creates a separate `testing_*` database per worker, so every worker also pays the migration cost and the MySQL user needs permission to create databases. It is not the default and must not be combined with a sequential run against `testing`. Use it only after confirming the worker databases and cleanup on the target machine.

Switching the root checkout to SQLite `:memory:` is **not** the default. It would avoid most MySQL DDL cost, but the migration set and query layer must first be audited for MySQL-specific SQL; the multi-tenant worktree already runs on SQLite and is the reference for what that requires.

## Performance guardrails

- Keep per-test setup in the low milliseconds. If a fixture must be created for many tests, add it to the bulk catalogue path or a shared factory helper instead of re-running a seeder.
- Keep seeders idempotent and update-safe: `PermissionSeeder` upserts by `name`, `roles` insert-or-ignore, and `role_permissions` insert-or-ignore on the composite unique key.
- Do not add `sleep()`, retry loops or real HTTP calls to tests. Fake external clients.
- Do not add a test-wide seeder that writes thousands of rows; use a factory with the minimum required records.
- When changing `TestCase`, `phpunit.xml`, a seeder or the schema, run the full suite and record the wall-clock time. A setup regression of 100 ms per test adds roughly 46 seconds to a 462-test suite.
- Clean up ad-hoc `*_testing` databases and old worktree databases. They are not used by the configured suite and only add catalog and connection pressure.

## Troubleshooting

| Symptom | Cause and action |
| --- | --- |
| Entire run takes about 40 seconds before the first assertion | Expected: `RefreshDatabase` is migrating 110 tables. Check that only one process is migrating. |
| One file takes about 40 seconds and the rest is fast | Same fixed migration cost; run more files per process or use `--parallel` deliberately. |
| Tests fail immediately with missing tables | The configured database was wiped or two runs collided. Re-run, and ensure only one suite uses `testing`. |
| Suite time jumps by minutes | A test or base fixture is re-running Eloquent seeders, full `DatabaseSeeder`, or real network/retry work. Profile with `DB::listen()` inside a rolled-back transaction rather than guessing. |
| `.phpunit.result.cache` names tests that no longer exist | Stale cache from another branch/worktree; delete the file and re-run. |
| `db:show`/number formatting raises an `intl` error | The PHP `intl` extension is absent; it affects formatting, not the database connection. |
| Lock wait or "table already exists" errors | Another test process is using the same database. Use one database per worktree or `--parallel` worker isolation. |

## Maintenance

Update this file with the code whenever the test database configuration, `TestCase` seeding, PHPUnit version or the canonical commands change. Keep historical testing handoffs historical; link them from here only when they explain a past incident, never as the current procedure.
