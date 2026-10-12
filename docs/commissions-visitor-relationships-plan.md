# Commissions, Visitor Counselor & Applicant Relationships — Plan & Progress

**Created:** 2026-10-12
**Owner:** Engineering
**Status:** In progress
**Source request:** `docs/Educational New Comments++.md`

This is the single source of truth for the three requested workstreams. The
**Progress tracker** (§7) and **Progress log** (§8) are updated after every
sub-task.

---

## 0. Requested changes (verbatim from the comments)

1. **Visitor form** — "if a user with role of counselor tried to add a visitor
   the visitor's counselor becomes auto-filled with that counselor and
   immutable."
2. **Applicants relationship** — "Applicants relationship need to be in the
   applications page as well."
3. **Commissions** —
   - "if a contract has the name of the employee in it and it reached completed
     stage then the associated employee gets a percentage of that service."
   - "if an employee was marked as commission-based it needs a commission list
     that is per each service. these both need to be editable in staff
     creation/edit form for each employee."
   - "when a contract reached completed stage the system needs to check if the
     contract's associated employees are commission-based or not. if they are,
     depending on the service, employee's commission for that service, service
     fee, etc. the system needs to account for those employees' commission."
   - "bring the commissions page back to life from previous commits.
     settlements, accruals are correctly in place in this page but I'm not sure
     if backend logic that was in place for commissions still applies to new
     architecture. please have a look at that as well and only reuse it if it's
     still viable for the new structure."

---

## 1. Diagnosis (verified in code)

| # | Surface | State found |
|---|---------|-------------|
| 1 | `resources/ts/pages/visitor.vue` | Add/Edit dialog renders a `VAutocomplete` for `counselor_employee_id` with no awareness of the signed-in user. `service-contracts.vue` already runs the counselor-detection pattern (`useCookie('userData')`, roles `counselor`/`senior_counselor`, `employee.id`), so the pattern exists to reuse. `VisitorController::store` accepts any valid counselor id. |
| 2 | `resources/ts/pages/applicants.vue` | The applicants workspace table has no relationship affordance. `visitor.vue` and `all_customers.vue` already embed `CrmRelationshipDetails` (`type="applicant"` supported by the component + `crmRelationships.ts`). |
| 3 | Commissions | The whole commission ledger was **deleted** in commit `f0f8bad` ("Restructure 2.0"): migrations `2026_10_08_000003_drop_commission_and_outsourcing` + `…000004_retire_commission_and_outsourcing_permissions`, models `CommissionAccrual`/`CommissionSettlement`, `CommissionController`, `CommissionAccrualService`, resources, `resources/ts/api/commission.ts`, `resources/ts/pages/commissions.vue`, `tests/Feature/CommissionLifecycleTest.php`. |

### What the old commission backend was coupled to

- Accruals were keyed on **invoices**: `income_id`, `invoice_id`,
  `invoice_service_id`, `contract_service_id`, `invoice_service_commission_recipients`.
- `CommissionAccrualService::syncForIncome()` read `InvoiceService` lines and
  their pivot recipients.
- `employees.employment_type` (salaried/commission) and
  `employee_service_commissions.service_id` referenced the now retired
  `services` table.

### Viability assessment (the requester asked for this explicitly)

**Not viable as-is:** everything invoice/income-driven. Invoices,
`invoice_service`, `invoice_service_commission_recipients` and the `services`
table are gone; `AGENTS.md` forbids restoring them. The trigger is now a
**completed contract**, not an income, and per-service rates key on the
`BuiltInService` string, not a `service_id`.

**Viable and reused:** the **settlement lifecycle** and its accounting:
`CommissionSettlement` model + `HasDocumentNumber` (`COM` prefix), the
draft → approved → paid → cancelled control flow, the separation-of-duties
guards (creator ≠ approver ≠ payer), and the `pay()` step that books an
`Expense` through `CurrencyConversionService`. The accrual table shape is
reused but re-pointed at `contract_id` + `applicant_contract_id`.

---

## 2. Design decisions

| Topic | Decision |
|-------|----------|
| Employee "commission-based" flag | Re-add a boolean `employees.is_commission_based` (not the old `employment_type` enum) — the comment only needs a marker. |
| Per-service rates | Recreate `employee_service_commissions` keyed on `(employee_id, service)` string + `percentage`. |
| Accrual trigger | A completed contract: `CommissionService::syncForContract()` runs when a contract's status becomes `completed`. |
| Associated employees | `contracts.employee_id`, `contracts.sold_by_employee_id`, and each `applicant_contract.sold_by_employee_id` (deduplicated). Only commission-based employees with a rate for the contract's service accrue. |
| Commission base | Per line: `fee − discount` converted to the base currency (`payableTotal() / currency_rate`), then `× percentage / 100`. Uses the line's `currency_rate`, falling back to `CurrencyConversionService`/currency rate. |
| Settlement payment | Books an `expense` (type `Commissions`) exactly like the old `pay()` did; that part is still viable. |
| Permissions | Re-add `view_commissions`, `manage_commission_settlements`, `approve_commissions`, `pay_commissions`; grant to finance roles + super admin bypass. |
| Visitor counselor | Frontend auto-fills + disables for `counselor`/`senior_counselor`; backend forces the value on store for those roles (defence in depth). |
| Applicants relationship | Add `CrmRelationshipDetails type="applicant"` to the applicants workspace rows, mirroring `all_customers.vue`. |

