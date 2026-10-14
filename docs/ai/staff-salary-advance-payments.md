# Task record: Advance payments for staff salaries

## Task: Add advance payments to Staff Salary

- **Task ID:** staff-salary-advance-payments
- **Created:** 2026-10-13
- **Last updated:** 2026-10-13
- **Status:** COMPLETED
- **Outcome:** Let payroll staff record salary advances — money paid to an employee ahead of the payroll period — in an employee-level ledger on the Staff Salary page, and remove an advance when the employee pays it back.

### 1. Requirements and acceptance criteria

- [x] AC1 Staff salary shows an **employee-level advances ledger** (employee, date, amount, remarks, reference) alongside the existing Salaries and Salary-months tabs.
- [x] AC2 A user with payroll rights can **record an advance** for a selectable employee (amount, date, remarks).
- [x] AC3 A user can **delete an advance**, covering the case where the employee returns/repays the money.
- [x] AC4 Recording an advance creates a matching **payroll Expense**; deleting the advance removes that Expense, so cash-out and reports stay correct.
- [x] AC5 Advances are **base-currency** payroll disbursements, consistent with salaries (which are base-currency only).
- [x] AC6 Advance recovery is **manual only** — no automatic deduction is attached to any salary; staff recover it through the existing salary deduction editor.
- [x] AC7 Backend validation rejects a non-selectable employee, non-positive amount, and a future date.
- [x] AC8 All new UI is RTL/LTR-correct, light/dark-safe, responsive, and fully translated (en, fa-AF, ps-AF).

### 2. Scope and non-goals

- In scope: new `employee_advances` table + model + resource + controller + service, routes/permissions, typed frontend API contract, Staff Salary "Advances" tab with add/delete dialogs, i18n, feature tests.
- Out of scope: auto-deduction from payslips; linking an advance to a specific salary; the payment-requests/loans approval flow; payslip print changes; multi-currency advances; editing an advance in place (correct by delete + re-add).

### 3. Project findings

- **Partial payments already exist.** `SalaryPayment` (ledger) + `SalaryExpenseSynchronizer` compute `amount_due`, `amount_paid_total`, and `payment_status` (`open` / `partially_paid` / `paid`). `POST/DELETE /salaries/{salary}/payments` and `initial_payment` on salary creation drive it. Each disbursement creates a matching `Expense`.
- **Deductions are separate.** `SalaryDeduction` holds manual rows plus one locked automatic attendance-proration row (`is_automatic`). This is the intended manual recovery path for an advance.
- **Salaries are base-currency only.** `SalaryController::validateSalaryRequest` forces the base currency (`business.salary_base_currency_required`).
- **Payroll expenses use type `Salaries`** — a system `finance_types` row (`FinanceTypeSeeder`). `Expense` auto-computes `base_amount = amount * quantity / rate`.
- **Existing flows that are NOT this feature:** `PendingTransaction` ("payment-requests / loans", see `LoanReceiptService`) is a separate approval-based request system.
- **Document numbers:** `App\Models\Concerns\HasDocumentNumber` issues `PREFIX-YYYY-######` and is immutable. `SalaryPayment` uses prefix `SPY`.
- **Audit:** `App\Support\AuditRegistry::resources()` must register a model for `Auditable` change logging.
- **Permissions:** `database/seeders/PermissionSeeder.php` defines `view_salaries` / `create_salaries` / `edit_salaries` / `delete_salaries` (category `human_resources`).
- Relevant paths: `app/Http/Controllers/Salary*`, `app/Models/Salary*`, `app/Services/SalaryExpenseSynchronizer.php`, `app/Http/Resources/SalaryResource.php`, `resources/ts/api/salary.ts`, `resources/ts/api/incomes.ts` (contract reference), `resources/ts/pages/staff_salary.vue`, `database/migrations/0001_04_01_000004_create_hr_tables.php`, `routes/api.php`.

### 4. Graph and impact review

