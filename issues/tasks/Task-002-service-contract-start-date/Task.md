# Task Task-002 — Align the service-contract client schema with the server

## Metadata
- Task ID: `Task-002`
- Title: `Remove the client-only start_date requirement from service contracts`
- Initial status: `Ready`
- Type: `Full-stack`
- Priority: `P1`
- Owner role: `Frontend API contract implementer`
- Estimated size: `S`
- Source plan/version: `issues/review/issues/18-service-contract-start-date-blocker/issue.md`; `issues/review/REPORT.md` §11; validated in `issues/tasks/VALIDATION.md`
- Requirement / behavior IDs: `ISSUE-18`

## Objective
A valid Create contract form submits and creates the contract, because the browser validator no longer demands a field the server prohibits and the dialog never collects.

## Context and problem
`resources/ts/utils/apiValidation.ts` defines `serviceContractRules` with `start_date: ['required', 'date']` and applies it to `POST /contracts/scholarship`, `/test_offer`, `/academy` and `/private`. `app/Http/Requests/StoreServiceContractRequest.php` marks `start_date` as `prohibited`; `ContractGenerationService` derives it as today. `service-contracts.vue::save()` builds a payload with no `start_date`, and `resources/ts/api/axios.ts` rejects the request in the request interceptor before it reaches Laravel. The user sees "Start Date: Required." for a control that does not exist in the dialog.

## Scope
### Included
- Remove `start_date` from the shared service-contract client schema so it matches the server request class.
- Audit the other keys in `serviceContractRules` against `StoreServiceContractRequest` and record any further drift in `Progress.md`; fix only the keys that block a correct submission.
- Clear the dialog's form-level API error when the user edits a field, so stale validation text cannot persist.
- Add a regression test that a minimal valid payload passes client validation and reaches the server action.

### Excluded
- Adding a start-date input, changing server derivation, or backfilling existing contract dates.
- Contract register presentation and expiry (Task-003).
- Template-version selection UX and version creation.

## Preconditions and assumptions
- The server continues to derive `start_date` from `now()`; this task does not change derivation or timezone policy.
- Assumption to validate: no other screen calls the four POST routes with a deliberately supplied start date. If one does, it must be migrated in this task rather than preserving a contradictory exception.
- Test fixtures use an existing active template version and base currency; no production records.

## Dependencies
- `None` — the fix is independent; no upstream task is required.

## Contracts and boundaries
- Input/output: the four POST routes keep their URLs, response envelope and payload fields other than the removed client-only rule.
- Compatibility guarantee: the server remains the authority for `start_date`; existing contracts keep their dates.
- Error behavior: a genuinely invalid payload still returns field-level client errors; a server validation failure still reaches the dialog.
- Permission/data scope: unchanged; the existing `create_contracts` permission and applicant access rules apply.
- Integration seam: the client schema must mirror the server request class, not the other way round.
- Files this task must not modify: `app/Services/Contracts/ContractGenerationService.php`, `app/Http/Requests/StoreServiceContractRequest.php` (read-only reference unless an actual drift is proven), `resources/ts/pages/contract_list.vue`.

## Exclusive file ownership
- `resources/ts/utils/apiValidation.ts` — remove the stale start_date rule and keep the four routes on one schema.
- `resources/ts/pages/service-contracts.vue` — clear form-level errors on edit and confirm the payload has no start_date.
- `tests/Feature/ServiceContractStartDateTest.php` — new regression test for the client/schema contract and server acceptance.

## Acceptance criteria
- [ ] Given the four service-contract POST routes, when `serviceContractRules` is inspected, then it no longer requires or accepts a client-supplied `start_date`, matching the server `prohibited` rule.
- [ ] Given a minimal valid scholarship payload built by the dialog, when `validateApiRequest()` runs, then it returns no errors and the request is dispatched.
- [ ] Given a compatible active template version and all visible required fields, when Save is pressed, then no "Start Date: Required." message appears and the server creates the contract with its derived start date.
- [ ] Given a server or client validation error is displayed in the dialog, when the user edits the offending field, then the stale error clears.
- [ ] Given the regression test suite, when the new test runs, then it fails if a future change reintroduces a client-required field that the server prohibits.

## Verification plan
- Automated: `php artisan test --filter=ServiceContractStartDateTest`; `npm run typecheck`; search `serviceContractRules` for `start_date` and assert no match.
- Manual: create one contract of each of the four built-in services from the UI using non-production data; confirm the server-derived date equals the current business date in the app timezone.
- Fixtures: a draft applicant, an active template version for each service, the base currency and a valid language. Delete the created rows after verification.
- Regression: run the existing contract generation and service-contract tests, and confirm editing an existing contract still works.
- Evidence: request payload from the browser network panel, created contract number and derived start date, test output.

## Implementation notes
Keep one shared schema for the four routes. Do not replace the validator with "skip validation"; the remaining keys must still guard malformed requests. If the server request class gains a new required key, the client schema should be updated in the same change, and a drift test should compare the key sets rather than only `start_date`.

## Risks and rollback
- Risk: removing a required rule could let a payload through that the server also requires after a future change. Mitigation: keep the drift regression test and add new server keys deliberately.
- Risk: stale form error persists because it is stored outside the field. Mitigation: clear the dialog error on every form change and on dialog open.
- Data migration: none. Rollback is a one-line schema revert plus the test.
- Stop condition: if the server request class is found to accept and need a start date for a fifth route, stop and document the divergence before changing the shared schema.

## Handoff checklist
- [ ] Contract and file ownership respected.
- [ ] Acceptance criteria are supported by recorded evidence in `Progress.md`.
- [ ] Checks and failures are accurately recorded; no invented pass claims.
- [ ] Relevant docs/observability/audit notes updated if in scope.
- [ ] Remaining limitations, deviations and follow-up tasks are explicit.
- [ ] Task is ready for review/integration, not merely code complete.
