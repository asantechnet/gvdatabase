# Task Task-016 — Align the commission trigger and make its copy truthful

## Metadata
- Task ID: `Task-016`
- Title: `Align the commission trigger and make its copy truthful`
- Initial status: `Ready`
- Type: `Full-stack`
- Priority: `P2`
- Owner role: `Backend commission owner with Vue copy support`
- Estimated size: `M`
- Source plan/version: `hr-issues/issues/HR-006-commission-trigger-contract-vs-case.md`; `fin-issues/issues/FIN-010-commission-pluralization.md`; `fin-issues/README.md` cross-module note; validated in `issues/tasks/VALIDATION.md`
- Requirement / behavior IDs: `HR-006`, `FIN-010`

## Decision — approved 2026-11-04
**The authoritative trigger is case completion.** The product owner selected case completion on 2026-11-04. This supersedes the HR review quotation that commission is earned when a contract completes: a contract marked completed on an in-progress case earns nothing, and completing the case accrues for its eligible contract lines. The existing `CommissionService::syncForApplicant()` gate is therefore the approved rule, and the page copy (subtitle, reconcile action) naming completed cases is correct. No observer, contract-status hook or trigger change is required.

## Objective
Commission accrual fires on the event the business actually uses, and every commission screen and toast states that event and its counts in correct language.

## Context and problem
`CommissionService::syncForApplicant()` voids all unsettled accruals unless `$applicant->isCaseCompleted()`, and `syncForContract()` simply delegates to the applicant path. A contract marked completed on an in-progress case therefore earns nothing, while completing the case accrues for every non-cancelled contract line, including active ones. The requirement quoted in the HR review says commission is earned when a contract completes. The finance page repeats the case wording, so the product currently documents one rule and implements another.

`commissions.vue` also uses `syncedCases` with developer-style `case(s)` and `accrual(s)`, and the settlement preview has the same `(s)` pattern.

## Scope
### Included
- Record the product decision on the accrual trigger: contract completion or case completion. Implement exactly one.
- If contract completion is chosen: accrue when an eligible contract reaches `completed`, void only accruals whose contract is no longer eligible, and stop treating case completion as the controlling gate.
- If case completion is chosen: rename the page subtitle, action and any requirement text so contract completion is not implied; keep the existing gate but explain it in the UI.
- Make accrual idempotent: syncing or completing twice must not duplicate or resurrect a settled accrual.
- Replace `(s)` pluralization in reconcile, settlement preview and related counts with proper plural forms in every supported locale.
- Keep settlement approval, payment, cancellation and adjustment behavior unchanged.

### Excluded
- Changing commission rates, eligible employees or the per-service rate table.
- Redesigning the commission ledger or settlement lifecycle.
- Payroll integration beyond the existing accrual source amount.
- Contract register/case workflow UI (Tasks 001 and 003).

## Preconditions and assumptions
- The product decision is a hard precondition. Do not implement a trigger before it is recorded in `Progress.md` with the approver.
- `Contract::status` and `Applicant::isCaseCompleted()` are the two candidate events; the chosen one must be observable at the service boundary.
- Assumption to validate: commission rows are unique per contract/employee/service; if not, add an idempotency key rather than relying on update-or-create. Record the uniqueness constraint.
- Existing tests in `tests/Feature/CommissionLifecycleTest.php` describe current behavior and may need to change with the approved decision.

## Dependencies
- `None` — the decision and the commission service are independent of the other task layers. The contract observer, if chosen, is additive.

## Contracts and boundaries
- Input/output: `/commissions` and `/commissions/sync` keep their routes and response envelopes; the meaning of the sync result may change with the approved rule.
- Compatibility guarantee: settled, paid or cancelled accruals are never altered by a sync; only unsettled accruals are eligible for void/accrual.
- Error behavior: an ineligible contract produces no accrual and no exception; a failed sync reports a specific error and leaves prior rows intact.
- Permission/data scope: `manage_commission_settlements` remains the gate for sync and settlement actions.
- Integration seam: if contract completion is the trigger, add an observer/event path that calls the commission service; do not duplicate accrual logic in `ContractController`.
- Files this task must not modify: `app/Http/Controllers/ContractController.php`, `resources/ts/pages/contract_list.vue`, `app/Services/ApplicantContractPaymentService.php`.

