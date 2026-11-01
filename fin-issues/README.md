# Finance Module Review — Educational Agency Portal

- **Date:** 31 October 2026
- **Reviewer:** Buffy (AI), UI-driven session with Playwright CLI
- **Environment:** local app at `http://127.0.0.1:8000`, Laravel 12 + Vue 3, MySQL
- **Account:** `admin@example.test` (super admin)
- **Mode:** read-only. No application code was changed.

## Scope

Everything reachable from the **Finance** navigation group:

| Page | Route |
|---|---|
| Income | `/income` |
| Expenses | `/expenses` |
| Payment requests | `/payment-requests` |
| Commissions | `/commissions` |
| Asset | `/asset` |
| Reports | `/reports` (same Reporting Studio as `/finance`) |
| Finance options | `/options/finance/income-types` |

## Method

The module was exercised as a user through the browser. The source was opened **only** to
explain one observed failure (payment-request approval), because the UI error text was too
generic to identify the rule being enforced. Every finding below was reproduced by clicking,
except where a report explicitly says it is a source-level observation.

## What was exercised

| Flow | Result |
|---|---|
| Record income (standalone, 5,000 AFN) | Created `RCPT-2026-000001` |
| Edit income (6,000 × qty 3) | Updated to 18,000 AFN — silently |
| Delete income | Confirmation dialog shown, row removed, no toast |
| Record income (1,000 AFN) | Created `RCPT-2026-000002` (number not reused) |
| Record expense (Marketing, 2,500 AFN) | Created `EXP-000001` |
| Empty income form submit | Blocked: "Select an income type." then "Amount must be greater than zero." |
| Empty expense form submit | Blocked: type + description messages |
| Empty asset form submit | Blocked: three bare "Required." messages |
| New payment request (500 AFN) | Created with status `submitted` |
| Send to review | Status → `under_review`, raw-token toast |
| Approve / Reject as the requester | Both rejected with duplicate generic toasts |
| Reconcile completed cases | Ran cleanly: 0 cases, 0 accruals |
| Add commission adjustment (empty) | Dialog opened; save path not completed |
| Finance dashboard totals | Reconciled to the ledger |
| Income type deletion | Used type `Service` offers no delete; unused types do |
| Service payments dialog | Opened; requires applicant selection |

## Verified-correct behaviour

- **Ledger math is coherent.** Dashboard after the run: Cash collected **AFN 1K (1 record)**,
  Expenses **AFN 2.5K (1 record)**, Net cash flow **−AFN 1.5K**. Exactly 1,000 − 2,500.
- **Base-currency conversion** computes correctly (`2,500 × 1 = 2,500`; `6,000 × 3 × 1 = 18,000`).
- **Receipt numbering** is sequential and does not reuse a number after deletion (000001 deleted,
  next issue was 000002) — correct for auditability.
- **Amount validation**: zero is refused with "Amount must be greater than zero."
- **Required-field validation** on income/expense is field-specific and readable.
- **Delete confirmation** for a receipt names the record type and warns it cannot be undone.
- **Self-approval is blocked server-side**, which is the right financial control.
- **Used income types are protected**: `Service` (in use) has no delete action, matching the page
  copy "Types used by transactions cannot be deleted."
- **Commissions empty state** is handled: reconcile reports 0 cases and 0 voided accruals instead
  of failing.

## Issues

| ID | Severity | Title |
|---|---|---|
| [FIN-001](issues/FIN-001-payment-request-self-approval-toasts.md) | High | Requester is offered Approve/Reject; both fail with duplicate generic toasts |
| [FIN-002](issues/FIN-002-receipt-silent-revaluation.md) | High | Editing an issued receipt silently triples its value |
| [FIN-003](issues/FIN-003-income-mutations-no-toast.md) | Medium | Income create/edit/delete give no success feedback |
| [FIN-004](issues/FIN-004-send-to-review-raw-status.md) | Medium | "Send to review" toast exposes the raw status token `under_review` |
| [FIN-005](issues/FIN-005-payment-request-toast-submit.md) | Medium | Payment request confirmation toast reads only "Submit" |
| [FIN-006](issues/FIN-006-unlabeled-icon-actions.md) | Medium | Icon-only receipt/asset actions have no accessible name |
| [FIN-007](issues/FIN-007-two-pagination-controls.md) | Low | Payment requests page renders two pagination controls |
| [FIN-008](issues/FIN-008-asset-bare-required-errors.md) | Low | Asset form shows three bare "Required." errors |
| [FIN-009](issues/FIN-009-receipt-detail-raw-ids.md) | Low | Receipt detail exposes internal database IDs |
| [FIN-010](issues/FIN-010-commission-pluralization.md) | Low | Commission reconcile confirmation has broken pluralization |

## Cross-module note

The Commissions page states that commissions are earned **"when a case reaches the completed
stage"**. This matches the code-level finding recorded in the HR review (`HR-006`) that accrual is
case-driven rather than fired by contract completion. If the intended rule is contract completion,
the copy, the trigger and the accrual service all need to change together.

## Limitations

- Approval by a **different** user was not exercised. The observed failure is the self-approval
  guard, which is correct; a second approver would confirm the happy path.
- **CSV export** produced no toast and no visible download confirmation in the CLI. Content and
  filename were not verified.
- **Print/PDF** output was not verified on paper.
- Commission **settlement** and **adjustment save** were opened but not completed (no
  commission-based employee or accrual existed to post against).
- Asset **edit/delete/allocation** were not completed; only create validation was tested.
- Ledger **filters**, date ranges and multi-currency rates were not exercised.
- Income/expense **type create/edit** was not exercised; only delete protection was observed.

Screenshots: `evidence/fin-001-duplicate-toasts.png` (approval failure toast pair).
