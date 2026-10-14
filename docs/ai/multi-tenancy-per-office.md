## Task: Multi-tenancy per office — database-per-office, owners, office switching, SaaS operator dashboard

- **Task ID:** multi-tenancy-per-office
- **Created:** 2026-10-13
- **Last updated:** 2026-10-13
- **Status:** PLANNED (planning only — **no implementation has been performed**)
- **Outcome:** Each office becomes an isolated tenant with its **own database** (hand-created on Hostinger). A **landlord database** holds SaaS operators, the owner registry and the office registry. Assigning an office to an owner creates **exactly one system-generated super-admin user inside that office's database** carrying `users.owner_id`; that single row is what makes the office owned. Offices are addressed by path `/offices/{office-uuid}`, which selects which office database to look up users in. The owner user switches offices from Settings → Offices. A separate admin area lets SaaS operators create owners and offices. Every tenant business table also keeps a denormalized `office_id`. The existing Google Drive storage plan is honoured and made per-office.

---

### 1. Requirements and acceptance criteria

- [ ] **DB-per-office:** each office has its own MySQL database; databases are created by hand on Hostinger (the app never runs `CREATE DATABASE` in production).
- [ ] **Landlord database** holds: SaaS operator accounts, the **owner registry** (no login), and the **office registry** (uuid, owner, DB name + credentials, status, provisioning state).
- [ ] **Owners are a non-login registry.** An owner cannot sign in with an owner account; the owners table only exists so the system knows who owns which office.
- [ ] **One owner per office.** An owner can own many offices.
- [ ] **Generated owner user:** assigning an office to an owner creates exactly one super-admin user inside that office DB, with `users.owner_id` set to the landlord owner. **Only that one user may have `owner_id` set**; every other user in the office DB has `owner_id = NULL`.
- [ ] Additional (non-owner) super-admins can be created normally inside an office DB with `owner_id = NULL`.
- [ ] **Office resolution by path:** `/offices/{office-uuid}` determines which office database is used to look up users and serve the app.
- [ ] **Strict separation:** an office's data is never readable from another office.
- [ ] **Office switching** in Settings → Offices, available to the owner user, by navigating to the target office and signing in there.
- [ ] `owner.active_office_id` is persisted (optional) as the owner's default/last office.
- [ ] **SaaS operator dashboard** creates owners and offices and drives provisioning.
- [ ] Every tenant business table carries a denormalized `office_id`.
- [ ] **Full per-office runtime isolation:** files, cache, queue, scheduler (not only the database).
- [ ] **Google Drive storage plan remains per-office** (its tables live in the office DB; the Drive tree is office-scoped).
- [ ] All UI works in RTL + LTR, light + dark, and across mobile/tablet/desktop; printing (A4 contracts, A5 receipts/payslips) is unchanged.
- [ ] `npm run typecheck` and `php artisan test` pass at the end of every phase.

### 2. Scope and non-goals

- **In scope:** landlord DB + connections, office registry, owner registry, SaaS operator auth + dashboard, generated owner user, per-office DB provisioning + migrations + seeders, tenant-context middleware, `office_id` + `owner_id` schema, per-office URL scheme (backend + SPA), office switcher, per-office storage/cache/queue/scheduler, Google Drive per office, i18n/RTL/dark/responsive, tests, docs.
- **Out of scope:** billing/subscriptions, per-office custom domains or subdomains (path prefix only, per the decision), data migration from the current database (fresh production start), cross-office aggregate reporting, per-user Google Drives, changing print layouts or payment/asset rules, reviving invoicing/company/commission-removed features.
- **Explicitly untouched behaviour:** `Auditable` history semantics, RBAC vocabulary and `super_admin` bypass, the double `data` API wrapper, `printDocument` + namespaced `@media print` + injected `@page`, asset-condition enum, applicant-contract payment rules, generated `icons.css`.

### 3. Clarified design decisions (authoritative)

