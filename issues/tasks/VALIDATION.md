# Validation of the 2026-10-29 review issues

## Purpose

This record checks every filed issue in `issues/review/issues/` against the current repository and the live demo database before any fix task was written. The rule applied was: an issue is only carried into a fix task when the current code, schema or data reproduces the reported behaviour. Claims that no longer reproduce are recorded as already fixed, not re-planned.

## Environment and method

- **Repository:** current `main` working tree at the start of this session.
- **Application:** Laravel 12.70.1; MySQL demo database reachable through `php artisan tinker` (read-only queries).
- **Method:** source trace from route to controller, request, service, resource and Vue page; schema inspection with `SHOW COLUMNS`; row counts and value checks with read-only SQL; cross-check against the screenshots and evidence index in `issues/review/evidence/`.
- **Not performed:** destructive transitions, payment approvals, payroll generation, 2FA enabling, printing and file uploads. Those flows were assessed from code and existing evidence; the review report itself also excluded them.
- **Data drift:** the demo database has moved since the report. It now has 9 users and 14 salaries totalling 1,070,000 AFN (all still `payment_status = open`, paid total 0). Contract numbers are already compact `CTR-26-001`. These changes do not invalidate the structural defects; they are noted where relevant.

## Verdict summary

| Issue | Verdict | Reproduced evidence | Fix task |
|---|---|---|---|
| 01 Case stage stored twice and pages disagree | **Confirmed** | DB: applicant 1 `current_stage_id = 6`; both scholarship lines `current_stage_id = 1`. `ApplicantController::changeStage` writes only the applicant; `ApplicantContractStageController` mirrors a line onto the applicant. `pipeline()` builds line cards plus unlined applicant cards while totals count applicants. | Task-001 |
| 02 Document and close gates not enforced | **Confirmed** | `ApplicantDocumentReadiness` has no call sites. `changeStage` validates `note` as nullable. Case page states a document is required. | Task-001 |
| 03 Unpaid salaries labelled posted; payroll excluded from expenses | **Confirmed** | `EducationalDashboardController::financial()` sums `salaries.total_amount` by `payment_date` with no `payment_status` filter. UI label `educational.postedSalaries` = "Salaries posted". DB: 14 open salaries, 0 paid. `EducationalReportController::financialReport()` hard-codes every salary row as `status = posted`. | Task-004 |
| 04 Payment status hidden on case and contract screens | **Confirmed** | DB has paid / partially_paid / unpaid lines. Applications column renders `item.payment_status`, but the API loads `applicantContracts` only for holders of `view_applicant_financials`; otherwise the column is all dashes. Contract register has no payment column at all. | Task-001 (case list), Task-003 (contract register) |
| 05 Follow-up list not filtered | **Not reproducible — already fixed** | Current `visitor.vue` sends `follow_up: isFollowUpList ? 1 : 0`; `VisitorController::index()` filters when the parameter is present; the page has a distinct follow-up title, icon, empty state and row actions. The DB still has 0 flagged visitors, so the list must correctly be empty. | Task-005 keeps a regression criterion only |
| 06 Contract attention cannot be diagnosed | **Confirmed** | `EducationalDashboardController` counts active contracts with `end_date <= now()+30 days`; DB shows CTR-26-015 ending 2026-11-18. `contract_list.vue` headers include only start date and status; no end date, days remaining or expiring filter. | Task-003, Task-004 (dashboard link) |
| 07 Hidden columns/actions and unnamed icon buttons | **Confirmed** | Register/visitor/staff/payroll tables still rely on horizontal scrolling; several row `VBtn icon` controls have tooltips but no `aria-label` (a tooltip does not name a button); login password eye has no `aria-label`; `ResponsiveDataTableServer` has no sticky key/action configuration. | Task-013 (shared primitives), page tasks |
| 08 Duplicate pagination on Payment requests | **Confirmed** | `pending-transactions.vue` renders `ResponsiveDataTable` (which owns a footer) and then a second `VPagination` + per-page select + result count in `VCardText`. | Task-011 |
| 09 Weak 2FA setup and weak seeded password | **Confirmed at code level** | `beginTwoFactor` returns a raw secret and `otpauth://` URL using `config('app.name')`; no QR generation, no recovery codes; security page renders the secret in a code block. Sessions card only signs out all other devices. Seeder path for the demo administrator was not found in `database/seeders`; the reported live password is treated as an environment/seed policy defect. | Task-009 |
| 10 Super admin missing from Users; Email column mislabelled | **Confirmed** | `UserController::index()` uses `excludePrimaryRecord()`; `User::primaryRecordId()` is 1, the super admin. `users.vue` headers map `common.fields.email` to the `email_verified` key. DB has 9 users; the list therefore returns 8. | Task-008 |
| 11 Audit trail unreadable; timeline omits actor | **Confirmed** | `audits.vue::fieldLabel()` falls back to `pages.audits.otherInformation` for unknown columns, so stage ids appear as "Other information: 5 → 6". `applicant-case.vue::stageSubtitle()` prints time + note only; `changed_by` is not shown. Notification locale string is generic: "{resource} {record} was changed." | Task-010 (audits/notifications), Task-001 (timeline) |
| 12 Currency/date/number formatting inconsistent | **Confirmed** | `staff_list.vue` hard-codes `${{ Number(...).toFixed(2) }}`; payroll amounts append a symbol; bonuses combine symbol + code; contract list prints raw ISO dates; dashboard filter uses `mm/dd/yyyy`; pages each format locally. | Task-012 (core), screen tasks |
| 13 Mobile dashboard overflow; application cards lose context | **Confirmed** | Evidence screenshots; Applicants mobile card mode is configured with only a title key and action key, so stage/service/assignee/status are absent. Dashboard KPI strip uses fixed auto columns. | Task-004 (dashboard), Task-001 (mobile cards), Task-013 (table primitives) |
| 14 Asset prices lack currency and valuation data | **Confirmed** | `SHOW COLUMNS FROM assets`: id, serial_number, item, quantity, price, model, condition, office_id, timestamps, deleted_at. No currency, acquisition date, total value or depreciation. `AssetController` validates only price/quantity. | Task-007 |
| 15 Visitor service is free text; percentage undefined | **Confirmed** | `VisitorController` validates `service` as `required|string|max:255`; the Add Visitor control is a plain text field; no controlled vocabulary or migration. | Task-005 |
| 16 Placeholder copy and inconsistent headers | **Confirmed** | `pages.payrolls.description` is literally "Description". The shared details dialog eyebrow is generated as `{kind} · {count} useful details`, producing "Contract · 18 useful details". Several pages lack `ErpPageHeader`; counts are not pluralized. | Task-014, plus copy criteria in screen tasks |
| 17 Primary and extension contracts indistinguishable | **Confirmed** | `contract_list.vue` headers omit `contract_role`, `parent_contract_id`, `end_date` and payment state. DB has four extensions with future `start_date = 2026-11-28`, all `status = active`. | Task-003 |
| 18 Service contract creation blocked by `start_date` | **Confirmed** | `resources/ts/utils/apiValidation.ts` requires `start_date` for all four service-contract POST routes; `StoreServiceContractRequest` marks it `prohibited`; `service-contracts.vue::save()` never sends it; `axios.ts` rejects in the request interceptor before the network call. | Task-002 |

