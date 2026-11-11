# Progress — Task-004

## Status snapshot
- Status: `Review`
- Owner: `Implementation agent`
- Last updated: `2026-11-10`
- Current focus: `Review-ready contract extension lifecycle guards and evidence.`
- Blocker: `None`
- Next action: `Review the diff, API transcript, migration output, and both register menu captures, then merge.`

## Acceptance criteria progress
- [x] Given a draft extension, when it is opened in Edit contract and saved without changes, then parent_contract_id and snapshot_data.extension_of remain unchanged. — Evidence: `ContractExtensionTest::test_editing_an_extension_preserves_its_parent_and_snapshot_origin` passes; the live API transcript shows before `parent_contract_id=33` / `extension_of=CTR-26-033`, and after the same values.
- [x] Given an edit request that supplies a different parent_contract_id, when it is submitted, then the request is rejected or the field is ignored so the extension cannot be re-parented. — Evidence: the same test submits parent 34 for an extension of parent 33; the update strips the field and returns 200 with parent 33 / `CTR-26-033`. The live API transcript records the submitted 34 and the preserved 33; the model `updating` hook also rejects a direct parent change.
- [x] Given a primary contract with a live extension, when another extension is requested, then creation is rejected with the existing business.contract_renewal_exists error. — Evidence: `ContractExtensionTest::test_a_live_extension_blocks_a_second_renewal_and_cancellation_releases_it` passes; the duplicate request returns 422 `business.contract_renewal_exists`, and cancelling the first extension releases the slot.
- [x] Given a parent and an extension with a different employee or applicant, when creation is requested, then it is rejected and no extension row is written. — Evidence: `ContractExtensionTest::test_extension_parties_must_match_the_parent_and_null_parties_remain_valid` passes; a different employee returns 422 `business.contract_extension_party_mismatch` with zero child rows, while null/null Other parties create successfully.
- [x] Given a new extension, when it is created through the API or UI, then it has a parent and satisfies the invariant; existing orphan rows are reported for manual repair rather than guessed. — Evidence: `ContractExtensionTest::test_the_model_prevents_creating_an_extension_without_a_parent` passes. `2026_11_10_000002_enforce_contract_extension_parent` logs orphan IDs 21, 23, 24 (trashed), and 25, and changes no rows or parent links.
- [x] Given the approved edit-window policy, when a contract edit is expired, then the API and UI follow that policy exactly and enforce the override audit when applicable. — Evidence: Task-001 keeps contracts outside the employee one-hour window. `ContractEditWindowTest` and `EditWindowPolicyTest::test_task_and_contract_edits_are_exempt_from_the_employee_window` pass; no contract window enforcement or override audit was added.
- [x] Given the shared contract register, when a parent already has a live extension, then Extend contract is unavailable and the form sends parent_contract_id only during creation. — Evidence: `ContractForm.vue` emits `parent_contract_id` only in extension-create mode; `ContractResource` exposes `has_live_extension`, and the list hides Extend when true. Playwright confirmed no Extend on employment parent CTR-26-028 and Other parent CTR-26-031, while open employment parent CTR-26-030 still shows Extend.

## Work completed with evidence
- Added `Contract::LIVE_EXTENSION_STATUSES` (`draft`, `active`), a `liveExtension()` relation, `hasLiveExtension()` / `isLiveExtension()` helpers, and create/update invariant hooks. New extensions require a parent; primaries cannot carry one; direct parent changes are rejected.
- Hardened `ContractController::extensionParent()`: locks the parent row, rejects a parent that already has a live extension with the existing renewal error, and requires both employee and applicant parties to match the parent exactly. Null/null remains valid for Other contracts.
- Changed `ContractController::update()` to drop `parent_contract_id` from update input, preserve `snapshot_data.extension_of` while rebuilding an extension document, and continue returning the preserved origin.
- Exposed `has_live_extension` from `ContractResource`, eager-loaded the relation for the register, typed the field in the API contract, and hid the Extend action in `contract_list.vue` when a live renewal exists. Save errors now use `apiErrorMessage` so server business messages remain visible.
- Limited `ContractForm.vue` to sending `parent_contract_id` only when creating an extension; edit payloads omit it.
- Added `business.contract_extension_party_mismatch` translations in English, Persian, and Pashto, plus the frontend fallback mapping.
- Added the non-destructive orphan-audit migration. It logs `contract_extension_invariant_orphan` with contract id, number, role, parent, status, and deleted state; it never guesses or repairs a parent.
- Added extension lifecycle regressions and the Task-001 contract-window exemption test.