- Methods actually used: repository search (`code_search`), targeted `grep` over `app`, `routes`, `database`, `resources/ts`, and direct file inspection. No dedicated graph tool run (none available for a deep call graph here); fallback documented.
- Relationships examined: salary creation/permission/lookup, `SalaryExpenseSynchronizer` ↔ `Expense`, `SalaryPayment` migration shape, base-currency enforcement, `HasDocumentNumber`/`Auditable`/`AuditRegistry`, `Expense` type vocabulary, Staff Salary page structure and tabs, i18n namespace `pages.payroll.staffSalary.staffSalary`.
- Downstream effects: expenses/reports gain advance rows (intended, mirrors payroll disbursements); `AuditRegistry` gains a resource key; no existing tests depend on a new table.
- Limitations: no browser/visual run; migrations validated on fresh SQLite only.

### 5. Clarifications, decisions, and assumptions

- **User decisions (asked before planning):**
  - An "advance payment" = a **salary advance**, i.e. money paid to staff ahead of the payroll period, tracked per employee.
  - Recovery is **manual only**, but the system must support **deleting an advance** so the staff can handle "employee got an advance and paid it back".
  - Advances are recorded/visible as an **employee-level ledger on the Staff Salary page**.
- **Assumptions and rationale (low risk, recorded):**
  - Advances are **base-currency only**, matching the salary rule. Rationale: payroll already rejects secondary currencies; an advance is a payroll prepayment.
  - Each advance creates a matching `Expense` with type `Salaries` and `employee_id` set, and deleting the advance deletes that expense. Rationale: mirrors `SalaryExpenseSynchronizer`, keeps cash-flow/report totals honest; fully reversible (soft deletes + expense removal).
  - **Reuse the salary permissions** (`view_salaries` / `create_salaries` / `delete_salaries`) instead of introducing new permission rows, so custom roles keep working without extra seeding. Reversible if separate permissions are later wanted.
  - Give advances a document number (`ADV-YYYY-######`) for reference/audit, mirroring `SalaryPayment`.
  - No auto-deduction or advance↔salary link (per the user's manual-recovery choice).

### 6. Implementation plan

- [x] Step 1 — Migration `2026_10_13_000003_create_employee_advances_table`: `advance_number`, `employee_id`, `currency_id`, `base_currency_id`, currency snapshot (`currency_code`, `currency_symbol`, `rate`, `base_amount`), `amount`, `advance_date`, `remarks`, `expense_id` (FK `expenses` nullOnDelete), `recorded_by`, timestamps, soft deletes.
- [x] Step 2 — Model `app/Models/EmployeeAdvance.php` (Auditable + HasDocumentNumber prefix `ADV` + SoftDeletes) with `employee`/`currency`/`baseCurrency`/`expense`/`recorder` relations; register `employee_advances` in `AuditRegistry`.
- [x] Step 3 — `EmployeeAdvanceResource` exposing camelCase fields (advanceNumber, employeeName, currency, amount, rate, baseAmount, date, remarks, expenseId, recordedBy).
- [x] Step 4 — `app/Services/EmployeeAdvanceService.php`: `record()` (create advance + linked `Expense`) and `remove()` (delete expense + advance), in one transaction.
- [x] Step 5 — `EmployeeAdvanceController` (`index` filter by employee/search, `store`, `destroy`); routes in `routes/api.php` under the salary permission middleware.
- [x] Step 6 — Frontend contract `resources/ts/api/employeeAdvances.ts` (typed, unwraps `response.data.data`).
- [x] Step 7 — Staff Salary page: third "Advances" tab with ledger table, "Add advance" dialog, and delete confirmation; wire to the API.
- [x] Step 8 — i18n keys under `pages.payroll.staffSalary.staffSalary` in en / fa-AF / ps-AF.
- [x] Step 9 — `tests/Feature/EmployeeAdvanceTest.php` feature coverage; run focused + full suites and `npm run typecheck`; `npm run build:icons`.
- [x] Step 10 — Update this record's progress/validation and completion summary.

### 7. Validation plan and results

- [x] `php artisan test --filter=EmployeeAdvanceTest` — **5 passed (34 assertions)**, exit 0.
- [x] Full `php artisan test` (SQLite in-memory) — **379 passed (3533 assertions)**, exit 0 (was 374 before this task).
- [x] `npm run typecheck` — exit 0.
- [x] `npm run build:icons` — 294 references, no unavailable-token warning.
- Results: all green (commands run with `set -o pipefail`).
- Checks not run and why: MySQL migration application and browser/visual acceptance are out of this environment.

### 8. Risks and mitigations

- Advances add `Expense` rows and therefore change expense/report totals — mitigation: type `Salaries`, `employee_id` set, deletion removes the expense; documented.
- Wrong currency rate on the expense — mitigation: base-currency only, `rate = 1`, snapshot stored on the advance.
- Deleting an advance could leave an orphan expense — mitigation: delete the linked expense inside the same transaction (mirrors `SalaryController::destroy`).
- Migration only tested on SQLite — mitigation: plain `Schema::create` with standard column types; note that the owner must run it on MySQL.
- New icon token not bundled — mitigation: run `npm run build:icons` and confirm no unavailable-token warning.

### 9. Progress log

- 2026-10-13 — DISCOVERY: reviewed salary partial-payment implementation (`SalaryPayment`, `SalaryExpenseSynchronizer`, `SalaryController`/`SalaryPaymentController`), deductions, `Expense` conventions, `HasDocumentNumber`/`Auditable`/`AuditRegistry`, permission seeder, Staff Salary page + i18n, and the separate payment-requests/loans flow.
- 2026-10-13 — CLARIFIED: user defined an advance as a pre-period salary advance, per-employee, manual recovery with delete-to-refund, recorded on the Staff Salary page.
- 2026-10-13 — PLAN: full plan saved before implementation (this file).
- 2026-10-13 — PROGRESS: added migration `2026_10_13_000003_create_employee_advances_table`, model `EmployeeAdvance` (+ `AuditRegistry` entry), `EmployeeAdvanceResource`, `EmployeeAdvanceService` (advance + linked payroll `Expense`; delete removes it), `EmployeeAdvanceController`, and routes `GET/POST/DELETE /api/employee-advances`.
- 2026-10-13 — PROGRESS: added typed frontend contract `resources/ts/api/employeeAdvances.ts`, a third "Advances" tab on the Staff Salary page (ledger, employee filter, search, total, add + delete dialogs), and en/fa-AF/ps-AF translation keys. No new icon tokens were required.
- 2026-10-13 — VALIDATION: `EmployeeAdvanceTest` 5 passed; full suite 379 passed; `npm run typecheck` and `npm run build:icons` clean.

### 10. Completion summary

- **Delivered:**
  - `employee_advances` table + `EmployeeAdvance` model (audited, `ADV-YYYY-######` reference), resource, service, controller, and REST routes (`GET/POST/DELETE /api/employee-advances`) gated by the existing salary permissions.
  - Each advance creates a matching payroll `Expense` (`type` Salaries, `employee_id` set); deleting the advance deletes that expense.
  - Typed API contract and a new **Advances** tab on the Staff Salary page: employee-level ledger with employee filter, search, running total, and add/delete dialogs.
  - Translations in en / fa-AF / ps-AF.
- **Acceptance criteria:** AC1–AC8 satisfied.
- **Tests/checks passed:** `EmployeeAdvanceTest` = 5 passed (34 assertions); full `php artisan test` = 379 passed (3533 assertions); `npm run typecheck` exit 0; `npm run build:icons` clean (294 references).
- **Remaining limitations / follow-up work:**
  - The migration was validated on fresh SQLite only; apply `2026_10_13_000003` to the MySQL working database.
  - Advances are base-currency only (matching salaries) and are recovered manually via a salary deduction — there is no automatic advance↔salary link by design.
  - Editing an advance in place is not supported; correct it by deleting and re-adding.
  - No browser/visual acceptance run.
