# Migration Merge Plan — `database/migrations` Consolidation

> **Status:** Implemented & verified · **Started:** 2026-09-27 · **Completed:** 2026-09-28 · **Progress log:** §10

## 1. Goal

Collapse the **88 historical migrations** (2023-01-01 → 2026-09-27) into a small set of
**section-organized create-schema migrations** whose end state is **schema-identical**
to what `migrate:fresh` produces today. Where migrations disagreed, the **latest migration
wins**; the result is verified against the **Eloquent models**, which are the contract the
application actually runs on.

## 2. Verified end-state rules (later-wins + model confirmation)

| Area | Evolution | Final state (model-verified) |
|---|---|---|
| Customers → Applicants | `customers` created (2023), grew, merged additively + cutover (2026-09-26) | **No `customers` table.** `applicants` owns party + case data; `parent_applicant_id` groups sibling cases; `invoices`/`projects` point at `applicant_id`; `crm_interactions` morphs to `Applicant`. |
| Customer services → Contracts | `customer_service` created (2026-08-10), merged then dropped (2026-09-23) | **No `customer_service` table.** Lines live in `contract_service` (one per contract by convention). `employee_customer_service_commissions` gone → `contract_service_employee`. |
| Educational sales | `scholarships` / `test_offers` created, re-pointed `customer_id` → `applicant_id` | Tables **survive** (`Scholarship`, `TestOffer`) with `applicant_id`, case dimensions, `package_type_id`. |
| Stocks → Assets | renamed | `assets` + `asset_allocations`; no `assets.staff_id`. |
| Paid time off → Leaves | renamed + fractional accounting | Table **`leaves`** (`duration_days`, `paid_days`, `paid_override`, `approval_note`); model `PaidTimeOff` (`$table='leaves'`). |
| Attendance | statuses renamed, fractional leave days | enum `present, absent, paid-leave, unpaid-leave` + three decimal(3,1) leave-day columns. |
| Daily reports | `attendance_id` unique FK created 2026-08-13, **never dropped** | **KEPT**: `daily_reports.attendance_id` unique + restrict (model has the relation). |
| Employee files | `ID_file`/`assure_file`/`tax_number` removed; doc fields added | None of the removed exist; `employees.document_{path,name,mime,size}` exist. |
| Invoices | `class_type` added then removed | No `class_type`. Money decimal(20,6). `applicant_id` NOT NULL restrict. |
| Contract types | Company → Other; educational types folded into `customer` | System types: `employment`, `customer`, `other` (+ `description` column). |
| Offices | multi-office → singleton | `offices.is_singleton` bool unique. |
| Marketers | directory → employees | `marketers.{user_id, employee_id}` (both unique); `positions.system_key` = `marketer`. |
| Departments | free-text → table + supervisors | `departments` table; `employees.department_id`, `supervisor_id`; legacy `employees.department` text kept. |
| Roles | flat → hierarchy | `roles.level` unsigned int default 0. |
| Contract templates | static → versioned | `contract_template_versions` incl. `name`, `current_scope_key` unique; `contract_templates.{description,is_active,created_by,service_id}`; `contracts.contract_template_version_id`, `contract_country_id`, `contract_language_id`. |
| Settings | palette replaced, letterhead safe area, mail, attendance toggle | Final list per `Setting::$fillable`; `automatic_attendance_enabled` default **false** (harden migration wins); no `tertiary_color`; `paid_leave_days_per_year`. |
| Finance precision | money expanded | decimal(20,6) invoice/income amounts + line totals; `base_currency_id` FKs + snapshot columns everywhere. |
| Commissions / Outsourcing | ledger built up | `commission_accruals` (nullable source FKs, `entry_type`, `commission_role`, unique per source+employee+role), `outsourcing_*` as authored; `employee_service_commissions` retained. |
| Salary payments | single → ledger | `salaries.{amount_due, amount_paid_total, payment_status}` + `salary_payments`. |
| Income currency | legacy `string('currency',3)` dropped when currency_id introduced | `incomes` has **no** `currency` column; `expenses.currency` (3) **kept** (model fillable has it). |
| Bonus sources | sources consolidated | `educational_bonus_sources`: `employee_id`, `applicant_id`, morph source, amounts, status, rule_source — **no `customer_id`**. |
| Audits | reviewed → audited | `audit_logs.{subject_type, subject_label, audited_by, audited_at}`; `audit_resource_settings`. |
| Assets storage | task attachments moved private | `task_attachments.disk` default **`local`**. |

