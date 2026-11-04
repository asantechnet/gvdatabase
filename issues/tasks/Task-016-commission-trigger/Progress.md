# Progress — Task-016

## Status snapshot
- Status: `Review`
- Owner: `Buffy (erp-task-implementer)`
- Last updated: `2026-11-04`
- Current focus: `The case-completion trigger is approved and recorded; code, tests and page copy already name that event.`
- Blocker: `None.`
- Next action: `Reviewer confirms the recorded decision and the case-completion copy; the browser/manual ledger check remains a review item.`

## Acceptance criteria progress
- [x] Given the approved trigger decision is recorded in `Progress.md`, when the implementation is reviewed, then the code, page copy and requirement text all name that same event and no other event controls accrual. — Evidence: the product owner selected **case completion** on 2026-11-04 (recorded under Decisions and in `Task.md`). `CommissionService::syncForApplicant()` gates on `Applicant::isCaseCompleted()` only; `CommissionController::sync()` queries completed cases; the English/Dari/Pashto subtitles say commission is earned when the case reaches the completed stage and the action is "Reconcile completed cases".
- [x] Given the contract-completion trigger is approved, when a contract reaches completed on an in-progress case, then the eligible employees accrue the correct amount; completing the case alone does not accrue. — Evidence: not applicable — contract completion was **not** approved. The inverse is covered instead by `test_a_completed_contract_alone_does_not_accrue_commission`: completing the contract alone creates zero accruals, and completing the case afterwards creates one.
- [x] Given the case-completion trigger is approved, when a case completes, then accruals appear for its eligible contract lines and the UI no longer claims contract completion is the trigger. — Evidence: `test_completing_a_case_accrues_commission_for_commission_based_employees` (one pending accrual, 50% of 100) and the three-locale copy above. No observer was added, so no second event can control accrual.
- [x] Given a sync runs twice for the same unchanged contract or case, when the second sync completes, then no duplicate accrual is created and no settled row is voided or resurrected. — Evidence: `test_syncing_the_same_completed_case_twice_does_not_duplicate_accruals` completes a case, syncs, and asserts one pending accrual with the same amount. The service still keys on contract, line and employee, so a second pass updates rather than inserts.
- [x] Given the reconcile action returns with three cases and two voided accruals, when the toast renders, then it uses correct plural forms and contains no `(s)` suffix in any supported locale. — Evidence: the toast now composes `syncedCases` and `syncedVoided`; both keys have singular/plural forms in `en`, `fa-AF` and `ps-AF`. A Node assertion confirmed all four commission count keys contain a plural separator and the entire commission subtree contains no `(s)`. No browser capture.
- [x] Given a settlement preview or approval message with one or many accruals, when it renders, then the count is pluralized correctly and the amount still matches the selected accruals. — Evidence: `settlementPreview` and `accrualCount` now have singular/plural forms in all three locales; the page still passes `selectedSettlementAccruals.length` and the computed `settlementTotal`, so the amount is unchanged. No browser capture.
- [x] Given an existing settled or paid accrual, when a sync runs after a status change, then the settled row is untouched. — Evidence: `test_sync_does_not_resurrect_a_paid_accrual` pays a settlement, runs reconcile, and asserts the accrual remains `paid`, keeps its `commission_settlement_id`, and keeps its amount. This test exposed and now guards a real defect (see below).

## Work completed with evidence
- Fixed a trigger-independent integrity defect in `CommissionService::lineAccruals()`. The previous `withTrashed()->updateOrCreate(...)` always wrote `commission_settlement_id => null` and `status => pending`, so a reconcile after settlement would detach and resurrect settled/paid money even though `voidStale()` tried to protect it. The method now uses `firstOrNew()`, keeps a settled accrual in the eligible set, and returns before writing it.
- Added two lifecycle tests: double-sync idempotence and paid-accrual protection. The suite is now 12 tests / 453 assertions and passes.
- Replaced the commission page's `(s)` copy:
  - `syncedCases` and new `syncedVoided` are composed by the toast instead of one string with two unpluralized counts.
  - `accrualCount` and `settlementPreview` now use singular/plural forms.
  - All four keys were added in English, Dari and Pashto with the same placeholders.
- Recorded the approved trigger (case completion) and aligned the lifecycle suite with Task-027: a contracted case is now walked through `POST /api/applicant-contracts/{line}/change-stage`, because the case-level endpoint correctly refuses a case that owns service lines. The suite had been red since Task-027 landed.
- Fixed the automatic accrual on the line path: `ApplicantContractStageController` wrote the stage-history row before mirroring the new stage onto the applicant, so `ApplicantStageHistory::created` re-synced commission against a stale (still-open) case and no accrual was created. Mirroring first makes the approved case-completion trigger fire from the line workflow too.