---

## 3. Workstream A — Visitor counselor auto-fill & immutability

- [ ] **A1.** `visitor.vue`: read `useCookie('userData')`; if the signed-in user
  is a counselor, prefill `visitorData.counselor_employee_id` with their
  `employee.id`, make the field `disabled`/`readonly`, and show a hint. Applied
  to the **add** dialog.
- [ ] **A2.** `VisitorController::store`: if the actor's roles include
  `counselor`/`senior_counselor`, force `counselor_employee_id` to the actor's
  `employee_id` (ignore/override the payload).
- [ ] **A3.** i18n key for the locked-field hint (en/fa-AF/ps-AF).

## 4. Workstream B — Applicant relationships on the applications page

- [ ] **B1.** Import + render `CrmRelationshipDetails type="applicant"` in the
  `applicants.vue` row actions.
- [ ] **B2.** Verify the applicant endpoint payload (`CrmRelationshipDetails`
  fetches `GET /applicants/{id}` and the CRM interaction routes) is sufficient.

## 5. Workstream C — Commissions

### C1. Schema

- [ ] `employees.is_commission_based` boolean default false.
- [ ] `employee_service_commissions` (employee_id, service, percentage, unique).
- [ ] `commission_accruals` (contract_id, applicant_contract_id, employee_id,
  service, base_currency_id, settlement FK, entry_type, earned_at,
  source/eligible/commission base amounts, percentage, status, recorded_by,
  adjustment_reason, timestamps, soft deletes).
- [ ] `commission_settlements` (as before: number, employee, currency, period,
  gross, status, approvals, payment, expense FK).

### C2. Backend

- [ ] Models `CommissionAccrual`, `CommissionSettlement`,
  `EmployeeServiceCommission`; relations on `Employee`, `Contract`,
  `ApplicantContract`, `Expense`.
- [ ] `EmployeeServiceCommission` sync in `EmployeeController` store/update;
  validation + `EmployeeResource` output.
- [ ] `CommissionService` — `syncForContract(Contract)`, `voidForContract`,
  `assertContractMutable`.
- [ ] Trigger: `Contract` model `updated` hook → when status flips to
  `completed`, run `syncForContract`; when it leaves `completed`/is cancelled,
  void unsettled accruals.
- [ ] `CommissionController` (accruals, summary, adjustments, settlements,
  show, store, approve, pay, cancel) adapted from the old controller.
- [ ] Routes in `api.php` + permission catalog + role grants.
- [ ] `CommissionAccrualResource`, `CommissionSettlementResource`.

### C3. Frontend

- [ ] `resources/ts/api/commission.ts`.
- [ ] `resources/ts/pages/commissions.vue` (restored from `c4c9743`, adapted:
  no invoices, service labels from `BuiltInService`).
- [ ] Route + navigation entry (Finance/HR section) gated by `view_commissions`.
- [ ] `staff_list.vue` + `api/employee.ts`: commission-based toggle and the
  per-service percentage list.

### C4. Validation

- [ ] `npm run typecheck`, `npm run i18n:check` + `npm run i18n:test`,
  `npm run build:icons`.
- [ ] PHPUnit: a commission lifecycle test (completed contract accrues, non
  commission-based employees skipped, settlement approve/pay books an expense,
  separation-of-duties guards).
- [ ] `vendor/bin/pint` on changed PHP.

---

## 6. Risks / follow-ups

- Contract **completion** is currently only reachable through contract update
  flows that require a draft; the trigger is wired on the model so any future
  completion path accrues correctly, but the reachable completion surface
  should be confirmed with the requester.
- Commission is computed on the **contracted** fee (`fee − discount`), not on
  money actually collected. If the business wants it on realised income, switch
  the accrual source to `applicant_contract.incomes`; the settlement layer is
  unchanged.

---

## 7. Progress tracker

Legend: `[ ]` not started · `[~]` in progress · `[x]` done · `[!]` blocked