## Claims that changed or were already fixed

| Claim | Current state | Action |
|---|---|---|
| Follow-up list shows everyone and ignores the flag | Fixed in current code: the page sends the boolean filter and renders a separate follow-up surface. Keep a regression criterion so it cannot regress. | No rebuild; Task-005 criterion |
| Employee contract numbers are `CTR-2026-000001` | Already `CTR-26-001` via `Contract::documentNumberFormat()` = `%s-%s-%03d` with a two-digit year. Do not change. | No task |
| Timesheet has six KPIs and a Date/Role attendance table | Current page has exactly four KPIs in a horizontal strip and the ledger columns are Employee, Office, Status, In Time, Out Time, Worked, Overtime, Action. Skeleton and empty state are present. | No task; verify in Task-006 regression |
| Leave request details show no field titles | Current shared `FullDetailsButton` renders section titles and per-field labels, and `leave_requests.vue` supplies explicit `detailFields`. Recent commit `c796fb6` refined this. | No task; verify in Task-006 regression |
| Leave requests page shows the sideways-scroll hint | No `scrollHint` usage remains on `leave_requests.vue`. | No task |
| Payroll/dashboard values are 580,000 AFN | Demo data now has 14 open salaries totalling 1,070,000 AFN, still 0 paid. The label defect is unchanged. | Task-004 uses live totals |
| Users database has 8 users | Now 9; primary record 1 is still excluded from the list. | Task-008 |