### 2.1 Morph-map reality check

`educational_bonus_sources.source_type` stores plain FQCNs (no morph map registered).
Insertable morphs on a fresh install: `App\Models\ContractService` and `App\Models\Contract`
(their tables pre-exist `bonus_sources`). Historical literals (`Scholarship`, `TestOffer`,
`CustomerService`) are dead mid-history values — not resurrected.

### 2.2 Bug fix in the merge (documented deviation)

Historical `merge_customers_into_applicants_additive` added
`educational_bonus_sources.customer_id` FK→`customers`, never dropped by the cutover
(table omitted from its DUAL_KEY_TABLES list). On a fresh serialized run that FK is
impossible (points at a dropped table) — and the model never had it. **Merged history
omits the column.** Schema is otherwise identical.

## 3. Target file set (16 migrations)

| # | File | Section | Tables created |
|---|---|---|---|
| 1-4 | `0000_*` / `0001_0*_*` (unchanged) | Core infra | `currencies`; `users`/`password_reset_tokens`/`sessions`; `cache`/`cache_locks`; `jobs`/`job_batches`/`failed_jobs` |
| 5 | `0001_03_01_000003_create_core_tables.php` | Core | `settings`, `permissions`, `roles`, `user_roles`, `role_permissions`, `personal_access_tokens`, `offices` |
| 6 | `0001_04_01_000004_create_hr_tables.php` | HR | `pending_transactions`, `departments`, `positions`, `marketers`, `employees`, `position_user`, `attendances`, `leaves`, `salaries`, `salary_deductions`, `salary_payments`, `assets`, `asset_allocations`, `worthy_employees`†, `salary_worthy_employee` |
| 7 | `0001_05_01_000005_create_crm_tables.php` | CRM | `tags`, `visitors`, `leads`, `crm_interactions` |
| 8 | `0001_06_01_000006_create_reference_tables.php` | Reference | `countries`, `education_levels`, `languages`, `package_types`, `universities`, `semesters`, `applicant_stages` (seeded with final exit/appeal edges) |
| 9 | `0001_07_01_000007_create_applicant_tables.php` | Educational visa | `applicants`, `applicant_stage_histories`, `applicant_universities`, `applicant_attachments` |
| 10 | `0001_08_01_000008_create_contract_tables.php` | Contracts | `contract_types`, `companies`, `contract_policies`, `contract_templates`†, `contract_policy_contract_template`, `contract_template_versions`, `contracts`†, `contract_service`†, `contract_service_employee`, `contract_document_designs`† |
| 11 | `0001_09_01_000009_create_finance_tables.php` | Finance | `services`, `payment_details`, `invoices`†, `incomes`, `expenses`, `invoice_service`, `invoice_service_commission_recipients`, `commission_settlements`, `commission_accruals`, `outsourcing_settlements`, `outsourcing_accruals`, `currency_rate_histories` |
| 12 | `0001_10_01_000010_create_educational_commerce_tables.php` | Educational commerce | `scholarships`, `test_offers`, `customer_packages`, `package_service`, `case_wins`, `educational_bonus_sources` |
| 13 | `0001_11_01_000011_create_kanban_tables.php` | Task management | `boards`, `board_user`, `tasks`, `task_user`, `task_tag`, `task_dependencies`, `subtasks`, `comments`, `task_attachments`, `projects`, `project_service`, `task_service` |
| 14 | `0001_12_01_000012_create_reporting_tables.php` | Reporting | `daily_reports`, `monthly_attendance_summaries`, `daily_analytics`, `monthly_analytics`, `automation_runs`, `bonus_rule_sets` (+ default rules row) |
| 15 | `0001_13_01_000013_create_support_tables.php` | Support | `support_tickets`, `support_ticket_attachments` |
| 16 | `0001_14_01_000014_create_notification_and_audit_tables.php` | Notification & Audit | `notifications`, `notification_user`, `recurring_events`, `recurring_event_occurrences`, `audit_logs`, `audit_resource_settings`, `account_deactivation_requests` |