| ID | Sub-task | Status | Notes |
|----|----------|--------|-------|
| A1 | Visitor form counselor auto-fill + immutable | [x] | `visitor.vue`: `openAddDialog()` seeds `counselor_employee_id` from `userData.employee.id` for `counselor`/`senior_counselor` and disables the field |
| A2 | Backend forces counselor on store for counselor role | [x] | `VisitorController::lockedCounselorId()` overrides the submitted counselor on `store()`; defence in depth |
| A3 | i18n hint key | [x] | `agencyStaff.counselorLockedToYou` added to en/fa-AF/ps-AF |
| B1 | Applicant relationship on applications page | [x] | `applicants.vue` renders `CrmRelationshipDetails type="applicant"` per row (click propagation stopped) |
| B2 | Verify CRM applicant payload | [x] | Component already used on `all_customers.vue`; `CrmInteractionTest` green |
| C1 | Commission schema migrations | [x] | `2026_10_12_000001_restore_commission_ledger` recreates accruals/settlements/per-service rates and adds `employees.is_commission_based` |
| C2 | Commission models + service + controller + routes | [x] | `CommissionService`, `CommissionController`, resources, routes, permissions, `Contract` completion hook, `EmployeeController` sync |
| C3 | Commission frontend (page, api, nav, staff form) | [x] | `api/commission.ts`, `pages/commissions.vue`, `/commissions` route + Finance nav, `StaffCommissionFields.vue` + `staff_list.vue` |
| C4 | Validation (typecheck, i18n, tests) | [x] | typecheck ✓, i18n check/test ✓, icon generation ✓ (289 refs, no warnings), PHPUnit employee/visitor/contract/commission/compliance ✓, pint ✓ |

---

## 8. Progress log

- **2026-10-12** — Recon complete. Confirmed the old commission ledger was
  deleted in `f0f8bad`; inspected the pre-delete controller/service/models and
  schema snapshots. Assessed viability: the invoice/income-sourced accrual sync
  is not compatible with the new architecture (invoices/services removed), but
  the settlement lifecycle (draft→approved→paid, `Expense` booking via
  `CurrencyConversionService`, separation of duties) is reusable. Plan written.
- **2026-10-12** — A1–A3 done. Visitor add dialog locks the counselor to the
  signed-in counselor (role `counselor`/`senior_counselor`); the API force-fills
  the same value on store so a direct call cannot attribute the visitor to
  somebody else. Counselor/senior-counselor roles were granted visitor
  view/create/edit so the flow is actually reachable. New regression test
  `AgencyWorkflowCoherenceTest::test_a_counselor_creating_a_visitor_is_locked_to_their_own_employee`.
- **2026-10-12** — B1–B2 done. The applicants workspace now carries the CRM
  relationship viewer per row, matching the visitors and all-customers pages.
- **2026-10-12** — C1–C2 done. `2026_10_12_000001_restore_commission_ledger`
  rebuilds `commission_accruals` (keyed on `contract_id` + `applicant_contract_id`
  + `employee_id`), `commission_settlements` and `employee_service_commissions`
  (service string), and adds `employees.is_commission_based`. `CommissionService`
  accrues on contract completion (associated employees = contract employee, sold-by
  and each line's sold-by; only commission-based employees with a service rate), the
  `Contract` model hook triggers it (and voids unsettled accruals when a contract
  leaves `completed`), and `CommissionController` restores the accrual/summary/
  adjustment/settlement/approve/pay/cancel API with the reused `Expense` payout path.
  `EmployeeController` syncs the per-service rates and the flag; permissions and
  role grants restored. `FinanceRetirementMigrationTest` / `VisaFirmComplianceTest`
  updated to record that the commission ledger is intentionally restored while
  invoicing/companies/outsourcing stay retired.
- **2026-10-12** — C3 done. Restored `resources/ts/api/commission.ts` and
  `resources/ts/pages/commissions.vue` (invoice columns replaced by contract number;
  a reconcile action rebuilds accruals for completed contracts), added the
  `/commissions` route and Finance navigation entry gated by `view_commissions`, and
  added the commission-based toggle + per-service rate editor to the staff create/edit
  form via `StaffCommissionFields.vue`.
- **2026-10-12** — C4 done. `npm run typecheck`, `npm run i18n:check` (2979 used,
  no missing) and `npm run i18n:test` pass; `npm run build:icons` picks up the two
  new icons with no unavailable warnings. `php artisan test` targeted suites all green
  (`CommissionLifecycleTest` 5, `VisaFirmComplianceTest` 3, `AgencyWorkflowCoherenceTest`
  9, `FinanceRetirementMigrationTest` 2, plus the full `Contract` — 94 — and
  `Employee` — 23 — filters). `vendor/bin/pint` clean.

### Open follow-ups

- Contract **completion** is reached through the model hook and the commissions
  reconcile action; there is no dedicated “Mark completed” button on the contract
  pages yet. Confirm whether one should be added.
- Commission is computed on the contracted fee (`fee − discount`), not realised
  income. Switch the accrual source if the business prefers cash-basis commission.