## Exclusive file ownership
- `app/Http/Controllers/CommissionController.php` — sync result and permission boundary.
- `resources/ts/pages/commissions.vue` — subtitle, action copy and pluralized toasts.
- `app/Observers/ContractObserver.php` — new contract-completion hook if that trigger is approved.
- `app/Providers/AppServiceProvider.php` — observer registration.

Forward ownership of the contract-generation, applicant, stage, commission and contract-register paths needed by Task-046 moved on 2026-11-07; this task's delivered behaviour remains historical evidence.

## Acceptance criteria
- [ ] Given the approved trigger decision is recorded in `Progress.md`, when the implementation is reviewed, then the code, page copy and requirement text all name that same event and no other event controls accrual.
- [ ] Given the contract-completion trigger is approved, when a contract reaches completed on an in-progress case, then the eligible employees accrue the correct amount; completing the case alone does not accrue.
- [ ] Given the case-completion trigger is approved, when a case completes, then accruals appear for its eligible contract lines and the UI no longer claims contract completion is the trigger.
- [ ] Given a sync runs twice for the same unchanged contract or case, when the second sync completes, then no duplicate accrual is created and no settled row is voided or resurrected.
- [ ] Given the reconcile action returns with three cases and two voided accruals, when the toast renders, then it uses correct plural forms and contains no `(s)` suffix in any supported locale.
- [ ] Given a settlement preview or approval message with one or many accruals, when it renders, then the count is pluralized correctly and the amount still matches the selected accruals.
- [ ] Given an existing settled or paid accrual, when a sync runs after a status change, then the settled row is untouched.

## Verification plan
- Automated: extend `tests/Feature/CommissionLifecycleTest.php` for the approved trigger, double sync, settled-row protection and ineligible contract; run `php artisan test --filter=CommissionLifecycleTest` and `npm run typecheck`.
- Manual: create a commission-based employee with a service rate, attach them to a contract, exercise both orders (contract completed/case in progress and case completed/contract active), run reconcile twice and inspect the ledger.
- Fixtures: one commission-based employee, one service contract with a rate, one settled accrual. Non-production only.
- Regression: confirm settlement create/approve/pay/cancel and adjustment creation still work; confirm no accrual is created for a non-commission employee or a missing rate.
- Evidence: decision note, ledger rows before/after each order, sync result, plural strings in each locale.

## Implementation notes
Put the eligibility rule in one service method so the observer and the sync endpoint cannot drift. If a contract observer is used, run the accrual after the status transaction commits and make the sync idempotent with an update-or-create keyed by contract, employee and service. Settled rows must be filtered out before any void or update. Keep the locale change inside the commission page's key subtree and add the same plural keys to every supported locale.

## Risks and rollback
- Risk: changing the trigger retroactively revalues open accruals. Mitigation: restrict sync to unsettled rows and provide a reconciliation report before enabling.
- Risk: an observer causes duplicate accruals on repeated status saves. Mitigation: idempotency test and a uniqueness constraint or update-or-create key.
- Data migration: none required for the trigger change; if a uniqueness key is added, make it additive and backfill carefully.
- Stop condition: if the product decision is not recorded, implement only the pluralization copy and leave the trigger unchanged, with the decision blocker explicit.

## Handoff checklist
- [ ] Contract and file ownership respected.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Relevant docs/observability/audit notes updated if in scope.
- [ ] Remaining limitations, deviations and follow-up tasks are explicit.
- [ ] Task is ready for review/integration, not merely code complete.