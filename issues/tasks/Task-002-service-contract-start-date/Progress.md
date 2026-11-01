# Progress — Task-002

## Status snapshot
- Status: `Review`
- Owner: `Buffy (erp-task-implementer)`
- Last updated: `2026-10-31`
- Current focus: `Client schema no longer requires start_date; dialog error clears on edit; regression test passes.`
- Blocker: `None`
- Next action: `Independent review; end-to-end creation and stale-error clearing were verified against the local runtime.`

## Acceptance criteria progress
- [x] Given the four service-contract POST routes, when `serviceContractRules` is inspected, then it no longer requires or accepts a client-supplied `start_date`, matching the server `prohibited` rule. — Evidence: the rule key was removed from `resources/ts/utils/apiValidation.ts`; all four routes still share `serviceContractRules`. Scoped extraction of the schema block (`sed -n '/const serviceContractRules/,/^}/p' ... | grep -c start_date`) reports one match, which is the explanatory comment, not a rule key; `ServiceContractStartDateTest::test_the_client_service_contract_schema_does_not_require_a_prohibited_field` asserts `assertArrayNotHasKey('start_date', $clientKeys)`.
- [x] Given a minimal valid scholarship payload built by the dialog, when `validateApiRequest()` runs, then it returns no errors and the request is dispatched. — Evidence: the dialog payload (`save()` in `service-contracts.vue`) has no `start_date` key; every field it sends that the client schema declares required (`fee`, `discount`, `currency_id`, `currency_rate`, `language_id`) is present and valid. Server side, `test_a_dialog_shaped_payload_is_accepted_and_the_server_derives_the_start_date` posts that exact payload and receives a successful response. No JS unit runtime exists, so `validateApiRequest()` itself could not be executed in isolation; the schema-shape conclusion is from source inspection plus the passing server test.
- [x] Given a compatible active template version and all visible required fields, when Save is pressed, then no "Start Date: Required." message appears and the server creates the contract with its derived start date. — Evidence: Playwright run against `http://127.0.0.1:8000/contracts/scholarship` created `CTR-26-016` (`POST /api/contracts/scholarship → 201`) with alert "Contract created"; no Start Date error appeared; DB start date = `2026-10-31` (today); the list row rendered the new contract.
- [x] Given a server or client validation error is displayed in the dialog, when the user edits the offending field, then the stale error clears. — Evidence: Playwright mocked `PUT /api/contracts/scholarship/*` with a 422 in the edit dialog; the alert rendered "Fee: E2E mocked fee error." and disappeared after typing in the Applicant name field. Source: deep watcher over the editable form state in `service-contracts.vue`; `npm run typecheck` passes.
- [x] Given the regression test suite, when the new test runs, then it fails if a future change reintroduces a client-required field that the server prohibits. — Evidence: `test_the_client_service_contract_schema_does_not_require_a_prohibited_field` parses the client schema and calls `StoreServiceContractRequest::rules()` for all four services, failing on any client key whose server rules contain `prohibited`, and on any client-required key the server does not rule at all. Run: 3 passed, 66 assertions.

## Schema drift audit (server `StoreServiceContractRequest` vs client `serviceContractRules`)
- Fixed (blocking): `start_date` was client-required but server-`prohibited`; removed.
- Known, non-blocking, deliberately unchanged: the client schema omits server-required keys `contract_template_version_id`, `father_name`, `father_phone`, `contact` and the per-service detail fields, so the server still enforces them. `name` is `nullable` client-side but required server-side for non-extension creates (and prohibited for most extensions), so tightening it client-side would break extensions. `fee`/`discount` also lack the server's `decimal:0,2`; the client validator has no `decimal` rule type.
- No fifth route uses `serviceContractRules`; the four documented POST routes are the complete set.

## Work completed with evidence
- Removed the stale `start_date` rule from the shared client schema and left a comment explaining why the server owns the date.
- Cleared the dialog's form-level API error whenever an editable field changes.
- Added `tests/Feature/ServiceContractStartDateTest.php`: dialog-shaped payload accepted with a server-derived date, explicit `start_date` rejected as prohibited, and a client/server drift guard for all four services.