## Confirmed non-issues

- Payment-request self-approval is blocked server-side (`PendingTransactionController`); no issue was filed and none is opened.
- Logout, back-button and deep-link protection behave correctly; no issue was filed.
- Sequential stage transition rules exist and reject arbitrary jumps; the defect is the missing gate and the competing stage fields, not the transition graph itself.

## Residual risks and limitations

- The demo database is not a reproducible fixture. Tasks must create their own fixtures instead of relying on case #1 or CTR-26-015 remaining in a particular state.
- The review did not exercise permissions for non-super-admin roles. Task acceptance criteria include permission boundaries where the reported screen is permission-scoped, but a role-matrix test is not part of this set.
- Printing, payroll generation, 2FA confirmation and payment posting remain unexecuted; their tasks require explicit non-production fixtures.
- UI-only claims about exact 1280 px clipping are taken from the retained screenshots plus current table configuration. Fixes must be verified at 390, 768 and 1280 px in both LTR and RTL.

## HR and finance issue validation (2026-10-31)

### Method and limits

- **Sources:** `hr-issues/README.md`, `hr-issues/issues/HR-001..009`, `fin-issues/README.md`, `fin-issues/issues/FIN-001..010`, plus the retained evidence PNGs.
- **Method:** source trace from Vue page to API contract, controller and resource; read-only SQL against the local demo database; cross-check of the response envelope (`response.data.data`) and the i18n catalogs. No write, approval, grant or payroll generation was executed.
- **Limits:** UI-only assertions (toast count, dropdown contents) were confirmed from the rendered source and the shared notifier contract, not by clicking through a browser. The exact literal "Required." in FIN-008 was not reproduced; the structural validation gap was.
- **Data drift:** the demo database still contains the reported zero bonus row and the silently revalued receipt, so those findings reproduce. Other demo rows may change; tasks create their own fixtures.

### Verdicts