## Files changed and why
- `app/Services/CommissionService.php` — settled-accrual guard in `lineAccruals()`; no trigger change.
- `tests/Feature/CommissionLifecycleTest.php` — two regression tests for double sync and paid-row protection.
- `resources/ts/pages/commissions.vue` — compose two pluralized toast sentences.
- `resources/ts/plugins/i18n/locales/en.json`, `fa-AF.json`, `ps-AF.json` — plural forms for the four commission count messages; no other subtrees touched.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-10-31 | `php -l app/Services/CommissionService.php` | Local PHP 8.2 | PASS | terminal output | |
| 2026-10-31 | `php -l tests/Feature/CommissionLifecycleTest.php` | Local PHP 8.2 | PASS | terminal output | |
| 2026-10-31 | `npm run typecheck` | Local | PASS — exit 0 | terminal output | After the page and locale edits |
| 2026-10-31 | `php artisan test --filter=CommissionLifecycleTest` | Local | PASS — 12 tests, 453 assertions | terminal output | Includes the two new tests |
| 2026-10-31 | Node assertion over the commission locale subtree | Local Node 24 | PASS — four plural keys, no `(s)` | terminal output | `en`, `fa-AF`, `ps-AF` |
| 2026-10-31 | `git diff --check` | Local | PASS | terminal output | Clean |
| 2026-11-04 | `php artisan test --filter=CommissionLifecycleTest` | Local SQLite `RefreshDatabase` | PASS — 12 tests, 453 assertions | terminal output | After line-workflow test alignment and the mirror-before-history fix |
| 2026-11-04 | `npm run typecheck` | Local | PASS — exit 0 | terminal output | Commission page and locale copy unchanged in this run |

The paid-accrual test is the important one: the old `updateOrCreate` path would fail it. The new guard is what makes the "settled rows are never touched" contract true.

## Decisions and deviations
- **Decision: case completion is the approved trigger (2026-11-04).** The product owner chose case completion when asked to settle contract vs case. The existing `syncForApplicant()` gate and the page copy therefore become the approved behaviour; the earlier 2026-11-01 deferral is superseded. No observer, contract-status hook or service rewrite is needed.
- **Contract completion does not accrue.** `test_a_completed_contract_alone_does_not_accrue_commission` pins the rejected alternative, so a future contract observer cannot silently reintroduce a second trigger.
- **Pluralization split into two keys.** `syncedCases` and `syncedVoided` have independent counts; vue-i18n allows only one `count` plural per message, so composing two pluralized sentences is the correct form. The old single key combined both counts and could not pluralize both.
- **No browser capture** for the toast/preview strings; the evidence is the locale assertions and typecheck.
- No data migration; the service fix is behavior-only.

## Blockers and open questions
- None. The trigger decision is recorded and the implementation matches it.
- Limitation: no browser session was run to read the three-locale commission screens or to compare ledger rows before/after both completion orders; that is a review item, not a code gap.

## Handoff notes
- Do not "fix" the trigger by inference from the current code; it is the exact ambiguity this task exists to resolve.
- Keep the `firstOrNew` settled-row guard. Any future `updateOrCreate` refactor must not write `commission_settlement_id` or `status` for a row that already has a settlement.
- The four commission plural keys are localized; page tasks should use them rather than rebuilding `case(s)`/`accrual(s)` strings.
- Once the decision is recorded, update this Progress file with the approver and re-open the trigger criteria.

## Activity log (append-only)
- `2026-10-31 00:00 Asia/Kabul` Created task record from validated HR-006 and FIN-010; implementation has not started.
- `2026-10-31` Claimed by `Buffy (erp-task-implementer)`, status → `In Progress`.
- `2026-10-31` Confirmed the product decision is still unrecorded; did not change the trigger. Implemented the stop-condition pluralization and fixed settled-accrual resurrection in `lineAccruals()`. Added two regression tests; 12/12 pass. Status → `Blocked` pending the product/HR decision.
- `2026-11-01` Product owner explicitly deferred the trigger decision and chose to keep only the pluralization fix. No code, page copy or trigger change. Task remains `Blocked`; recorded as the final state for this task.
- `2026-11-04` Product owner approved **case completion** as the authoritative trigger. Recorded the decision in `Task.md` and above; confirmed the service, the reconcile endpoint and all three locale subtitles/actions already name case completion, and the contract-completion negative test pins the alternative. Status → `Review`.