## Files changed and why
- `resources/ts/utils/apiValidation.ts` — dropped `start_date` from `serviceContractRules`; four routes still share one schema.
- `resources/ts/pages/service-contracts.vue` — deep watcher clears the stale form-level error on edit.
- `tests/Feature/ServiceContractStartDateTest.php` — new regression and drift test.

## Checks run and results
| Date/time | Command/check | Environment | Result | Evidence path | Notes |
|---|---|---|---|---|---|
| 2026-10-31 | `php artisan test --filter=ServiceContractStartDateTest` | Local MySQL test DB | PASS — 3 tests, 66 assertions, exit 0 | `/tmp/task002-test.log` | First run failed only in the test's fake route binding; fixed with a minimal resolver object, then rerun green |
| 2026-10-31 | `npm run typecheck` | Local | PASS — exit 0 | `/tmp/task002-typecheck.log` | Project script, not bare `vue-tsc` |
| 2026-10-31 | Scoped schema grep for `start_date` | Local | Only the explanatory comment remains | `resources/ts/utils/apiValidation.ts` | `sed` range limited to the `serviceContractRules` block |
| 2026-10-31 | `python3 issues/tasks/scripts/validate_tasks.py issues/tasks` | Local | PASS | Task set validator | Run after the index update |
| 2026-10-31 | Playwright end-to-end create | Local `php artisan serve` + Vite dev, MySQL | PASS | POST request/response 794, created `CTR-26-016` | Dialog opened, filled and submitted; alert "Contract created"; request body has no `start_date`; DB start date = today |
| 2026-10-31 | Playwright end-to-end stale error | Same session, mocked 422 PUT | PASS | Dialogue alert DOM dump | Alert "Fee: E2E mocked fee error." appeared, then zero `.v-alert` nodes after editing a field |
| 2026-10-31 | Test-data cleanup | Local demo DB | PASS | Tinker transaction | Contract, line, task, 2 stage histories and applicant removed; contract count returned to 15 |

## Decisions and deviations
- End-to-end verification used the local demo database with the documented `admin@example.test` account. The created contract and its applicant were removed afterwards, per the verification plan.
- The client schema intentionally remains weaker than the server for keys it does not model; the task says to fix only keys that block a correct submission. Tightening `name` would break extension flows, and the missing detail fields belong to per-service forms rather than this shared schema.
- The drift test reads the TypeScript schema text from disk because the repo has no Jest/Vitest harness. It is a contract test, not a replacement for a future JS validator test.
- Manual creation of one contract per service from the UI was not performed; there was no non-production browser session in this run.

## Blockers and open questions
- None.

## Handoff notes
- End-to-end evidence: Playwright created a scholarship contract without a start date, and the server persisted today's date; the mocked-422 alert cleared on edit.
- Reviewer should confirm the deep watcher's reset scope is acceptable for the shared `error` ref (also used for list-load failures, which only show when the dialog is closed).
- If a later task adds a client rule for a key the server prohibits, the new drift test fails on purpose.
- Follow-up (out of scope): add a `decimal` rule type to the client validator so `decimal:0,2` drift can be mirrored.

## Activity log (append-only)
- `2026-10-31 00:00 Asia/Kabul` Created task record from validated issue 18; implementation has not started.
- `2026-10-31` Claimed by `Buffy (erp-task-implementer)`, status → `In Progress`.
- `2026-10-31` Removed the stale rule, added the error-clear watcher and the regression test. First test run failed on a fake route binding in the test only; fixed and reran: 3 passed (66 assertions). `npm run typecheck` exit 0. Status → `Review`.
- `2026-10-31` Ran the app locally (`php artisan serve` + Vite) and verified with Playwright: create dialog submitted, `POST /api/contracts/scholarship` returned 201, DB `start_date` = today, and a mocked 422 alert cleared on field edit. Cleaned up the created contract/applicant afterwards.