| # | Decision |
|---|---|
| 1 | Isolation: **true database-per-office** (not shared-schema). |
| 2 | Databases are **created by hand on Hostinger**; the app only runs migrations/seeders against them. |
| 3 | A **landlord (central) database** holds operators, owners and the office registry. |
| 4 | Owners are a **non-login registry** with an optional `active_office_id`. |
| 5 | Assigning an office to an owner creates **exactly one system-generated super-admin user** in that office DB; only it has `owner_id`. |
| 6 | **One owner per office**; an owner owns many offices. |
| 7 | Office is addressed by **path `/offices/{office-uuid}`**; the whole SPA lives under that prefix. |
| 8 | SaaS operators live in a **dedicated landlord table with their own guard**. |
| 9 | **Switching = re-login in the target office** (independent session/token per office). `owner.active_office_id` records the owner's default/last office. |
| 10 | **Provisioning:** operator enters the hand-created DB name on the office form; the app runs migrations + seeders on demand via an explicit, idempotent action. |
| 11 | The generated owner user is created with the **owner's email + the existing `must_set_password` invitation flow**. |
| 12 | **Per-office DB credentials are stored (encrypted) on the office registry record.** |
| 13 | **Full per-office runtime isolation** (storage, cache, queue, scheduler). |
| 14 | **Everything business-related is per-office**; the landlord DB holds only operators, owners, the office registry, and (if needed) operator sessions/tokens/cache/jobs. |
| 15 | **Fresh production start:** the current database stays a dev/seed database; production begins with a new landlord DB and empty office DBs. No live business data is migrated between databases. |
| 16 | Every tenant business table keeps a denormalized **`office_id`** as a safety/audit key. |

**Derived design decision (recorded, not guessed blindly):** `offices` stays in each office's database as the **tenant-local office profile** (name, code, address, phone, geofence) with its existing singleton rule — this keeps `employees.office_id`, `attendances.office_id`, `assets.office_id`, the geofence feature and `OfficeGeofenceAttendanceTest` working unchanged. The **tenancy metadata** (uuid, owner, DB name + encrypted credentials, status, provisioning state) lives in the landlord registry. Rationale: decision 14 puts business data per-office; geofence/profile is business data, while DB routing is platform metadata.

**Derived consequence (recorded):** because office user tables are per-office, a person working in two offices has two accounts (one per office DB). This follows directly from decisions 1 and 14 and from the user's statement that "all users in office database have an owner_id".

### 4. Project findings

**Backend**
- Laravel 12 / PHP 8.2, `laravel/sanctum ^4`. Token auth: routes use `auth:sanctum` + `abilities:access` + `active` + `edit-window` (`routes/api.php`, `bootstrap/app.php`).
- Single DB connection today (`config/database.php`); real `.env` uses MySQL (`DB_DATABASE=laravel_test`). Session/cache/queue drivers are `database`.
- **`offices` is currently a singleton** (`is_singleton` unique column) used only for the attendance geofence: `database/migrations/0001_03_01_000003_create_core_tables.php`, `app/Http/Controllers/OfficeController.php` (`business.single_office_only`, delete forbidden), `app/Models/Office.php`, `app/Http/Resources/OfficeResource.php`, `OfficeSeeder`.
- `office_id` exists only on `employees`, `attendances`, `assets` (physical office reference), not as a tenant key.
- `settings` is a singleton row (`Setting`, `SettingController`, `/api/company-profile`) — becomes one row per office DB naturally.
- RBAC: `roles` + `level` (`App\Support\RoleHierarchy`), `permissions`, `user_roles`, `role_permissions`; `super_admin` bypasses (`User::isSuperAdmin`); `permission:` middleware (`app/Http/Middleware/EnsurePermission.php`).
- Audit: `App\Models\Concerns\Auditable` writes `AuditLog` and notifies every `super_admin`; `App\Support\AuditRegistry` gates models.
- Auth flows that embed absolute URLs and must become office-aware: `AuthController::{verifyEmail,resendEmailVerification,completeInvitation}`, `AppServiceProvider` `ResetPassword::createUrlUsing` / `VerifyEmail::toMailUsing`.
- Files: `public` disk (avatars, logos, letterheads, contract designs) + `local` disk (attachments/documents); see `docs/google-drive-storage-plan.md` §3 for the full writer audit.
- 83 models, 63 migrations, 34 seeders; `tests/TestCase.php` seeds `PermissionSeeder`/`RoleSeeder`/`RolePermissionSeeder` and the suite runs on MySQL.