`†` = column present but **FK added in the completion pass** (§6) because the target table
is created later (cycles/section boundaries).

### 3.1 Why this order (hard FK edges)

- `departments` before `employees` (employees.department_id)
- `pending_transactions` before `salaries` (salaries.pending_transaction_id)
- `attendances` before `daily_reports` (unique FK)
- `applicant_stages` + `applicants` before `leads` (CRM morph + FK), `contracts`, `invoices`, `tasks`
- Reference tables (`countries`, `languages`, …) before contracts/educational tables
- `services` before `contract_templates`/`invoice_service` — FK deferred to §6 pass since finance is after contracts
- `tasks` before `worthy_employees.source_task_id` — FK deferred to §6 pass
- `projects` before `expenses.project_id` — FK deferred to §6 pass
- `expenses` before `commission_settlements.expense_id` / `outsourcing_settlements.expense_id`
- `customer_packages` before `contract_service.customer_package_id` — FK deferred to §6 pass

## 4. Implementation rules

1. **No data migrations.** All backfills/renames/permission-grant loops are dropped.
   Fresh DB = final schema. RBAC seeding stays in seeders.
2. **Enums/decimals at final precision**, no `.change()` calls anywhere.
3. **SoftDeletes only where final state has them.**
4. **Deferred-FK pattern:** a table created before its FK target gets the column as
   `unsignedBigInteger` + index; the completion pass (§6) adds `constrained()` FKs.
5. Old migration files are **deleted** (git), replaced by the section files.
6. `down()` drops created tables in reverse FK order.

## 5. Completion pass — `0001_15_01_000015_add_cross_section_foreign_keys.php`

Eleven FKs are deferred to the completion pass (targets live in later sections):

| Table.column | → references |
|---|---|
| `departments.manager_employee_id` | `employees` (nullOnDelete) |
| `pending_transactions.applicant_id` | `applicants` (nullOnDelete) |
| `salary_payments.expense_id` | `expenses` (nullOnDelete) |
| `worthy_employees.source_task_id` | `tasks` (nullOnDelete) |
| `leads.applicant_id` | `applicants` (nullOnDelete) |
| `contract_templates.service_id` | `services` (nullOnDelete) |
| `contract_document_designs.service_id` | `services` (cascadeOnDelete) |
| `contract_service.customer_package_id` | `customer_packages` (nullOnDelete) |
| `contract_service.service_id` | `services` (no action) |
| `contracts.source_service_id` | `services` (nullOnDelete) |
| `expenses.project_id` | `projects` (nullOnDelete) |

All columns are created (with their indexes, under their historical names) in their section files; this pass only attaches the constraints. `down()` drops them in reverse order.

## 6. Deployment note (existing environments)

- **New/fresh:** run the new migrations — end schema identical to pre-merge.
- **Existing dev DB:** `php artisan migrate:fresh --seed` after pulling this change.
- **Production (if any):** do not re-run migrations; applied history stays. Squashing a live
  `migrations` table is an operator task, out of scope here.

## 7. Verification plan

1. `php artisan migrate:fresh --seed` on MySQL (`laravel_test`) — clean run.
2. Fresh migrate on SQLite with foreign keys on (proves no FK cycles).
3. `php artisan test` — full suite.
4. Spot-check hottest tables: `applicants`, `contracts`, `contract_service`, `invoices`, `salaries`, `attendances`, `settings`.
5. FK inventory dump vs §5 table.

## 8. Rollout checklist

- [x] Read all 85 historical migrations + schema-sensitive models (the "88" figure was an overcount; the migrations table recorded 85)
- [x] Write plan doc
- [x] Delete old migrations (85 files; 4 infra files kept)
- [x] Implement 12 section migrations + 1 FK-completion pass
- [x] MySQL `migrate:fresh --seed` green
- [x] Schema strict-diff vs golden snapshot: **0 lines**
- [~] Full test suite: **zero regressions** — all 37 remaining failures/errors reproduce identically on the old-migration baseline (pre-existing WIP-branch issues)
- [x] Progress log updated

Note: the SQLite fresh-migrate check was skipped — the environment's DB is the
Windows-native MariaDB reachable via the WSL NAT gateway; FK-cycle safety was
proven by the serialized MySQL run plus the strict schema diff.

