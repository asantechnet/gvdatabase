# Educational Visa Agency Implementation Notes

## Delivery rules

The implementation follows the plan in order: analyse → extend/refactor → migrate → integrate. Existing working tables and records will not be deleted or repurposed. Every new relationship is additive, nullable where historical data does not yet have a value, protected by server-side validation, and covered by a reversible migration where practical.

## Phase 0 — existing application analysis

### Architecture

| Area | Confirmed implementation | Reuse decision |
| --- | --- | --- |
| Frontend | Vue 3.5, TypeScript, Vite, Vuetify, Pinia, Axios, vue-i18n, CASL. Static routes live in `resources/ts/plugins/1.router/routes.ts`; API contracts live in `resources/ts/api`. | Add typed API contracts, static routes, localized pages, and permission-aware controls there. |
| Backend | Laravel 12 / PHP 8.2 REST API. Controllers validate requests; Eloquent models carry relationships and lifecycle hooks; domain services contain non-trivial business logic. | Use models, API resources, policies, services, transactions, and request validation—never frontend-only business rules. |
| Database | MySQL (`laravel_test` local configuration), with Laravel migrations, soft deletes, foreign keys, and database-backed session/cache/queue. | Add migrations only; retain data; validate with `migrate --pretend` before applying. |
| Authentication | Laravel Sanctum, protected API group requires `auth:sanctum`, `abilities:access`, and active account status. | New endpoints remain in this protected group. |
| Authorization | Configurable roles: `super_admin`, `admin`, `manager`, `staff`, `viewer`. Permission middleware calls `User::hasPermission`; frontend route guards use permission metadata. | Add visa permissions to the existing permission catalog and enforce both route middleware and object-aware policies. |
| Audit/history | `Auditable` trait writes immutable audit records; contracts/invoices already snapshot legal/financial data. | Use it for mutable business entities; write explicit append-only stage/approval histories for workflow changes. |
| Seed strategy | `DatabaseSeeder` is demo/local data in dependency order. | Add targeted reference seeders; do not use the full demo seeder in production. |

### Existing domain inventory

| Existing entity/module | Current responsibility | New educational-visa behavior | Required change | Impacted modules |
| --- | --- | --- | --- | --- |
| Visitor, Lead, Customer, `CrmInteraction` | Prospect/customer CRM, conversion, notes, marketer attribution | A customer becomes the commercial parent of one or more applicant cases | `applicants.customer_id`; retain visitor/lead conversion | CRM, applicant case, search, reports |
| Employee, User, Position, Office | Staff identity, roles, attendance, salary, workday | Responsible case owner and seller/provider attribution | `applicants.responsible_employee_id`; service/test/scholarship attribution | HR, applicant case, commission, permissions |
| Service, `CustomerService`, Invoice, `InvoiceService` | Catalog, customer-assigned services, invoice line snapshots | Educational services/packages attach to a customer and optionally an applicant | Extend `customer_service` with applicant/service metadata only after its lifecycle constraints are reviewed | Customer, applicant, invoice, commissions |
| ContractType, ContractTemplate, ContractPolicy, Contract | Existing type/template contract rendering, content sanitization, placeholder resolution, contract snapshots, lifecycle reminders | Contract types and logical templates gain language-specific immutable versions; contracts reference the exact version used | Add version table and `contracts.contract_template_version_id`; preserve existing `content` snapshot | Contracts, applicant case, finance eligibility, audit, printing |
| Task, Board, Project, Subtask, Comment, attachments | Kanban operations, assigned work, project/customer linkage, audit and edit-window support | Tasks are operational work for a case | `tasks.applicant_id`; case detail queries pending work using existing task access rules | Tasks, applicant case, dashboard |
| Income, Expense, Salary, PaymentDetail, Currency | Official financial records, currency conversion/snapshots, salary-to-expense synchronization | Employee-originated financial requests require approval before creating official entries | Pending transaction + posting service; optional applicant/visitor/employee/source links on official records | Finance, applicant case, salary, reports, audit |
| CommissionAccrual, CommissionSettlement, bonus rules | Existing commission accrues from paid invoice service lines, with traceable recipients and settlement protection | Generic bonus sources must include contracts, scholarship services, and test offers | Extend the existing ledger, do not create isolated hard-coded bonus columns | Services, tests, scholarship, finance, employee reports |
| DashboardController, ReportService | Permission-aware authoritative metrics and CSV/print reports | Applicant pipeline, approval, sales and visa outcomes become query-derived metrics with drill-down | Add report sections/query scopes after operational data exists | Dashboard, reports, navigation |

### Foreign-key and lifecycle map relevant to this work