**Frontend**
- Static router authoritative at `resources/ts/plugins/1.router` (`routes.ts` 255 lines with `pageComponents` + `routeDefinitions`, `guards.ts` permission hydration, `additional-routes.ts` root redirect, `index.ts` `createWebHistory(import.meta.env.BASE_URL)`).
- No auth store: tokens in `utils/authStorage`, `userData` cookie, CASL abilities in `utils/authorization`.
- `api/axios.ts` uses `baseURL = VITE_API_BASE_URL || '/api'`, plus request-side permission/validation maps (`utils/apiAuthorization.ts`, `utils/apiValidation.ts`) that match on relative `config.url`.
- Settings page `pages/setup/settings.vue` already has an **`office` section** rendering `components/setup/OfficeSettingsPanel.vue` (singleton profile + geofence), gated by `view_offices`/`manage_offices`; `OfficeApi` (`api/office.ts`) is singleton-shaped (`getAll()[0]`).
- SPA is served by a catch-all Blade view: `routes/web.php` → `view('application')` for every non-`api` path; assets use Vite's default absolute base.
- Navigation modules: `resources/ts/navigation/horizontal/*`; setup pages under `pages/setup/*`.

### 5. Graph and impact review

- **Tooling used:** `graphify-out/GRAPH_REPORT.md` + `manifest.json` were consulted. The graph is built from commit `c4c97435` (2026-10-09) and is **stale** relative to the 2026-10-13 migrations, so it was used for orientation only.
- **Graph evidence:** `User` is the top god node (402 edges), `Employee` 243, `Applicant` 213 — the tenant boundary touches identity and therefore the largest blast radius. The `Office` community is thin (`Office`, `Asset`, `OfficeFactory/Seeder`, `OfficeGeofenceAttendanceTest`), confirming `Office` is **not** currently a tenant boundary.
- **Fallback methods actually used:** `code_search` for `office_id` (only employees/attendances/assets + the `assets` FK), route/middleware/guard reading, per-file reads of models/controllers/settings/office/audit/role code, and review of `docs/ai` + `docs/google-drive-storage-plan.md`.
- **Downstream impact identified:** every tenant controller/resource/query (80+ models), `Auditable` (audit rows + super-admin notifications), `Setting`/`CompanyProfile`, `Employee`/`Attendance`/`Asset` office relations, auth email URLs, file storage + Drive mirroring, cache/queue/scheduler, seeders, and the whole SPA boot/router/API layer.
- **Tests likely to need updates:** `tests/Feature/OfficeGeofenceAttendanceTest.php`, `tests/Feature/StockOfficeRelationshipTest.php`, `tests/Feature/SettingsEmailAndFileRemovalTest.php`, `tests/Feature/CompanySettingsTest.php`, `DepartmentTaskAssignmentTest`, plus `tests/TestCase.php` database bootstrapping.
- **Limitations:** no tenancy library and no existing tenant tests, so per-module impact (especially audit, notifications, jobs) must be re-verified during implementation. The graph was not regenerated for this plan.

### 6. Target architecture

#### 6.1 Databases and connections
- **`landlord`** connection (new) from `LANDLORD_DB_HOST/PORT/DATABASE/USERNAME/PASSWORD` in `.env` (`config/database.php`).
- **`office`** connection: built at runtime per request from the office registry record's **encrypted** host/database/username/password; registered into `config('database.connections.office')`, `DB::purge('office')`, then set as the default connection for the request.
- Landlord models are an explicit set under `App\Models\Landlord\*` with `protected $connection = 'landlord'` (`SaasOperator`, `Owner`, `Office` registry). All other models keep using the default connection (switched to `office` for tenant requests).
- Tokens/sessions/cache/jobs tables exist in **both** databases; a tenant request writes them in the office DB, an operator request in the landlord DB.

