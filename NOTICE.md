# NOTICE — Services → Contracts merge (session 2026-09-23)

> Status: **additive step only. Nothing was dropped, deleted, or renamed.**
> Every change below ADDS tables / columns / relations / optional API fields.
> All legacy paths (`customer_service`, singular `service_id`, deadlines,
> end-dates, durations, renewals, educational slugs) still work exactly as before.

## 0. Target workflow (the big picture)

Agreed flow for a service sale, end to end:

1. **Catalogue** — user creates a service in `Options → Services`
   (`options/services.vue`, `view/manage_services` permissions). Services are
   mere catalogue rows (type/category/fee/currency + `active|inactive` status
   for fee tracking). **Inactive services are not registerable anywhere**:
   template service-select, contract services-multiselect, and the nav/filter
   list all show active-only.
2. **Template** — user goes to contract templates and creates a template for
   that service: contract type = `customer` + selects **the** service
   (single-select, required for customer templates). Employment templates
   select a **position** (single-select) instead. `other` templates may use
   either/both (placeholders cover both). Template types are ONLY
   `employment | customer | other`.
3. **Documents** — user goes to Settings → Documents and creates a cover page
   + letterhead for it. (**Out of scope for this merge; untouched.**)
4. **Sale** — user navigates to `Contracts → Service Contracts` in global
   navigation and creates a contract for that service for a customer
   (customer contract = ≥1 services multiselect, mandatory). Inactive services
   do not show up in this list.
5. **Print** — on the contract printing page, when service(s) are selected,
   the template picker displays only templates related to those services
   (`GET /contract-templates?service_ids[]=…`). Signatory name is pre-filled
   with the employee's user name and **not editable** for normal employees;
   **editable** for super-admins (already implemented in
   `ContractController@validatedData` + `ContractForm.vue`; preserved as-is).

Placeholders per type: customer → services + customer details + contract
details (+ related); employment → employee details + contract details (+
related); other → both.

## 1. What was done

- **Migration `2026_09_23_000001_merge_customer_service_into_contracts_additive`**
  (additive: creates tables/columns only):
  - `contract_service` pivot — one row per contracted service (see mapping §2).
  - `contract_service_employee` pivot — delivery-commission employees per line
    (successor of `employee_customer_service_commissions`).
  - `contracts.sold_by_employee_id`, `contracts.marketer_id` (header-level
    acquisition attribution; lines may override per service).
- **New model `app/Models/ContractService.php`** (extends `Pivot`,
  `incrementing = true`, `Auditable`, `SoftDeletes`) with relations to
  contract, service, applicant, package, sold-by, marketer, outsourcing
  company, currency, country/education-level/language, `deliveryEmployees`,
  `bonusSource`.
- **`app/Models/Contract.php`**: `STATUSES` gains `completed`; fillable gains
  `sold_by_employee_id`, `marketer_id`; new `contractServices()` (hasMany),
  `services()` (BelongsToMany via `contract_service` with full pivot columns),
  `soldByEmployee()`, `marketerEmployee()`.
- **`app/Http/Controllers/ContractController.php`** (all optional/back-compat):
  - `service_ids[]` (active services only) + `service_lines[]` (per-line
    fee/currency/applicant/package/attribution/outsourcing/case-dimensions/
    notes/`employee_ids`) accepted on store/update.
  - Legacy `service_id` is mirrored as one pivot line, so old single-service
    contracts appear in the new structure automatically.
  - `index` filters `service_ids[]`/`service_id` (legacy link OR pivot line)
    and `without_service` (no link AND no lines). Shows `contractServices`
    eager loads.
  - `update` re-syncs lines + refreshes `snapshot.entity.services` when the
    new keys are present. `renew` copies lines + delivery percentages.
  - Also removed one **dead broken block** in `validatedData` that referenced
    an undefined `$query` (it fatal-errored whenever `service_id` was posted
    to store/update; index filtering now lives in `index` where it belongs).
- **`app/Http/Resources/ContractResource.php`**: new `service_ids`,
  `services` (ContractServiceResource), `sold_by_employee_id`, `marketer_id`.
  New **`app/Http/Resources/ContractServiceResource.php`**.
- **`ContractTemplateController@index`**: additive `service_ids[]`,
  `contract_type_id`, `include_generic` filters for the create/print-page
  template picker (linked templates + generic `service_id IS NULL` templates).
- **Placeholders** (additive): `PlaceholderRegistry` gains `services.*`
  (`names/count/total_fee/table`) for `customer`+`other`, and singular
  `service.*` is now also allowed for `customer`/`other` (falls back to first
  service). `PlaceholderResolver` renders `services.table` (raw HTML like
  `employee.identity`); `ContractController` feeds all selected services into
  the resolver context.