## Files changed and why
- `app/Models/Contract.php` — live-extension vocabulary/relation and create/update invariant enforcement.
- `app/Http/Controllers/ContractController.php` — parent lock, duplicate/party guards, create-only parent handling, and snapshot-origin preservation.
- `app/Http/Resources/ContractResource.php` — additive `has_live_extension` register field.
- `resources/ts/api/contracts.ts` — typed live-extension state and create-only parent update contract.
- `resources/ts/components/ContractForm.vue` — parent payload only for extension creation.
- `resources/ts/pages/contract_list.vue` — Extend availability and business-error feedback.
- `resources/ts/utils/apiErrors.ts` — translated party-mismatch fallback.
- `lang/en/api.php`, `lang/fa/api.php`, `lang/ps/api.php` — party-mismatch business message.
- `database/migrations/2026_11_10_000002_enforce_contract_extension_parent.php` — audit-only orphan report.
- `tests/Feature/ContractExtensionTest.php` — edit preservation/reparent attempt, duplicate/cancellation release, party mismatch/null-party, and model orphan prevention.
- `tests/Feature/ContractEditWindowTest.php` — contracts remain exempt from the employee edit window and produce no override audit.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-11-10 | `php artisan test tests/Feature/ContractExtensionTest.php tests/Feature/ContractEditWindowTest.php --stop-on-failure` | Local dev | PASS — 12 tests, 83 assertions | Terminal output | Initial focused Task-004 suite. |
| 2026-11-10 | `php artisan test tests/Feature/EditWindowPolicyTest.php tests/Feature/ContractRegisterTest.php tests/Feature/ContractExtensionTest.php tests/Feature/ContractEditWindowTest.php --stop-on-failure` | Local dev | PASS — 21 tests, 147 assertions | Terminal output | Existing edit-window and contract register regressions plus the focused suite. |
| 2026-11-10 | `npm run typecheck` | Local dev | PASS | Terminal output | `vue-tsc --noEmit` through the project script. |
| 2026-11-10 | `php -l` on changed PHP files; scoped `git diff --check` | Local dev | PASS | Terminal output | No syntax or whitespace errors. |
| 2026-11-10 | `php artisan migrate --step` | Local dev | PASS | Terminal output | Migration applied in 8.23ms and logged four existing orphans (21, 23, 24 trashed, 25); no data changed. |
| 2026-11-10 | `php artisan migrate:rollback --step=1` then `php artisan migrate --step` | Local dev | PASS | Terminal output | Rollback/re-apply safe; 25 contracts and 4 orphan rows before and after. |
| 2026-11-10 | Live API GET then PUT with a different parent | Local dev | PASS | Terminal output | Before: parent 33 / `CTR-26-033`. Submitted 34. After: parent 33 / `CTR-26-033`, status draft. |
| 2026-11-10 | Playwright employee register, 1440px, light LTR | Local dev | PASS | `.playwright-cli/page-2026-11-10T12-35-33-759Z.png`; `.playwright-cli/page-2026-11-10T12-35-37-581Z.png` | CTR-26-028 (live extension 029) has no Extend; open CTR-26-030 has Extend. |
| 2026-11-10 | Playwright Other register, 1440px, light LTR | Local dev | PASS | `.playwright-cli/page-2026-11-10T12-35-25-217Z.png` | CTR-26-031 (live extension 032) has no Extend. |
| 2026-11-10 | Browser console during both register checks | Local dev | PASS | Terminal output | 0 errors, 0 warnings. |
| 2026-11-10 | Fixture cleanup: contracts 28–32, user 45, API contracts 33–35, user 46 | Local dev | PASS | Terminal output | All temporary contracts/users returned 0; browser session closed. |

## Decisions and deviations
- A live extension is a non-cancelled extension with status `draft` or `active`. Completed, terminated, and cancelled extensions do not block a new renewal; cancellation releases the slot.
- `parent_contract_id` is ignored on update rather than rejected, preserving compatibility with stale clients while the model hook independently prevents direct reparenting. The form omits it entirely during edits.
- Party matching is exact for both `employee_id` and `applicant_id`. A null/null pair is valid for Other contracts; any differing party is a mismatch, not a reason to rewrite the parent identity.
- The migration audits soft-deleted orphans too, which is why trashed contract 24 appears in addition to known IDs 21, 23, and 25. No orphan is guessed or modified.
- Task-001's employee-only window decision is unchanged. No contract window enforcement, middleware, route, or override-audit code was added.
- No changes were made to `ContractGenerationService.php`, `routes/api.php`, `app/Support/EditWindow.php`, employee/staff screens, or Academy commission screens.
- The UI pass covered Extend availability in both registers. The create/edit dialog flow was validated through the focused API suite and typecheck rather than repeated as a manual browser workflow.

## Blockers and open questions
- None. The change is ready for code review.

## Handoff notes
- Keep Task-004 at `Review` until review and merge; it is intentionally not marked `Done`.
- The task changes are uncommitted and must not be committed or pushed without a request.
- Review the focused diff plus the API, migration, and two-register evidence above.
- Existing orphan rows remain for manual data repair; the migration only reports them.

## Activity log (append-only)
- `2026-11-10` Created task record; implementation has not started.
- `2026-11-10` Implemented the model invariants, controller parent/duplicate/party guards, snapshot preservation, resource/API/UI changes, translations, orphan audit migration, and focused tests.
- `2026-11-10` Focused 12-test and combined 21-test suites passed; typecheck and PHP syntax/whitespace checks passed.
- `2026-11-10` Applied the migration, confirmed four existing orphans were only logged, and proved rollback/re-apply leaves the data unchanged.
- `2026-11-10` Captured a live before/after API transcript proving a submitted reparent is ignored.
- `2026-11-10` Verified Extend availability in both registers with temporary fixtures, then removed the fixtures and closed the browser.
- `2026-11-10` Status moved to `Review`; task remains uncommitted.