## 9. Detailed column specs

Authoritative sources used per table (checked against models):
`Setting::$fillable`, `Employee::$fillable`, `Applicant::$fillable`, `Contract::$fillable`,
`ContractService::$fillable`, `Invoice(Income/Expense/Salary)::$fillable`+casts,
`InvoiceService::$fillable`, `CommissionAccrual::$fillable`, `WorthyEmployee::$fillable`,
`Attendance::$fillable`, `PaidTimeOff::$fillable` (`leaves`), `Task::$fillable`,
`PendingTransaction::$fillable`, `Package(=package_types)::$fillable`,
`CustomerPackage::$fillable`, `EducationalBonusSource::$fillable`, `ContractTemplate(Version)::$fillable`,
`Office`, `Position(system_key)`, `Marketer`, `Scholarship`/`TestOffer`, `Project`, `DailyReport`,
`ContractDocumentDesign`, `Department`, plus the original create-migrations for table shapes
the models don't constrain (analytics, automation, notifications, audit, support, recurring).

## 10. Progress log

- 2026-09-27 — Plan written after reading all 88 migrations + Eloquent models (Applicant,
  Contract, ContractService, Service, Employee, Invoice, InvoiceService, CommissionAccrual,
  WorthyEmployee, Salary, Setting, Task, Project, Visitor, Marketer, Scholarship, TestOffer,
  Currency, PaidTimeOff, Attendance, Office, Package, PackageType, PendingTransaction,
  CustomerPackage, Income, Expense, DailyReport, ContractTemplate, ContractTemplateVersion,
  EducationalBonusSource). Layout settled: 4 infra + 12 section files + 1 FK-completion pass.
- 2026-09-28 — **Ground truth rebuilt.** Discovered the runtime DB is the Windows-native
  MariaDB 10.4 (not Sail): reachable from WSL via `DB_HOST=172.25.32.1` (NAT gateway).
  `migrate:fresh` on it produced the golden snapshot saved to
  `storage/schema-snapshots/post-fresh-laravel_test.sql` (103 tables) + full FK inventory.
  Every section migration was transcribed from that dump, preserving historical index and
  constraint names (e.g. `paid_time_offs_*` on `leaves`, `salaries_employee_month_unique`).
- 2026-09-28 — **Files implemented:** core(7), HR(15), CRM(4), reference(7+17-stage seed),
  applicant(4), contract(10+3 system types), finance(13), educational commerce(6), kanban(12),
  reporting(6+default rules row), support(2), notification/audit(7+AuditRegistry rows),
  FK pass(11). Corrections made during validation: `position_user` initially forgotten;
  pivot `contract_policy_contract_template` had to be created after `contract_templates`;
  `constrained()` on `*_currency_id` must name `currencies` explicitly;
  `pending_transactions.posted_record` must be `nullableMorphs()`;
  `task_attachments.disk` default is `public` (the `local` default belongs to
  `support_ticket_attachments`); `contract_templates.position_id` index keeps its
  historical name with the FK added after.
- 2026-09-28 — **Seeder fixes** (all minimal): `PermissionSeeder` gained the four
  outsourcing permissions the old migrations used to insert; `EducationalSeeder` now creates
  one demo attachment per case before issuing packages (satisfies the
  `business.applicant_document_required` guard); `TaskDependencySeeder` switched
  `attach` → `syncWithoutDetaching` for idempotent re-seeds.
- 2026-09-28 — **Verification:** strict schema diff vs golden snapshot = **0 lines**
  (all columns/types/defaults/index names/FK actions); `migrate:fresh --seed` green
  (48 seeders); seed-row counts match golden (permissions 242, audit settings 53,
  stages 17, contract types 3, bonus rule sets 1). Test suite run in domain chunks with
  A/B against the old migrations: every one of the 37 failures/errors reproduces
  identically on the baseline — **zero regressions from the merge**. (`AuditManagementTest`
  passes 7/7 on the rebuilt audit tables.) The 37 pre-existing failures share one root
  family (`business.applicant_document_required` guard vs. tests that don't seed
  attachments) plus dashboard/authorization WIP; they belong to the branch's own work,
  not this consolidation.
