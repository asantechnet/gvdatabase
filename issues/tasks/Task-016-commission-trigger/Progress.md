# Progress — Task-016

## Status snapshot
- Status: `Blocked`
- Owner: `Buffy (erp-task-implementer)`
- Last updated: `2026-10-31`
- Current focus: `Pluralization and trigger-independent sync integrity are implemented; the accrual trigger itself is blocked on the product decision.`
- Blocker: `Product/HR owner has not recorded whether contract completion or case completion is the authoritative accrual event. The task forbids changing the trigger without that decision.`
- Next action: `Record the approved trigger (and its approver) here, then align CommissionService, the observer path and the page wording to that single event.`

## Acceptance criteria progress
- [ ] Given the approved trigger decision is recorded in `Progress.md`, when the implementation is reviewed, then the code, page copy and requirement text all name that same event and no other event controls accrual. — Evidence: blocked. The INDEX still lists "Commission accrual trigger (contract vs case completion)" as an unresolved product/HR decision; no approver or choice is recorded. Per the task's stop condition, the trigger was left unchanged.
- [ ] Given the contract-completion trigger is approved, when a contract reaches completed on an in-progress case, then the eligible employees accrue the correct amount; completing the case alone does not accrue. — Evidence: blocked; contract completion was not approved, so no observer or contract-status trigger was added. The existing case-based behavior is unchanged.
- [ ] Given the case-completion trigger is approved, when a case completes, then accruals appear for its eligible contract lines and the UI no longer claims contract completion is the trigger. — Evidence: blocked; case completion is what the current service and tests implement, but that rule has not been recorded as the approved decision, so the copy was not renamed to endorse it.
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
- Did **not** change the accrual trigger, the service's case-completion gate, the reconcile endpoint's result shape, or the page's "Reconcile completed cases" wording. Those depend on the unrecorded product decision.

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

The paid-accrual test is the important one: the old `updateOrCreate` path would fail it. The new guard is what makes the "settled rows are never touched" contract true.

## Decisions and deviations
- **Trigger left unchanged.** The task says the product decision is a hard precondition and provides a stop condition: implement only the pluralization copy and leave the trigger unchanged with the blocker explicit. That is what was done, plus the trigger-independent sync-integrity fix the same scope explicitly requires.
- **Pluralization split into two keys.** `syncedCases` and `syncedVoided` have independent counts; vue-i18n allows only one `count` plural per message, so composing two pluralized sentences is the correct form. The old single key combined both counts and could not pluralize both.
- **Existing case-completion behavior is not endorsed.** It remains because it is the current implementation and tests; changing it either way requires the approver's decision. The page still says "Reconcile completed cases", matching the current code but not a recorded product decision.
- **No browser capture** for the toast/preview strings; the evidence is the locale assertions and typecheck.
- No data migration; the service fix is behavior-only.

## Blockers and open questions
- **Required decision:** is commission earned when a **contract** completes or when the **case** completes? The INDEX assigns this to Product/HR and the task requires the approver to be recorded. Until then, criteria 1–3 and any trigger/copy rename cannot be completed.
- If contract completion is chosen, the next step is an observer/event path plus contract-eligibility sync; if case completion is chosen, the existing gate stays and the page copy must name the case as the trigger. Both are documented in the task's implementation notes.

## Handoff notes
- Do not "fix" the trigger by inference from the current code; it is the exact ambiguity this task exists to resolve.
- Keep the `firstOrNew` settled-row guard. Any future `updateOrCreate` refactor must not write `commission_settlement_id` or `status` for a row that already has a settlement.
- The four commission plural keys are localized; page tasks should use them rather than rebuilding `case(s)`/`accrual(s)` strings.
- Once the decision is recorded, update this Progress file with the approver and re-open the trigger criteria.

## Activity log (append-only)
- `2026-10-31 00:00 Asia/Kabul` Created task record from validated HR-006 and FIN-010; implementation has not started.
- `2026-10-31` Claimed by `Buffy (erp-task-implementer)`, status → `In Progress`.
- `2026-10-31` Confirmed the product decision is still unrecorded; did not change the trigger. Implemented the stop-condition pluralization and fixed settled-accrual resurrection in `lineAccruals()`. Added two regression tests; 12/12 pass. Status → `Blocked` pending the product/HR decision.