#### 6.2 Migration layout (prevents cross-tenant schema misfires)
- Move the 63 tenant migrations to `database/migrations/office/` (mechanical `git mv`, no content change).
- New landlord migrations in `database/migrations/landlord/`.
- Leave `database/migrations/` empty so a stray `php artisan migrate` is a safe no-op.
- Add wrapper commands: `php artisan landlord:migrate` and `php artisan office:migrate {office} [--seed]`, both delegating to `migrate --database=… --path=… --force`.
- Tests migrate both explicit paths (see §9).

#### 6.3 URL and routing scheme
- **Office app:** everything under `/offices/{office-uuid}/` — e.g. `/offices/{uuid}/login`, `/offices/{uuid}/home`, and the tenant API at `/offices/{uuid}/api/*`.
- **SaaS operator area:** `/saas/*` with its own API at `/saas/api/*`.
- **Root `/`** redirects to `/saas` (operator sign-in) — owners receive direct `/offices/{uuid}/...` links (invitation email) rather than a central owner login.
- `routes/web.php` catch-all keeps serving `application`, but a controller now resolves `{office}` for office paths (validates the office exists, is not suspended, is provisioned) and injects a `window.__APP_CONTEXT__` bootstrap payload (mode, office uuid, office display name, base path) into the Blade view; unknown/inactive offices render a friendly error page.
- Frontend router base becomes dynamic: `createWebHistory('/offices/{uuid}/')` for office mode and `createWebHistory('/saas/')` for operator mode, so existing relative route paths and `router.push` calls keep working unchanged.
- The owner-switcher navigates the browser to `/offices/{target-uuid}/login` (decision 9).

#### 6.4 Data model additions
**Landlord DB (`database/migrations/landlord/`)**
- `saas_operators`: `id`, `name`, `email` (unique), `password`, `status`, `two_factor_*` (parity with users), timestamps, soft deletes.
- `owners`: `id`, `name`, `email` (unique, nullable), `phone`, `active_office_id` (nullable), timestamps, soft deletes. **No password column.**
- `offices` (registry): `id`, `uuid` (unique, used in the URL), `name`, `code`, `owner_id` (nullable, FK → owners, one owner per office), `db_host`, `db_name`, `db_username`, `db_password` (**encrypted**), `status` (`active|suspended`), `provisioned_at`, `last_migrated_at`, `provisioning_error`, timestamps, soft deletes.
- Landlord `personal_access_tokens` (Sanctum) + `sessions`/`cache`/`jobs` as required by the operator area.

**Office DB (additions to the existing schema)**
- `users.owner_id`: nullable unsigned bigint, **unique index** (MySQL permits many `NULL`s), plus a model guard so only the provisioning service can set/clear it. This is the single "system-generated owner user".
- `office_id`: nullable→backfilled (`0`-safe) unsigned bigint added to **every tenant business table** with an index, auto-filled on create by a new `App\Models\Concerns\BelongsToOffice` trait and enforced by a global scope as defense-in-depth. Rule: every tenant table that owns business rows — including `settings`, `roles`, `permissions`, `currencies`, all educational master-data tables, contracts/templates/policies/designs, applicants/universities/stages, employees/attendance/leaves/salaries/assets/expenses/incomes/commissions/pending-transactions, tasks/boards/comments/subtasks/attachments, notifications/announcements/audit_logs, support tickets, CRM, and the new `google_drive_*` tables. Pure pivot/join tables (`user_roles`, `role_permissions`, `role_user`, `position_user`, `board_user`, `task_user`, `task_tag`, `notification_user`, `google_drive_*` joins) are excluded.
- `google_drive_connections` / `google_drive_folders` / `google_drive_files` (from `docs/google-drive-storage-plan.md` §4.4) are created **per office** (they live in the office migration set) with its `manage_storage_settings` permission seeded per office.