- **`app/Support/AuditRegistry.php`**: registered `contract_services`
  (label = contract · service). Old `customer_services` entry untouched.
- **Tests**: new `tests/Feature/ContractServiceLinesTest.php` (3 passing):
  multiselect + per-customer fee + snapshot content; inactive-service 422;
  template-by-service filter (+`include_generic=0` strict mode).
- Verified: migration applies cleanly; `ServiceContractNavigationTest`,
  `ContractTemplateUpdateTest`, `ContractServiceLinesTest` all pass on MySQL.

## 2. Column mapping — `customer_service` → contract system

| `customer_service` column | New home | Note |
|---|---|---|
| `customer_id` | `contracts.customer_id` | existed already |
| `applicant_id` | `contracts.applicant_id` (existed) + `contract_service.applicant_id` (per line) | |
| `service_id` | `contract_service.service_id` (+ legacy `contracts.service_id` mirror) | multiselect now |
| `customer_package_id` | `contract_service.customer_package_id` | bundle-issued lines |
| `employee_id` (delivery) | `contract_service_employee` rows | replaces `employee_customer_service_commissions` |
| `sold_by_employee_id` | `contracts.sold_by_employee_id` + `contract_service.sold_by_employee_id` | |
| `marketer_id` | `contracts.marketer_id` + `contract_service.marketer_id` | |
| `outsourcing_company_id` / `outsourcing_percentage` | `contract_service.*` (same names) | |
| `fee` | `contract_service.fee` (NULL = catalogue fee) | **per-customer fee preserved** |
| `currency_id` | `contract_service.currency_id` | |
| `status` | `contracts.status` (contract-level; `completed` added) | no per-line status |
| `notes` | `contract_service.notes` | |
| `country_id` / `education_level_id` / `language_id` | `contract_service.*` (same names) | |
| `sold_at` | `contracts.start_date` | no separate sale date |
| `deadline` | **dropped by design** (deadline concept removed — §4) | |
| `legacy_source_type` / `legacy_source_id` | **omitted by design** (literal legacy columns) | |

## 3. Files / logic NOT needed anymore (do NOT delete yet — removal batch)

- `app/Models/CustomerService.php` — superseded by `ContractService`.
- `app/Http/Controllers/CustomerServiceController.php` + routes
  `POST/PUT/DELETE /customers/{customer}/services` (`routes/api.php:420-422`)
  + `manage_customer_services` permission (`PermissionSeeder`,
  `RolePermissionSeeder` manager/staff grants, `resources/ts/utils/apiAuthorization.ts:124-127`).
- `app/Http/Resources/CustomerServiceResource.php`.
- `app/Services/CustomerServices/CustomerServiceLifecycleService.php` +
  `app/Console/Commands/ProcessCustomerServiceLifecycle.php` + schedule
  `customer-services:process-lifecycle` (`routes/console.php:27`) — deadline
  engine, dead once `deadline` goes.
- `app/Services/Contracts/ContractLifecycleService.php` +
  `app/Console/Commands/ProcessContractLifecycle.php` + schedule
  `contracts:process-lifecycle` (`routes/console.php:25`) — 100% `end_date`
  driven (expiry + 0/1/3/7/14/30-day reminders); meaningless without end dates.
- `ContractController@renew` + `POST /contracts/{id}/renew` route — renewal is
  date arithmetic (`duration_months` + `end_date`); remove with the date fields.
- `Customer::services()` / `Customer::customerServices()`,
  `Service::customers()` / `Service::customerServices()`,
  `Employee::deliveryCustomerServices()`, `Company::outsourcedCustomerServices()`,
  `CustomerPackage::services?`, `Applicant::services?` relations pointing at
  `CustomerService`.
- Invoice chain: `InvoiceService.customerService()` + `customer_service_id`
  fillable, `InvoiceController@commissionCandidates` + `store` assignment
  lookup, `CommissionAccrualService` (`customer_service_id` write),
  `OutsourcingAccrualService`, `CommissionAccrual::customerService()`,
  `OutsourcingAccrual::customerService()`, `InvoiceResource` pivot passthrough.
- `EducationalBonusSourceService::sourceTypes()['service']` +
  `CustomerService` branch in `createFor`/`syncForService`.
- `PackageIssuanceService` `CustomerService::create` loop → create one
  customer contract + `contract_service` lines instead.
- `EducationalServiceController` (scholarships/test-offers workspace over
  `CustomerService`) → rewire to `ContractService` lines of customer contracts
  (or retire the page in favor of `contracts?service_ids=`).