| Relationship | Current behavior | Visa-domain rule |
| --- | --- | --- |
| Customer → contracts/services/invoices/incomes/expenses/tasks | Existing relationships use foreign keys and soft deletes; customer deletion is blocked while related operational data exists | Applicant references customer with `restrictOnDelete`; never remove a customer solely because its case has ended. |
| Employee → user/position/office/salary/service attribution | Employee may be soft deleted but historical relations remain available via `withTrashed` where required | Responsible employee and seller/provider must remain traceable after staff departure. |
| Contract → template/type/customer/employee/company | Contract has rendered content and `snapshot_data`; only draft contracts can be deleted, issued history remains | Add template-version foreign key but preserve rendered snapshot forever; cancelled/expired contracts remain auditable. |
| Customer service → service/customer/employee/marketer | Existing lifecycle auto-pauses expired services; invoice lines snapshot source values and recipients | Applicant linkage must not break existing deadline/commission lifecycle. |
| Invoice → customer/currency/services → income → commission accrual | Income drives the existing invoice-service commission ledger | New sale types must use a compatible source ledger or a shared source model, never bypass financial traceability. |
| Task → board/customer/users/services/comments/subtasks/attachments | Existing policies enforce assignment and the 60-minute edit rule | Case task linkage must retain all task policy checks and attachments. |
| Income/Expense/Salary → currency/customer/company/project/recorder | Official accounts currently accept direct creation under finance permissions | Pending transaction is the only new employee workflow path; posting is idempotent and records the source transaction. |

### Reusable implementation patterns

- API contracts unwrap the Laravel resource double-`data` response in `resources/ts/api`, not in components.
- Controllers use request validation, Eloquent relationships, and transaction boundaries for state changes.
- Currency conversion and historical amounts use the existing `CurrencyConversionService` and currency snapshots.
- Contract HTML uses the existing sanitizer and placeholder resolver; document printing uses `printDocument` and feature-scoped print CSS.
- Customer service lifecycle, contract lifecycle, salary expense synchronization, commission accruals, and reports already run through dedicated services and should be extended rather than duplicated.
- Soft delete and audit trail are the default; business records with legal/financial history are cancelled, voided, rejected, or archived rather than deleted.

### Applicant baseline finding

No applicant module existed in the repository before this work—the plan’s listed legacy applicant screens are not present in the static router, navigation, frontend API contracts, or backend domain code. The educational applicant model must therefore be built as a real connected domain module, not a renamed legacy list.

## Completion log

- [x] Phase 0: inspected project/frontend/backend/database/auth/RBAC; CRM, contracts, finance, HR, task, dashboard/report, audit, service/commission modules; migration/seeding strategy; foreign-key/lifecycle relationships; controllers/services; static router/navigation and API-contract patterns. The dependency map above is the implementation baseline.
- [x] Phase 1: master-data and contract-type foundation complete: protected CRUD API, typed frontend management page, country-linked universities, static navigation/route, auditable models, reversible migration, and permission catalog implemented.
- [x] Phase 2: logical contract templates now support per-language, immutable numbered versions with controlled activation, audit fields, protected APIs, typed frontend API support, and tested historical activation behavior.
- [x] Phase 3: contract generation resolves the active language-specific template version, persists its ID, renders from that immutable content, and stores version metadata in the contract snapshot. End-to-end API coverage passes.
- [ ] Phase 4: applicant/case model, stage workflow, history, and connected case detail.
- [ ] Phase 5: scholarship services, test offers, attribution, and bonus ledger integration.
- [ ] Phase 6: pending transactions, approval, edit policy, and safe posting to finance.
- [ ] Phase 7: permissions, audit coverage, navigation, pipeline/case UX, reports, dashboards, migration, regression and acceptance testing.

## Pre-audit implementation status

An earlier implementation pass added a reversible, unapplied educational foundation migration plus associated backend models/controllers. It is real backend logic, not a mockup, but it is **not marked Phase 1 complete** until it is reconciled against this Phase 0 dependency map and extended with the required RBAC, frontend API contracts/pages, and test coverage. No migration has been applied to the working database.

## Current verification

- [x] PHP syntax checks pass for the master-data controller, contract-type controller, and educational migration.
- [x] `EducationalMasterDataTest` passes: country creation and country-linked university creation are verified through authenticated API calls.
- [!] Full frontend type-check currently fails on pre-existing unrelated typing issues in `FullDetailsButton`, customer services, commissions, companies, income, outsourcing, and roles pages. The newly added educational master-data files are not reported by the checker.

## Decisions that require business confirmation before the relevant phase

- “Scholarship” may mean an agency service/package or an actual third-party award. The implementation will distinguish these terms and initially use a service-oriented record with space for future award details; it will not silently treat them as the same thing.
- The exact roles permitted to approve/post financial transactions and activate legal template versions must be finalized during the permissions phase. The schema will retain the flexibility to assign those capabilities through the existing permission system.