#### 6.5 Office resolution (backend)
- New middleware `App\Http\Middleware\ResolveOfficeContext`: for routes mounted under `/offices/{office}`, look the office up in the landlord DB by `uuid`, register + purge the `office` connection from its credentials, set `database.default = office`, and bind a request-scoped `App\Support\OfficeContext` (office registry model + uuid) singleton. Aborts with `business.office_not_found` (404), `business.office_suspended` (403) or `business.office_not_provisioned` (409) as appropriate.
- New middleware `App\Http\Middleware\UseLandlordConnection` for `/saas/api/*`.
- Route files: existing `routes/api.php` keeps its content but is mounted inside the office group; new `routes/saas-api.php` for operator/owner/office endpoints. `bootstrap/app.php` registers both prefixes and the two middleware.
- `App\Support\OfficeContext` is `scoped()` (never a singleton) to stay safe on queue/Octane workers, mirroring the existing `EmployeeScope` convention.

#### 6.6 Auth and identity
- **Tenant auth** is unchanged in shape: Sanctum tokens in the office DB, `auth:sanctum`, `AuthController` at `/offices/{uuid}/api/auth/*`. `AuthController::accountData()` gains `owner_id` and an `is_office_owner` flag so the SPA can show the switcher only for the owner user.
- **Landlord auth** is new: `saas_operator` guard/provider (`config/auth.php`) + `SaasOperator` model with `HasApiTokens`, `/saas/api/auth/*` login/logout/me.
- Email flows become office-aware: `ResetPassword::createUrlUsing`, `VerifyEmail` verification URLs, and `AuthController::verifyEmail`'s redirect must build `/offices/{uuid}/...` URLs from `OfficeContext`.
- Users remain per-office (each office DB has its own `users`); the landlord DB has no staff users.

#### 6.7 Owners, switching, and `active_office_id`
- Owners are created/edited only by SaaS operators (landlord CRUD). No credentials exist for an owner; there is nothing to authenticate with.
- Assigning/reassigning an office to an owner runs the provisioning step that creates (or relinks) the single `owner_id` user inside that office DB using the owner's email and the `must_set_password` invitation (decision 11).
- The generated user is created as a `super_admin` with `owner_id` set; additional super-admins created later have `owner_id = NULL`.
- **Settings → Offices** renders a switcher only when the current user has `owner_id` set. It lists the owner's other offices by querying the **landlord** registry (`DB::connection('landlord')->table('offices')->where('owner_id', …)`), and each entry links to `/offices/{other-uuid}/login` (re-login per decision 9). Selecting an office also updates `owners.active_office_id` in the landlord DB.
- `owner.active_office_id` is the owner's default/last office, used by the operator dashboard ("jump to office") and as the preferred target for any owner-facing link.

#### 6.8 SaaS operator dashboard (landlord)
- `SaasOperator` auth + layout on the frontend (`/saas`).
- Landlord controllers/resources: `SaasOperatorAuthController`, `Landlord\OwnerController` (CRUD), `Landlord\OfficeController` (CRUD, assign owner, suspend/activate, **provision**, re-migrate, health), `Landlord\DashboardController` (counts: owners, offices, provisioned/pending/failed).
- New permissions seeded in the **landlord** DB: `manage_owners`, `manage_offices_registry`, `provision_offices`, `view_platform_dashboard` (landlord RBAC is separate and minimal; operators hold them).
- Owner assignment + provisioning are the only paths that create the `owner_id` user.

#### 6.9 Provisioning workflow (decision 10)
1. Operator hand-creates an empty MySQL database on Hostinger.
2. Operator enters `db_host`, `db_name`, `db_username`, `db_password` (and office name/code/owner) on the office form.
3. Operator clicks **Provision**. `App\Services\Tenancy\OfficeProvisioner` then, idempotently:
   - registers/purges the office connection and verifies connectivity (clear error if not reachable);
   - runs `migrate --database=office --path=database/migrations/office --force`;
   - runs the office seeders (permissions, roles, role_permissions, currencies, educational reference/master data, finance types, settings defaults, and the tenant-local office profile);
   - creates the tenant-local `offices` profile row (uuid synced from the registry) so geofence/attendance/assets keep working;
   - creates the single `owner_id` super-admin user with the owner's email + `must_set_password`, and sends the invitation;
   - marks the registry `provisioned_at` (or records `provisioning_error`).
4. Re-running is safe (idempotent): migrations are tracked per office DB; seeders use `firstOrCreate`/`insertOrIgnore`; the owner user is upserted, never duplicated.