- `ProjectController` available-ids query over `CustomerService`.
- `DashboardController` (`activeServices`, `serviceDeadlines`,
  `service_deadlines_30_days`, expiring-contracts blocks),
  `ReportService` `customer_services` section (deadline/fee/currency columns),
  `EducationalDashboardController`, `EducationalReportController`
  (`end_date` column, `services_sold` source_type count).
- Frontend attach flows: `resources/ts/api/customer.ts`
  (`attachServices/updateService/detachService`, `CustomerService` types),
  `components/forms/CustomerServiceFormDialog.vue`,
  `all_customers.vue` service tab, `applicant-case.vue` service dialog,
  `innvoice.vue` `customer_service_id` lines, `api/invoice.ts`,
  `educational-services.vue` + `api/educationalServices.ts` (if controller is
  rewired, keep; otherwise retire).
- `resources/ts/pages/audits.vue` `customer_services` key/description (backend
  `AuditRegistry` entry too).
- `lang/{en,fa,ps}` + `resources/ts/plugins/i18n/locales/*` keys for
  deadlines/durations/renewals/expiry/customer-service CRUD.
- Tests hitting the old paths: `CustomerServiceAssignmentTest`,
  `CustomerServiceAttachmentTest`, `ProjectServiceAssignmentTest`,
  `CommissionLifecycleTest`, `BusinessLogicConsistencyTest`,
  `PaymentDetailManagementTest`, `UnifiedPackageServiceTest`,
  `EducationalContractIntegrityTest` (educational slugs + CustomerService),
  `ContractLifecycleTest` (renewals), `OptionalServiceDurationTest`,
  `ServiceContractNavigationTest` (rewrite to `service_ids`).

## 4. Date/duration/deadline removal list (exact locations, still present)

- `contracts.end_date`, `contracts.duration_months` (+ `Contract` fillable/casts,
  `ContractController` calc/validation/`normalizeLocalizedInteger`,
  `ContractResource`, `ContractForm.vue` duration/end-date inputs + watcher,
  `contracts.ts` api types, `utils/apiValidation.ts`, `contract_list.vue`
  renewal dialog + `ContractExpiryDate` component, `PlaceholderRegistry`
  `contract.end_date` + resolver map).
- `customer_service.deadline` (+ `CustomerService` casts, `CustomerServiceController`
  validation, `CustomerServiceFormDialog.vue`, `CustomerResource` pivot,
  `CustomerServiceResource`, `AuditRegistry` candidates).
- `services.duration` catalogue column (+ `Service` fillable, `ServiceController`
  validation, `ServiceResource?`, `ServiceForm.vue`, `options/services.vue`
  duration column, `OptionalServiceDurationTest`, migration
  `2026_09_22_000000_make_service_duration_optional_and_link_portal_leads`).
- `Contract::STATUSES`: shrink to
  `draft, active, completed, terminated, cancelled` (drop `expired`, `renewed`).
- Educational slugs `educational-service/scholarship-service/test-service/
  visa-service`: `PlaceholderRegistry::{EDUCATIONAL_,SYSTEM_}SLUGS`,
  `ContractController::index/SUPPORTED_SLUGS/resolveContextEntity`,
  `ContractTemplateController`, `policies.vue:isServiceType`,
  `applicant-case.vue:openContractDialog`, `ContractLifecycleService::contractUrl`,
  seed migration `2026_09_18_000005_seed_educational_contract_types`.

## 5. Still to wire (next batches, all non-destructive until §3 executes)

1. `ContractTemplateController@validated`: `service_id` required iff type =
   `customer`; `position_id` required iff `employment` (currently only
   employment-position is enforced).
2. `ContractController@validatedData`: `service_ids` required/min:1 iff
   customer; prohibited iff employment; optional iff other (currently all
   optional). Delivery-capability assertion for `employee_ids` (mirror
   `CustomerServiceController`).
3. Invoice/commission/outsourcing/package/project/dashboard/report/educational
   rewires listed in §3.
4. Frontend: `ContractForm.vue` (services multiselect, position single-select,
   template-by-service filter, drop duration/end-date, signatory rule already
   correct), `contract_list.vue` (services column, service filter limited to
   active services, print-dialog template picker, drop renewal/expiry UI),
   `contract/policies.vue` (single service select for customer, required),
   nav (`contracts.ts` — Service Contracts entry already exists), delete
   attach UIs, permission strings, locale keys.
5. Data migration (one-way, at removal time): each `customer_service` row → one
   customer contract + one `contract_service` line per §2; re-link
   `invoice_service`/`commission_accruals`/`outsourcing_accruals` to
   `contract_service_id`; backfill `contracts.service_id` → lines; then drop
   `customer_service`, `employee_customer_service_commissions`,
   `contracts.service_id/end_date/duration_months`, `services.duration`,
   `*_accruals.customer_service_id`, `invoice_service.customer_service_id`.