| Issue | Verdict | Evidence | Task |
|---|---|---|---|
| HR-001 Bonus can be granted at zero | **Confirmed; one diagnosis wrong** | DB row 1 is `is_granted = 1`, `selected_amount = 0`, `granted_amount = 0`. The page sends only `isGranted`; `WorthyEmployeeController` accepts `selectedAmount` `min:0` and copies it. The resource already exposes `selectedAmount`, so the claimed field-name mismatch is not the cause. | Task-017 |
| HR-002 Department delete shows generic duplicate toast | **Confirmed** | `DepartmentController::destroy` aborts with `business.department_has_employees`; that code has no catalog entry, and both the notifier and the page surface the failure. | Task-019 (page), Task-018 (copy/toast) |
| HR-003 Duplicate toast on department manager save | **Confirmed** | The controller rejects a non-supervisory manager and a rank violation; the form has no inline `manager_id` mapping, so the notifier falls back to a toast while the page also reports failure. | Task-019, Task-018 |
| HR-004 Manager dropdown lists ineligible employees | **Confirmed** | `departments.vue` uses `EmployeeApi.getOptions()`, which calls `/employees` with no supervisory/account filter; the server guard rejects the selection only at save time. | Task-019 |
| HR-005 Payroll generate toast has empty counts | **Confirmed** | `SalaryController::generate` returns `created`/`skipped`, but the API helper leaves the response nested and the page reads `result.created`/`result.skipped` at the top level, producing `undefined`. | Task-006 |
| HR-006 Commission trigger is contract vs case | **Confirmed** | `CommissionService::syncForApplicant` gates on `isCaseCompleted()`; `syncForContract` delegates to it. The page copy names the case, the HR requirement names the completed contract. | Task-016 |
| HR-007 Timesheet save has no toast feedback | **Confirmed** | The attendance-save handler updates state but emits no success or error toast; failures rely on the global notifier only. | Task-006 |
| HR-008 Staff form keeps stale inline errors | **Confirmed at code level** | The shared `formErrors` store is global and is not cleared when the staff dialog opens for another record; changing position also writes the salary without a confirmation step. Exact browser reproduction was not performed. | Task-006 |
| HR-009 Leave/off-day edge cases have no tests | **Confirmed gap** | The two existing HR test files cover a single-day grant, allowance split, off-day materialization, payroll proration and advance recovery. `OffDayController::validated()` has no overlap check and `OffDayService` has no overlap guard. | Task-020 |
| FIN-001 Self-approval buttons visible and duplicate toasts | **Confirmed** | `PendingTransactionController` rejects self-approval with `business.pending_transaction_self_approval`; the page still shows approve/reject to any `approve_pending_transactions` holder, including the requester, and both the page and the notifier toast the failure. | Task-011, Task-018 |
| FIN-002 Issued receipt silently revalued | **Confirmed** | DB still shows `RCPT-2026-000001` at 6000 × 3. `IncomeController::update` accepts amount/quantity with no reason or confirmation; the page saves without a warning or success toast. | Task-015 |
| FIN-003 Income mutations have no toast | **Confirmed** | Create, edit and delete handlers have no `toast.success`; failures are only globally notified. | Task-015 |
| FIN-004 Review toast prints raw status | **Confirmed** | `translateBackendValue('under_review')` looks up `common.statuses.underReview`; the key is absent from `en.json`, so the fallback is the raw token. | Task-011, Task-018 |
| FIN-005 Submit toast reuses the button label | **Confirmed** | `save()` toasts `common.actions.submit` ("Submit") or `common.actions.update`; neither states that a request was saved or submitted. | Task-011 |
| FIN-006 Unlabeled icon actions | **Confirmed** | Income print/edit/delete buttons have no `aria-label` (attachment and details do). Asset row actions likewise lack accessible names. | Task-015 (income), Task-007 (asset) |
| FIN-007 Two pagination controls | **Confirmed; duplicate of review issue 08** | `pending-transactions.vue` renders the table footer plus a second `VPagination`, per-page select and count. | Task-011 (existing) |
| FIN-008 Asset form shows bare "Required." errors | **Partially confirmed** | `asset.vue` defines no field-level rules, so required-field feedback is generic and not tied to the field. The exact literal "Required." was not reproduced; the structural gap is real. | Task-007 |
| FIN-009 Receipt detail shows raw ids | **Confirmed** | `IncomeResource` exposes `base_currency_id` and `applicant_contract_id`; `FullDetailsButton` does not hide the `base_currency` or `applicantcontract` stems and adds the "{count} useful details" eyebrow. | Task-015 |
| FIN-010 Commission pluralization is `(s)` | **Confirmed** | `pages.commissions.syncedCases` is `"Reconciled {cases} case(s) and updated {accruals} accrual(s)."`; the settlement preview repeats the pattern. | Task-016 |

### Cross-issue notes

- FIN-007 adds no new task: the duplicate paginator is already the exclusive scope of Task-011.
- FIN-001 and FIN-004 share Task-011 (page) and Task-018 (single-toast contract and missing translation). Neither task hides the server guard.
- FIN-006 is split by page so file ownership stays exclusive: income in Task-015, asset in Task-007, and shared table primitives in Task-013.
- FIN-002's audit-trail expectation is only partly verifiable from code because the existing audit pipeline captures model changes but has no reason field. Task-015 adds the reason to the record and requires it in the audit entry.
- HR-006 is the only finding that cannot be implemented without a product decision. Task-016 records the decision as a blocker and keeps the pluralization slice independently actionable.
- No finding required a new dependency or a service integration; all fixes stay inside the existing Laravel/Vue stack.