#### 6.10 Per-office runtime isolation (decision 13)
- **Files:** office uploads live under `storage/app/offices/{uuid}/...`; the public disk serves `/storage/offices/{uuid}/...` so branding artwork and avatars cannot leak across offices. `Storage::url()` usage in `SettingResource`, `Employee`, `AuthController` avatar handling and the Drive mirror must build office-scoped paths.
- **Cache:** all cache keys namespaced `office:{uuid}:`. Landlord keys namespaced `landlord:`.
- **Queue:** jobs carry the office uuid; a new `App\Jobs\Concerns\RunsForOffice` bootstraps `OfficeContext` + connection on `handle()`. Serialization must not rely on the ambient connection.
- **Scheduler:** `routes/console.php` per-office entries iterate every active office and run with its connection (replacing today's global entries); `docs/scheduled-jobs.md` updated.
- **Google Drive (per `docs/google-drive-storage-plan.md`):** the connection/folder/file tables live in each office DB; the Drive root folder is `Educational DB/{office-name}-{uuid}/…` so multiple offices can safely share one Google account; `drive:verify|mirror|backfill|reconcile` gain an office selector (`--office=uuid` or iterate all); the callback route becomes office-aware (`/offices/{uuid}/api/storage/google-drive/callback`) and the post-connect redirect targets `/offices/{uuid}/setup/settings/storage`.
- **Audit + notifications:** stay per-office (they already read the default connection). `Auditable` must never fire for landlord models.

### 7. Implementation plan (phased, ordered)

**Phase 0 — Safety net**
- [ ] Capture the current test baseline: `php artisan test` and `npm run typecheck` (record results before any change).
- [ ] `git mv` the 63 files in `database/migrations` → `database/migrations/office/`; create `database/migrations/landlord/`; confirm bare `migrate` is a no-op.
- [ ] Add `landlord:migrate` / `office:migrate {office}` artisan commands (`app/Console/Commands/`).

**Phase 1 — Landlord foundation**
- [ ] `config/database.php`: add the `landlord` connection + `LANDLORD_DB_*` env entries in `.env.example`.
- [ ] Landlord migrations: `saas_operators`, `owners`, `offices` (registry), landlord tokens/sessions/cache/jobs, landlord permission tables.
- [ ] Models `App\Models\Landlord\{SaasOperator, Owner, Office}` (+ `HasApiTokens` on `SaasOperator`), landlord resource classes, `SaasOperatorSeeder`, landlord `PermissionSeeder`.
- [ ] Landlord seeders: first operator from env (`SAAS_OPERATOR_EMAIL/PASSWORD`), plus the default operator permissions.

**Phase 2 — Tenant-context plumbing**
- [ ] `App\Support\OfficeContext` (scoped), `App\Http\Middleware\ResolveOfficeContext`, `App\Http\Middleware\UseLandlordConnection`.
- [ ] `bootstrap/app.php`: register guards/middleware and mount `routes/api.php` under `/offices/{office}/api` and `routes/saas-api.php` under `/saas/api`; add new error codes to `ApiMessage` + `lang/{en,fa,ps}/api.php`.
- [ ] `routes/web.php` → controller that validates the office and injects `window.__APP_CONTEXT__`; friendly error page for unknown/suspended/unprovisioned offices.
- [ ] `App\Models\Concerns\BelongsToOffice` trait (auto `office_id` on create + global scope) applied to tenant business models; index migration for `office_id` across those tables.
- [ ] `users.owner_id` migration + unique index + model guard (only the provisioner may set it).
- [ ] `config/filesystems.php` + storage path helper for per-office roots; `config/cache.php`/`config/queue.php` name prefixes; `App\Jobs\Concerns\RunsForOffice`.
- [ ] Office-aware auth email URLs (`AppServiceProvider`, `AuthController::verifyEmail`).

**Phase 3 — Provisioning + owner user**
- [ ] `App\Services\Tenancy\OfficeProvisioner` (connect, migrate, seed, sync tenant office profile, upsert owner super-admin + invite, record state).
- [ ] Split seeders into landlord vs office sets; add `OfficeSeeder` (tenant profile) + `OfficeBootstrapSeeder` orchestration.
- [ ] Landlord `Landlord\OfficeController` (index/store/show/update/destroy/suspend/provision/health) + `Landlord\OwnerController` + `Landlord\DashboardController` + resources + `routes/saas-api.php`.
- [ ] `SaasOperatorAuthController` (login/logout/me/refresh) + `saas_operator` guard/provider.

**Phase 4 — Owner switching + tenant office section**
- [ ] Tenant endpoint to list the owner's offices (`GET /api/office-switcher`) reading the landlord registry by `owner_id`; returns uuid/name/status only.
- [ ] Endpoint to set `owners.active_office_id`.
- [ ] Keep the tenant-local `offices` singleton endpoints (`OfficeController`) for the office profile + geofence; ensure the registry name/code stay in sync on update.

**Phase 5 — Google Drive per office**
- [ ] Apply `docs/google-drive-storage-plan.md` phases 1–5 **inside the office migration set and office context**: `google_drive_*` tables in `database/migrations/office/`, office-scoped storage paths, office-scoped Drive root folder, office-aware callback + post-connect redirect, office-aware `drive:*` commands and `StorageServiceProvider`, per-office cache keys and jobs.

**Phase 6 — Frontend**
- [ ] Boot context: `resources/ts/utils/appContext.ts` reading `window.__APP_CONTEXT__` (or the path) → `officeBase`, mode, uuid; `main.ts` keys the app on it.
- [ ] Router: dynamic history base (`/offices/{uuid}/` vs `/saas/`); keep `routes.ts` paths unchanged; add `pages/saas/*` routes + `blank`-style operator layout.
- [ ] Axios: tenant instance `baseURL = ${officeBase}/api`; separate landlord instance `baseURL = /saas/api`; keep `apiAuthorization.ts`/`apiValidation.ts` working (they match relative URLs — verify).
- [ ] Auth storage keys namespaced per office + landlord keys; operator auth store/composable.
- [ ] `api/saas/*.ts` contracts (operators, owners, offices, provisioning) — unwrap `response.data.data` in the API layer only.
- [ ] `api/officeSwitcher.ts` + owner switcher UI inside `pages/setup/settings.vue`'s `office` section; keep `OfficeSettingsPanel.vue` for the current office profile.
- [ ] SaaS operator dashboard pages: owners list/form, offices list/form (create, assign owner, provision, status/health), platform KPIs.
- [ ] `useOfficeSwitcher` composable + `useAppContext` composable; nav gating so the switcher is visible only to the owner user.
- [ ] i18n keys in `en.json`, `fa-AF.json`, `ps-AF.json`; verify RTL/LTR, light/dark, responsive; run `npm run build:icons` if any new `tabler-*` token is introduced (never hand-edit `icons.css`).
- [ ] Docs: `.env.example`, `README.md`, `docs/scheduled-jobs.md`, and this record.

### 8. Validation plan

- **Automated**
  - `php artisan test` (full suite) — must stay green after every phase.
  - New tests: `OfficeResolutionTest` (unknown/suspended/unprovisioned uuid, connection switching), `OfficeProvisionerTest` (idempotent migrate/seed/owner-user creation), `OwnerUserConstraintTest` (only the generated user may hold `owner_id`; unique index), `OwnerOfficeSwitcherTest`, `LandlordAuthTest` (operator guard), `TenantIsolationTest` (office A cannot read office B's data; cache/storage key prefixes), `GoogleDrivePerOfficeTest`.
  - `tests/TestCase.php` updated to migrate the landlord path + a dedicated office test database, with an `actingAsOffice($office)` helper; test-only `CREATE DATABASE` for the throwaway office/test DBs.
  - `npm run typecheck` (never bare `vue-tsc`/`tsc`).
- **Manual / integration**
  - `php artisan landlord:migrate` + `office:migrate {uuid} --seed` against two throwaway MySQL databases on the dev machine.
  - Operator flow: create owner → create office (hand-created DB) → provision → confirm the office DB schema/seed data and the single `owner_id` super-admin, and that the invitation email points to `/offices/{uuid}/create-password`.
  - Office flow: sign in at `/offices/{uuid}/login` as the generated owner user → confirm Settings → Offices shows the switcher → switch to a second owned office → confirm re-login and that data differs.
  - Isolation check: upload a file, create a record, and verify nothing appears in the other office (DB, storage path, cache keys).
  - Print regression: contract A4 + receipt/payslip A5 layouts render identically (per AGENTS.md print rules).
  - Non-owner user check: a normal super-admin inside an office DB has no switcher and `owner_id = NULL`.
- **Regression gates:** `OfficeGeofenceAttendanceTest`, `StockOfficeRelationshipTest`, `CompanySettingsTest`, `SettingsEmailAndFileRemovalTest`, contract/payment suites.
- **Checks not runnable here:** real Hostinger provisioning and a live Google Drive smoke test (need hand-created DBs and OAuth credentials owned by the operator).

### 9. Risks and mitigations

- **Cross-tenant leakage** (unscoped queries, global audit/notifications, cache collisions, public file paths) — mitigation: `OfficeContext` + `BelongsToOffice` global scope, per-office cache/storage prefixes, explicit isolation tests.
- **Wrong migrations applied to the wrong DB** (bare `migrate`) — mitigation: split `database/migrations/{landlord,office}` folders so top-level `migrate` is a no-op, and wrapper commands only.
- **A stray query on the landlord connection inside a tenant request** — mitigation: default connection is switched per request; landlord models are the only explicit `landlord` users; isolation tests.
- **Token/connection confusion** (tokens are per DB) — mitigation: separate guards/paths, separate frontend token namespaces.
- **Provisioning partially failing** (DB unreachable mid-way) — mitigation: idempotent provisioner, `provisioning_error` surfaced in the operator dashboard, safe re-run.
- **`owner_id` uniqueness broken by direct writes** — mitigation: DB unique index + model guard + test.
- **Email/URL templates leaking the wrong office** — mitigation: office-aware URL builders and tests for invitation/verification links.
- **Queue/Octane staleness** — mitigation: `scoped()` context, jobs re-bootstrap their office, never cache a connection on a singleton.
- **Stale `graphify-out`** — mitigation: regenerate (`graphify update .`) after implementation before relying on it again.
- **Google Drive plan drift** (its tracker assumes a single org connection) — mitigation: this plan's Phase 5 explicitly supersedes that assumption (per-office connection) and that file's tracker must be updated alongside.

### 10. Progress log

- 2026-10-13 — DISCOVERY: Reviewed AGENTS.md, backend (routes, middleware, auth, models, migrations, seeders, RBAC, audit, settings, office), frontend (router, guards, navigation, settings/office panel, API contracts), tests, the stale `graphify-out` graph, and `docs/google-drive-storage-plan.md`. Key findings: `offices` is a singleton geofence record, not a tenant; `office_id` exists only on employees/attendances/assets; auth is Sanctum token-based on one connection; the SPA is served by a catch-all Blade view with an absolute-base router.
- 2026-10-13 — CLARIFICATION (4 rounds): Resolved isolation (hybrid → then true DB-per-office), landlord/owner/office model, generated owner user + `owner_id` rule, path-based office resolution, SaaS-operator dashboard, per-office DB credentials, full runtime isolation, fresh production start, everything-per-office, and the Google Drive continuity requirement.
- 2026-10-13 — PLAN: Full plan written and saved (this file). Status `PLANNED`. **Planning only — implementation deliberately not started**, per the user's explicit instruction.
- Not yet run: baseline `php artisan test` / `npm run typecheck` (Phase 0).

### 11. Completion summary

- **Delivered:** a complete, clarified, system-wide multi-tenancy plan covering backend, database, provisioning, auth, frontend, storage/Drive, tests, and risks.
- **Acceptance criteria satisfied:** none yet — no code was changed (planning only).
- **Tests/checks passed:** none run (planning only).
- **Remaining:** implementation phases 0–6 above, plus updating `docs/google-drive-storage-plan.md` and `docs/scheduled-jobs.md`; regenerate `graphify-out` after implementation.
